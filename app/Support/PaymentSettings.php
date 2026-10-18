<?php

namespace App\Support;

use App\Models\PaymentAttempt;
use App\Models\ThemeSetting;

/**
 * How customers can pay, managed in Studio → Settings → Payments.
 *
 *  - M-Pesa: pay now with an STK prompt (needs Daraja credentials).
 *  - WhatsApp: the order is recorded (code, stock hold, tracking, Studio), the
 *    customer sends it on WhatsApp and pays as instructed; the studio confirms
 *    the payment in the Studio, which settles it exactly like M-Pesa does.
 *
 * Safety rules:
 *  - M-Pesa only counts as on when its credentials are configured.
 *  - If M-Pesa ends up unavailable, WhatsApp is always offered, so checkout
 *    can never dead-end, whatever the toggles say.
 *  - Switching M-Pesa off only stops *new* prompts: callbacks and
 *    reconciliation still settle payments already in progress.
 *
 * Stored as one JSON value in theme_settings under self::KEY.
 */
class PaymentSettings
{
    public const KEY = 'payment_settings';

    public const MPESA = 'mpesa';
    public const WHATSAPP = 'whatsapp';

    /** Allowed WhatsApp hold windows, in hours. */
    public const HOLD_OPTIONS = [12, 24, 48, 72];

    private static function saved(): array
    {
        $saved = json_decode((string) (ThemeSetting::values()[self::KEY] ?? ''), true);

        return is_array($saved) ? $saved : [];
    }

    /** Daraja credentials and the callback secret are all present. */
    public static function mpesaConfigured(): bool
    {
        foreach (['key', 'secret', 'shortcode', 'passkey', 'callback_token'] as $key) {
            if (blank(config("services.mpesa.{$key}"))) {
                return false;
            }
        }

        return true;
    }

    public static function mpesaAvailable(): bool
    {
        return self::mpesaConfigured() && (bool) (self::saved()['mpesa_enabled'] ?? true);
    }

    public static function whatsappAvailable(): bool
    {
        // Fail-safe: never leave checkout without a way to order.
        return (bool) (self::saved()['whatsapp_enabled'] ?? true) || ! self::mpesaAvailable();
    }

    /** @return list<string> */
    public static function methods(): array
    {
        return array_values(array_filter([
            self::mpesaAvailable() ? self::MPESA : null,
            self::whatsappAvailable() ? self::WHATSAPP : null,
        ]));
    }

    /** As the owner wrote it, with {whatsapp}/{phone}/{email}/{store} placeholders. */
    public static function rawInstructions(): string
    {
        return trim((string) (self::saved()['instructions'] ?? ''));
    }

    /** As customers read it: placeholders filled from the store profile. */
    public static function instructions(): string
    {
        return StoreProfile::fill(self::rawInstructions());
    }

    public static function holdHours(): int
    {
        $hours = (int) (self::saved()['whatsapp_hold_hours'] ?? 48);

        return in_array($hours, self::HOLD_OPTIONS, true) ? $hours : 48;
    }

    /** What the storefront needs (shared with every page). */
    public static function forCustomers(): array
    {
        $methods = self::methods();

        return [
            'mpesa' => in_array(self::MPESA, $methods, true),
            'whatsapp' => in_array(self::WHATSAPP, $methods, true),
            'default' => $methods[0],
            'instructions' => self::instructions(),
            'hold_hours' => self::holdHours(),
        ];
    }

    /** The Studio editor: the stored switches plus what is actually live. */
    public static function forStudio(): array
    {
        $saved = self::saved();

        return [
            'mpesa_enabled' => (bool) ($saved['mpesa_enabled'] ?? true),
            'whatsapp_enabled' => (bool) ($saved['whatsapp_enabled'] ?? true),
            'instructions' => self::rawInstructions(),
            'instructions_preview' => self::instructions(),
            'whatsapp_hold_hours' => self::holdHours(),
            'hold_options' => self::HOLD_OPTIONS,
            'mpesa_configured' => self::mpesaConfigured(),
            'mpesa_env' => config('services.mpesa.env') === 'production' ? 'live' : 'sandbox',
            'live' => self::forCustomers(),
            // Prompts sent in the last 15 minutes: these still complete if M-Pesa is switched off.
            'prompts_in_progress' => PaymentAttempt::where('status', PaymentAttempt::PENDING)
                ->where('created_at', '>=', now()->subMinutes(15))->count(),
        ];
    }

    public static function rules(): array
    {
        return [
            'mpesa_enabled' => ['required', 'boolean'],
            'whatsapp_enabled' => ['required', 'boolean'],
            'instructions' => ['nullable', 'string', 'max:600'],
            'whatsapp_hold_hours' => ['required', 'integer', 'in:' . implode(',', self::HOLD_OPTIONS)],
        ];
    }

    public static function save(array $validated): void
    {
        ThemeSetting::set(self::KEY, json_encode([
            'mpesa_enabled' => (bool) $validated['mpesa_enabled'],
            'whatsapp_enabled' => (bool) $validated['whatsapp_enabled'],
            'instructions' => trim((string) ($validated['instructions'] ?? '')),
            'whatsapp_hold_hours' => (int) $validated['whatsapp_hold_hours'],
        ]));
    }
}
