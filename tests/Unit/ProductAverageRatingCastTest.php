<?php

declare(strict_types=1);

use App\Models\Product;

/**
 * SQLite's AVG() returns a real float, so this bug was invisible in every
 * local/CI test run — it only reproduced against real MySQL, where AVG()
 * returns a DECIMAL and PDO fetches that as a string. Simulating the raw
 * attribute directly (rather than needing a real MySQL connection) tests
 * the actual fix: the cast, not the database driver.
 */
test('average_rating is cast to a float even when the underlying value is a numeric string', function () {
    $product = new Product;
    $product->setRawAttributes(['average_rating' => '4.5000'], true);

    expect($product->average_rating)->toBe(4.5)->toBeFloat();
});

test('average_rating stays null when there are no approved reviews', function () {
    $product = new Product;
    $product->setRawAttributes(['average_rating' => null], true);

    expect($product->average_rating)->toBeNull();
});
