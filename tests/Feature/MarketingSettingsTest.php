<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['inertia.ssr.enabled' => false]);
});

const GTM_HEADER_SNIPPET = <<<'HTML'
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-XXXXXXX');</script>
<!-- End Google Tag Manager -->
HTML;

const GTM_FOOTER_SNIPPET = <<<'HTML'
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-XXXXXXX"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
HTML;

test('custom header and footer code render on the storefront, never on admin', function () {
    Setting::create(['key' => 'custom_header_code', 'value' => GTM_HEADER_SNIPPET, 'group' => 'marketing']);
    Setting::create(['key' => 'custom_footer_code', 'value' => GTM_FOOTER_SNIPPET, 'group' => 'marketing']);

    $html = $this->get(route('home'))->assertOk()->getContent();
    expect($html)->toContain(GTM_HEADER_SNIPPET)->toContain(GTM_FOOTER_SNIPPET);

    $adminHtml = $this->get(route('admin.login'))->assertOk()->getContent();
    expect($adminHtml)->not->toContain('googletagmanager.com');
});

test('nothing renders when no custom code is configured', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->not->toContain('googletagmanager.com');
});

test('a truncated script tag is rejected', function () {
    $mother = createAdmin();
    $mother->forceFill(['is_mother' => true])->save();
    actingAs($mother, 'admin');

    $this->put(route('admin.settings.marketing.custom-code.update'), [
        'custom_header_code' => '<script>console.log("oops")', // never closed
    ])->assertSessionHasErrors('custom_header_code');

    expect(Setting::where('key', 'custom_header_code')->first())->toBeNull();
});

test('well-formed multi-vendor code with several script tags is accepted', function () {
    $mother = createAdmin();
    $mother->forceFill(['is_mother' => true])->save();
    actingAs($mother, 'admin');

    $code = GTM_HEADER_SNIPPET."\n".'<script>console.log("second vendor")</script>';

    $this->put(route('admin.settings.marketing.custom-code.update'), [
        'custom_header_code' => $code,
    ])->assertSessionHasNoErrors();

    expect(Setting::where('key', 'custom_header_code')->first()->value)->toBe($code);
});

test('an ordinary admin, even with full marketing_tracking permission, cannot change custom code', function () {
    actingAs(createAdmin(), 'admin');

    $this->put(route('admin.settings.marketing.custom-code.update'), [
        'custom_header_code' => GTM_HEADER_SNIPPET,
    ])->assertSessionHas('error');

    expect(Setting::where('key', 'custom_header_code')->first())->toBeNull();
});

test('the mother admin can clear custom code back to empty', function () {
    Setting::create(['key' => 'custom_header_code', 'value' => GTM_HEADER_SNIPPET, 'group' => 'marketing']);
    $mother = createAdmin();
    $mother->forceFill(['is_mother' => true])->save();
    actingAs($mother, 'admin');

    $this->put(route('admin.settings.marketing.custom-code.update'), [
        'custom_header_code' => '',
        'custom_footer_code' => '',
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    $this->app['auth']->forgetGuards();
    $html = $this->get(route('home'))->assertOk()->getContent();
    expect($html)->not->toContain('googletagmanager.com');
});

test('an admin granted marketing_tracking:update can save the Facebook Conversions API fields', function () {
    actingAs(createAdmin(), 'admin');

    $this->put(route('admin.settings.marketing.facebook-conversions-api.update'), [
        'google_analytics_id' => 'G-TEST123',
        'facebook_pixel_id' => '123456789012345',
        'facebook_conversions_api_token' => 'EAAG...secret',
        'facebook_conversions_api_test_event_code' => 'TEST123',
        'facebook_conversions_api_enabled' => true,
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    expect(Setting::where('key', 'facebook_pixel_id')->first()->value)->toBe('123456789012345');
    expect(Setting::where('key', 'facebook_conversions_api_token')->first()->value)->toBe('EAAG...secret');
    expect((bool) Setting::where('key', 'facebook_conversions_api_enabled')->first()->value)->toBeTrue();
});

test('an admin without marketing_tracking permission cannot save the Facebook Conversions API fields', function () {
    actingAs(createAdmin(['permissions' => []]), 'admin');

    $this->put(route('admin.settings.marketing.facebook-conversions-api.update'), [
        'facebook_pixel_id' => '123456789012345',
        'facebook_conversions_api_enabled' => true,
    ])->assertForbidden();

    expect(Setting::where('key', 'facebook_pixel_id')->first())->toBeNull();
});

test('a blank access token submission leaves the previously saved token unchanged', function () {
    Setting::create(['key' => 'facebook_conversions_api_token', 'value' => 'original-token', 'group' => 'marketing']);
    actingAs(createAdmin(), 'admin');

    $this->put(route('admin.settings.marketing.facebook-conversions-api.update'), [
        'facebook_conversions_api_enabled' => true,
    ])->assertSessionHasNoErrors();

    expect(Setting::where('key', 'facebook_conversions_api_token')->first()->value)->toBe('original-token');
});

test('the edit page masks the access token instead of exposing it', function () {
    $token = 'abcd1234567890secret';
    Setting::create(['key' => 'facebook_conversions_api_token', 'value' => $token, 'group' => 'marketing']);
    actingAs(createAdmin(), 'admin');

    $expectedMask = str_repeat('*', strlen($token) - 6).substr($token, -6);

    $response = $this->get(route('admin.settings.marketing.edit'))->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->where('settings.has_facebook_conversions_api_token', true)
        ->where('settings.facebook_conversions_api_token_masked', $expectedMask)
    );
    expect($response->getContent())->not->toContain($token);
});
