<?php

namespace Tests\Feature;

use App\Console\Commands\ProductionPreflight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * X-Forwarded-For is only believed from proxies listed in TRUSTED_PROXIES.
 * Otherwise anyone could forge it and walk past the M-Pesa callback's IP
 * allowlist (and dodge per-IP rate limits).
 */
class TrustedProxiesTest extends TestCase
{
    use RefreshDatabase;

    private const SAFARICOM_IP = '196.201.214.200';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.mpesa.env' => 'production',
            'services.mpesa.callback_token' => 'secret',
            'services.mpesa.allowed_ips' => [self::SAFARICOM_IP],
        ]);
    }

    private function forgedCallback()
    {
        // Sent from an untrusted address, claiming to come from Safaricom.
        return $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->withHeaders(['X-Forwarded-For' => self::SAFARICOM_IP])
            ->postJson(route('mpesa.callback', ['token' => 'secret']), [
                'Body' => ['stkCallback' => ['ResultCode' => 1032, 'CheckoutRequestID' => 'ws_CO_NONE']],
            ]);
    }

    public function test_forged_forwarded_for_is_rejected_when_no_proxy_is_trusted(): void
    {
        config(['trustedproxy.proxies' => null]);

        $this->forgedCallback()->assertForbidden();
    }

    public function test_forwarded_for_is_honoured_from_a_configured_proxy(): void
    {
        config(['trustedproxy.proxies' => ['203.0.113.0/24']]);

        // Past the allowlist: the (unknown) checkout id is answered, not refused.
        $this->forgedCallback()->assertOk()->assertJsonPath('ResultDesc', 'Order Not Found');
    }

    public function test_trusted_proxies_are_read_from_a_comma_separated_env_value(): void
    {
        $this->assertSame(['10.0.0.1', '173.245.48.0/20'], \App\Support\TrustedProxies::parse(' 10.0.0.1, 173.245.48.0/20 ,'));
        $this->assertSame('*', \App\Support\TrustedProxies::parse('*'));
        $this->assertNull(\App\Support\TrustedProxies::parse(''));
        $this->assertNull(\App\Support\TrustedProxies::parse(null));
    }

    public function test_preflight_warns_when_every_proxy_is_trusted_alongside_an_ip_allowlist(): void
    {
        config(['trustedproxy.proxies' => '*']);
        User::factory()->create(['role' => 'owner', 'password' => Hash::make('kitenge-sunrise-42')]);
        Cache::forever(ProductionPreflight::HEARTBEAT, now()->timestamp);

        $this->artisan('serana:preflight')
            ->expectsOutputToContain('M-Pesa IP allowlist cannot be spoofed: TRUSTED_PROXIES is "*"');
    }

    public function test_preflight_is_quiet_when_proxies_are_listed(): void
    {
        config(['trustedproxy.proxies' => ['173.245.48.0/20']]);

        $this->artisan('serana:preflight')
            ->doesntExpectOutputToContain('TRUSTED_PROXIES is "*"');
    }
}
