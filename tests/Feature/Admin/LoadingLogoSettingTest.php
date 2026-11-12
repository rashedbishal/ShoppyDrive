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

function loadingLogoValue(): ?string
{
    return Setting::where('key', 'loading_logo')->value('value');
}

test('uploading a loading logo stores it and shares it with the storefront', function () {
    actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
        'loading_logo' => UploadedFile::fake()->image('spinner.png', 200, 200),
    ])->assertSessionHasNoErrors();

    $stored = loadingLogoValue();
    expect($stored)->toStartWith('/storage/logos/');
    Storage::disk('public')->assertExists(substr($stored, strlen('/storage/')));

    auth('admin')->logout(); // admins are redirected away from storefront pages

    $this->get('/order/track')->assertInertia(fn (Assert $page) => $page->where('settings.loading_logo', $stored));
});

test('replacing the loading logo deletes the previous file', function () {
    actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
        'loading_logo' => UploadedFile::fake()->image('first.png'),
    ]);
    $first = substr(loadingLogoValue(), strlen('/storage/'));

    $this->put(route('admin.settings.update'), [
        'loading_logo' => UploadedFile::fake()->image('second.png'),
    ]);

    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists(substr(loadingLogoValue(), strlen('/storage/')));
});

test('resubmitting the current url leaves the loading logo untouched', function () {
    actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
        'loading_logo' => UploadedFile::fake()->image('spinner.png'),
    ]);
    $stored = loadingLogoValue();

    $this->put(route('admin.settings.update'), ['loading_logo' => 'https://evil.example/x.png'])
        ->assertSessionHasNoErrors();

    expect(loadingLogoValue())->toBe($stored);
});

test('clearing the loading logo removes it so the brand logo is used again', function () {
    actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
        'loading_logo' => UploadedFile::fake()->image('spinner.png'),
    ]);
    $file = substr(loadingLogoValue(), strlen('/storage/'));

    $this->put(route('admin.settings.update'), ['loading_logo' => ''])->assertSessionHasNoErrors();

    expect(loadingLogoValue())->toBeNull();
    Storage::disk('public')->assertMissing($file);

    auth('admin')->logout();

    $this->get('/order/track')->assertInertia(fn (Assert $page) => $page->where('settings.loading_logo', null));
});

test('a loading logo that is not an image is rejected', function () {
    actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
        'loading_logo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
    ])->assertSessionHasErrors('loading_logo');

    expect(loadingLogoValue())->toBeNull();
});

test('an admin without settings update permission cannot change the loading logo', function () {
    $limited = createAdmin(['permissions' => ['settings' => ['view']]]);

    actingAs($limited, 'admin')->put(route('admin.settings.update'), [
        'loading_logo' => UploadedFile::fake()->image('spinner.png'),
    ])->assertForbidden();

    expect(loadingLogoValue())->toBeNull();
});
