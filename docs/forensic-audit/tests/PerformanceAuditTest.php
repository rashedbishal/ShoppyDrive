<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('P01 the product listing must not run one query per variant', function () {
    Product::factory()->count(12)->create(['is_active' => true, 'published_at' => now()->subDay()])
        ->each(fn (Product $product) => ProductVariant::factory()->count(6)->create(['product_id' => $product->id]));

    DB::enableQueryLog();
    $this->get('/products')->assertOk();
    $count = count(DB::getQueryLog());
    fwrite(STDERR, "listing queries: {$count}\n");

    expect($count)->toBeLessThan(40);
});

test('P02 an unapproved review must not change the rating shown on the home page', function () {
    $product = Product::factory()->create(['is_active' => true, 'is_featured' => true, 'published_at' => now()->subDay()]);
    Review::create(['product_id' => $product->id, 'user_id' => User::factory()->create()->id, 'rating' => 1, 'body' => 'x', 'is_approved' => false]);

    $response = $this->get('/')->assertOk();
    $body = json_encode($response->viewData('page')['props']);

    expect($body)->toContain($product->slug)->not->toContain('"reviews_count":1');
});
