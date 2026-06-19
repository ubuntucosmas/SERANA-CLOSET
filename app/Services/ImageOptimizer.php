<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

/**
 * Provider-agnostic, upload-time image optimization.
 *
 * Resizes oversized uploads and re-encodes them to WebP before they ever hit
 * storage, so delivery needs no transform layer (works identically on local,
 * S3, and Cloudflare R2). Replaces the old Supabase render-API dependency.
 */
class ImageOptimizer
{
    private ImageManager $manager;

    public function __construct(?ImageManager $manager = null)
    {
        $this->manager = $manager ?? new ImageManager(
            config('media.driver') === 'imagick' ? new ImagickDriver() : new GdDriver()
        );
    }

    /**
     * Optimize and store an uploaded image, returning its storage path.
     * Falls back to storing the original untouched if it can't be processed
     * (e.g. SVGs, or a decode failure) so an upload never hard-fails.
     */
    public function store(UploadedFile $file, string $folder, ?string $disk = null): string
    {
        $disk ??= config('filesystems.public_disk');
        $folder = trim($folder, '/');

        // Only rasterize true photo formats; vector/animated/documents (svg, gif,
        // pdf, …) are stored untouched.
        $rasterizable = ['jpg', 'jpeg', 'png', 'webp', 'bmp', 'tiff'];
        if (!in_array(strtolower((string) $file->getClientOriginalExtension()), $rasterizable, true)) {
            return $file->store($folder, $disk);
        }

        try {
            $image = $this->manager->read($file->getRealPath());
            $image->scaleDown(width: config('media.max_width', 1600));
            $encoded = $image->toWebp(quality: config('media.webp_quality', 82));

            $path = $folder . '/' . Str::uuid()->toString() . '.webp';
            Storage::disk($disk)->put($path, (string) $encoded, 'public');

            return $path;
        } catch (\Throwable $e) {
            Log::warning('Image optimization failed; storing original.', [
                'file'  => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);

            return $file->store($folder, $disk);
        }
    }

    /** Extensions worth rasterizing. Anything else (webp, svg, gif, pdf) is left alone. */
    private const RASTERIZABLE = ['jpg', 'jpeg', 'png', 'bmp', 'tiff'];

    /** Whether a stored path is a candidate for re-optimization. */
    public function isOptimizable(?string $path): bool
    {
        if (!$path || str_starts_with($path, 'http')) {
            return false;
        }

        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::RASTERIZABLE, true);
    }

    /**
     * Re-encode an already-stored image to optimized WebP, returning the new path
     * (and deleting the old file). Returns null if the file isn't optimizable,
     * doesn't exist, or fails to decode — callers should leave such paths as-is.
     * Used by the media:reoptimize backfill command.
     */
    public function optimizeStored(string $path, ?string $disk = null): ?string
    {
        $disk ??= config('filesystems.public_disk');

        if (!$this->isOptimizable($path)) {
            return null;
        }

        $storage = Storage::disk($disk);
        if (!$storage->exists($path)) {
            return null;
        }

        try {
            $image = $this->manager->read($storage->get($path));
            $image->scaleDown(width: config('media.max_width', 1600));
            $encoded = $image->toWebp(quality: config('media.webp_quality', 82));

            $newPath = preg_replace('/\.[^.\/]+$/', '', $path) . '.webp';
            $storage->put($newPath, (string) $encoded, 'public');

            if ($newPath !== $path) {
                $storage->delete($path);
            }

            return $newPath;
        } catch (\Throwable $e) {
            Log::warning('Image re-optimization failed; leaving original.', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
