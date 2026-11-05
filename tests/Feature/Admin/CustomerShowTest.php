<?php

declare(strict_types=1);

use App\Models\SocialAccount;
use App\Models\User;

use function Pest\Laravel\actingAs;

test('admin can view a customer\'s detail page', function () {
    actingAs(createAdmin(), 'admin');
    $user = User::factory()->create();

    $this->get(route('admin.customers.show', $user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/customers/show')
            ->where('customer.id', $user->id)
        );
});

test('SEC-004: a customer\'s OAuth tokens never reach the admin customer page', function () {
    actingAs(createAdmin(), 'admin');
    $user = User::factory()->create();
    SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-456',
        'token' => 'canary-access-token',
        'refresh_token' => 'canary-refresh-token',
    ]);

    $response = $this->get(route('admin.customers.show', $user));

    $response->assertOk();
    expect(json_encode($response->viewData('page')['props']))->not->toContain('canary-access-token')
        ->and(json_encode($response->viewData('page')['props']))->not->toContain('canary-refresh-token');
});
