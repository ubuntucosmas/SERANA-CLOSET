<?php

namespace App\Providers;

use App\Models\ThemeSetting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureRateLimits();

        // No blanket Vite::prefetch(): it downloaded every page chunk, Studio
        // included, for every visitor. app.js warms only the storefront pages
        // a shopper is likely to open next, once the browser is idle.

        // Branded password-reset email (the framework default reads
        // "Reset Password Notification" and signs off with APP_NAME).
        ResetPassword::toMailUsing(function ($user, string $token) {
            $store = \App\Support\StoreProfile::name();
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

    /**
     * Per-shopper limits. Kenyan mobile networks put many customers behind a
     * few shared public IPs (carrier NAT), so per-IP limits alone lock real
     * shoppers out at peak: three people paying at once used to exhaust the
     * payment-status limit. Each limit is counted per browser session, with a
     * much higher per-IP ceiling as the backstop against scripted abuse.
     */
    private function configureRateLimits(): void
    {
        $shopper = function (string $name, int $perSession, int $perIp): void {
            RateLimiter::for($name, fn (Request $request) => [
                Limit::perMinute($perSession)->by("{$name}:s:" . ($request->hasSession() ? $request->session()->getId() : $request->ip())),
                Limit::perMinute($perIp)->by("{$name}:ip:" . $request->ip()),
            ]);
        };

        $shopper('checkout', 20, 300);
        $shopper('checkout-switch', 10, 200);
        $shopper('custom-orders', 10, 150);
        $shopper('drops', 20, 300);
        $shopper('newsletter', 5, 120);
        $shopper('track', 10, 200);
        $shopper('stk', 6, 150);          // each prompt reaches Safaricom; the order must also belong to the session
        $shopper('payment-status', 40, 2000); // polled every 5s while paying
    }
}
