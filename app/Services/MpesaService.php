<?php

namespace App\Services;

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
     * Step 1: Authentication
     * Generates an OAuth2 access token valid for 3600 seconds.
     */
    public function getAccessToken()
    {
        try {
            $response = Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
                ->get("{$this->baseUrl}/oauth/v1/generate?grant_type=client_credentials");

            if ($response->successful()) {
                return $response->json()['access_token'];
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
            $response = Http::withToken($token)
                ->post("{$this->baseUrl}/mpesa/stkpush/v1/query", $payload); // Wait, stkpush usually has a different endpoint

            // Correction: Real Daraja endpoint for Lipa Na M-Pesa Online
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
}
