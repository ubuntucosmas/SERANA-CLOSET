<?php

namespace App\Providers;

use App\Models\ThemeSetting;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped: rebuilt per request and per queued job, so workers never serve stale settings.
        $this->app->scoped(\App\Models\ThemeSetting::CACHE, fn () => \App\Models\ThemeSetting::query()->pluck('value', 'key')->all());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // No blanket Vite::prefetch(): it downloaded every page chunk, Studio
        // included, for every visitor. app.js warms only the storefront pages
        // a shopper is likely to open next, once the browser is idle.

        // Branded password-reset email (the framework default reads
        // "Reset Password Notification" and signs off with APP_NAME).
        ResetPassword::toMailUsing(function ($user, string $token) {
            $store = ThemeSetting::get('site_name') ?: 'Serana Closet';
            $minutes = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);
            $url = url(route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()], false));

            return (new MailMessage)
                ->from(config('mail.from.address'), $store)
                ->subject("Reset your {$store} password")
                ->greeting('Hello ' . ($user->name ?: 'there') . ',')
                ->line("We received a request to reset the password for your {$store} account.")
                ->action('Choose a new password', $url)
                ->line("This link expires in {$minutes} minutes.")
                ->line('If you did not ask for this, you can ignore this email. Your password stays the same.')
                ->salutation("— {$store}");
        });

        // SendOrderConfirmation listens for App\Events\MpesaWebhookHandled via
        // Laravel's listener auto-discovery (app/Listeners) — no manual binding,
        // which would double-register and send the receipt twice.
    }
}
