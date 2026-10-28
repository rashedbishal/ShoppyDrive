<?php

declare(strict_types=1);

use App\Models\BlockedIp;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin([
        'name' => 'Admin User',
        'email' => 'admin-blocked-ip@example.com',
        'password' => bcrypt('password'),
    ]);
});

test('admin can view the blocked ip list', function () {
    BlockedIp::create(['ip_address' => '203.0.113.10', 'reason' => 'Fraudulent orders']);

    actingAs($this->admin, 'admin');

    $response = $this->get(route('admin.blocked-ips.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/blocked-ips/index')
        ->where('blockedIps.0.ip_address', '203.0.113.10')
    );
});

test('admin can block a new ip address', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.blocked-ips.store'), [
        'ip_address' => '198.51.100.20',
        'reason' => 'Repeated fake COD orders',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('blocked_ips', [
        'ip_address' => '198.51.100.20',
        'reason' => 'Repeated fake COD orders',
        'blocked_by' => $this->admin->id,
    ]);
});

test('an ip address cannot be blocked twice', function () {
    BlockedIp::create(['ip_address' => '198.51.100.20']);

    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.blocked-ips.store'), [
        'ip_address' => '198.51.100.20',
    ]);

    $response->assertSessionHasErrors('ip_address');
    expect(BlockedIp::where('ip_address', '198.51.100.20')->count())->toBe(1);
});

test('blocking an invalid ip address is rejected', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.blocked-ips.store'), [
        'ip_address' => 'not-an-ip',
    ]);

    $response->assertSessionHasErrors('ip_address');
});

test('admin can unblock an ip address', function () {
    $blockedIp = BlockedIp::create(['ip_address' => '203.0.113.10']);

    actingAs($this->admin, 'admin');

    $response = $this->delete(route('admin.blocked-ips.destroy', $blockedIp));

    $response->assertRedirect();
    $this->assertDatabaseMissing('blocked_ips', ['id' => $blockedIp->id]);
});

test('guests cannot manage blocked ips', function () {
    $response = $this->get(route('admin.blocked-ips.index'));

    $response->assertRedirect(route('admin.login'));
});
