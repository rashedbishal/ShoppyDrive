<?php

declare(strict_types=1);

use App\Mail\VerifyEmailCodeMail;
use App\Models\Setting;
use App\Support\BrandColor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Owner decisions 2026-11-14: Admin › Settings › Brand Colour, mother admin
 * only; one colour drives the CMS, the storefront's brand spots, the Classic
 * theme and emails, with every shade derived from it.
 */

test('with nothing saved the original red is used and no override is rendered', function () {
    expect(BrandColor::hex())->toBe('#c8102e')
        ->and(BrandColor::css())->toBeNull()
        ->and(BrandColor::palette())->toMatchArray([
            'brand' => '#c8102e',
            'hover' => '#a50d25',
            'light' => '#fff0f2',
            'foreground' => '#ffffff',
            'dark' => '#e8112a',
            'bright' => '#ff3a55',
        ]);

    $this->get(route('login'))->assertOk()
        ->assertSee('<style id="brand-color"></style>', false)
        ->assertInertia(fn (Assert $page) => $page->where('brandCss', null));
});

test('every shade is derived from one colour, with readable text on light and dark colours', function () {
    $blue = BrandColor::palette('#1d4ed8');

    expect($blue['brand'])->toBe('#1d4ed8')
        ->and($blue['hover'])->toBe('#1840b2')
        ->and($blue['rgb'])->toBe('29 78 216')
        ->and($blue['foreground'])->toBe('#ffffff')
        ->and(BrandColor::palette('#facc15')['foreground'])->toBe('#1a1a2e');
});

test('the mother admin can save a colour, and the whole site gets it on the next page', function () {
    actingAs(createMotherAdmin(), 'admin');

    $this->get(route('admin.settings.brand-color.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/brand-color')
            ->where('brandColor', '#c8102e')
            ->where('defaultColor', '#c8102e'));

    $this->put(route('admin.settings.brand-color.update'), ['brand_color' => '#1D4ED8'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect(Setting::where('key', BrandColor::KEY)->value('value'))->toBe('#1d4ed8')
        ->and(BrandColor::hex())->toBe('#1d4ed8');

    // A storefront visitor's next page.
    auth('admin')->logout();
    $this->flushSession();

    $this->get(route('login'))->assertOk()
        ->assertSee('--brand:#1d4ed8;', false)
        ->assertSee('--brand-foreground:#ffffff;', false)
        ->assertInertia(fn (Assert $page) => $page->where('brandCss', BrandColor::css()));
});

test('emails use the saved colour', function () {
    expect((new VerifyEmailCodeMail('123456'))->render())
        ->toContain('color: #c8102e;')
        ->toContain('background: #fef2f2;');

    BrandColor::save('#047857');

    expect((new VerifyEmailCodeMail('123456'))->render())
        ->toContain('color: #047857;')
        ->toContain('background: '.BrandColor::palette('#047857')['light'].';')
        ->not->toContain('#c8102e');
});

test('resetting goes back to the original red', function () {
    BrandColor::save('#7c3aed');
    actingAs(createMotherAdmin(), 'admin');

    $this->delete(route('admin.settings.brand-color.destroy'))->assertSessionHasNoErrors();

    expect(Setting::where('key', BrandColor::KEY)->exists())->toBeFalse()
        ->and(BrandColor::hex())->toBe('#c8102e')
        ->and(BrandColor::css())->toBeNull();
});

test('an invalid colour is refused and nothing is saved', function (mixed $value) {
    actingAs(createMotherAdmin(), 'admin');

    $this->put(route('admin.settings.brand-color.update'), ['brand_color' => $value])
        ->assertSessionHasErrors('brand_color');

    expect(Setting::where('key', BrandColor::KEY)->exists())->toBeFalse();
})->with([
    'missing hash' => ['1d4ed8'],
    'short form' => ['#fff'],
    'not hex' => ['#zzzzzz'],
    'css injection' => ['#123456;}body{display:none'],
    'empty' => [''],
]);

test('only the mother admin can see or change the brand colour, whatever their permissions', function () {
    actingAs(createAdmin(), 'admin');

    $this->get(route('admin.settings.brand-color.edit'))->assertForbidden();
    $this->put(route('admin.settings.brand-color.update'), ['brand_color' => '#1d4ed8'])->assertForbidden();
    $this->delete(route('admin.settings.brand-color.destroy'))->assertForbidden();

    expect(Setting::where('key', BrandColor::KEY)->exists())->toBeFalse();
});

test('a guest is sent to the admin login', function () {
    $this->get(route('admin.settings.brand-color.edit'))->assertRedirect();
    $this->put(route('admin.settings.brand-color.update'), ['brand_color' => '#1d4ed8'])->assertRedirect();

    expect(Setting::where('key', BrandColor::KEY)->exists())->toBeFalse();
});

test('a tampered saved value falls back to the original red', function () {
    Setting::updateOrCreate(['key' => BrandColor::KEY], ['value' => 'red;}body{display:none', 'group' => 'general']);

    expect(BrandColor::hex())->toBe('#c8102e')
        ->and(BrandColor::css())->toBeNull();
});

test('DISC-45: the live preview overrides the Tailwind brand tokens, not only --brand', function () {
    // app.css's @theme fixes --color-brand to var(--brand) on :root, so the
    // preview box must override --color-brand* itself to show the picked colour.
    $page = (string) file_get_contents(resource_path('js/pages/admin/settings/brand-color.tsx'));

    expect($page)->toContain('[`--color-${name}`, value]')
        ->toContain('[`--${name}`, value]')
        ->toContain('style={previewStyle(preview)}');
});
