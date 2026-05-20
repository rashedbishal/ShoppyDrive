<?php

use App\Models\NewsletterSubscriber;
use Illuminate\Support\Str;

beforeEach(function (): void {
    NewsletterSubscriber::query()->delete();
});

test('user can subscribe with a new email', function (): void {
    $response = $this->post('/newsletter/subscribe', ['email' => 'test@example.com']);

    $response->assertRedirect();
    $this->assertDatabaseHas('newsletter_subscribers', [
        'email' => 'test@example.com',
        'is_active' => true,
    ]);
});

test('subscribing with an existing active email returns already_subscribed status', function (): void {
    NewsletterSubscriber::create([
        'email' => 'test@example.com',
        'token' => Str::random(64),
        'is_active' => true,
        'subscribed_at' => now(),
    ]);

    $response = $this->post('/newsletter/subscribe', ['email' => 'test@example.com']);

    $response->assertRedirect();
    $response->assertSessionHas('newsletter_status', 'already_subscribed');
    $this->assertDatabaseCount('newsletter_subscribers', 1);
});

test('resubscribing an inactive email reactivates it', function (): void {
    NewsletterSubscriber::create([
        'email' => 'test@example.com',
        'token' => Str::random(64),
        'is_active' => false,
        'subscribed_at' => now()->subDay(),
        'unsubscribed_at' => now(),
    ]);

    $this->post('/newsletter/subscribe', ['email' => 'test@example.com']);

    $this->assertDatabaseHas('newsletter_subscribers', [
        'email' => 'test@example.com',
        'is_active' => true,
    ]);
});

test('user can unsubscribe', function (): void {
    NewsletterSubscriber::create([
        'email' => 'test@example.com',
        'token' => Str::random(64),
        'is_active' => true,
        'subscribed_at' => now(),
    ]);

    $this->post('/newsletter/unsubscribe', ['email' => 'test@example.com']);

    $this->assertDatabaseHas('newsletter_subscribers', [
        'email' => 'test@example.com',
        'is_active' => false,
    ]);
});

test('status endpoint returns subscription state', function (): void {
    NewsletterSubscriber::create([
        'email' => 'active@example.com',
        'token' => Str::random(64),
        'is_active' => true,
        'subscribed_at' => now(),
    ]);

    $this->getJson('/newsletter/status?email=active@example.com')
        ->assertJson(['is_subscribed' => true]);

    $this->getJson('/newsletter/status?email=notfound@example.com')
        ->assertJson(['is_subscribed' => false]);
});

test('subscribe validates email format', function (): void {
    $this->post('/newsletter/subscribe', ['email' => 'not-an-email'])
        ->assertSessionHasErrors(['email']);
});
