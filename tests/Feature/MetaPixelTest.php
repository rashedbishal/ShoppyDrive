<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
