<?php

use App\Models\Order;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

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

function makeOrder(User $user): Order
{
    return Order::create([
        'order_number' => 'ORD-'.$user->id.'-1',
        'user_id' => $user->id,
        'subtotal' => 100,
        'total' => 100,
        'shipping_address' => ['line1' => 'Test Address'],
        'billing_address' => ['line1' => 'Test Address'],
    ]);
}

test('admin can delete a customer with the correct mother admin password', function () {
    actingAs($this->admin, 'admin');

    $customer = User::factory()->create();

    $response = $this->delete(route('admin.customers.destroy', $customer), [
        'mother_password' => 'mother-secret',
    ]);

    $response->assertRedirect(route('admin.customers.index'));
    expect(User::withTrashed()->find($customer->id))->toBeNull();
});

test('deleting a customer also deletes their orders', function () {
    actingAs($this->admin, 'admin');

    $customer = User::factory()->create();
    $order = makeOrder($customer);

    $this->delete(route('admin.customers.destroy', $customer), [
        'mother_password' => 'mother-secret',
    ])->assertRedirect(route('admin.customers.index'));

    expect(Order::withTrashed()->find($order->id))->toBeNull();
});

test('deleting a customer also deletes their social accounts and frees the email', function () {
    actingAs($this->admin, 'admin');

    $customer = User::factory()->create(['email' => 'reusable@example.com']);
    $account = SocialAccount::create([
        'user_id' => $customer->id,
        'provider' => 'google',
        'provider_id' => 'google-123',
    ]);

    $this->delete(route('admin.customers.destroy', $customer), [
        'mother_password' => 'mother-secret',
    ])->assertRedirect(route('admin.customers.index'));

    expect(SocialAccount::find($account->id))->toBeNull();
    expect(User::factory()->create(['email' => 'reusable@example.com']))->not->toBeNull();
});

test('customer deletion is rejected with an incorrect mother admin password', function () {
    actingAs($this->admin, 'admin');

    $customer = User::factory()->create();

    $response = $this->delete(route('admin.customers.destroy', $customer), [
        'mother_password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('mother_password');
    expect(User::find($customer->id))->not->toBeNull();
});

test('customer deletion requires a mother admin password', function () {
    actingAs($this->admin, 'admin');

    $customer = User::factory()->create();

    $response = $this->delete(route('admin.customers.destroy', $customer), []);

    $response->assertSessionHasErrors('mother_password');
    expect(User::find($customer->id))->not->toBeNull();
});
