<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Drop;
use App\Models\ThemeSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class PageSeo
{
    public static function forRequest(Request $request): array
    {
        $name = ThemeSetting::get('site_name', 'Serana Closet');
        $path = $request->getPathInfo();
        $base = rtrim(config('app.url'), '/');
        $titles = ['/' => 'Serana Closet', '/shop' => 'Shop Collection', '/drops' => 'Serana Drops', '/custom-order' => 'Custom Order', '/about' => 'About Serana', '/contact' => 'Contact Serana', '/the-circle' => 'The Serana Circle'];
        $descriptions = [
            '/' => 'Explore Serana’s ready-to-wear collection, limited Drops and custom orders.',
            '/shop' => 'Browse the Serana ready-to-wear collection. View each piece, available sizes and price.',
            '/drops' => 'Explore limited Serana Drops. Review the campaign goal, price and sizes before reserving.',
            '/custom-order' => 'Place a custom order, then discuss your design, quote and timeline with the studio on WhatsApp.',
            '/about' => 'Learn about Serana Closet and the studio behind the collection.',
            '/contact' => 'Contact Serana about sizing, an order or a custom order.',
            '/the-circle' => 'Explore the Serana gallery and find inspiration for a custom order.',
        ];
        $seo = ['title' => ($titles[$path] ?? 'Serana Closet').' — '.$name,
            'description' => $descriptions[$path] ?? 'Manage your Serana account or order.',
            'url' => $base.$path, 'image' => ThemeSetting::get('site_logo'),
            'type' => 'website', 'robots' => array_key_exists($path, $titles) ? 'index,follow' : 'noindex,nofollow', 'structured_data' => null];
        $product = $request->route('product');
        $drop = $request->route('drop');
        if ($product instanceof Product || $drop instanceof Drop) {
            $item = $product instanceof Product ? $product : $drop;
            $seo['title'] = ($item->name ?? $item->title).' — '.$name;
            $seo['description'] = Str::limit(Str::squish(strip_tags($item->description ?? '')), 160, '') ?: 'View this Serana piece, its price and availability.';
            $seo['image'] = $item->image_url;
            $seo['robots'] = 'index,follow';
            if ($product instanceof Product) {
                $available = $product->in_stock && ($product->availableStock() === null || $product->availableStock() > 0);
                $seo['type'] = 'product';
                $seo['structured_data'] = ['@context' => 'https://schema.org', '@type' => 'Product',
                    'name' => $product->name, 'description' => $seo['description'], 'sku' => (string) $product->id,
                    'url' => $seo['url'], 'offers' => ['@type' => 'Offer', 'url' => $seo['url'],
                        'priceCurrency' => 'KES', 'price' => $product->price,
                        'availability' => 'https://schema.org/'.($available ? 'InStock' : 'OutOfStock')]];
                if ($seo['image']) $seo['structured_data']['image'] = self::absolute($seo['image'], $base);
            }
        }
        if ($seo['image']) $seo['image'] = self::absolute($seo['image'], $base);
        return $seo;
    }

    private static function absolute(string $path, string $base): string
    {
        return str_starts_with($path, 'https://') || str_starts_with($path, 'http://') ? $path : $base.'/'.ltrim($path, '/');
    }
}
