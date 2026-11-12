<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    $this->admin = createAdmin();
});

function faviconValue(): ?string
{
    return Setting::where('key', 'favicon')->value('value');
}

test('uploading a favicon stores it, shares it and puts it in the page head', function () {
    actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
        'favicon' => UploadedFile::fake()->image('icon.png', 256, 256),
    ])->assertSessionHasNoErrors();

    $stored = faviconValue();
    expect($stored)->toStartWith('/storage/logos/');
    Storage::disk('public')->assertExists(substr($stored, strlen('/storage/')));

    auth('admin')->logout();

    $this->get('/order/track')
        ->assertInertia(fn (Assert $page) => $page->where('settings.favicon', $stored))
        ->assertSee('<link rel="icon" href="'.$stored.'">', false)
        ->assertSee('<link rel="apple-touch-icon" href="'.$stored.'">', false);
});

test('without a favicon the bundled icons are linked', function () {
    $this->get('/order/track')
        ->assertSee('<link rel="icon" href="/favicon.ico" sizes="any">', false)
        ->assertSee('<link rel="apple-touch-icon" href="/apple-touch-icon.png">', false);
});

test('an ico favicon is the tab icon but never the home-screen icon', function () {
    actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
        'favicon' => UploadedFile::fake()->create('favicon.ico', 4, 'image/x-icon'),
    ])->assertSessionHasNoErrors();

    $stored = faviconValue();
    auth('admin')->logout();

    $this->get('/order/track')
        ->assertSee('<link rel="icon" href="'.$stored.'">', false)
        ->assertSee('<link rel="apple-touch-icon" href="/apple-touch-icon.png">', false);
});

test('replacing and clearing the favicon deletes the old file', function () {
    actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
        'favicon' => UploadedFile::fake()->image('first.png'),
    ]);
    $first = substr(faviconValue(), strlen('/storage/'));

    $this->put(route('admin.settings.update'), ['favicon' => UploadedFile::fake()->image('second.png')]);
    Storage::disk('public')->assertMissing($first);
    $second = substr(faviconValue(), strlen('/storage/'));

    $this->put(route('admin.settings.update'), ['favicon' => ''])->assertSessionHasNoErrors();

    expect(faviconValue())->toBeNull();
    Storage::disk('public')->assertMissing($second);
});

test('resubmitting the current url leaves the favicon untouched', function () {
    actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
        'favicon' => UploadedFile::fake()->image('icon.png'),
    ]);
    $stored = faviconValue();

    $this->put(route('admin.settings.update'), ['favicon' => 'https://evil.example/x.png'])->assertSessionHasNoErrors();

    expect(faviconValue())->toBe($stored);
});

test('a favicon must be a small image', function () {
    actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
        'favicon' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
    ])->assertSessionHasErrors('favicon');

    $this->put(route('admin.settings.update'), [
        'favicon' => UploadedFile::fake()->image('big.png')->size(600),
    ])->assertSessionHasErrors('favicon');

    expect(faviconValue())->toBeNull();
});
