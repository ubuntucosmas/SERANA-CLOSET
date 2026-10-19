<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forwarded headers from an address TRUSTED_PROXIES doesn't list mean a proxy
 * (Cloudflare, a load balancer) sits in front of the app unconfigured: every
 * visitor then looks like the proxy's IP (shared rate limits) and HTTPS ended
 * there looks like HTTP. serana:preflight runs from the CLI and can't see
 * this, so requests report it: one log warning an hour, and a record for
 * preflight that lasts a day. Runs after the framework's TrustProxies.
 */
class WarnOnUntrustedForwardedHeaders
{
    /** Read by serana:preflight: ['remote_addr' => ..., 'at' => timestamp]. */
    public const SEEN_KEY = 'serana:untrusted-proxy:seen';

    private const THROTTLE_KEY = 'serana:untrusted-proxy:warned';

    public function handle(Request $request, Closure $next): Response
    {
        if (($request->headers->has('X-Forwarded-For') || $request->headers->has('X-Forwarded-Proto'))
            && ! $request->isFromTrustedProxy()
            && Cache::add(self::THROTTLE_KEY, true, now()->addHour())) {
            $remote = (string) $request->server->get('REMOTE_ADDR');

            Log::warning('Forwarded headers arrived from an untrusted address. If a proxy or CDN sits in front of the app, add it to TRUSTED_PROXIES.', [
                'remote_addr' => $remote,
            ]);
            Cache::put(self::SEEN_KEY, ['remote_addr' => $remote, 'at' => now()->timestamp], now()->addDay());
        }

        return $next($request);
    }
}
