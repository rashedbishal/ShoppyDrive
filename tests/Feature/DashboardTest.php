<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('account.dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('account.dashboard'));
    $response->assertOk();
});

test('DISC-46: the dashboard no longer sends or shows a total spent', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('account.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('account/Dashboard')
            ->has('recentOrders')
            ->missing('totalSpent'));

    expect((string) file_get_contents(resource_path('js/pages/account/Dashboard.tsx')))
        ->not->toContain('Total Spent')
        ->not->toContain('totalSpent');
});

test('DISC-46: recent orders open by row, with no Details button, and show as cards on phones', function () {
    // Source-level check (this project has no JavaScript test runner).
    $page = (string) preg_replace('/\s+/', ' ', (string) file_get_contents(resource_path('js/pages/account/Dashboard.tsx')));

    expect($page)->not->toContain('Details')
        ->toContain('role="link"')
        ->toContain('openOrder(order.order_number)')
        ->toContain('hidden w-full border-collapse text-left md:table')
        ->toContain('md:hidden')
        ->not->toContain('overflow-x-auto');
});

test('DISC-48: the account menu has no Sign out and shows as wrapping pills below lg', function () {
    // Source-level check (this project has no JavaScript test runner).
    $layout = (string) preg_replace('/\s+/', ' ', (string) file_get_contents(resource_path('js/components/layout/AccountLayout.tsx')));

    expect($layout)->not->toContain('Sign out')
        ->not->toContain("route('logout')")
        ->toContain('flex flex-wrap gap-2 lg:block')
        ->not->toContain('overflow-x-auto');

    // Customers can still sign out from the site header and the mobile menu.
    expect((string) file_get_contents(resource_path('js/components/layout/Header.tsx')))->toContain("route('logout')")
        ->and((string) file_get_contents(resource_path('js/components/layout/MobileMenu.tsx')))->toContain("route('logout')");
});
