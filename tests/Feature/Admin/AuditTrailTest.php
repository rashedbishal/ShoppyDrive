<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Category;
use App\Models\Order;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

function regularAuditAdmin(array $attributes = []): Admin
{
    return Admin::create(array_merge([
        'name' => 'Staff Admin',
        'email' => 'staff-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
    ], $attributes));
}

function minimalOrder(array $attributes = []): Order
{
    return Order::create(array_merge([
        'order_number' => 'ORD-'.uniqid(),
        'status' => OrderStatus::PENDING,
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 500,
        'shipping_cost' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 560,
        'shipping_address' => [
            'name' => 'Rafiq Islam',
            'phone' => '01712345678',
            'address_line1' => 'House 1, Road 2',
            'city' => 'Dhaka',
            'state' => 'Dhaka',
            'country' => 'Bangladesh',
        ],
        'billing_address' => [],
    ], $attributes));
}

// ── Access control ───────────────────────────────────────────────────────────

test('a regular admin without can_view_audit_trail cannot view the audit trail', function () {
    actingAs(regularAuditAdmin(), 'admin');

    $this->get(route('admin.audit-trail.index'))->assertStatus(403);
});

test('a regular admin granted can_view_audit_trail can view the audit trail', function () {
    $admin = regularAuditAdmin();
    $admin->forceFill(['can_view_audit_trail' => true])->save();
    actingAs($admin, 'admin');

    $this->get(route('admin.audit-trail.index'))->assertOk();
});

test('the mother admin can always view the audit trail regardless of the flag', function () {
    actingAs(createMotherAdmin(), 'admin');

    $this->get(route('admin.audit-trail.index'))->assertOk();
});

test('a client cannot smuggle can_view_audit_trail into their own self-edit request', function () {
    $regular = regularAuditAdmin(['email' => 'selfgrant@example.com']);
    actingAs($regular, 'admin');

    $this->put(route('admin.admins.update', $regular), [
        'name' => $regular->name,
        'email' => 'selfgrant@example.com',
        'can_view_audit_trail' => true,
    ]);

    expect($regular->refresh()->can_view_audit_trail)->toBeFalse();
});

test('the mother admin can grant can_view_audit_trail to another admin', function () {
    actingAs(createMotherAdmin(), 'admin');
    $regular = regularAuditAdmin(['email' => 'grantee@example.com']);

    $this->put(route('admin.admins.update', $regular), [
        'name' => $regular->name,
        'email' => 'grantee@example.com',
        'can_view_audit_trail' => true,
    ]);

    expect($regular->refresh()->can_view_audit_trail)->toBeTrue();
});

// ── Admin CRUD logging ───────────────────────────────────────────────────────

test('creating an admin-managed model writes an admin-log activity entry', function () {
    $admin = createMotherAdmin();
    actingAs($admin, 'admin');

    $category = Category::factory()->create(['name' => 'Electronics']);

    $activity = Activity::where('log_name', 'admin')
        ->where('subject_type', Category::class)
        ->where('subject_id', $category->id)
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->event)->toBe('created');
    expect($activity->causer_id)->toBe($admin->id);
    expect($activity->causer_type)->toBe(Admin::class);
});

// ── Login / logout logging ───────────────────────────────────────────────────

test('a successful admin login writes a login activity entry', function () {
    $admin = regularAuditAdmin(['email' => 'logger@example.com']);

    $this->post(route('admin.login.submit'), [
        'email' => 'logger@example.com',
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard'));

    $activity = Activity::where('log_name', 'admin')->where('event', 'login')->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($admin->id);
});

test('admin logout writes a logout activity entry', function () {
    $admin = regularAuditAdmin(['email' => 'loggerout@example.com']);
    actingAs($admin, 'admin');

    $this->post(route('admin.logout'));

    $activity = Activity::where('log_name', 'admin')->where('event', 'logout')->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($admin->id);
});

// ── Order placement / status-change logging (client side) ──────────────────

test('placing an order writes an orders-log activity entry', function () {
    $order = minimalOrder();

    $activity = Activity::where('log_name', 'orders')
        ->where('subject_id', $order->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->description)->toContain($order->order_number);
});

test('changing an orders status writes an orders-log activity entry', function () {
    $admin = createMotherAdmin();
    actingAs($admin, 'admin');
    $order = minimalOrder();

    $this->patch(route('admin.orders.cancel', $order), ['reason' => 'Customer request']);

    $activity = Activity::where('log_name', 'orders')
        ->where('subject_id', $order->id)
        ->where('event', 'updated')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->description)->toContain('pending')
        ->toContain('cancelled');
    expect($activity->causer_id)->toBe($admin->id);
});

test('an order update that does not touch status is not logged', function () {
    $order = minimalOrder();
    Activity::where('subject_id', $order->id)->delete();

    $order->update(['tracking_number' => 'TRK-123']);

    expect(Activity::where('subject_id', $order->id)->where('log_name', 'orders')->exists())->toBeFalse();
});

// ── Clearing the log ─────────────────────────────────────────────────────────

test('a regular admin, even with view access, cannot clear the audit trail', function () {
    $admin = regularAuditAdmin();
    $admin->forceFill(['can_view_audit_trail' => true])->save();
    actingAs($admin, 'admin');

    $this->delete(route('admin.audit-trail.destroy'), ['password' => 'password'])
        ->assertStatus(403);
});

test('the mother admin must provide her correct password to clear the audit trail', function () {
    $mother = createMotherAdmin();
    actingAs($mother, 'admin');
    Category::factory()->create();

    $this->delete(route('admin.audit-trail.destroy'), ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password');

    expect(Activity::count())->toBeGreaterThan(0);
});

test('the mother admin can clear the audit trail with her correct password', function () {
    $mother = createMotherAdmin();
    actingAs($mother, 'admin');
    Category::factory()->create();

    expect(Activity::count())->toBeGreaterThan(0);

    $this->delete(route('admin.audit-trail.destroy'), ['password' => 'password'])
        ->assertRedirect(route('admin.audit-trail.index'));

    // Only the "cleared" entry itself survives — logged after the wipe.
    expect(Activity::count())->toBe(1);
    expect(Activity::first()->event)->toBe('cleared');
});
