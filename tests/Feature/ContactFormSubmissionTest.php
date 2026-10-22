<?php

declare(strict_types=1);

use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('submitting the contact form stores a contact message', function () {
    $response = $this->post(route('contact.store'), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'subject' => 'Where is my order?',
        'message' => 'It has been a week and I have not received it.',
    ]);

    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors();

    expect(ContactMessage::count())->toBe(1);
    $message = ContactMessage::first();
    expect($message->name)->toBe('Jane Doe');
    expect($message->email)->toBe('jane@example.com');
    expect($message->subject)->toBe('Where is my order?');
});

test('the contact form requires all fields', function () {
    $response = $this->post(route('contact.store'), []);

    $response->assertInvalid(['name', 'email', 'subject', 'message']);
    expect(ContactMessage::count())->toBe(0);
});
