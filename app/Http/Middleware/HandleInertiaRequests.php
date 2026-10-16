<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\ThemeSetting;
use App\Support\WhatsApp;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'seo' => fn () => \App\Support\PageSeo::forRequest($request),
            'auth' => [
                'user' => fn () => $request->user()
                    ? $request->user()->only('id', 'name', 'email', 'role')
                    : null,
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'drops_enabled' => (bool) config('services.drops.enabled'),
            'whatsapp_number' => fn () => WhatsApp::normalizeNumber(
                ThemeSetting::get('whatsapp_number', config('services.whatsapp.number'))
            ),
            // Custom order options are passed only to the pages that use them.
            'theme_settings' => fn () => collect(ThemeSetting::values())->except(\App\Support\CustomOrderOptions::KEY)->mapWithKeys(function ($value, $key) {
                return [$key => ThemeSetting::get($key)];
            })->all(),
            'categories' => fn () => \App\Models\Category::all()->map(function ($cat) {
                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'banner_url' => $cat->banner_url,
                ];
            }),
        ]);
    }
}
