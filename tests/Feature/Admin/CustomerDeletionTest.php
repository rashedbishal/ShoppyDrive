<?php

use App\Models\Address;
use App\Models\Order;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Support\SessionKey;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Inertia::flash() stores everything under one combined session key
 * (SessionKey::FLASH_DATA), not literally under the flashed key's own name.
 */
function flashedToast(): ?array
{
    return session(SessionKey::FLASH_DATA, [])['toast'] ?? null;
}

beforeEach(function () {
    $this->mother = createMotherAdmin([
        'name' => 'Mother Admin',
        'email' => 'mother@example.com',
        'password' => bcrypt('mother-secret'),
    ]);

    $this->admin = createAdmin([
        'name' => 'Staff Admin',
        'email' => 'staff@example.com',
        'password' => bcrypt('password'),
    ]);
});

function makeOrderForAnonymizationTest(User $user): Order
{
    return Order::create([
        'order_number' => 'ORD-'.$user->id.'-1',
        'user_id' => $user->id,
        'subtotal' => 100,
        'total' => 100,
        'shipping_address' => ['line1' => 'Test Address', 'name' => 'Real Name'],
        'billing_address' => ['line1' => 'Test Address'],
    ]);
}

test('admin can anonymize a customer with the correct mother admin password', function () {
    actingAs($this->admin, 'admin');

    $customer = User::factory()->create(['name' => 'Real Name', 'phone' => '01700000000']);

    $response = $this->delete(route('admin.customers.destroy', $customer), [
        'mother_password' => 'mother-secret',
    ]);

    $response->assertRedirect(route('admin.customers.index'));

    $fresh = $customer->fresh();
    expect($fresh)->not->toBeNull();
    expect($fresh->anonymized_at)->not->toBeNull();
    expect($fresh->name)->toBe("Deleted Customer #{$customer->id}");
    expect($fresh->phone)->toBeNull();
});

test('anonymizing a customer leaves their email unchanged', function () {
    actingAs($this->admin, 'admin');

    $customer = User::factory()->create(['email' => 'keepme@example.com']);

    $this->delete(route('admin.customers.destroy', $customer), ['mother_password' => 'mother-secret']);

    expect($customer->fresh()->email)->toBe('keepme@example.com');
});

test('anonymizing a customer preserves their orders', function () {
    actingAs($this->admin, 'admin');

    $customer = User::factory()->create();
    $order = makeOrderForAnonymizationTest($customer);

    $this->delete(route('admin.customers.destroy', $customer), ['mother_password' => 'mother-secret']);

    $freshOrder = Order::find($order->id);
    expect($freshOrder)->not->toBeNull();
    expect($freshOrder->user_id)->toBe($customer->id);
    expect($freshOrder->shipping_address['name'])->toBe('Real Name');
});

test('anonymizing a customer redacts their address book without touching order-time snapshots', function () {
    actingAs($this->admin, 'admin');

    $customer = User::factory()->create();
    $order = makeOrderForAnonymizationTest($customer);
    $address = Address::create([
        'user_id' => $customer->id,
        'name' => 'Real Name',
        'phone' => '01700000000',
        'address_line1' => '123 Real Street',
        'address_line2' => 'Apt 4',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
        'postal_code' => '1207',
    ]);

    $this->delete(route('admin.customers.destroy', $customer), ['mother_password' => 'mother-secret']);

    $freshAddress = $address->fresh();
    expect($freshAddress)->not->toBeNull();
    expect($freshAddress->name)->toBe('[redacted]');
    expect($freshAddress->phone)->toBe('[redacted]');
    expect($freshAddress->address_line1)->toBe('[redacted]');
    expect($freshAddress->address_line2)->toBeNull();
    expect($freshAddress->city)->toBe('[redacted]');
    expect($freshAddress->state)->toBe('[redacted]');
    expect($freshAddress->postal_code)->toBeNull();

    // The order's own shipping snapshot is independent of the address book and must be untouched.
    expect(Order::find($order->id)->shipping_address['name'])->toBe('Real Name');
});

test('anonymizing a customer removes their linked social accounts', function () {
    actingAs($this->admin, 'admin');

    $customer = User::factory()->create();
    $account = SocialAccount::create([
        'user_id' => $customer->id,
        'provider' => 'google',
        'provider_id' => 'google-123',
    ]);

    $this->delete(route('admin.customers.destroy', $customer), ['mother_password' => 'mother-secret']);

    expect(SocialAccount::find($account->id))->toBeNull();
});

test('anonymizing a customer rotates their remember token', function () {
    actingAs($this->admin, 'admin');

    $customer = User::factory()->create();
    $originalToken = $customer->remember_token;

    $this->delete(route('admin.customers.destroy', $customer), ['mother_password' => 'mother-secret']);

    expect($customer->fresh()->remember_token)->not->toBe($originalToken);
});

test('anonymizing a customer deletes their database session rows', function () {
    actingAs($this->admin, 'admin');

    $customer = User::factory()->create();

    DB::table('sessions')->insert([
        'id' => 'a-fake-session-id',
        'user_id' => $customer->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => base64_encode('irrelevant'),
        'last_activity' => now()->timestamp,
    ]);

    $this->delete(route('admin.customers.destroy', $customer), ['mother_password' => 'mother-secret']);

    expect(DB::table('sessions')->where('user_id', $customer->id)->count())->toBe(0);
});

test('anonymization is rejected with an incorrect mother admin password, and nothing changes', function () {
    actingAs($this->admin, 'admin');

    $customer = User::factory()->create(['name' => 'Real Name']);

    $response = $this->delete(route('admin.customers.destroy', $customer), [
        'mother_password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('mother_password');
    expect($customer->fresh()->anonymized_at)->toBeNull();
    expect($customer->fresh()->name)->toBe('Real Name');
});

test('anonymization requires a mother admin password', function () {
    actingAs($this->admin, 'admin');

    $customer = User::factory()->create();

    $response = $this->delete(route('admin.customers.destroy', $customer), []);

    $response->assertSessionHasErrors('mother_password');
    expect($customer->fresh()->anonymized_at)->toBeNull();
});

test('a repeated anonymization request on an already-anonymized customer is rejected without double-processing', function () {
    actingAs($this->admin, 'admin');

    $customer = User::factory()->create();

    $this->delete(route('admin.customers.destroy', $customer), ['mother_password' => 'mother-secret'])
        ->assertRedirect(route('admin.customers.index'));

    $firstAnonymizedAt = $customer->fresh()->anonymized_at;
    expect(Activity::where('event', 'customer.anonymized')->count())->toBe(1);

    $response = $this->delete(route('admin.customers.destroy', $customer), ['mother_password' => 'mother-secret']);

    $response->assertSessionHas('error', 'This customer has already been anonymized.');
    expect($customer->fresh()->anonymized_at->equalTo($firstAnonymizedAt))->toBeTrue();
    expect(Activity::where('event', 'customer.anonymized')->count())->toBe(1);
});

test('a failure partway through anonymization rolls back every change, including the audit log entry', function () {
    $customer = User::factory()->create(['name' => 'Real Name', 'phone' => '01700000000']);
    $address = Address::create([
        'user_id' => $customer->id,
        'name' => 'Real Name',
        'phone' => '01700000000',
        'address_line1' => '123 Real Street',
        'city' => 'Dhaka',
        'state' => 'Dhaka',
    ]);

    // Simulate a mid-transaction failure on the real single-model save step
    // (the User::forceFill(...)->save() call), which happens *after* the
    // address redaction in the same transaction — proving that a late
    // failure rolls back the earlier address changes too, not just itself.
    User::updating(function (User $updating) use ($customer) {
        if ($updating->id === $customer->id) {
            throw new RuntimeException('Simulated mid-anonymization failure.');
        }
    });

    expect(fn () => $customer->anonymize($this->mother))->toThrow(RuntimeException::class);

    expect($customer->fresh()->anonymized_at)->toBeNull();
    expect($customer->fresh()->name)->toBe('Real Name');
    expect($address->fresh()->name)->toBe('Real Name');
    expect(Activity::where('event', 'customer.anonymized')->count())->toBe(0);
});

test('anonymizing deletes a real avatar file from storage and shows a plain success toast', function () {
    actingAs($this->admin, 'admin');
    Storage::fake('public');

    $customer = User::factory()->create();
    Storage::disk('public')->put('avatars/real.webp', 'fake-image-bytes');
    $customer->forceFill(['avatar' => '/storage/avatars/real.webp'])->save();

    $response = $this->delete(route('admin.customers.destroy', $customer), ['mother_password' => 'mother-secret']);

    $response->assertRedirect(route('admin.customers.index'));
    Storage::disk('public')->assertMissing('avatars/real.webp');
    expect(session('success'))->not->toBeNull();
});

test('anonymizing with an already-missing avatar file is treated as success, no warning', function () {
    actingAs($this->admin, 'admin');
    Storage::fake('public');

    $customer = User::factory()->create();
    $customer->forceFill(['avatar' => '/storage/avatars/already-gone.webp'])->save();

    $response = $this->delete(route('admin.customers.destroy', $customer), ['mother_password' => 'mother-secret']);

    $response->assertRedirect(route('admin.customers.index'));
    expect(session('success'))->not->toBeNull();
    expect($customer->fresh()->anonymized_at)->not->toBeNull();
});

test('anonymizing skips an avatar path outside the avatars directory or containing traversal segments', function () {
    actingAs($this->admin, 'admin');
    Storage::fake('public');

    $customer = User::factory()->create();
    $customer->forceFill(['avatar' => '/storage/avatars/../../.env'])->save();

    $response = $this->delete(route('admin.customers.destroy', $customer), ['mother_password' => 'mother-secret']);

    $response->assertRedirect(route('admin.customers.index'));
    expect(session('success'))->not->toBeNull();
    expect($customer->fresh()->anonymized_at)->not->toBeNull();
});

test('avatar delete() returning false produces a visible warning without undoing anonymization', function () {
    actingAs($this->admin, 'admin');

    $customer = User::factory()->create();
    $customer->forceFill(['avatar' => '/storage/avatars/real.webp'])->save();

    $diskMock = Mockery::mock();
    $diskMock->shouldReceive('exists')->once()->with('avatars/real.webp')->andReturn(true);
    $diskMock->shouldReceive('delete')->once()->with('avatars/real.webp')->andReturn(false);
    Storage::shouldReceive('disk')->with('public')->andReturn($diskMock);

    $response = $this->delete(route('admin.customers.destroy', $customer), ['mother_password' => 'mother-secret']);

    $response->assertRedirect(route('admin.customers.index'));
    expect(flashedToast()['type'])->toBe('warning');
    expect(flashedToast()['message'])->toContain('could not be removed from storage');
    // Anonymization itself must still be reported complete, not undone.
    expect($customer->fresh()->anonymized_at)->not->toBeNull();
});
