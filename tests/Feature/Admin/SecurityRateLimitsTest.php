<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Setting;
use App\Models\User;
use App\Support\SecurityRateLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Every field at its lowest allowed value, so limits trip after a handful of requests.
 *
 * @return array<string, int>
 */
function lowestSecurityLimits(): array
{
    return collect(SecurityRateLimits::FIELDS)->map(fn (array $bounds): int => $bounds['min'])->all();
}

function plainAdmin(): Admin
{
    return createAdmin(['name' => 'Staff', 'email' => 'staff-'.uniqid().'@example.com', 'password' => bcrypt('password')]);
}

// ── Settings storage & guardrails ────────────────────────────────────────────

test('defaults apply when nothing has been saved', function () {
    expect(SecurityRateLimits::get('orders_per_network_times'))->toBe(10)
        ->and(SecurityRateLimits::get('coupon_checks_minutes'))->toBe(1);
});

test('out-of-range or junk stored values are clamped, never disabling a limit', function () {
    Setting::create(['key' => SecurityRateLimits::SETTING_KEY, 'group' => 'security', 'value' => [
        'orders_per_network_times' => 0,
        'orders_per_phone_times' => 999999,
        'coupon_checks_times' => 'abc',
    ]]);

    expect(SecurityRateLimits::get('orders_per_network_times'))->toBe(3)
        ->and(SecurityRateLimits::get('orders_per_phone_times'))->toBe(100)
        ->and(SecurityRateLimits::get('coupon_checks_times'))->toBe(10);
});

// ── Admin > Security page ────────────────────────────────────────────────────

test('any admin can view the security page, but only the mother can edit', function () {
    actingAs(plainAdmin(), 'admin')
        ->get(route('admin.settings.security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/security')
            ->where('canEdit', false)
            ->where('limits.orders_per_network_times', 10));

    actingAs(createMotherAdmin(), 'admin')
        ->get(route('admin.settings.security.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('canEdit', true));
});

test('the mother admin can save new limits and they take effect immediately', function () {
    SecurityRateLimits::all(); // warm the cache

    actingAs(createMotherAdmin(), 'admin')
        ->put(route('admin.settings.security.update'), [...SecurityRateLimits::all(), 'orders_per_network_times' => 25])
        ->assertRedirect(route('admin.settings.security.edit'))
        ->assertSessionHasNoErrors();

    expect(SecurityRateLimits::get('orders_per_network_times'))->toBe(25);
});

test('a non-mother admin cannot change limits', function () {
    actingAs(plainAdmin(), 'admin')
        ->put(route('admin.settings.security.update'), [...SecurityRateLimits::all(), 'orders_per_network_times' => 25])
        ->assertSessionHas('error');

    expect(SecurityRateLimits::get('orders_per_network_times'))->toBe(10);
});

test('values outside the allowed range are rejected with a plain-language message', function () {
    actingAs(createMotherAdmin(), 'admin')
        ->put(route('admin.settings.security.update'), [...SecurityRateLimits::all(), 'orders_per_network_times' => 1, 'coupon_checks_minutes' => 99999])
        ->assertSessionHasErrors([
            'orders_per_network_times' => 'This number is too low — it must be at least 3.',
            'coupon_checks_minutes' => 'This number is too high — it can be at most 1440.',
        ]);
});

// ── Limits actually enforced ─────────────────────────────────────────────────

test('placing orders from one customer is limited and shows a friendly message', function () {
    SecurityRateLimits::save(lowestSecurityLimits());
    $user = User::factory()->create();

    foreach (range(1, 3) as $attempt) {
        actingAs($user)->post(route('checkout.store'), ['phone' => '0170000000'.$attempt])
            ->assertSessionMissing('inertia.flash_data.toast');
    }

    actingAs($user)->post(route('checkout.store'), ['phone' => '01700000009'])
        ->assertRedirect()
        ->assertSessionHasErrors('rate_limit')
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');
});

test('placing orders with one phone number is limited across different customers', function () {
    SecurityRateLimits::save(lowestSecurityLimits());

    foreach (range(1, 2) as $ignored) {
        actingAs(User::factory()->create())->post(route('checkout.store'), ['phone' => '+880 1711-111111']);
    }

    actingAs(User::factory()->create())->post(route('checkout.store'), ['phone' => '01711111111'])
        ->assertSessionHasErrors('rate_limit');
});

test('bKash/Nagad transaction ID tries are limited per order', function () {
    SecurityRateLimits::save(lowestSecurityLimits());
    $user = User::factory()->create();

    foreach (range(1, 3) as $ignored) {
        actingAs($user)->post(route('payment.mobile.submit', 'some-token'), ['transaction_id' => 'ABCD1234']);
    }

    actingAs($user)->post(route('payment.mobile.submit', 'some-token'), ['transaction_id' => 'ABCD1234'])
        ->assertSessionHasErrors('rate_limit');

    actingAs($user)->post(route('payment.mobile.submit', 'other-token'), ['transaction_id' => 'ABCD1234'])
        ->assertSessionDoesntHaveErrors('rate_limit');
});

test('coupon checks are limited and return the message the coupon box displays', function () {
    SecurityRateLimits::save(lowestSecurityLimits());

    foreach (range(1, 3) as $ignored) {
        $this->postJson(route('coupon.validate'), ['code' => 'GUESS', 'subtotal' => 100])->assertStatus(422);
    }

    $this->postJson(route('coupon.validate'), ['code' => 'GUESS', 'subtotal' => 100])
        ->assertStatus(429)
        ->assertJsonPath('error', 'Too many coupon tries. Please wait a minute and try again.');
});

test('sign-ups from one network are limited', function () {
    SecurityRateLimits::save(lowestSecurityLimits());

    foreach (range(1, 2) as $attempt) {
        $this->post(route('register.store'), ['name' => 'Bot', 'email' => "bot{$attempt}@example.com", 'password' => 'x']);
        auth('web')->logout();
    }

    $this->post(route('register.store'), ['name' => 'Bot', 'email' => 'bot9@example.com', 'password' => 'x'])
        ->assertSessionHasErrors('rate_limit');
});

test('password reset emails are limited per email address', function () {
    SecurityRateLimits::save(lowestSecurityLimits());

    $this->post(route('password.email'), ['email' => 'victim@example.com']);

    $this->post(route('password.email'), ['email' => 'victim@example.com'])
        ->assertSessionHasErrors('rate_limit');
});

test('other Fortify routes are not affected by the sign-up and reset limits', function () {
    SecurityRateLimits::save(lowestSecurityLimits());

    foreach (range(1, 5) as $ignored) {
        $this->get(route('register'))->assertOk();
    }
});

test('customer sign-in attempts across many accounts from one network are limited', function () {
    SecurityRateLimits::save(lowestSecurityLimits());

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), ['email' => "user{$attempt}@example.com", 'password' => 'wrong']);
    }

    $this->post(route('login.store'), ['email' => 'user9@example.com', 'password' => 'wrong'])
        ->assertSessionHasErrors('rate_limit');
});

test('admin sign-in has a fixed per-network cap that the security settings cannot change', function () {
    foreach (range(1, 20) as $attempt) {
        $this->post(route('admin.login.submit'), ['email' => "admin{$attempt}@example.com", 'password' => 'wrong']);
    }

    $this->post(route('admin.login.submit'), ['email' => 'admin99@example.com', 'password' => 'wrong'])
        ->assertStatus(429);
});

// ── Extra protection ─────────────────────────────────────────────────────────

test('each protected route has its limiter attached', function (string $routeName, string $middleware) {
    expect(app('router')->getRoutes()->getByName($routeName)->gatherMiddleware())->toContain($middleware);
})->with([
    ['contact.store', 'throttle:contact-message'],
    ['newsletter.subscribe', 'throttle:newsletter-signup'],
    ['newsletter.unsubscribe', 'throttle:newsletter-signup'],
    ['newsletter.status', 'throttle:newsletter-check'],
    ['checkout.track-incomplete', 'throttle:checkout-tracking'],
    ['order.track', 'throttle:order-tracking'],
    ['order.track.check', 'throttle:order-tracking'],
    ['products.index', 'throttle:product-browsing'],
    ['category.show', 'throttle:product-browsing'],
    ['search', 'throttle:product-browsing'],
    ['account.orders.invoice', 'throttle:invoice-download'],
    ['lp.invoice', 'throttle:invoice-download'],
    ['product.reviews.store', 'throttle:review'],
    ['connections.password.update', 'throttle:password-check'],
]);

test('the contact form is limited with a friendly message', function () {
    SecurityRateLimits::save(lowestSecurityLimits());

    foreach (range(1, 2) as $ignored) {
        $this->post(route('contact.store'), []);
    }

    $this->post(route('contact.store'), [])
        ->assertSessionHasErrors('rate_limit')
        ->assertSessionHas('inertia.flash_data.toast.type', 'error');
});

test('a throttled page shows the 429 page instead of redirecting, and queues no pop-up', function () {
    SecurityRateLimits::save(lowestSecurityLimits());

    foreach (range(1, 10) as $ignored) {
        $this->get(route('order.track'))->assertOk();
    }

    $this->get(route('order.track'))
        ->assertStatus(429)
        ->assertSessionMissing('inertia.flash_data.toast');
});

test('a background check without a JSON header gets a plain 429 and queues no pop-up', function () {
    SecurityRateLimits::save(lowestSecurityLimits());

    foreach (range(1, 10) as $ignored) {
        $this->get(route('newsletter.status', ['email' => 'a@example.com']));
    }

    $this->get(route('newsletter.status', ['email' => 'a@example.com']))
        ->assertStatus(429)
        ->assertSessionMissing('inertia.flash_data.toast');
});

test('the live order lookup returns the message the tracking page displays', function () {
    SecurityRateLimits::save(lowestSecurityLimits());

    foreach (range(1, 10) as $ignored) {
        $this->getJson(route('order.track.check', ['order_number' => 'ORD-X']));
    }

    $this->getJson(route('order.track.check', ['order_number' => 'ORD-X']))
        ->assertStatus(429)
        ->assertJsonPath('error', 'Too many order lookups. Please wait a minute and try again.');
});

test('search suggestions are limited', function () {
    SecurityRateLimits::save(lowestSecurityLimits());

    foreach (range(1, 20) as $ignored) {
        $this->getJson('/api/search/suggestions?q=ab')->assertOk();
    }

    $this->getJson('/api/search/suggestions?q=ab')->assertStatus(429);
});

test('invoice downloads are limited', function () {
    SecurityRateLimits::save(lowestSecurityLimits());

    foreach (range(1, 3) as $ignored) {
        $this->get(route('lp.invoice', 'no-such-token'))->assertNotFound();
    }

    $this->get(route('lp.invoice', 'no-such-token'))->assertStatus(429);
});

test('password confirmation attempts are limited per customer', function () {
    SecurityRateLimits::save(lowestSecurityLimits());
    $user = User::factory()->create();

    foreach (range(1, 3) as $ignored) {
        actingAs($user)->post(route('password.confirm.store'), ['password' => 'wrong']);
    }

    actingAs($user)->post(route('password.confirm.store'), ['password' => 'wrong'])
        ->assertSessionHasErrors('rate_limit');
});

// ── Low priority: webhooks, account actions, removed API ─────────────────────

test('account actions and webhooks have their limiters attached', function (string $routeName, string $middleware) {
    expect(app('router')->getRoutes()->getByName($routeName)->gatherMiddleware())->toContain($middleware);
})->with([
    ['account.addresses.store', 'throttle:account-action'],
    ['account.addresses.update', 'throttle:account-action'],
    ['account.addresses.destroy', 'throttle:account-action'],
    ['account.addresses.default', 'throttle:account-action'],
    ['account.orders.cancel', 'throttle:account-action'],
    ['account.orders.return.store', 'throttle:account-action'],
    ['account.referral.daily-login', 'throttle:account-action'],
    ['wishlist.toggle', 'throttle:account-action'],
    ['wishlist.sync', 'throttle:account-action'],
    ['wishlist.clear', 'throttle:account-action'],
    ['wishlist.add-all-to-cart', 'throttle:account-action'],
    ['profile.update', 'throttle:account-action'],
    ['profile.destroy', 'throttle:account-action'],
    ['connections.destroy', 'throttle:account-action'],
    ['zinipay.webhook', 'throttle:webhook'],
    ['steadfast.webhook', 'throttle:webhook'],
]);

test('cart syncing is deliberately not limited, since fast quantity clicks must never desync the cart', function () {
    expect(app('router')->getRoutes()->getByName('cart.sync')->gatherMiddleware())
        ->not->toContain('throttle:account-action');
});

test('account actions are limited per customer', function () {
    SecurityRateLimits::save(lowestSecurityLimits());
    $user = User::factory()->create();

    foreach (range(1, 20) as $ignored) {
        actingAs($user)->post(route('wishlist.clear'))->assertSessionDoesntHaveErrors('rate_limit');
    }

    actingAs($user)->post(route('wishlist.clear'))->assertSessionHasErrors('rate_limit');
});

test('wrong-token requests to the SMS forwarder endpoint are capped', function () {
    config(['services.sms_forwarder.token' => 'real-phone-token']);

    foreach (range(1, 60) as $ignored) {
        $this->withToken('guess')->postJson('/api/sms/receive')->assertUnauthorized();
    }

    $this->withToken('guess')->postJson('/api/sms/receive')->assertStatus(429);
});

test('the real SMS forwarder phone is never limited, even in a large burst', function () {
    config(['services.sms_forwarder.token' => 'real-phone-token']);

    foreach (range(1, 100) as $ignored) {
        expect($this->withToken('real-phone-token')->postJson('/api/sms/receive')->status())->not->toBe(429);
    }
});

test('a missing token on both sides never counts as the real phone', function () {
    config(['services.sms_forwarder.token' => '']);

    foreach (range(1, 60) as $ignored) {
        $this->postJson('/api/sms/receive');
    }

    $this->postJson('/api/sms/receive')->assertStatus(429);
});

test('the unused v1 API routes are gone', function (string $method, string $uri) {
    $this->json($method, $uri)->assertNotFound();
})->with([
    ['GET', '/api/v1/products'],
    ['GET', '/api/v1/products/some-slug'],
    ['POST', '/api/v1/auth/login'],
    ['POST', '/api/v1/auth/register'],
]);

test('the ZiniPay webhook logs only the identifiers, not the whole request', function () {
    Log::spy();

    $this->getJson('/api/zinipay/webhook?val_id=tok123&invoice_id=inv456&junk='.str_repeat('x', 5000));

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message, array $context = []) => $message === '[ZiniPay] Webhook received'
            && $context === ['val_id' => 'tok123', 'invoice_id' => 'inv456'])
        ->once();
});
