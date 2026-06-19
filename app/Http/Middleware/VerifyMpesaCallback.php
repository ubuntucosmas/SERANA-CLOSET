<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates inbound M-Pesa STK callbacks.
 *
 * The callback endpoint is unauthenticated by nature (Safaricom calls it
 * server-to-server, CSRF-exempt). Without a guard, anyone who learns or guesses
 * a CheckoutRequestID can POST a forged "success" payload and mark an order paid.
 *
 * Two layers, fail-closed:
 *   1. A high-entropy token in the URL path must match config('services.mpesa.callback_token').
 *   2. In production, if an IP allowlist is configured, the source IP must be in it.
 */
class VerifyMpesaCallback
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.mpesa.callback_token');
        $provided = (string) $request->route('token');

        // Fail closed: no token configured means the endpoint is not safe to serve.
        if ($expected === '' || !hash_equals($expected, $provided)) {
            Log::warning('M-Pesa Callback rejected: invalid token.', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Rejected'], 403);
        }

        $allowedIps = config('services.mpesa.allowed_ips', []);
        if (config('services.mpesa.env') === 'production' && !empty($allowedIps)) {
            if (!in_array($request->ip(), $allowedIps, true)) {
                Log::warning('M-Pesa Callback rejected: source IP not allowlisted.', [
                    'ip' => $request->ip(),
                ]);

                return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Rejected'], 403);
            }
        }

        return $next($request);
    }
}
