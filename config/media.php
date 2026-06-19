<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Upload-time image optimization
    |--------------------------------------------------------------------------
    |
    | Images are resized and re-encoded to WebP when an admin/client uploads
    | them, rather than relying on a storage provider's transform feature. This
    | keeps delivery provider-agnostic (local, S3, Cloudflare R2 — all the same).
    |
    */

    // Largest dimension (px) kept on the long edge. Images are only scaled down,
    // never up. 1600 is plenty for full-bleed product photography on retina.
    'max_width' => (int) env('MEDIA_MAX_WIDTH', 1600),

    // WebP quality (0–100). 82 is visually lossless for fashion photography
    // while cutting file size ~30% vs JPEG.
    'webp_quality' => (int) env('MEDIA_WEBP_QUALITY', 82),

    // Imaging backend: "gd" (default, always available) or "imagick".
    'driver' => env('MEDIA_IMAGE_DRIVER', 'gd'),

];
