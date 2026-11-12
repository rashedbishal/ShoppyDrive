<?php

declare(strict_types=1);

use App\Models\LandingPage;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Text and number limits of the admin forms. Each limit is sized for where the
 * text is shown (a card title, a table cell, a meta tag), and the form mirrors
 * it with maxLength — these tests pin the server side, which is the real
 * boundary. A value exactly at the limit must still be accepted.
 */
beforeEach(function () {
    $this->admin = createMotherAdmin();
});

dataset('field limits', [
    'product name' => ['admin.products.store', 'name', 150],
    'product short description' => ['admin.products.store', 'short_description', 300],
    'product description' => ['admin.products.store', 'description', 20000],
    'product meta title' => ['admin.products.store', 'meta_title', 70],
    'product meta description' => ['admin.products.store', 'meta_description', 160],
    'product sku' => ['admin.products.store', 'sku', 50],
    'category name' => ['admin.categories.store', 'name', 80],
    'category description' => ['admin.categories.store', 'description', 2000],
    'category meta description' => ['admin.categories.store', 'meta_description', 160],
    'sub-category name' => ['admin.sub-categories.store', 'name', 80],
    'brand name' => ['admin.brands.store', 'name', 80],
    'brand description' => ['admin.brands.store', 'description', 2000],
    'coupon code' => ['admin.coupons.store', 'code', 30],
    'coupon name' => ['admin.coupons.store', 'name', 100],
    'blog title' => ['admin.blogs.store', 'title', 150],
    'blog excerpt' => ['admin.blogs.store', 'excerpt', 400],
    'blog content' => ['admin.blogs.store', 'content', 50000],
    'popular keyword' => ['admin.popular-keywords.store', 'keyword', 60],
    'blocked ip reason' => ['admin.blocked-ips.store', 'reason', 200],
]);

it('refuses text one character over its limit and accepts it at the limit', function (string $route, string $field, int $limit) {
    actingAs($this->admin, 'admin')
        ->post(route($route), [$field => str_repeat('a', $limit + 1)])
        ->assertSessionHasErrors($field);

    actingAs($this->admin, 'admin')
        ->post(route($route), [$field => str_repeat('a', $limit)])
        ->assertSessionDoesntHaveErrors($field);
})->with('field limits');

it('limits the numbers an admin can type', function (string $route, array $payload, string $field) {
    actingAs($this->admin, 'admin')
        ->post(route($route), $payload)
        ->assertSessionHasErrors($field);
})->with([
    'product price' => ['admin.products.store', ['price' => 100000000], 'price'],
    'product stock' => ['admin.products.store', ['stock_quantity' => 10000000], 'stock_quantity'],
    'fixed coupon value' => ['admin.coupons.store', ['type' => 'fixed', 'value' => 100000000], 'value'],
    'coupon uses' => ['admin.coupons.store', ['max_uses' => 10000000], 'max_uses'],
]);

it('limits the landing page builder texts', function () {
    $product = Product::factory()->create();
    $page = LandingPage::create(['title' => 'Promo', 'slug' => 'promo', 'product_id' => $product->id, 'is_active' => true, 'sections' => []]);
    $update = fn (array $sections) => actingAs($this->admin, 'admin')->put(route('admin.landing-pages.update', $page), [
        'title' => 'Promo', 'slug' => 'promo', 'product_id' => $product->id, 'is_active' => true, 'sections' => $sections,
    ]);

    $update(['hero' => ['headline' => str_repeat('a', 121)]])->assertSessionHasErrors('sections.hero.headline');
    $update(['hero' => ['headline' => str_repeat('a', 120)]])->assertSessionDoesntHaveErrors('sections.hero.headline');
    $update(['faq' => ['items' => [['question' => 'Q', 'answer' => str_repeat('a', 801)]]]])->assertSessionHasErrors('sections.faq.items.0.answer');
    $update(['testimonials' => ['items' => [['name' => 'A', 'rating' => 9, 'text' => 'ok']]]])->assertSessionHasErrors('sections.testimonials.items.0.rating');
    $update(['hero' => ['bg_color' => 'red']])->assertSessionHasErrors('sections.hero.bg_color');
});

it('limits the About page hero description to 200 characters', function () {
    actingAs($this->admin, 'admin')
        ->put(route('admin.settings.update'), ['about_hero_description' => str_repeat('a', 201)])
        ->assertSessionHasErrors('about_hero_description');

    actingAs($this->admin, 'admin')
        ->put(route('admin.settings.update'), ['about_hero_description' => str_repeat('a', 200)])
        ->assertSessionDoesntHaveErrors('about_hero_description');
});

it('limits the Contact page hero description to 400 characters', function () {
    actingAs($this->admin, 'admin')
        ->put(route('admin.settings.update'), ['contact_page_hero_description' => str_repeat('a', 401)])
        ->assertSessionHasErrors('contact_page_hero_description');

    actingAs($this->admin, 'admin')
        ->put(route('admin.settings.update'), ['contact_page_hero_description' => str_repeat('a', 400)])
        ->assertSessionDoesntHaveErrors('contact_page_hero_description');
});

it('limits the About page mission text to 400 visible characters, not counting markup', function () {
    $save = fn (string $body) => actingAs($this->admin, 'admin')
        ->put(route('admin.settings.update'), ['about_mission_body' => $body]);

    $save('<p>'.str_repeat('a', 401).'</p>')->assertSessionHasErrors('about_mission_body');
    $save('<p><strong>'.str_repeat('a', 400).'</strong></p><p></p>')->assertSessionDoesntHaveErrors('about_mission_body');
});
