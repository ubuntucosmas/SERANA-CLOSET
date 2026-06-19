<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    protected $baseUrl;
    protected $consumerKey;
    protected $consumerSecret;
    protected $shortcode;
    protected $passkey;
    protected $callbackUrl;

    public function __construct()
    {
        $config = config('services.mpesa');
        $this->baseUrl = $config['env'] === 'production' 
            ? 'https://api.safaricom.co.ke' 
            : 'https://sandbox.safaricom.co.ke';
        
        $this->consumerKey = $config['key'];
        $this->consumerSecret = $config['secret'];
        $this->shortcode = $config['shortcode'];
        $this->passkey = $config['passkey'];
        $this->callbackUrl = $config['callback_url'];
    }

    /**
     * Step 1: Authentication — OAuth2 access token (valid 3600s on Daraja).
     *
     * The token is CACHED for its lifetime. Safaricom rate-limits token
     * generation, so requesting a fresh one on every call (the reconcile job
     * loops over many orders) trips the limit and returns intermittent
     * "Failed to authenticate" errors — silently stranding paid orders. We
     * cache only on success (never cache a null) so transient failures retry.
     */
    public function getAccessToken()
    {
        $cacheKey = 'mpesa_access_token_' . md5($this->consumerKey . '|' . $this->baseUrl);

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->get("{$this->baseUrl}/oauth/v1/generate?grant_type=client_credentials");

            if ($response->successful() && ($token = $response->json('access_token'))) {
                // Expire a minute early to avoid using a token that lapses mid-request.
                $ttl = max(60, (int) ($response->json('expires_in') ?? 3600) - 60);
                Cache::put($cacheKey, $token, now()->addSeconds($ttl));
                return $token;
            }

            Log::error("Mpesa Access Token Error: " . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error("Mpesa Access Token Exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Step 3: STK Push Request
     * Dispatches the payment prompt to the user's phone.
     */
    public function initiateStkPush($phone, $amount, $reference, $description)
    {
        $token = $this->getAccessToken();
        if (!$token) return ['success' => false, 'message' => 'Failed to authenticate with M-Pesa.'];

        $timestamp = now()->format('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);
        
        // Clean phone number (Ensure it starts with 254)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) $phone = '254' . substr($phone, 1);
        if (!str_starts_with($phone, '254')) $phone = '254' . $phone;

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => round($amount),
            'PartyA' => $phone,
            'PartyB' => $this->shortcode,
            'PhoneNumber' => $phone,
            'CallBackURL' => $this->callbackUrl,
            'AccountReference' => $reference,
            'TransactionDesc' => $description,
        ];

        try {
            // Daraja "Lipa Na M-Pesa Online" STK push endpoint.
            $response = Http::withToken($token)
                ->post("{$this->baseUrl}/mpesa/stkpush/v1/processrequest", $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            Log::error("Mpesa STK Push Error: " . $response->body());
            return ['success' => false, 'message' => 'STK Push initiation failed.', 'details' => $response->json()];

        } catch (\Exception $e) {
            Log::error("Mpesa STK Push Exception: " . $e->getMessage());
            return ['success' => false, 'message' => 'An unexpected error occurred.'];
        }
    }

    /**
     * STK Push Query — authoritatively resolves a transaction's final state when
     * the async callback never arrived. Used by the reconciliation job.
     *
     * Returns ['success' => bool, 'data' => [...]] where data['ResultCode'] is:
     *   "0"    => paid
     *   others => failed/cancelled/timed-out (e.g. 1032 cancelled, 1037 timeout)
     *   while still pending, Daraja returns a non-success body we leave untouched.
     */
    public function queryStkStatus($checkoutRequestId)
    {
        $token = $this->getAccessToken();
        if (!$token) return ['success' => false, 'message' => 'Failed to authenticate with M-Pesa.'];

        $timestamp = now()->format('YmdHis');
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ];

        try {
            $response = Http::withToken($token)
                ->post("{$this->baseUrl}/mpesa/stkpushquery/v1/query", $payload);

            return ['success' => $response->successful(), 'data' => $response->json()];
        } catch (\Exception $e) {
            Log::error("Mpesa STK Query Exception: " . $e->getMessage());
            return ['success' => false, 'message' => 'An unexpected error occurred.'];
        }
    }
}
