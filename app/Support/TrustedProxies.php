<?php

namespace App\Support;

/**
 * Parses TRUSTED_PROXIES: comma-separated IPs/CIDRs of the proxies in front of
 * the app (Cloudflare, a load balancer), or "*" for "whoever connects".
 * Empty means trust none, so X-Forwarded-* headers are ignored and
 * $request->ip() is the real connecting address.
 */
final class TrustedProxies
{
    /** @return list<string>|string|null */
    public static function parse(?string $value): array|string|null
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }
        if ($value === '*' || $value === '**') {
            return $value;
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), fn ($ip) => $ip !== ''));
    }
}
