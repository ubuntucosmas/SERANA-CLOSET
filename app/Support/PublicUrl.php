<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Resolves a stored media path to a browser-usable URL on whichever disk is
 * configured as the public one (local /storage symlink vs cloud/S3). This was
 * previously copy-pasted across Product and CustomOrder accessors.
 */
final class PublicUrl
{
    public static function resolve(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        $disk = config('filesystems.public_disk');

        if (config("filesystems.disks.{$disk}.driver", 'local') === 'local') {
            return '/storage/' . ltrim(str_replace('/storage/', '/', $path), '/');
        }

        return Storage::disk($disk)->url($path);
    }
}
