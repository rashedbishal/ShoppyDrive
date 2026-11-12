<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;

/*
 * DISC-06: the shared rich-text-editor image upload needs create or update on
 * at least one section that uses the editor (blog, landing_pages, newsletter,
 * products, about_page, pages). The mother admin always passes; anyone else
 * gets 403 and nothing is stored.
 */

function disc06Upload(): TestResponse
{
    return test()->post(route('admin.content-images.store'), [
        'image' => UploadedFile::fake()->image('inline.jpg', 400, 300),
    ]);
}

test('an admin with no relevant permission gets 403 and nothing is stored', function (array $permissions) {
    Storage::fake('public');
    actingAs(createAdmin(['permissions' => $permissions]), 'admin');

    disc06Upload()->assertForbidden();

    expect(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    'no grants at all' => [[]],
    'only unrelated sections' => [['orders' => ['create', 'update'], 'coupons' => ['create', 'update', 'delete']]],
    'editor sections but only delete' => [['blog' => ['delete'], 'products' => ['delete'], 'landing_pages' => ['delete'], 'newsletter' => ['delete']]],
]);

test('an admin with create or update on any one editor section can upload', function (string $section, string $ability) {
    Storage::fake('public');
    actingAs(createAdmin(['permissions' => [$section => [$ability]]]), 'admin');

    $url = disc06Upload()->assertOk()->json('url');

    expect($url)->toStartWith('/storage/content-images/');
    Storage::disk('public')->assertExists(str_replace('/storage/', '', $url));
})->with([
    'blog create' => ['blog', 'create'],
    'blog update' => ['blog', 'update'],
    'landing pages create' => ['landing_pages', 'create'],
    'landing pages update' => ['landing_pages', 'update'],
    'newsletter create' => ['newsletter', 'create'],
    'products create' => ['products', 'create'],
    'products update' => ['products', 'update'],
    'about page update' => ['about_page', 'update'],
    'policy pages update (DISC-05)' => ['pages', 'update'],
]);

test('the mother admin can upload', function () {
    Storage::fake('public');
    actingAs(createMotherAdmin(), 'admin');

    disc06Upload()->assertOk();
});

test('a permitted admin still gets the existing validation error for a non-image', function () {
    Storage::fake('public');
    actingAs(createAdmin(['permissions' => ['blog' => ['update']]]), 'admin');

    $this->post(route('admin.content-images.store'), [
        'image' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
    ])->assertInvalid(['image']);

    expect(Storage::disk('public')->allFiles())->toBe([]);
});
