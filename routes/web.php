<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\CustomOrderController;
use App\Http\Controllers\CircleController;
use App\Http\Controllers\Admin\StudioController;
use App\Http\Controllers\CheckoutController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| SERANA CLOSET — Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::get('/shop',                [ShopController::class, 'index'])->name('shop');
Route::get('/shop/{product:slug}', [ShopController::class, 'show'])->name('shop.show');
Route::get('/custom-order',        [CustomOrderController::class, 'index'])->name('custom-order');
Route::post('/custom-orders',      [CustomOrderController::class, 'store'])->name('custom-orders.store');
Route::get('/about',               fn () => Inertia::render('About'))->name('about');
Route::get('/the-circle',          [CircleController::class, 'index'])->name('circle');
Route::post('/newsletter/subscribe', [App\Http\Controllers\NewsletterController::class, 'subscribe'])->name('newsletter.subscribe');
Route::get('/contact',             fn () => Inertia::render('Contact'))->name('contact');
Route::get('/cart',                fn () => Inertia::render('Cart'))->name('cart');
Route::get('/checkout',            [CheckoutController::class, 'index'])->name('checkout');
Route::post('/checkout/process',   [CheckoutController::class, 'process'])->name('checkout.process');

// Centralized Dashboard Redirect (Handles legacy Breeze redirects)
Route::get('/dashboard', function () {
    if (auth()->user()->isAdmin()) {
        return redirect()->route('studio.index');
    }
    return redirect()->route('profile.designs');
})->middleware(['auth'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| SERANA CLOSET — Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    // Profile Management
    Route::get('/profile',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Client Vault & Personal Archive
    Route::get('/my-vault', function () {
        return Inertia::render('Profile/DesignsVault', [
            'orders' => auth()->user()->customOrders()->latest()->get(),
            'user' => auth()->user()
        ]);
    })->name('profile.designs');

    // Invitation System
    Route::post('/my-vault/invite', [ProfileController::class, 'generateInvitation'])->name('profile.invite');
});

/*
|--------------------------------------------------------------------------
| SERANA CLOSET — Admin / Studio Management
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'admin'])->prefix('studio')->group(function () {
    Route::get('/', [StudioController::class, 'index'])->name('studio.index');
    Route::put('/order/{order}', [StudioController::class, 'update'])->name('studio.update');
    Route::post('/order/{order}/progress-snap', [StudioController::class, 'storeProgressSnap'])->name('studio.progress_snap');

    // Catalog Management
    Route::get('/catalog', [App\Http\Controllers\Admin\CatalogController::class, 'index'])->name('admin.catalog.index');
    Route::post('/catalog/product', [App\Http\Controllers\Admin\CatalogController::class, 'storeProduct'])->name('admin.catalog.store_product');
    Route::put('/catalog/product/{product}', [App\Http\Controllers\Admin\CatalogController::class, 'updateProduct'])->name('admin.catalog.update_product');
    Route::delete('/catalog/product/{product}', [App\Http\Controllers\Admin\CatalogController::class, 'destroyProduct'])->name('admin.catalog.destroy_product');
    Route::post('/catalog/category', [App\Http\Controllers\Admin\CatalogController::class, 'storeCategory'])->name('admin.catalog.store_category');

    // Gallery Management
    Route::post('/gallery', [App\Http\Controllers\Admin\GalleryController::class, 'store'])->name('admin.gallery.store');
    Route::delete('/gallery/{image}', [App\Http\Controllers\Admin\GalleryController::class, 'destroy'])->name('admin.gallery.destroy');

    // Theme & Asset Management
    Route::post('/theme/update', [App\Http\Controllers\Admin\ThemeController::class, 'update'])->name('admin.theme.update');
    Route::post('/theme/bulk-update', [App\Http\Controllers\Admin\ThemeController::class, 'bulkUpdate'])->name('admin.theme.bulk_update');
    Route::delete('/theme/{key}', [App\Http\Controllers\Admin\ThemeController::class, 'destroy'])->name('admin.theme.destroy');
});

require __DIR__.'/auth.php';
