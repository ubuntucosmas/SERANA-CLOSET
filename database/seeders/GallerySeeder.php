<?php

namespace Database\Seeders;

use App\Models\GalleryImage;
use Illuminate\Database\Seeder;

class GallerySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $images = [
            [
                'image_path' => '/images/hero_editorial.png',
                'client_name' => 'Amina W.',
                'garment_name' => 'Midnight Silk Gown',
                'testimonial' => 'The fit is absolutely impeccable. I have never felt more confident in a custom piece.',
                'is_featured' => true,
            ],
            [
                'image_path' => '/images/beige_contrast_set.png',
                'client_name' => 'David O.',
                'garment_name' => 'Signature Two-Piece',
                'testimonial' => 'Serana Closet is the only place I trust with my measurements. A true studio experience.',
                'is_featured' => true,
            ],
            [
                'image_path' => '/images/navy_contrast_set.png',
                'client_name' => 'Kevin M.',
                'garment_name' => 'Tracksuit Royale',
                'testimonial' => 'From the first stitch to final delivery, the attention to detail is unmatched.',
                'is_featured' => false,
            ],
            [
                'image_path' => '/images/category_ladies.png',
                'client_name' => 'Sarah L.',
                'garment_name' => 'Neon Trim Set',
                'testimonial' => 'The technical accuracy of the sizing guide made the process so easy.',
                'is_featured' => true,
            ],
        ];

        foreach ($images as $img) {
            GalleryImage::updateOrCreate(['image_path' => $img['image_path']], $img);
        }
    }
}
