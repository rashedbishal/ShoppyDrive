<?php

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Admin User',
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]);
});

test('admin can upload an image for the rich-text editor and gets back a usable url', function () {
    Storage::fake('public');

    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.content-images.store'), [
        'image' => UploadedFile::fake()->image('inline.jpg', 800, 600),
    ]);

    $response->assertOk();
    $url = $response->json('url');

    expect($url)->toStartWith('/storage/content-images/');
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $url));
});

test('content image upload requires a valid image file', function () {
    actingAs($this->admin, 'admin');

    $response = $this->post(route('admin.content-images.store'), [
        'image' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
    ]);

    $response->assertInvalid(['image']);
});

test('guests cannot upload content images', function () {
    $response = $this->post(route('admin.content-images.store'), [
        'image' => UploadedFile::fake()->image('inline.jpg'),
    ]);

    $response->assertRedirect(route('admin.login'));
});
