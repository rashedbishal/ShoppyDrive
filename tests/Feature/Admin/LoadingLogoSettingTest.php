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

test('the loading ring speed is saved as a number and shared with the storefront', function () {
    actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
        'loading_spin_seconds' => '2.5',
    ])->assertSessionHasNoErrors();

    expect(Setting::where('key', 'loading_spin_seconds')->value('value'))->toBe(2.5);

    auth('admin')->logout();

    $this->get('/order/track')->assertInertia(fn (Assert $page) => $page->where('settings.loading_spin_seconds', 2.5));
});

test('the loading ring speed defaults to 1.1 seconds when never set', function () {
    $this->get('/order/track')->assertInertia(fn (Assert $page) => $page->where('settings.loading_spin_seconds', 1.1));
});

test('a loading ring speed outside 0.3 to 10 seconds is rejected', function (string $seconds) {
    actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
        'loading_spin_seconds' => $seconds,
    ])->assertSessionHasErrors('loading_spin_seconds');

    expect(Setting::where('key', 'loading_spin_seconds')->exists())->toBeFalse();
})->with([
    'too fast' => '0.2',
    'too slow' => '10.1',
    'not a number' => 'fast',
    'two decimal places' => '1.25',
    'empty' => '',
]);

test('a stored loading ring speed outside the bounds is clamped for the storefront', function () {
    Setting::create(['key' => 'loading_spin_seconds', 'value' => 0, 'group' => 'general']);

    $this->get('/order/track')->assertInertia(fn (Assert $page) => $page->where('settings.loading_spin_seconds', 0.3));
});

test('the loading ring diameter and logo size are saved as whole pixels and shared with the storefront', function () {
    actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
        'loading_ring_size' => '420',
        'loading_logo_size' => '300',
    ])->assertSessionHasNoErrors();

    expect(Setting::where('key', 'loading_ring_size')->value('value'))->toBe(420)
        ->and(Setting::where('key', 'loading_logo_size')->value('value'))->toBe(300);

    auth('admin')->logout();

    $this->get('/order/track')->assertInertia(fn (Assert $page) => $page
        ->where('settings.loading_ring_size', 420)
        ->where('settings.loading_logo_size', 300));
});

test('the loading ring diameter and logo size default to 340px and 224px', function () {
    $this->get('/order/track')->assertInertia(fn (Assert $page) => $page
        ->where('settings.loading_ring_size', 340)
        ->where('settings.loading_logo_size', 224));
});

test('a loading size outside its bounds is rejected', function (string $key, string $value) {
    actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
        $key => $value,
    ])->assertSessionHasErrors($key);

    expect(Setting::where('key', $key)->exists())->toBeFalse();
})->with([
    'ring too small' => ['loading_ring_size', '79'],
    'ring too large' => ['loading_ring_size', '601'],
    'logo too small' => ['loading_logo_size', '31'],
    'logo too large' => ['loading_logo_size', '561'],
    'logo not a number' => ['loading_logo_size', 'big'],
    'ring not whole pixels' => ['loading_ring_size', '340.5'],
    'logo not whole pixels' => ['loading_logo_size', '224.5'],
    'ring empty' => ['loading_ring_size', ''],
    'logo empty' => ['loading_logo_size', ''],
    'thickness too thin' => ['loading_ring_thickness', '0'],
    'thickness too thick' => ['loading_ring_thickness', '21'],
    'thickness not whole pixels' => ['loading_ring_thickness', '2.5'],
    'thickness empty' => ['loading_ring_thickness', ''],
]);

test('the loading ring thickness is saved as whole pixels, defaults to 4px and is clamped for the storefront', function () {
    $this->get('/order/track')->assertInertia(fn (Assert $page) => $page->where('settings.loading_ring_thickness', 4));

    actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
        'loading_ring_thickness' => '7',
    ])->assertSessionHasNoErrors();

    expect(Setting::where('key', 'loading_ring_thickness')->value('value'))->toBe(7);

    auth('admin')->logout();
    $this->get('/order/track')->assertInertia(fn (Assert $page) => $page->where('settings.loading_ring_thickness', 7));

    Setting::where('key', 'loading_ring_thickness')->first()->update(['value' => 99]);
    $this->get('/order/track')->assertInertia(fn (Assert $page) => $page->where('settings.loading_ring_thickness', 20));
});

test('a logo larger than the ring submitted with it is rejected', function () {
    actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
        'loading_ring_size' => '200',
        'loading_logo_size' => '201',
    ])->assertSessionHasErrors(['loading_logo_size' => 'The logo size cannot be larger than the ring diameter (200 px).']);

    expect(Setting::where('key', 'loading_logo_size')->exists())->toBeFalse();
});

test('a logo as large as the ring is accepted', function () {
    actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
        'loading_ring_size' => '200',
        'loading_logo_size' => '200',
    ])->assertSessionHasNoErrors();

    expect(Setting::where('key', 'loading_logo_size')->value('value'))->toBe(200);
});

test('a logo size sent alone is checked against the saved ring diameter', function () {
    Setting::create(['key' => 'loading_ring_size', 'value' => 150, 'group' => 'general']);

    actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
        'loading_logo_size' => '151',
    ])->assertSessionHasErrors('loading_logo_size');
});

test('stored loading sizes outside the bounds are clamped for the storefront', function () {
    Setting::create(['key' => 'loading_ring_size', 'value' => 5000, 'group' => 'general']);
    Setting::create(['key' => 'loading_logo_size', 'value' => 1, 'group' => 'general']);

    $this->get('/order/track')->assertInertia(fn (Assert $page) => $page
        ->where('settings.loading_ring_size', 600)
        ->where('settings.loading_logo_size', 32));
});

test('an oversized loading logo is resized to at most 1024px on its longest side', function () {
    actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
        'loading_logo' => UploadedFile::fake()->image('huge.png', 2000, 1500),
    ])->assertSessionHasNoErrors();

    [$width, $height] = getimagesize(Storage::disk('public')->path(substr(loadingLogoValue(), strlen('/storage/'))));

    expect(max($width, $height))->toBe(1024);
});

test('a loading logo larger than 4096 pixels on a side is rejected', function () {
    actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
        'loading_logo' => UploadedFile::fake()->image('too-big.png', 4097, 10),
    ])->assertSessionHasErrors(['loading_logo' => 'The loading logo can be at most 4096 × 4096 pixels. Please resize it and upload again.']);

    expect(loadingLogoValue())->toBeNull();
});
