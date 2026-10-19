<?php

namespace Tests\Feature;

use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscribing_stores_the_email_once(): void
    {
        $this->post(route('newsletter.subscribe'), ['email' => 'Fan@Example.com'])->assertRedirect()->assertSessionHasNoErrors();
        // A second sign-up (any casing) is a quiet success, not "already taken".
        $this->post(route('newsletter.subscribe'), ['email' => 'fan@example.com '])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, Lead::where('email', 'fan@example.com')->count());
    }

    public function test_an_invalid_email_is_rejected(): void
    {
        $this->post(route('newsletter.subscribe'), ['email' => 'not-an-email'])->assertSessionHasErrors('email');
        $this->assertSame(0, Lead::count());
    }
}
