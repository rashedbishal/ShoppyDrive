<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['inertia.ssr.enabled' => false]);
});

const META_PIXEL_SNIPPET = <<<'HTML'
<!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '1395344526007424');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id=1395344526007424&ev=PageView&noscript=1"
/></noscript>
<!-- End Meta Pixel Code -->
HTML;

test('the admin-pasted Meta Pixel code renders verbatim, unescaped, on the storefront', function () {
    Setting::create(['key' => 'meta_pixel_code', 'value' => META_PIXEL_SNIPPET, 'group' => 'general']);

    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain(META_PIXEL_SNIPPET);
});

test('nothing renders when no Meta Pixel code is configured', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)->not->toContain('connect.facebook.net');
});

test('the Meta Pixel never renders in the admin back office', function () {
    Setting::create(['key' => 'meta_pixel_code', 'value' => META_PIXEL_SNIPPET, 'group' => 'general']);

    $html = $this->get(route('admin.login'))->assertOk()->getContent();

    expect($html)->not->toContain('connect.facebook.net');
});

test('an ordinary admin, even with full settings permission, cannot change the Meta Pixel code', function () {
    actingAs(createAdmin(), 'admin');

    $this->put(route('admin.settings.meta-pixel.update'), ['meta_pixel_code' => META_PIXEL_SNIPPET])
        ->assertSessionHas('error');

    expect(Setting::where('key', 'meta_pixel_code')->first())->toBeNull();
});

test('the mother admin can change the Meta Pixel code', function () {
    $mother = createAdmin();
    $mother->forceFill(['is_mother' => true])->save();
    actingAs($mother, 'admin');

    $this->put(route('admin.settings.meta-pixel.update'), ['meta_pixel_code' => META_PIXEL_SNIPPET])
        ->assertSessionHas('success');

    expect(Setting::where('key', 'meta_pixel_code')->first()->value)->toBe(META_PIXEL_SNIPPET);
});

test('an ordinary admin with settings access can still save the rest of General Settings', function () {
    actingAs(createAdmin(), 'admin');

    $this->put(route('admin.settings.update'), ['site_name' => 'New Name'])
        ->assertSessionHasNoErrors();

    expect(Setting::where('key', 'site_name')->first()->value)->toBe('New Name');
    expect(Setting::where('key', 'meta_pixel_code')->first())->toBeNull();
});

test('the mother admin cannot save obviously-wrong text as the Meta Pixel code', function (string $bogus) {
    $mother = createAdmin();
    $mother->forceFill(['is_mother' => true])->save();
    actingAs($mother, 'admin');

    $this->put(route('admin.settings.meta-pixel.update'), ['meta_pixel_code' => $bogus])
        ->assertSessionHasErrors('meta_pixel_code');

    expect(Setting::where('key', 'meta_pixel_code')->first())->toBeNull();
})->with([
    'plain text' => ['just some random text, not a pixel at all'],
    'fbq call but no facebook domain' => ["fbq('init', '123456789012345');"],
    'facebook domain but no fbq call' => ['<img src="https://www.facebook.com/tr?id=123">'],
]);

test('the mother admin can clear the Meta Pixel code', function () {
    Setting::create(['key' => 'meta_pixel_code', 'value' => META_PIXEL_SNIPPET, 'group' => 'general']);
    $mother = createAdmin();
    $mother->forceFill(['is_mother' => true])->save();
    actingAs($mother, 'admin');

    $this->put(route('admin.settings.meta-pixel.update'), ['meta_pixel_code' => ''])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    // RedirectAdminToDashboard would otherwise bounce a logged-in admin
    // straight off the storefront home page.
    $this->app['auth']->forgetGuards();

    $html = $this->get(route('home'))->assertOk()->getContent();
    expect($html)->not->toContain('connect.facebook.net');
});
