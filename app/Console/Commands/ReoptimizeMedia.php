<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\CustomOrder;
use App\Models\GalleryImage;
use App\Models\OrderProgressSnap;
use App\Models\Product;
use App\Services\ImageOptimizer;
use Illuminate\Console\Command;

/**
 * One-off backfill: re-encode pre-existing stored images to optimized WebP.
 *
 * New uploads are optimized automatically by ImageOptimizer; this catches the
 * back catalogue uploaded before that existed (e.g. after migrating off
 * Supabase to R2). Idempotent — already-WebP/external paths are skipped, so it's
 * safe to re-run. Use --dry-run first to preview.
 */
class ReoptimizeMedia extends Command
{
    protected $signature = 'media:reoptimize {--dry-run : Report what would change without writing}';
    protected $description = 'Re-encode existing stored images to optimized WebP';

    public function handle(ImageOptimizer $optimizer): int
    {
        $dry = (bool) $this->option('dry-run');
        $count = 0;

        // Products — primary image + secondary gallery.
        foreach (Product::withTrashed()->cursor() as $product) {
            $dirty = false;

            if ($new = $this->handlePath($product->image_path, $optimizer, $dry, $count)) {
                $product->image_path = $new;
                $dirty = true;
            }

            $secondary = $product->secondary_images ?? [];
            foreach ($secondary as $i => $path) {
                if ($new = $this->handlePath($path, $optimizer, $dry, $count)) {
                    $secondary[$i] = $new;
                    $dirty = true;
                }
            }

            if ($dirty && !$dry) {
                $product->secondary_images = array_values($secondary);
                $product->saveQuietly();
            }
        }

        // Gallery showcases.
        foreach (GalleryImage::withTrashed()->cursor() as $image) {
            if ($new = $this->handlePath($image->image_path, $optimizer, $dry, $count)) {
                if (!$dry) {
                    $image->image_path = $new;
                    $image->saveQuietly();
                }
            }
        }

        // Studio progress snaps.
        foreach (OrderProgressSnap::cursor() as $snap) {
            if ($new = $this->handlePath($snap->image_path, $optimizer, $dry, $count)) {
                if (!$dry) {
                    $snap->image_path = $new;
                    $snap->saveQuietly();
                }
            }
        }

        // Category banners (raw stored path, accessor builds the URL).
        foreach (Category::cursor() as $category) {
            $raw = $category->getRawOriginal('banner_url');
            if ($new = $this->handlePath($raw, $optimizer, $dry, $count)) {
                if (!$dry) {
                    $category->banner_url = $new;
                    $category->saveQuietly();
                }
            }
        }

        // Custom-order inspiration uploads (PDFs in here are skipped automatically).
        foreach (CustomOrder::withTrashed()->cursor() as $order) {
            $paths = $order->inspiration_image_paths ?? [];
            $dirty = false;
            foreach ($paths as $i => $path) {
                if ($new = $this->handlePath($path, $optimizer, $dry, $count)) {
                    $paths[$i] = $new;
                    $dirty = true;
                }
            }
            if ($dirty && !$dry) {
                $order->inspiration_image_paths = array_values($paths);
                $order->saveQuietly();
            }
        }

        $this->info(($dry ? '[dry-run] ' : '') . "Processed {$count} image(s).");

        return self::SUCCESS;
    }

    /**
     * Returns the new path when an image was (or would be) re-optimized, else null.
     * Increments the running counter for any candidate.
     */
    private function handlePath(?string $path, ImageOptimizer $optimizer, bool $dry, int &$count): ?string
    {
        if (!$optimizer->isOptimizable($path)) {
            return null;
        }

        if ($dry) {
            $this->line("  would reoptimize: {$path}");
            $count++;
            return null;
        }

        $new = $optimizer->optimizeStored($path);
        if ($new !== null) {
            $count++;
        }

        return $new;
    }
}
