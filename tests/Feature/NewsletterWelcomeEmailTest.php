<?php

declare(strict_types=1);

use App\Mail\NewsletterWelcomeMail;
use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a new subscriber is sent a welcome email carrying their own unsubscribe token', function (): void {
    Mail::fake();

    $this->post(route('newsletter.subscribe'), ['email' => 'new@example.com'])->assertRedirect();

    $subscriber = NewsletterSubscriber::where('email', 'new@example.com')->sole();

    Mail::assertQueued(NewsletterWelcomeMail::class, fn (NewsletterWelcomeMail $mail): bool => $mail->hasTo('new@example.com')
        && $mail->token === $subscriber->token);
    Mail::assertQueuedCount(1);
});

test('a returning subscriber gets a welcome email with their new token, not the old one', function (): void {
    Mail::fake();
    $subscriber = NewsletterSubscriber::create([
        'email' => 'back@example.com',
        'token' => $oldToken = Str::random(64),
        'is_active' => false,
        'subscribed_at' => now()->subMonth(),
        'unsubscribed_at' => now()->subWeek(),
    ]);

    $this->post(route('newsletter.subscribe'), ['email' => 'back@example.com']);

    $newToken = $subscriber->refresh()->token;
    expect($newToken)->not->toBe($oldToken);
    Mail::assertQueued(NewsletterWelcomeMail::class, fn (NewsletterWelcomeMail $mail): bool => $mail->token === $newToken);
});

test('an already active subscriber is not emailed again', function (): void {
    Mail::fake();
    NewsletterSubscriber::create([
        'email' => 'active@example.com',
        'token' => Str::random(64),
        'is_active' => true,
        'subscribed_at' => now(),
    ]);

    $this->post(route('newsletter.subscribe'), ['email' => 'active@example.com']);

    Mail::assertNothingQueued();
});

test('an invalid email is not emailed', function (): void {
    Mail::fake();

    $this->post(route('newsletter.subscribe'), ['email' => 'not-an-email'])->assertSessionHasErrors('email');

    Mail::assertNothingQueued();
});

test('the welcome email links to that subscriber\'s working unsubscribe confirmation', function (): void {
    $mail = new NewsletterWelcomeMail('reader@example.com', 'reader-token');
    $unsubscribeUrl = route('newsletter.unsubscribe.confirm', ['email' => 'reader@example.com', 'token' => 'reader-token']);

    $mail->assertSeeInHtml(e($unsubscribeUrl), false)
        ->assertSeeInHtml('Thanks for subscribing!');
    expect($mail->headers()->text['List-Unsubscribe'])->toBe('<'.$unsubscribeUrl.'>');

    NewsletterSubscriber::create([
        'email' => 'reader@example.com',
        'token' => 'reader-token',
        'is_active' => true,
        'subscribed_at' => now(),
    ]);

    $this->get($unsubscribeUrl)->assertInertia(fn (Assert $page) => $page->where('status', 'confirm'));
});

test('a mail queue failure still subscribes the visitor and logs no email or token', function (): void {
    Log::spy();
    Mail::shouldReceive('to')->andReturnSelf();
    Mail::shouldReceive('queue')->andThrow(new RuntimeException('Queue connection refused'));

    $this->post(route('newsletter.subscribe'), ['email' => 'queued@example.com'])
        ->assertRedirect()
        ->assertInertiaFlash('newsletter.status', 'subscribed');

    $subscriber = NewsletterSubscriber::where('email', 'queued@example.com')->sole();
    expect($subscriber->is_active)->toBeTrue();

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message, array $context): bool => ! str_contains($message.json_encode($context), 'queued@example.com')
            && ! str_contains($message.json_encode($context), $subscriber->token))
        ->once();
});
