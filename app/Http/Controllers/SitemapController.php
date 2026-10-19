<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Drop;

class SitemapController extends Controller
{
    public function index()
    {
        $base = rtrim(config('app.url'), '/');
        $dropsEnabled = (bool) config('services.drops.enabled');
        $urls = collect(['/', '/shop', '/drops', '/custom-order', '/about', '/contact', '/the-circle'])
            ->reject(fn ($path) => $path === '/drops' && ! $dropsEnabled)
            ->map(fn ($path) => ['loc' => $base.$path]);
        Product::select(['id', 'slug', 'updated_at'])->orderBy('id')->chunk(200, function ($products) use (&$urls, $base) {
            foreach ($products as $product) $urls->push(['loc' => $base.'/shop/'.rawurlencode($product->slug), 'lastmod' => $product->updated_at->toAtomString()]);
        });
        $publicDrops = $dropsEnabled ? Drop::whereIn('status', ['live', 'greenlit', 'fulfilled', 'closed'])->get(['slug', 'updated_at']) : [];
        foreach ($publicDrops as $drop) {
            $urls->push(['loc' => $base.'/drops/'.rawurlencode($drop->slug), 'lastmod' => $drop->updated_at->toAtomString()]);
        }
        return response()->view('sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots()
    {
        $lines = ['User-agent: *', 'Disallow: /studio', 'Disallow: /profile', 'Disallow: /my-vault', 'Disallow: /checkout', 'Disallow: /cart', 'Disallow: /track', 'Disallow: /payments', 'Disallow: /orders', 'Sitemap: '.rtrim(config('app.url'), '/').'/sitemap.xml'];
        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
