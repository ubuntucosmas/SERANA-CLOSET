# Actual Laravel route inventory

Generated locally on 15 October 2026 using `php artisan route:list --json`. Includes supporting endpoints so actions are traceable to their implementation.

| Method | URI | Name | Action |
| --- | --- | --- | --- |
| GET / HEAD | `/` | home | `App\Http\Controllers\HomeController@index` |
| GET / HEAD | `/about` | about | `Closure` |
| GET / HEAD | `/cart` | cart | `Closure` |
| GET / HEAD | `/checkout` | checkout | `App\Http\Controllers\CheckoutController@index` |
| POST | `/checkout/process` | checkout.process | `App\Http\Controllers\CheckoutController@process` |
| GET / HEAD | `/confirm-password` | password.confirm | `App\Http\Controllers\Auth\ConfirmablePasswordController@show` |
| POST | `/confirm-password` | — | `App\Http\Controllers\Auth\ConfirmablePasswordController@store` |
| GET / HEAD | `/contact` | contact | `Closure` |
| GET / HEAD | `/custom-order` | custom-order | `App\Http\Controllers\CustomOrderController@index` |
| POST | `/custom-orders` | custom-orders.store | `App\Http\Controllers\CustomOrderController@store` |
| GET / HEAD | `/dashboard` | dashboard | `Closure` |
| GET / HEAD | `/drops` | drops | `App\Http\Controllers\DropController@index` |
| GET / HEAD | `/drops/{drop}` | drops.show | `App\Http\Controllers\DropController@show` |
| POST | `/drops/{drop}/reserve` | drops.reserve | `App\Http\Controllers\DropController@reserve` |
| POST | `/email/verification-notification` | verification.send | `App\Http\Controllers\Auth\EmailVerificationNotificationController@store` |
| GET / HEAD | `/forgot-password` | password.request | `App\Http\Controllers\Auth\PasswordResetLinkController@create` |
| POST | `/forgot-password` | password.email | `App\Http\Controllers\Auth\PasswordResetLinkController@store` |
| GET / HEAD | `/login` | login | `App\Http\Controllers\Auth\AuthenticatedSessionController@create` |
| POST | `/login` | — | `App\Http\Controllers\Auth\AuthenticatedSessionController@store` |
| POST | `/logout` | logout | `App\Http\Controllers\Auth\AuthenticatedSessionController@destroy` |
| GET / HEAD | `/my-vault` | profile.designs | `Closure` |
| POST | `/my-vault/invite` | profile.invite | `App\Http\Controllers\ProfileController@generateInvitation` |
| POST | `/newsletter/subscribe` | newsletter.subscribe | `App\Http\Controllers\NewsletterController@subscribe` |
| GET / HEAD | `/orders/{order}/receipt` | orders.receipt | `App\Http\Controllers\ReceiptController@download` |
| PUT | `/password` | password.update | `App\Http\Controllers\Auth\PasswordController@update` |
| POST | `/payments/mpesa/callback/{token}` | mpesa.callback | `App\Http\Controllers\MpesaController@callback` |
| GET / HEAD | `/payments/mpesa/status/{orderId}` | mpesa.status | `App\Http\Controllers\MpesaController@status` |
| POST | `/payments/mpesa/stk` | mpesa.stk | `App\Http\Controllers\MpesaController@initiate` |
| GET / HEAD | `/profile` | profile.edit | `App\Http\Controllers\ProfileController@edit` |
| PATCH | `/profile` | profile.update | `App\Http\Controllers\ProfileController@update` |
| DELETE | `/profile` | profile.destroy | `App\Http\Controllers\ProfileController@destroy` |
| GET / HEAD | `/register` | register | `App\Http\Controllers\Auth\RegisteredUserController@create` |
| POST | `/register` | — | `App\Http\Controllers\Auth\RegisteredUserController@store` |
| POST | `/reset-password` | password.store | `App\Http\Controllers\Auth\NewPasswordController@store` |
| GET / HEAD | `/reset-password/{token}` | password.reset | `App\Http\Controllers\Auth\NewPasswordController@create` |
| GET / HEAD | `/sanctum/csrf-cookie` | sanctum.csrf-cookie | `Laravel\Sanctum\Http\Controllers\CsrfCookieController@show` |
| GET / HEAD | `/shop` | shop | `App\Http\Controllers\ShopController@index` |
| GET / HEAD | `/shop/{product}` | shop.show | `App\Http\Controllers\ShopController@show` |
| GET / HEAD | `/storage/{path}` | storage.local | `Closure` |
| PUT | `/storage/{path}` | storage.local.upload | `Closure` |
| GET / HEAD | `/studio` | studio.index | `App\Http\Controllers\Admin\StudioController@index` |
| GET / HEAD | `/studio/catalog` | admin.catalog.index | `App\Http\Controllers\Admin\CatalogController@index` |
| POST | `/studio/catalog/category` | admin.catalog.store_category | `App\Http\Controllers\Admin\CatalogController@storeCategory` |
| PUT | `/studio/catalog/category/{category}` | admin.catalog.update_category | `App\Http\Controllers\Admin\CatalogController@updateCategory` |
| DELETE | `/studio/catalog/category/{category}` | admin.catalog.destroy_category | `App\Http\Controllers\Admin\CatalogController@destroyCategory` |
| POST | `/studio/catalog/product` | admin.catalog.store_product | `App\Http\Controllers\Admin\CatalogController@storeProduct` |
| PUT | `/studio/catalog/product/{product}` | admin.catalog.update_product | `App\Http\Controllers\Admin\CatalogController@updateProduct` |
| DELETE | `/studio/catalog/product/{product}` | admin.catalog.destroy_product | `App\Http\Controllers\Admin\CatalogController@destroyProduct` |
| DELETE | `/studio/catalog/product/{product}/force` | admin.catalog.force_delete_product | `App\Http\Controllers\Admin\CatalogController@forceDeleteProduct` |
| POST | `/studio/catalog/product/{product}/restore` | admin.catalog.restore_product | `App\Http\Controllers\Admin\CatalogController@restoreProduct` |
| POST | `/studio/drops` | admin.drops.store | `App\Http\Controllers\Admin\DropController@store` |
| PUT | `/studio/drops/{drop}` | admin.drops.update | `App\Http\Controllers\Admin\DropController@update` |
| DELETE | `/studio/drops/{drop}` | admin.drops.destroy | `App\Http\Controllers\Admin\DropController@destroy` |
| POST | `/studio/gallery` | admin.gallery.store | `App\Http\Controllers\Admin\GalleryController@store` |
| PUT | `/studio/gallery/{image}` | admin.gallery.update | `App\Http\Controllers\Admin\GalleryController@update` |
| DELETE | `/studio/gallery/{image}` | admin.gallery.destroy | `App\Http\Controllers\Admin\GalleryController@destroy` |
| DELETE | `/studio/gallery/{image}/force` | admin.gallery.force_delete | `App\Http\Controllers\Admin\GalleryController@forceDelete` |
| POST | `/studio/gallery/{image}/restore` | admin.gallery.restore | `App\Http\Controllers\Admin\GalleryController@restore` |
| DELETE | `/studio/order/{id}/force` | studio.order.force_delete | `App\Http\Controllers\Admin\StudioController@forceDelete` |
| POST | `/studio/order/{id}/restore` | studio.order.restore | `App\Http\Controllers\Admin\StudioController@restore` |
| PUT | `/studio/order/{order}` | studio.update | `App\Http\Controllers\Admin\StudioController@update` |
| DELETE | `/studio/order/{order}` | studio.order.destroy | `App\Http\Controllers\Admin\StudioController@destroy` |
| POST | `/studio/order/{order}/progress-snap` | studio.progress_snap | `App\Http\Controllers\Admin\StudioController@storeProgressSnap` |
| POST | `/studio/theme/bulk-update` | admin.theme.bulk_update | `App\Http\Controllers\Admin\ThemeController@bulkUpdate` |
| POST | `/studio/theme/update` | admin.theme.update | `App\Http\Controllers\Admin\ThemeController@update` |
| DELETE | `/studio/theme/{key}` | admin.theme.destroy | `App\Http\Controllers\Admin\ThemeController@destroy` |
| GET / HEAD | `/the-circle` | circle | `App\Http\Controllers\CircleController@index` |
| GET / HEAD | `/track` | track | `App\Http\Controllers\OrderTrackingController@index` |
| POST | `/track/lookup` | track.lookup | `App\Http\Controllers\OrderTrackingController@lookup` |
| GET / HEAD | `/up` | — | `Closure` |
| GET / HEAD | `/verify-email` | verification.notice | `App\Http\Controllers\Auth\EmailVerificationPromptController` |
| GET / HEAD | `/verify-email/{id}/{hash}` | verification.verify | `App\Http\Controllers\Auth\VerifyEmailController` |
