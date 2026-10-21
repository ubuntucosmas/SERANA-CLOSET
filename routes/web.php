<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\CustomOrderController;
use App\Http\Controllers\CircleController;
use App\Http\Controllers\Admin\StudioController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\MpesaController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| SERANA CLOSET — Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [\App\Http\Controllers\SitemapController::class, 'robots'])->name('robots');

Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

Route::get('/shop',                [ShopController::class, 'index'])->name('shop');
Route::get('/shop/{product:slug}', [ShopController::class, 'show'])->name('shop.show');
Route::get('/custom-order',        [CustomOrderController::class, 'index'])->name('custom-order');
Route::post('/custom-orders',      [CustomOrderController::class, 'store'])
    ->middleware('throttle:custom-orders')
    ->block(10, 10)->name('custom-orders.store');
// Serana Drops — sell-then-make pre-orders. Reserving creates a CustomOrder
// (type='drop') and then rides the standard M-Pesa STK flow.
Route::get('/drops',                 [App\Http\Controllers\DropController::class, 'index'])->name('drops');
Route::get('/drops/{drop}',          [App\Http\Controllers\DropController::class, 'show'])->name('drops.show');
Route::post('/drops/{drop}/reserve', [App\Http\Controllers\DropController::class, 'reserve'])
    ->middleware('throttle:drops')
    ->block(10, 10)->name('drops.reserve');
Route::get('/about',               fn () => Inertia::render('About'))->name('about');
Route::get('/the-circle',          [CircleController::class, 'index'])->name('circle');
Route::post('/newsletter/subscribe', [App\Http\Controllers\NewsletterController::class, 'subscribe'])->middleware('throttle:newsletter')->name('newsletter.subscribe');
Route::get('/contact',             fn () => Inertia::render('Contact'))->name('contact');
Route::get('/cart',                fn () => Inertia::render('Cart'))->name('cart');
Route::get('/checkout',            [CheckoutController::class, 'index'])->name('checkout');
Route::post('/checkout/process',   [CheckoutController::class, 'process'])
    ->middleware('throttle:checkout')
    ->block(10, 10)->name('checkout.process');
// Same order, paid via WhatsApp instead of an M-Pesa prompt (no duplicate order).
Route::post('/checkout/{order}/whatsapp', [CheckoutController::class, 'switchToWhatsApp'])
    ->middleware('throttle:checkout-switch')->name('checkout.whatsapp');

// Customer pay link (signed, expiring; checked in the controller so an expired
// link shows a friendly page). Opening it lets this browser pay this order.
Route::get('/pay/{order:cipher}', [App\Http\Controllers\PayController::class, 'show'])
    ->middleware('throttle:track')->name('pay');

// Public order tracking (guest-friendly). Lookup is throttled and requires
// reference + phone, so it can't be used to enumerate orders.
Route::get('/track',         [OrderTrackingController::class, 'index'])->name('track');
Route::post('/track/lookup', [OrderTrackingController::class, 'lookup'])
    ->middleware('throttle:track')
    ->name('track.lookup');

// M-Pesa Secure Protocol Routes
// Throttled: STK initiation sends a real (billable) M-Pesa prompt to a phone.
Route::post('/payments/mpesa/stk', [MpesaController::class, 'initiate'])
    ->middleware('throttle:stk')
    ->name('mpesa.stk');
// Polled every 5s by the checkout modal; 30/min accommodates that with headroom.
Route::get('/payments/mpesa/status/{orderId}', [MpesaController::class, 'status'])
    ->middleware('throttle:payment-status')
    ->name('mpesa.status');
// Token-guarded callback: Safaricom posts to the URL we register, which embeds a
// shared secret. See VerifyMpesaCallback middleware.
Route::post('/payments/mpesa/callback/{token}', [MpesaController::class, 'callback'])
    ->middleware('mpesa.callback')
    ->name('mpesa.callback');

// Receipt Logic — temporary signed URLs only (minted by MpesaController::status).
// Prevents enumerating /orders/{id}/receipt to harvest customer PII.
Route::get('/orders/{order}/receipt', [ReceiptController::class, 'download'])
    ->middleware('signed')
    ->name('orders.receipt');

// Centralized Dashboard Redirect (Handles legacy Breeze redirects)
Route::get('/dashboard', function () {
    if (auth()->user()->canUseStudio()) {
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
        // Customer-safe projection: never ship Studio notes or payment internals.
        return Inertia::render('Profile/DesignsVault', [
            'orders' => auth()->user()->customOrders()->latest()->get()
                ->map(fn ($o) => $o->only(['id', 'cipher', 'payment_method', 'created_at']) + [
                    // Read through the order adapter (LEDGER_READS), in the vocabulary the page uses.
                    'type' => $o->legacyType(), 'status' => $o->legacyStatus(), 'is_paid' => $o->isPaidUp(), 'price_quoted' => $o->total(),
                    // Deposits: what is paid and still owed.
                    'amount_paid' => (int) $o->amount_paid, 'balance_due' => max(0, (int) $o->balance_due),
                ]),
        ]);
    })->name('profile.designs');

    // Invitation System
});

/*
|--------------------------------------------------------------------------
| SERANA CLOSET — Admin / Studio Management
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'admin'])->prefix('studio')->group(function () {
    Route::get('/', [StudioController::class, 'index'])->name('studio.index');
    Route::put('/order/{order}', [StudioController::class, 'update'])->name('studio.update');
    Route::delete('/order/{order}', [StudioController::class, 'destroy'])->name('studio.order.destroy');
    Route::post('/order/{id}/restore', [StudioController::class, 'restore'])->name('studio.order.restore');
    Route::delete('/order/{id}/force', [StudioController::class, 'forceDelete'])->name('studio.order.force_delete');
    Route::post('/order/{order}/progress-snap', [StudioController::class, 'storeProgressSnap'])->name('studio.progress_snap');
    Route::post('/order/{order}/record-payment', [StudioController::class, 'recordPayment'])->middleware('can:money.record')->name('studio.order.record_payment');
    Route::post('/order/{order}/cancel', [StudioController::class, 'cancelOrder'])->name('studio.order.cancel');
    // Staff (Phase E-a), owner only.
    Route::post('/staff', [App\Http\Controllers\Admin\StaffController::class, 'store'])->middleware('can:staff.manage')->name('studio.staff.store');
    Route::put('/staff/{user}/role', [App\Http\Controllers\Admin\StaffController::class, 'role'])->middleware('can:staff.manage')->name('studio.staff.role');
    Route::post('/staff/{user}/suspend', [App\Http\Controllers\Admin\StaffController::class, 'suspend'])->middleware('can:staff.manage')->name('studio.staff.suspend');
    Route::post('/staff/{user}/reactivate', [App\Http\Controllers\Admin\StaffController::class, 'reactivate'])->middleware('can:staff.manage')->name('studio.staff.reactivate');
    Route::put('/staff/{user}/pin', [App\Http\Controllers\Admin\StaffController::class, 'pin'])->middleware('can:staff.manage')->name('studio.staff.pin');
    // Stock per size (Phase D1).
    Route::post('/stock/move', [App\Http\Controllers\Admin\StockController::class, 'move'])->middleware('can:stock.manage')->name('studio.stock.move');
    Route::post('/stock/product/{product}/sizes', [App\Http\Controllers\Admin\StockController::class, 'sizes'])->middleware('can:stock.manage')->name('studio.stock.sizes');
    Route::post('/stock/product/{product}/track', [App\Http\Controllers\Admin\StockController::class, 'track'])->middleware('can:stock.manage')->name('studio.stock.track');
    Route::post('/stock/cutover', [App\Http\Controllers\Admin\StockController::class, 'cutover'])->middleware('can:stock.cutover')->name('studio.stock.cutover');
    Route::post('/stock/rollback', [App\Http\Controllers\Admin\StockController::class, 'rollback'])->middleware('can:stock.cutover')->name('studio.stock.rollback');
    Route::post('/order/{order}/cancel-bespoke', [StudioController::class, 'cancelBespoke'])->middleware('can:money.record')->name('studio.order.cancel_bespoke');
    Route::post('/order/{order}/resolve', [StudioController::class, 'resolveOrder'])->middleware('can:money.record')->name('studio.order.resolve');
    Route::post('/order/{order}/refund', [StudioController::class, 'recordRefund'])->middleware('can:refunds.mark')->name('studio.order.refund');
    Route::post('/payment/{payment}/reverse', [StudioController::class, 'reversePayment'])->middleware('can:ledger.reverse')->name('studio.payment.reverse');
    Route::post('/payment/{payment}/confirm', [StudioController::class, 'confirmPayment'])->middleware('can:ledger.reverse')->name('studio.payment.confirm');
    Route::put('/order/{order}/deposit', [StudioController::class, 'setDeposit'])->middleware('can:deposit.override')->name('studio.order.deposit');

    // Catalog Management
    Route::get('/catalog', [App\Http\Controllers\Admin\CatalogController::class, 'index'])->name('admin.catalog.index');
    Route::post('/catalog/product', [App\Http\Controllers\Admin\CatalogController::class, 'storeProduct'])->name('admin.catalog.store_product');
    Route::put('/catalog/product/{product}', [App\Http\Controllers\Admin\CatalogController::class, 'updateProduct'])->name('admin.catalog.update_product');
    Route::delete('/catalog/product/{product}', [App\Http\Controllers\Admin\CatalogController::class, 'destroyProduct'])->name('admin.catalog.destroy_product');
    Route::post('/catalog/product/{product}/restore', [App\Http\Controllers\Admin\CatalogController::class, 'restoreProduct'])->name('admin.catalog.restore_product')->withTrashed();
    Route::delete('/catalog/product/{product}/force', [App\Http\Controllers\Admin\CatalogController::class, 'forceDeleteProduct'])->name('admin.catalog.force_delete_product')->withTrashed();
    Route::post('/catalog/category', [App\Http\Controllers\Admin\CatalogController::class, 'storeCategory'])->name('admin.catalog.store_category');
    Route::put('/catalog/category/{category}', [App\Http\Controllers\Admin\CatalogController::class, 'updateCategory'])->name('admin.catalog.update_category');
    Route::delete('/catalog/category/{category}', [App\Http\Controllers\Admin\CatalogController::class, 'destroyCategory'])->name('admin.catalog.destroy_category');

    // Gallery Management
    Route::post('/gallery', [App\Http\Controllers\Admin\GalleryController::class, 'store'])->name('admin.gallery.store');
    Route::put('/gallery/{image}', [App\Http\Controllers\Admin\GalleryController::class, 'update'])->name('admin.gallery.update');
    Route::delete('/gallery/{image}', [App\Http\Controllers\Admin\GalleryController::class, 'destroy'])->name('admin.gallery.destroy');
    Route::post('/gallery/{image}/restore', [App\Http\Controllers\Admin\GalleryController::class, 'restore'])->name('admin.gallery.restore')->withTrashed();
    Route::delete('/gallery/{image}/force', [App\Http\Controllers\Admin\GalleryController::class, 'forceDelete'])->name('admin.gallery.force_delete')->withTrashed();

    // Drops Management
    Route::post('/drops', [App\Http\Controllers\Admin\DropController::class, 'store'])->name('admin.drops.store');
    Route::put('/drops/{drop:id}', [App\Http\Controllers\Admin\DropController::class, 'update'])->name('admin.drops.update');
    Route::delete('/drops/{drop:id}', [App\Http\Controllers\Admin\DropController::class, 'destroy'])->name('admin.drops.destroy');

    // Theme & Asset Management
    Route::post('/theme/update', [App\Http\Controllers\Admin\ThemeController::class, 'update'])->name('admin.theme.update');
    Route::post('/theme/bulk-update', [App\Http\Controllers\Admin\ThemeController::class, 'bulkUpdate'])->name('admin.theme.bulk_update');
    Route::delete('/theme/{key}', [App\Http\Controllers\Admin\ThemeController::class, 'destroy'])->name('admin.theme.destroy');
    Route::put('/payment-settings', [App\Http\Controllers\Admin\ThemeController::class, 'updatePaymentSettings'])->middleware('can:settings.payments')->name('admin.payment_settings.update');
    Route::post('/payment-settings/test-mpesa', [App\Http\Controllers\Admin\ThemeController::class, 'testMpesa'])->middleware(['can:settings.payments', 'throttle:6,1'])->name('admin.payment_settings.test_mpesa');
    Route::put('/order-policy', [App\Http\Controllers\Admin\ThemeController::class, 'updateOrderPolicy'])->middleware('can:settings.policies')->name('admin.order_policy.update');
    Route::put('/shop-policy', [App\Http\Controllers\Admin\ThemeController::class, 'updateShopPolicy'])->middleware('can:settings.policies')->name('admin.shop_policy.update');
    Route::put('/custom-order-options', [App\Http\Controllers\Admin\ThemeController::class, 'updateCustomOrderOptions'])->name('admin.custom_order_options.update');
});

require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| The till (Phase E, design §15): the shop tablet, signed in as staff.
| Each person unlocks it with their PIN; every action is theirs.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'can:pos.sell'])->prefix('pos')->group(function () {
    Route::get('/', [App\Http\Controllers\Pos\PosController::class, 'index'])->name('pos.index');
    Route::post('/unlock', [App\Http\Controllers\Pos\UnlockController::class, 'unlock'])->middleware('throttle:10,1')->name('pos.unlock');
    Route::post('/lock', [App\Http\Controllers\Pos\UnlockController::class, 'lock'])->name('pos.lock');
    Route::middleware('pos.operator')->group(function () {
        Route::post('/till/open', [App\Http\Controllers\Pos\TillController::class, 'open'])->name('pos.till.open');
        Route::post('/till/payout', [App\Http\Controllers\Pos\TillController::class, 'payout'])->name('pos.till.payout');
        Route::post('/till/close', [App\Http\Controllers\Pos\TillController::class, 'close'])->name('pos.till.close');
        Route::get('/till/report', [App\Http\Controllers\Pos\TillController::class, 'report'])->name('pos.till.report');
        Route::get('/catalogue', [App\Http\Controllers\Pos\SaleController::class, 'catalogue'])->name('pos.catalogue');
        Route::get('/sales', [App\Http\Controllers\Pos\SaleController::class, 'index'])->name('pos.sales.index');
        Route::post('/sales', [App\Http\Controllers\Pos\SaleController::class, 'store'])->name('pos.sales.store');
        Route::get('/sales/{order}', [App\Http\Controllers\Pos\SaleController::class, 'show'])->name('pos.sales.show');
        Route::post('/sales/{order}/tender', [App\Http\Controllers\Pos\SaleController::class, 'tender'])->name('pos.sales.tender');
        Route::post('/sales/{order}/void', [App\Http\Controllers\Pos\SaleController::class, 'void'])->name('pos.sales.void');
        Route::get('/sales/{order}/receipt', [App\Http\Controllers\Pos\SaleController::class, 'receipt'])->name('pos.sales.receipt');
        Route::get('/orders/find', [App\Http\Controllers\Pos\ReturnController::class, 'find'])->name('pos.orders.find');
        Route::post('/returns', [App\Http\Controllers\Pos\ReturnController::class, 'store'])->name('pos.returns.store');
        Route::get('/collect/find', [App\Http\Controllers\Pos\DeskController::class, 'find'])->middleware('throttle:30,1')->name('pos.collect.find');
        Route::post('/collect/{order}', [App\Http\Controllers\Pos\DeskController::class, 'collect'])->name('pos.collect.store');
        Route::post('/intake', [App\Http\Controllers\Pos\DeskController::class, 'intake'])->name('pos.intake');
        Route::post('/late-entry', [App\Http\Controllers\Pos\DeskController::class, 'lateEntry'])->name('pos.late_entry');
    });
});
