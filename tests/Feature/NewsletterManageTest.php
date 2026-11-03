<?php

declare(strict_types=1);

use App\Mail\NewsletterCustomMail;
use App\Mail\NewsletterProductMail;
use App\Mail\NewsletterUnsubscribeLinkMail;
use App\Models\NewsletterSubscriber;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * FSA-14: the generic "Manage subscription / Unsubscribe" link in every
 * newsletter campaign email can't be personalized (FSA-13 sends each chunk
 * as one shared BCC message with one identical body for up to 50
 * recipients). Visiting it lets a subscriber request a *separate*,
 * one-to-one email containing their own token link — this never discloses
 * whether the submitted email exists or is subscribed, either way.
 */

test('the custom newsletter template contains the generic management link', function (): void {
    $mail = new NewsletterCustomMail(emailSubject: 'Hello', body: '<p>Hi</p>');

    expect($mail->render())->toContain(route('newsletter.manage'));
});

test('the product-promotion newsletter template contains the generic management link', function (): void {
    $product = Product::factory()->create();
    $mail = new NewsletterProductMail(emailSubject: 'New arrival', product: $product);

    expect($mail->render())->toContain(route('newsletter.manage'));
});

test('the management page is publicly accessible', function (): void {
    $this->get(route('newsletter.manage'))
        ->assertInertia(fn (Assert $page) => $page->component('newsletter/Manage'));
});

test('an active subscriber requesting a management link receives their own token', function (): void {
    Mail::fake();
    $subscriber = NewsletterSubscriber::create([
        'email' => 'subscriber@example.com',
        'token' => Str::random(64),
        'is_active' => true,
        'subscribed_at' => now(),
    ]);

    $this->post(route('newsletter.manage.send'), ['email' => $subscriber->email])
        ->assertRedirect()
        ->assertSessionHas('newsletter_status', 'manage_requested');

    Mail::assertQueued(NewsletterUnsubscribeLinkMail::class, fn (NewsletterUnsubscribeLinkMail $mail): bool => $mail->hasTo($subscriber->email)
        && $mail->subscriberEmail === $subscriber->email
        && $mail->token === $subscriber->token);
});

test('a nonexistent email gets the same generic response and no email is sent', function (): void {
    Mail::fake();

    $this->post(route('newsletter.manage.send'), ['email' => 'nobody@example.com'])
        ->assertRedirect()
        ->assertSessionHas('newsletter_status', 'manage_requested');

    Mail::assertNothingQueued();
});

test('an already-unsubscribed email gets the same generic response and no email is sent', function (): void {
    Mail::fake();
    NewsletterSubscriber::create([
        'email' => 'inactive@example.com',
        'token' => Str::random(64),
        'is_active' => false,
        'subscribed_at' => now()->subDay(),
        'unsubscribed_at' => now(),
    ]);

    $this->post(route('newsletter.manage.send'), ['email' => 'inactive@example.com'])
        ->assertRedirect()
        ->assertSessionHas('newsletter_status', 'manage_requested');

    Mail::assertNothingQueued();
});

test('a queue dispatch failure still returns the same generic response and changes nothing', function (): void {
    Log::spy();
    Mail::shouldReceive('to')->andReturnSelf();
    Mail::shouldReceive('queue')->andThrow(new RuntimeException('Queue connection refused'));

    $subscriber = NewsletterSubscriber::create([
        'email' => 'subscriber@example.com',
        'token' => Str::random(64),
        'is_active' => true,
        'subscribed_at' => now(),
    ]);

    $this->post(route('newsletter.manage.send'), ['email' => $subscriber->email])
        ->assertRedirect()
        ->assertSessionHas('newsletter_status', 'manage_requested');

    expect($subscriber->refresh()->is_active)->toBeTrue();
    expect($subscriber->refresh()->token)->not->toBeEmpty();

    Log::shouldHaveReceived('error')
        ->withArgs(function (string $message, array $context) use ($subscriber): bool {
            $haystack = $message.' '.json_encode($context);

            return ! str_contains($haystack, $subscriber->email)
                && ! str_contains($haystack, $subscriber->token);
        })
        ->once();
});

test('the full loop: request a link, confirm without unsubscribing, then unsubscribe', function (): void {
    Mail::fake();
    $subscriber = NewsletterSubscriber::create([
        'email' => 'subscriber@example.com',
        'token' => Str::random(64),
        'is_active' => true,
        'subscribed_at' => now(),
    ]);

    $this->post(route('newsletter.manage.send'), ['email' => $subscriber->email]);

    $sentToken = null;
    Mail::assertQueued(NewsletterUnsubscribeLinkMail::class, function (NewsletterUnsubscribeLinkMail $mail) use (&$sentToken): bool {
        $sentToken = $mail->token;

        return true;
    });

    // Opening the mailed link only shows the confirmation page — nothing changes yet.
    $this->get(route('newsletter.unsubscribe.confirm', ['email' => $subscriber->email, 'token' => $sentToken]))
        ->assertInertia(fn (Assert $page) => $page->where('status', 'confirm'));
    expect($subscriber->refresh()->is_active)->toBeTrue();

    // A tampered token from that same link cannot unsubscribe.
    $this->post(route('newsletter.unsubscribe'), ['email' => $subscriber->email, 'token' => $sentToken.'x']);
    expect($subscriber->refresh()->is_active)->toBeTrue();

    // Only the explicit confirm POST with the real token unsubscribes.
    $this->post(route('newsletter.unsubscribe'), ['email' => $subscriber->email, 'token' => $sentToken]);
    expect($subscriber->refresh()->is_active)->toBeFalse();
});
