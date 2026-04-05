<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Str;

class ShopSeeder extends Seeder
{
    public function run(): void
    {
        $dresses = Category::updateOrCreate(['slug' => 'dresses'], ['name' => 'Dresses']);
        $hoodies = Category::updateOrCreate(['slug' => 'hoodies'], ['name' => 'Hoodies']);
        $corporate = Category::updateOrCreate(['slug' => 'corporate-wear'], ['name' => 'Corporate Wear']);
        $casual = Category::updateOrCreate(['slug' => 'casual-collection'], ['name' => 'Casual Collection']);
        
        $ladies = Category::updateOrCreate(['slug' => 'ladies-wear'], ['name' => 'Ladies Wear']);
        $mens = Category::updateOrCreate(['slug' => 'mens-collection'], ['name' => 'Mens Collection']);
        $readyToWear = Category::updateOrCreate(['slug' => 'ready-to-wear'], ['name' => 'Ready To Wear']);
        $accessories = Category::updateOrCreate(['slug' => 'accessories'], ['name' => 'Accessories']);
        $kids = Category::updateOrCreate(['slug' => 'kids-collection'], ['name' => 'Kids Collection']);
        $teens = Category::updateOrCreate(['slug' => 'teens-archive'], ['name' => 'Teens Archive']);

        // Update existing products to new categories
        Product::updateOrCreate(
            ['slug' => 'midnight-silk-slip-dress'],
            [
                'category_id' => $dresses->id,
                'name' => 'Midnight Silk Slip Dress',
                'description' => 'The TikTok Viral Silhouette. A liquid-draping masterpiece crafted from 400 GSM heavyweight mulberry silk. Engineered with reinforced neon-taper stitching for a cyber-atelier finish that commands attention in any light.',
                'price' => 45000,
                'image_path' => '/images/hero_editorial.png',
                'is_customizable' => true,
                'specifications' => [
                    'Material' => '100% Mulberry Silk (Heavyweight)',
                    'Stitching' => 'Double-Reinforced Neon Taper',
                    'Origin' => 'Serana Nairobi Studio',
                    'Hardware' => 'Discrete Internal Zips',
                    'Density' => '14 Stitches Per Inch'
                ],
                'batch_limit' => 15,
                'batch_sold' => 4,
            ]
        );

        Product::updateOrCreate(
            ['slug' => 'asymmetric-canvas-utility-coat'],
            [
                'category_id' => $casual->id,
                'name' => 'Asymmetric Utility Coat',
                'description' => 'Urban Armor for the High-Altitude Nomad. Industrial-grade washed canvas with tactical gunmetal hardware. Engineered for the Nairobi micro-climate, merging extreme durability with an avant-garde silhouette.',
                'price' => 32000,
                'image_path' => '/images/ready_to_wear_rack.png',
                'is_customizable' => false,
                'specifications' => [
                    'Material' => 'Reinforced Industrial Canvas',
                    'Hardware' => 'Gunmetal Tactical Buckles',
                    'Lining' => 'Breathable Mesh Architecture',
                    'Pockets' => 'Hidden Interior Security Cavities'
                ],
                'batch_limit' => 10,
                'batch_sold' => 2,
            ]
        );

        Product::updateOrCreate(
            ['slug' => 'vicuna-blend-atelier-suit'],
            [
                'category_id' => $corporate->id,
                'name' => 'Vicuna Blend Atelier Suit',
                'description' => 'The 1% Standard. A pinnacle of bespoke tailoring. Crafted from ultra-rare Vicuna-Wool blend with floating full-canvas internal architecture. This piece is not just worn; it is inherited.',
                'price' => 185000,
                'image_path' => '/images/detailed_texture.png',
                'is_customizable' => true,
                'specifications' => [
                    'Fabric' => 'Vicuna & Super 150s Merino Blend',
                    'Architecture' => 'Floating Full Canvas',
                    'Buttons' => 'Hand-Carved Horn',
                    'Mapping' => 'Algorithmic Fit Profile'
                ],
                'batch_limit' => 5,
                'batch_sold' => 1,
            ]
        );

        Product::updateOrCreate(
            ['slug' => 'sand-signature-two-piece'],
            [
                'category_id' => $readyToWear->id,
                'name' => 'Sand Signature Two-Piece',
                'description' => 'Minimalism Reimagined. A high-contrast architectural silhouette in desert sand organic cotton. Over-engineered structural seams provide a structured yet relaxed nomad aesthetic.',
                'price' => 28000,
                'image_path' => '/images/beige_contrast_set.png',
                'is_customizable' => false,
                'specifications' => [
                    'Material' => 'Architectural Organic Cotton',
                    'Color' => 'Desert Sand (Matte)',
                    'Construction' => 'Reinforced Pivot Points',
                    'Fit' => 'Structured Relaxed'
                ],
                'batch_limit' => 20,
                'batch_sold' => 15,
            ]
        );

        Product::updateOrCreate(
            ['slug' => 'navy-tactical-tracksuit'],
            [
                'category_id' => $casual->id,
                'name' => 'Navy Tactical Tracksuit',
                'description' => 'Cyber-Atelier Athleisure. Scuba-grade neoprene with reflective 3M polymer piping. Designed for those who move between the shadows and the spotlight with tactical precision.',
                'price' => 25000,
                'image_path' => '/images/navy_contrast_set.png',
                'is_customizable' => false,
                'specifications' => [
                    'Material' => 'Scuba-Grade Neoprene Blend',
                    'Piping' => '3M Reflective Polymer',
                    'Storage' => 'Bonded Waterproof Zips',
                    'Origin' => 'Edition 01 / Batch 20'
                ],
                'batch_limit' => 20,
                'batch_sold' => 8,
            ]
        );

        Product::updateOrCreate(
            ['slug' => 'mini-monolith-track-set'],
            [
                'category_id' => $kids->id,
                'name' => 'Mini Monolith Track Set',
                'description' => 'The Future of the Circle. High-density organic cotton fleece for the next generation of nomads. Engineered for movement, durability, and absolute comfort.',
                'price' => 12000,
                'image_path' => '/images/kids_placeholder.png',
                'is_customizable' => false,
                'specifications' => [
                    'Material' => 'Organic Cotton Fleece',
                    'Fit' => 'Active Comfort',
                    'Age Group' => '4-12 Years',
                    'Origin' => 'Serana Nairobi Studio'
                ],
                'batch_limit' => 30,
                'batch_sold' => 5,
            ]
        );

        Product::updateOrCreate(
            ['slug' => 'teens-hyper-canvas-bomber'],
            [
                'category_id' => $hoodies->id,
                'name' => 'Hyper-Canvas Bomber',
                'description' => 'Rebel Neutrality. A tactical bomber jacket for the rising elite. Contrast-stitched canvas with oversized utility pockets and a cropped, high-fashion silhouette.',
                'price' => 18000,
                'image_path' => '/images/teens_placeholder.png',
                'is_customizable' => true,
                'specifications' => [
                    'Material' => 'Water-Resistant Technical Canvas',
                    'Stitching' => 'Luminous Contrast',
                    'Hardware' => 'Matte Black Zips',
                    'Style' => 'High-Street Tactical'
                ],
                'batch_limit' => 20,
                'batch_sold' => 12,
            ]
        );
    }
}
