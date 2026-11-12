<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

/*
 * Text limits of the customer-facing forms. Each is sized for where the text
 * lands (an address label, a shipping line, a message in the admin inbox) and
 * mirrored by maxLength on the input; these pin the server side. A value exactly
 * at the limit must still pass.
 */
it('limits the contact form fields', function (string $field, int $max) {
    $payload = ['name' => 'Rafi', 'email' => 'rafi@example.com', 'subject' => 'Hello', 'message' => 'A question about my order'];

    post(route('contact.store'), [...$payload, $field => str_repeat('a', $max + 1)])->assertSessionHasErrors($field);
    post(route('contact.store'), [...$payload, $field => str_repeat('a', $max)])->assertSessionDoesntHaveErrors($field);
})->with([
    'name' => ['name', 100],
    'subject' => ['subject', 150],
    'message' => ['message', 2000],
]);

it('limits the saved address fields', function (string $field, int $max) {
    $payload = [
        'label' => 'Home', 'name' => 'Rafi', 'phone' => '01712345678', 'address_line1' => 'House 1, Road 2',
        'city' => 'Dhaka', 'state' => 'Dhaka', 'postal_code' => '1212', 'country' => 'Bangladesh',
    ];
    $user = User::factory()->create();

    actingAs($user)->post(route('account.addresses.store'), [...$payload, $field => str_repeat('a', $max + 1)])
        ->assertSessionHasErrors($field);
    actingAs($user)->post(route('account.addresses.store'), [...$payload, $field => str_repeat('a', $max)])
        ->assertSessionDoesntHaveErrors($field);
})->with([
    'label' => ['label', 30],
    'name' => ['name', 100],
    'address line' => ['address_line1', 200],
    'city' => ['city', 60],
    'postal code' => ['postal_code', 10],
    'country' => ['country', 60],
]);

it('limits the newsletter email to 150 characters', function () {
    $tooLong = str_repeat('a', 142).'@test.com';

    post(route('newsletter.subscribe'), ['email' => $tooLong])->assertSessionHasErrors('email');
});

it('limits the registration name, email and password', function () {
    $payload = ['name' => 'Rafi', 'email' => 'rafi@example.com', 'password' => 'Str0ng!Passw0rd#1', 'password_confirmation' => 'Str0ng!Passw0rd#1'];

    post('/register', [...$payload, 'name' => str_repeat('a', 101)])->assertSessionHasErrors('name');
    post('/register', [...$payload, 'email' => str_repeat('a', 142).'@test.com'])->assertSessionHasErrors('email');

    $long = str_repeat('aB3!', 33);
    post('/register', [...$payload, 'password' => $long, 'password_confirmation' => $long])->assertSessionHasErrors('password');
});
