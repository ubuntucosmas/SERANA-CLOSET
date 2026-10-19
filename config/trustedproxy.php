<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Read by Laravel's global TrustProxies middleware. Only these addresses may
    | set X-Forwarded-For/-Proto/-Host. Unset = trust none: the connecting IP is
    | the client IP, which is what the M-Pesa callback allowlist and the per-IP
    | rate limits rely on. Set it when Cloudflare or a load balancer sits in
    | front of the app (see .env.example).
    |
    */

    'proxies' => \App\Support\TrustedProxies::parse(env('TRUSTED_PROXIES')),

];
