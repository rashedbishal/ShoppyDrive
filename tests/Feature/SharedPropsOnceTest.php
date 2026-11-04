<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Category;
use App\Models\HomepageSetting;
use App\Models\PopularKeyword;
use App\Models\Setting;
use Illuminate\Testing\TestResponse;
use Inertia\Support\Header;

/**
 * Proves the exact-invalidation design behind HandleInertiaRequests::onceKey():
 * a once-prop is excluded from a repeat SPA navigation only while its
 * content-hash key is unchanged, and the moment the underlying LayoutCache
 * value actually changes, the very next navigation gets it fresh again —
 * regardless of what the client still claims to have. Simulates the real
 * client/server once-prop protocol directly (X-Inertia-Except-Once-Props),
 * not just the plain non-XHR request assertInertia() normally exercises.
 *
 * A real XHR-style Inertia navigation also needs a matching X-Inertia-Version
 * or the middleware responds 409 with an empty body (version mismatch)
 * instead of the page data — discovered fresh per call rather than
 * hardcoded, since it's derived from the built asset manifest.
 */
function inertiaVisit(string $uri, array $exceptOnceProps = []): TestResponse
{
    $version = app(HandleInertiaRequests::class)->version(request());

    $headers = [
        Header::INERTIA => 'true',
        Header::VERSION => $version,
    ];

    if ($exceptOnceProps !== []) {
        $headers[Header::EXCEPT_ONCE_PROPS] = implode(',', $exceptOnceProps);
    }

    return test()->withHeaders($headers)->get($uri);
}

function decodedProps(TestResponse $response): array
{
    return json_decode($response->getContent(), true);
}

test('a once-prop is sent in full on the very first request', function () {
    Category::factory()->create(['is_active' => true, 'name' => 'Shoes']);

    $response = inertiaVisit('/order/track');
    $data = decodedProps($response);

    expect($data['props']['navCategories'][0]['name'])->toBe('Shoes');
    expect(array_column($data['onceProps'], 'prop'))->toContain('navCategories');
});

test('a once-prop is omitted from a repeat navigation once the client has it, but its key is unchanged', function () {
    Category::factory()->create(['is_active' => true, 'name' => 'Shoes']);

    $first = decodedProps(inertiaVisit('/order/track'));
    $onceKey = array_search('navCategories', array_map(fn ($p) => $p['prop'], $first['onceProps']), true);

    expect($onceKey)->not->toBeFalse();

    $second = decodedProps(inertiaVisit('/order/track', [$onceKey]));

    expect($second['props'])->not->toHaveKey('navCategories');
    expect($second['onceProps'][$onceKey]['prop'])->toBe('navCategories');
});

test('a category change invalidates the once-prop key, so the next navigation gets it fresh even though the client still claims the old key', function () {
    $category = Category::factory()->create(['is_active' => true, 'name' => 'Shoes']);

    $before = decodedProps(inertiaVisit('/order/track'));
    $oldKey = array_search('navCategories', array_map(fn ($p) => $p['prop'], $before['onceProps']), true);

    // Client correctly has nothing stale yet — confirms the exclusion itself works.
    $stillCurrent = decodedProps(inertiaVisit('/order/track', [$oldKey]));
    expect($stillCurrent['props'])->not->toHaveKey('navCategories');

    // The admin-facing change that should invalidate navCategories.
    $category->update(['name' => 'Footwear']);

    // The client hasn't been told about the change — it still sends the OLD key.
    $after = decodedProps(inertiaVisit('/order/track', [$oldKey]));

    expect($after['props'])->toHaveKey('navCategories');
    expect($after['props']['navCategories'][0]['name'])->toBe('Footwear');

    $newKey = array_search('navCategories', array_map(fn ($p) => $p['prop'], $after['onceProps']), true);
    expect($newKey)->not->toBe($oldKey);
});

test('settings and popular_keywords once-props also invalidate exactly when their data changes', function () {
    Setting::create(['key' => 'site_name', 'value' => 'ShoppyDrive', 'group' => 'general']);
    PopularKeyword::create(['keyword' => 'shirt', 'is_active' => true]);

    $before = decodedProps(inertiaVisit('/order/track'));
    $settingsKey = array_search('settings', array_map(fn ($p) => $p['prop'], $before['onceProps']), true);
    $keywordsKey = array_search('popular_keywords', array_map(fn ($p) => $p['prop'], $before['onceProps']), true);

    $unchanged = decodedProps(inertiaVisit('/order/track', [$settingsKey, $keywordsKey]));
    expect($unchanged['props'])->not->toHaveKey('settings');
    expect($unchanged['props'])->not->toHaveKey('popular_keywords');

    // Setting::updateOrCreate (an Eloquent write, not a bulk query-builder
    // update()) is required here to actually fire the saved event
    // FlushesLayoutCache listens for — see LayoutCache's own class docblock.
    Setting::updateOrCreate(['key' => 'site_name'], ['value' => 'New Name', 'group' => 'general']);
    PopularKeyword::create(['keyword' => 'pants', 'is_active' => true]);

    $after = decodedProps(inertiaVisit('/order/track', [$settingsKey, $keywordsKey]));
    expect($after['props']['settings']['site_name'])->toBe('New Name');
    expect($after['props']['popular_keywords'])->toBe(['shirt', 'pants']);
});

test('homepage_settings once-prop on a non-Home page only ever contains announcement_bar', function () {
    HomepageSetting::updateOrCreate(['section_key' => 'announcement_bar'], ['section_name' => 'Bar', 'metadata' => ['free_delivery_amount' => 500]]);
    HomepageSetting::updateOrCreate(['section_key' => 'new_arrivals'], ['section_name' => 'New', 'sort_order' => 0]);

    $data = decodedProps(inertiaVisit('/order/track'));

    expect(array_keys($data['props']['homepage_settings']))->toBe(['announcement_bar']);
    expect($data['props']['homepage_settings']['announcement_bar']['metadata']['free_delivery_amount'])->toBe(500);
});
