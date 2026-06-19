<?php

namespace Tests\Feature;

use App\Services\ImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageOptimizerTest extends TestCase
{
    public function test_it_converts_and_scales_oversized_images_to_webp(): void
    {
        Storage::fake('public');
        config(['filesystems.public_disk' => 'public', 'media.max_width' => 800]);

        // 2000px-wide source — wider than the 800px cap.
        $file = UploadedFile::fake()->image('hero.jpg', 2000, 1200);

        $path = app(ImageOptimizer::class)->store($file, 'products');

        $this->assertStringEndsWith('.webp', $path);
        $this->assertStringStartsWith('products/', $path);
        Storage::disk('public')->assertExists($path);

        // Decoded result must be capped at the configured max width.
        $bytes = Storage::disk('public')->get($path);
        $info = getimagesizefromstring($bytes);
        $this->assertNotFalse($info);
        $this->assertLessThanOrEqual(800, $info[0], 'image should be scaled down to max width');
        $this->assertSame('image/webp', $info['mime']);
    }

    public function test_it_stores_non_raster_files_untouched(): void
    {
        Storage::fake('public');
        config(['filesystems.public_disk' => 'public']);

        $pdf = UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf');

        $path = app(ImageOptimizer::class)->store($pdf, 'inspiration_images');

        $this->assertStringEndsWith('.pdf', $path);
        Storage::disk('public')->assertExists($path);
    }
}
