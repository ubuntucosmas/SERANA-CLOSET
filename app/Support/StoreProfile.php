<?php

namespace App\Support;

use App\Models\ThemeSetting;

/**
 * The single source for who the store is and how to reach it: name, WhatsApp,
 * phone, email, address, social links and where order alerts go.
 *
 * Edited in Studio → Settings → Store details & contact. Everything that shows
 * or uses these (header, footer, contact page, WhatsApp links and messages,
 * emails, receipts, SEO, owner alerts, payment instructions) reads them from
 * here, so a change in the Studio updates the whole platform at once. Server
 * config (.env) only provides first-run defaults.
 */
class StoreProfile
{
    /** theme_settings keys this profile owns. */
    public const KEYS = ['site_name', 'site_tagline', 'whatsapp_number', 'care_phone', 'contact_email', 'contact_address',
        'notification_email', 'instagram_url', 'tiktok_url', 'facebook_url', 'x_url'];

    public const SOCIALS = [
        'instagram_url' => 'Instagram',
        'tiktok_url' => 'TikTok',
        'facebook_url' => 'Facebook',
        'x_url' => 'X',
    ];

    private static function setting(string $key): string
    {
        return trim((string) (ThemeSetting::values()[$key] ?? ''));
    }

    public static function name(): string
    {
        return self::setting('site_name') ?: 'Serana Closet';
    }

    public static function tagline(): string
    {
        return self::setting('site_tagline');
    }

    /** WhatsApp for orders and chat, as bare international digits (2547…). */
    public static function whatsapp(): string
    {
        return WhatsApp::normalizeNumber(self::setting('whatsapp_number') ?: (string) config('services.whatsapp.number'));
    }

    /** Phone for calls; the WhatsApp number when none is set. */
    public static function phone(): string
    {
        return WhatsApp::normalizeNumber(self::setting('care_phone')) ?: self::whatsapp();
    }

    public static function email(): string
    {
        return self::setting('contact_email') ?: (string) config('services.store.contact_email', 'studio@seranacloset.com');
    }

    public static function address(): string
    {
        return self::setting('contact_address') ?: 'Nairobi, Kenya';
    }

    /** Where new-order and receipt copies go. */
    public static function notificationEmail(): string
    {
        return self::setting('notification_email') ?: (string) (config('services.store.notification_email') ?: config('mail.from.address'));
    }

    /** "+254 712 345 678" for people to read. */
    public static function display(string $digits): string
    {
        return preg_match('/^254(\d{3})(\d{3})(\d{3})$/', $digits, $m) ? "+254 {$m[1]} {$m[2]} {$m[3]}" : ($digits ? "+{$digits}" : '');
    }

    /** wa.me link to the store, optionally with a pre-filled message. */
    public static function whatsappLink(string $message = ''): string
    {
        return $message === '' ? 'https://wa.me/' . self::whatsapp() : WhatsApp::link(self::whatsapp(), $message);
    }

    /** @return list<array{key: string, label: string, url: string}> */
    public static function socials(): array
    {
        $out = [];
        foreach (self::SOCIALS as $key => $label) {
            $url = self::setting($key) ?: ($key === 'instagram_url' ? 'https://instagram.com/seranacloset' : '');
            if ($url !== '') $out[] = ['key' => str_replace('_url', '', $key), 'label' => $label, 'url' => $url];
        }
        return $out;
    }

    /** Replace {whatsapp}, {phone}, {email}, {store} in owner-written text. */
    public static function fill(string $text): string
    {
        return strtr($text, [
            '{whatsapp}' => self::display(self::whatsapp()),
            '{phone}' => self::display(self::phone()),
            '{email}' => self::email(),
            '{store}' => self::name(),
        ]);
    }

    /** Shared with every page as page.props.store. */
    public static function forFrontend(): array
    {
        return [
            'name' => self::name(),
            'tagline' => self::tagline(),
            'whatsapp' => self::whatsapp(),
            'whatsapp_display' => self::display(self::whatsapp()),
            'whatsapp_url' => self::whatsappLink(),
            'phone' => self::phone(),
            'phone_display' => self::display(self::phone()),
            'email' => self::email(),
            'address' => self::address(),
            'socials' => self::socials(),
        ];
    }

    /** schema.org description of the store for search engines (home page). */
    public static function structuredData(string $url): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'ClothingStore',
            'name' => self::name(),
            'description' => self::tagline() ?: null,
            'url' => $url,
            'telephone' => '+' . self::phone(),
            'email' => self::email(),
            'address' => ['@type' => 'PostalAddress', 'streetAddress' => self::address(), 'addressCountry' => 'KE'],
            'sameAs' => array_column(self::socials(), 'url') ?: null,
        ]);
    }
}
