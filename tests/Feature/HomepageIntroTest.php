<?php

declare(strict_types=1);

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * DISC-90 (SEO audit SEO-44 / F-12): the homepage shows a visible H1 and
 * intro line, editable on Admin › Homepage. Production has no SSR, so SSR
 * is forced off.
 */
beforeEach(function () {
    config(['inertia.ssr.enabled' => false]);
});

test('by default the homepage heading and intro describe the shop without invented claims', function () {
    Setting::updateOrCreate(['key' => 'site_name'], ['value' => 'ShoppyDrive', 'group' => 'general']);

    $this->get(route('home'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('intro.heading', 'Online Shopping in Bangladesh')
        ->where('intro.text', 'Shop new arrivals, best sellers, flash sales and bundle deals at ShoppyDrive, with prices in taka and delivery across Bangladesh.'));
});

test('the homepage shows the heading and intro the admin saved', function () {
    Setting::updateOrCreate(['key' => 'home_h1'], ['value' => 'Gadgets & gifts in Bangladesh', 'group' => 'homepage']);
    Setting::updateOrCreate(['key' => 'home_intro'], ['value' => 'Watches, toys and home goods.', 'group' => 'homepage']);

    $this->get(route('home'))->assertInertia(fn ($page) => $page
        ->where('intro.heading', 'Gadgets & gifts in Bangladesh')
        ->where('intro.text', 'Watches, toys and home goods.'));
});

test('a saved empty intro is hidden, but the heading never goes empty', function () {
    Setting::updateOrCreate(['key' => 'home_h1'], ['value' => '   ', 'group' => 'homepage']);
    Setting::updateOrCreate(['key' => 'home_intro'], ['value' => null, 'group' => 'homepage']);

    $this->get(route('home'))->assertInertia(fn ($page) => $page
        ->where('intro.heading', 'Online Shopping in Bangladesh')
        ->where('intro.text', null));
});

test('the heading and intro leave the homepage title, description and single h1 unchanged', function () {
    Setting::updateOrCreate(['key' => 'home_h1'], ['value' => 'Gadgets & gifts', 'group' => 'homepage']);
    Setting::updateOrCreate(['key' => 'home_intro'], ['value' => 'Intro text.', 'group' => 'homepage']);

    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('<title>Online Shopping in Bangladesh - '.config('app.name').'</title>')
        ->and(substr_count($html, '<meta name="description"'))->toBe(1)
        ->and($html)->toContain('name="description" content="Shop wrist watches')
        ->and(substr_count($html, '<h1'))->toBe(1)
        ->and(substr_count($html, 'application/ld+json'))->toBe(1);
});

test('admins can save and clear the homepage heading and intro', function () {
    actingAs(createAdmin(), 'admin');

    $this->put(route('admin.settings.update'), ['home_h1' => 'Shop online', 'home_intro' => 'Our intro.'])
        ->assertSessionHasNoErrors();

    expect(Setting::where('key', 'home_h1')->value('value'))->toBe('Shop online')
        ->and(Setting::where('key', 'home_intro')->value('value'))->toBe('Our intro.');

    $this->put(route('admin.settings.update'), ['home_h1' => 'Shop online', 'home_intro' => ''])->assertSessionHasNoErrors();

    // The saved empty row is what hides the intro (see the test above).
    expect(Setting::where('key', 'home_intro')->exists())->toBeTrue()
        ->and(Setting::where('key', 'home_intro')->value('value'))->toBeNull();
});

test('the homepage heading and intro have length limits', function () {
    actingAs(createAdmin(), 'admin');

    $this->put(route('admin.settings.update'), ['home_h1' => str_repeat('a', 81), 'home_intro' => str_repeat('b', 301)])
        ->assertSessionHasErrors(['home_h1', 'home_intro']);

    expect(Setting::whereIn('key', ['home_h1', 'home_intro'])->count())->toBe(0);
});

test('only an admin with homepage update permission can change them', function () {
    $aboutEditor = Admin::create([
        'name' => 'Staff', 'email' => 'about-editor@example.com', 'password' => bcrypt('password'),
        'permissions' => ['about_page' => ['view', 'update']],
    ]);
    actingAs($aboutEditor, 'admin');
    $this->put(route('admin.settings.update'), ['home_h1' => 'Hacked'])->assertForbidden();

    $homepageEditor = Admin::create([
        'name' => 'Staff', 'email' => 'homepage-editor@example.com', 'password' => bcrypt('password'),
        'permissions' => ['homepage' => ['view', 'update']],
    ]);
    actingAs($homepageEditor, 'admin');
    $this->put(route('admin.settings.update'), ['home_h1' => 'Shop online'])->assertSessionHasNoErrors();

    expect(Setting::where('key', 'home_h1')->value('value'))->toBe('Shop online');
});

test('Admin › Homepage pre-fills the form with what the homepage shows now', function () {
    actingAs(createAdmin(), 'admin');
    Setting::updateOrCreate(['key' => 'home_h1'], ['value' => 'Saved heading', 'group' => 'homepage']);

    $this->get(route('admin.homepage.index'))->assertInertia(fn ($page) => $page
        ->where('intro.heading', 'Saved heading')
        ->where('intro.text', fn ($text) => str_starts_with((string) $text, 'Shop new arrivals')));
});
