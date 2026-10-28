<?php

declare(strict_types=1);

use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = createAdmin([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);
});

test('admin can view the contact messages list', function () {
    actingAs($this->admin, 'admin');

    ContactMessage::create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'subject' => 'Where is my order?',
        'message' => 'It has been a week.',
    ]);

    $response = $this->get(route('admin.contact-messages.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/contact-messages/index')
        ->where('messages.data.0.name', 'Jane Doe'));
});

test('admin can search contact messages', function () {
    actingAs($this->admin, 'admin');

    ContactMessage::create(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'subject' => 'Order issue', 'message' => 'Help please.']);
    ContactMessage::create(['name' => 'John Smith', 'email' => 'john@example.com', 'subject' => 'Refund request', 'message' => 'Please refund.']);

    $response = $this->get(route('admin.contact-messages.index', ['search' => 'Jane']));

    $response->assertInertia(fn ($page) => $page
        ->component('admin/contact-messages/index')
        ->has('messages.data', 1)
        ->where('messages.data.0.name', 'Jane Doe'));
});

test('admin can delete a single contact message', function () {
    actingAs($this->admin, 'admin');

    $message = ContactMessage::create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'subject' => 'Where is my order?',
        'message' => 'It has been a week.',
    ]);

    $response = $this->delete(route('admin.contact-messages.destroy', $message));

    $response->assertRedirect();
    expect(ContactMessage::find($message->id))->toBeNull();
});

test('admin can erase all contact messages at once', function () {
    actingAs($this->admin, 'admin');

    ContactMessage::create(['name' => 'A', 'email' => 'a@example.com', 'subject' => 'One', 'message' => 'Msg one']);
    ContactMessage::create(['name' => 'B', 'email' => 'b@example.com', 'subject' => 'Two', 'message' => 'Msg two']);

    $response = $this->delete(route('admin.contact-messages.clear'));

    $response->assertRedirect();
    expect(ContactMessage::count())->toBe(0);
});
