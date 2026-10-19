<?php

namespace Tests\Feature;

use App\Console\Commands\ProductionPreflight;
use App\Http\Middleware\WarnOnUntrustedForwardedHeaders;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Preflight runs from the CLI and can't see the proxy in front of the app, but
 * requests can: forwarded headers from an untrusted address mean a proxy sits
 * in front and TRUSTED_PROXIES is missing it.
 */
class UntrustedProxyWarningTest extends TestCase
{
    use RefreshDatabase;

    private function forwardedRequest(string $remote = '172.70.1.5', array $headers = ['X-Forwarded-For' => '41.90.1.2'])
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $remote])->withHeaders($headers)->get('/robots.txt')->assertOk();
    }

    private function expectWarnings(int $times): void
    {
        if ($times === 0) {
            Log::shouldNotHaveReceived('warning');

            return;
        }

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message, $context = []) => str_contains($message, 'TRUSTED_PROXIES'))
            ->times($times);
    }

    public function test_an_untrusted_forwarded_request_logs_one_warning_per_hour(): void
    {
        config(['trustedproxy.proxies' => null]);
        Log::spy();

        $this->forwardedRequest();
        $this->forwardedRequest(headers: ['X-Forwarded-Proto' => 'https']);

        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $context = []) => str_contains($message, 'TRUSTED_PROXIES')
            && $context['remote_addr'] === '172.70.1.5');
        $this->assertSame('172.70.1.5', Cache::get(WarnOnUntrustedForwardedHeaders::SEEN_KEY)['remote_addr']);

        // An hour later it is worth saying again.
        $this->travel(61)->minutes();
        $this->forwardedRequest();
        $this->expectWarnings(2);
    }

    public function test_no_warning_when_the_proxy_is_trusted(): void
    {
        config(['trustedproxy.proxies' => ['172.64.0.0/13']]);
        Log::spy();

        $this->forwardedRequest();

        $this->expectWarnings(0);
        $this->assertNull(Cache::get(WarnOnUntrustedForwardedHeaders::SEEN_KEY));
    }

    public function test_no_warning_without_forwarded_headers(): void
    {
        config(['trustedproxy.proxies' => null]);
        Log::spy();

        $this->forwardedRequest(headers: []);

        $this->expectWarnings(0);
        $this->assertNull(Cache::get(WarnOnUntrustedForwardedHeaders::SEEN_KEY));
    }

    public function test_preflight_reports_a_proxy_seen_in_the_last_day(): void
    {
        User::factory()->create(['role' => 'admin', 'password' => Hash::make('kitenge-sunrise-42')]);
        config(['trustedproxy.proxies' => null]);
        $this->forwardedRequest();

        $this->artisan('serana:preflight')
            ->expectsOutputToContain('Requests reach the app through a trusted proxy only: forwarded headers arrived from untrusted 172.70.1.5');
    }

    public function test_preflight_is_quiet_when_no_untrusted_proxy_was_seen(): void
    {
        User::factory()->create(['role' => 'admin', 'password' => Hash::make('kitenge-sunrise-42')]);

        $this->artisan('serana:preflight')
            ->doesntExpectOutputToContain('forwarded headers arrived from untrusted');
    }

    public function test_the_record_expires_after_a_day(): void
    {
        config(['trustedproxy.proxies' => null]);
        $this->forwardedRequest();

        $this->travel(25)->hours();

        $this->assertNull(Cache::get(WarnOnUntrustedForwardedHeaders::SEEN_KEY));
    }
}
