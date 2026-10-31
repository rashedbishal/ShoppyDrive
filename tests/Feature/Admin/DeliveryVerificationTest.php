<?php

declare(strict_types=1);

use App\Enums\CourierSubmissionState;
use App\Enums\OrderStatus;
use App\Models\CourierSubmission;
use App\Models\Order;
use App\Models\OrderDeliveryVerification;
use App\Models\OrderStatusHistory;
use App\Services\CourierEvidenceChain;
use App\Support\MotherPasswordGate;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

/**
 * PAY-RETURN-001: the mother admin's audited delivery verification — route
 * protection and every refusal the service enforces.
 */
function verificationOrder(array $overrides = []): Order
{
    $order = Order::create(array_merge([
        'order_number' => 'ORD-DV-'.strtoupper(bin2hex(random_bytes(4))),
        'status' => OrderStatus::DELIVERED,
        'payment_status' => 'paid',
        'payment_method' => 'cod',
        'subtotal' => 500, 'shipping_cost' => 60, 'discount' => 0, 'tax' => 0, 'total' => 559.55,
        'shipping_address' => ['name' => 'T', 'phone' => '01700000000'],
        'billing_address' => [],
        'delivered_at' => now()->subDay(),
    ], $overrides));
    linkTestConsignment($order, 'steadfast', 'SF-DV-'.$order->id);

    return $order->refresh();
}

/** @param  array<string, mixed>  $overrides */
function verificationPayload(Order $order, array $overrides = []): array
{
    return array_merge([
        'password' => 'password',
        'target_type' => 'consignment',
        'provider' => 'steadfast',
        'consignment_id' => $order->steadfast_consignment_id,
        'outcome' => 'full_delivery',
        'verified_collected_amount' => 560, // round(559.55)
        'timeline_reviewed' => '1',
        'note' => 'Checked the Steadfast portal: delivered in full on the 30th.',
    ], $overrides);
}

test('the verification and readiness routes carry the mother, throttle, session-blocking and web (CSRF) middleware', function (string $name, bool $blocks) {
    $route = Route::getRoutes()->getByName($name);
    $middleware = $route->gatherMiddleware();

    expect($middleware)->toContain('admin.mother')->toContain('web')->toContain('admin');
    if ($blocks) {
        expect($middleware)->toContain('throttle:30,15')->and($route->locksFor())->toBe(10);
    }
})->with([
    ['admin.orders.deliveryVerifications.store', true],
    ['admin.courierReadiness.store', true],
    ['admin.courierReadiness.index', false],
]);

test('an ordinary admin with every permission is refused, and a guest is redirected', function () {
    $order = verificationOrder();

    actingAs(createAdmin(), 'admin')->post(route('admin.orders.deliveryVerifications.store', $order), verificationPayload($order))->assertForbidden();
    actingAs(createAdmin(), 'admin')->get(route('admin.courierReadiness.index'))->assertForbidden();
    actingAs(createAdmin(), 'admin')->post(route('admin.courierReadiness.store'), [])->assertForbidden();
    auth('admin')->logout();
    $this->post(route('admin.orders.deliveryVerifications.store', $order), verificationPayload($order))->assertRedirect();

    expect(OrderDeliveryVerification::count())->toBe(0);
});

test('verification is refused without a current readiness attestation', function () {
    $order = verificationOrder();
    fakeCourierReadiness(); // fake release but no accepted attestation

    actingAs(createMotherAdmin(), 'admin')->post(route('admin.orders.deliveryVerifications.store', $order), verificationPayload($order))
        ->assertSessionHasErrors('verification');
    expect(OrderDeliveryVerification::count())->toBe(0);
});

test('the mother admin records a verification with its snapshot under a current readiness', function () {
    $readiness = makeCourierReadinessCurrent();
    $order = verificationOrder();
    $mother = createMotherAdmin();

    actingAs($mother, 'admin')->post(route('admin.orders.deliveryVerifications.store', $order), verificationPayload($order))
        ->assertSessionHasNoErrors()->assertSessionHas('success');

    $verification = OrderDeliveryVerification::sole();
    expect($verification->readiness_id)->toBe($readiness->id)
        ->and($verification->admin_id)->toBe($mother->id)
        ->and((float) $verification->verified_collected_amount)->toBe(560.0)
        ->and($verification->submission_state_snapshot)->toEqual(app(CourierEvidenceChain::class)->submissionSnapshot($order)) // key order may differ (MySQL JSON)
        ->and(app(CourierEvidenceChain::class)->eligibility($order->refresh())['eligible'])->toBeTrue();
});

test('verification refusals', function (Closure $arrange, array $payload, string $field) {
    makeCourierReadinessCurrent();
    $order = verificationOrder();
    $arrange($order);

    actingAs(createMotherAdmin(), 'admin')->post(route('admin.orders.deliveryVerifications.store', $order), verificationPayload($order, $payload))
        ->assertSessionHasErrors($field);
    expect(OrderDeliveryVerification::count())->toBe(0);
})->with([
    'wrong password' => [fn () => null, ['password' => 'nope'], 'password'],
    'consignment typed differently' => [fn () => null, ['consignment_id' => 'SF-OTHER'], 'consignment_id'],
    'COD amount not round(total)' => [fn () => null, ['verified_collected_amount' => 559.55], 'verified_collected_amount'],
    'timeline not reviewed' => [fn () => null, ['timeline_reviewed' => null], 'timeline_reviewed'],
    'partial evidence on the order' => [fn (Order $o) => OrderStatusHistory::create(['order_id' => $o->id, 'status' => 'delivered', 'notes' => 'Steadfast reported "Partial Delivered".']), [], 'verification'],
    'unresolved submission' => [fn (Order $o) => CourierSubmission::factory()->forOrder($o)->create(['state' => CourierSubmissionState::UNKNOWN, 'active_order_id' => null]), [], 'verification'],
    'full delivery on an undelivered order' => [fn (Order $o) => $o->forceFill(['status' => OrderStatus::SHIPPED, 'delivered_at' => null])->save(), [], 'outcome'],
    'not_created for a consignment' => [fn () => null, ['outcome' => 'not_created'], 'outcome'],
]);

test('only a failed submission with no courier-returned ID can be verified as not created', function (array $submission, bool $accepted) {
    makeCourierReadinessCurrent();
    $order = verificationOrder();
    $attempt = CourierSubmission::factory()->forOrder($order)->create(array_merge(['courier' => 'steadfast', 'active_order_id' => null], $submission));

    $response = actingAs(createMotherAdmin(), 'admin')->post(route('admin.orders.deliveryVerifications.store', $order), verificationPayload($order, [
        'target_type' => 'submission', 'courier_submission_id' => $attempt->id, 'outcome' => 'not_created',
    ]));

    $accepted ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('courier_submission_id');
})->with([
    'failed, nothing returned' => [['state' => CourierSubmissionState::FAILED], true],
    'failed with a returned ID' => [['state' => CourierSubmissionState::FAILED, 'returned_consignment_id' => 'LATE'], false],
    'sent' => [['state' => CourierSubmissionState::SENT, 'consignment_id' => 'X'], false],
]);

test('five wrong passwords lock verification, even with the right password', function () {
    makeCourierReadinessCurrent();
    $order = verificationOrder();
    $mother = createMotherAdmin();

    foreach (range(1, 5) as $_) {
        actingAs($mother, 'admin')->post(route('admin.orders.deliveryVerifications.store', $order), verificationPayload($order, ['password' => 'wrong']));
    }

    actingAs($mother, 'admin')->post(route('admin.orders.deliveryVerifications.store', $order), verificationPayload($order))
        ->assertSessionHasErrors(['password' => MotherPasswordGate::lockedMessage($mother)]);
    expect(OrderDeliveryVerification::count())->toBe(0);
});

test('the verification route is capped at 30 requests per 15 minutes', function () {
    makeCourierReadinessCurrent();
    $order = verificationOrder();
    $mother = createMotherAdmin();

    foreach (range(1, 30) as $_) {
        actingAs($mother, 'admin')->post(route('admin.orders.deliveryVerifications.store', $order), ['note' => 'x']);
    }

    actingAs($mother, 'admin')->post(route('admin.orders.deliveryVerifications.store', $order), verificationPayload($order))->assertStatus(429);
});
