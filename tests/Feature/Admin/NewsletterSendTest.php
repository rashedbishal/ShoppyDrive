<?php

declare(strict_types=1);

use App\Mail\NewsletterCustomMail;
use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin();

    NewsletterSubscriber::create([
        'email' => 'subscriber@example.com',
        'token' => 'test-token',
        'is_active' => true,
        'subscribed_at' => now(),
    ]);
});

test('custom newsletter body strips malicious HTML before sending', function () {
    Mail::fake();
    actingAs($this->admin, 'admin');

    $maliciousBody = '<p>Hello</p><script>alert(1)</script><img src="x" onerror="alert(1)">';

    $response = $this->post(route('admin.newsletter.send'), [
        'type' => 'custom',
        'subject' => 'Test subject',
        'body' => $maliciousBody,
    ]);

    $response->assertRedirect();

    Mail::assertSent(NewsletterCustomMail::class, function (NewsletterCustomMail $mail) {
        return ! str_contains($mail->body, '<script')
            && ! str_contains($mail->body, 'onerror')
            && str_contains($mail->body, 'Hello');
    });
});

test('custom newsletter body preserves legitimate formatting supported by the current sanitizer', function () {
    Mail::fake();
    actingAs($this->admin, 'admin');

    $legitimateBody = '<p>Check out our <b>new</b> <a href="https://example.com">collection</a>.</p>';

    $this->post(route('admin.newsletter.send'), [
        'type' => 'custom',
        'subject' => 'Test subject',
        'body' => $legitimateBody,
    ]);

    Mail::assertSent(NewsletterCustomMail::class, function (NewsletterCustomMail $mail) {
        return str_contains($mail->body, '<b>new</b>')
            && str_contains($mail->body, 'href="https://example.com"')
            && str_contains($mail->body, 'collection');
    });
});
