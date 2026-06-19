<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReoptimizeMediaTest extends TestCase
{
    use RefreshDatabase;

    private function seedProductWithRawImage(string $path, int $width = 1500): Product
    {
        $file = UploadedFile::fake()->image('src.jpg', $width, 1000);
        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        return Product::create([
            'name'       => 'Legacy Piece ' . uniqid(),
            'slug'       => 'legacy-' . uniqid(),
            'price'      => 4200,
            'image_path' => $path,
        ]);
    }

    public function test_it_reencodes_legacy_images_to_webp_and_removes_the_original(): void
    {
        Storage::fake('public');
        config(['filesystems.public_disk' => 'public', 'media.max_width' => 600]);

        $product = $this->seedProductWithRawImage('products/legacy.jpg', width: 1500);

        $this->artisan('media:reoptimize')->assertSuccessful();

        $product->refresh();
        $this->assertStringEndsWith('.webp', $product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
        Storage::disk('public')->assertMissing('products/legacy.jpg');

        $info = getimagesizefromstring(Storage::disk('public')->get($product->image_path));
        $this->assertLessThanOrEqual(600, $info[0]);
        $this->assertSame('image/webp', $info['mime']);
    }

    public function test_it_is_idempotent_and_skips_already_optimized_images(): void
    {
        Storage::fake('public');
        config(['filesystems.public_disk' => 'public']);

        $product = $this->seedProductWithRawImage('products/legacy.jpg');

        $this->artisan('media:reoptimize')->assertSuccessful();
        $optimizedPath = $product->fresh()->image_path;

        // Second pass must not touch the already-webp asset.
        $this->artisan('media:reoptimize')->assertSuccessful();

        $this->assertSame($optimizedPath, $product->fresh()->image_path);
        Storage::disk('public')->assertExists($optimizedPath);
    }

    public function test_dry_run_changes_nothing(): void
    {
        Storage::fake('public');
        config(['filesystems.public_disk' => 'public']);

        $product = $this->seedProductWithRawImage('products/legacy.jpg');

        $this->artisan('media:reoptimize', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame('products/legacy.jpg', $product->fresh()->image_path);
        Storage::disk('public')->assertExists('products/legacy.jpg');
    }
}
