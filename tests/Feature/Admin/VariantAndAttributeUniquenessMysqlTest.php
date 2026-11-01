<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * MED-06 deferred: this integration-preparation branch intentionally
 * excludes database/migrations/2026_10_31_223219_add_unique_indexes_to_variant_skus_and_attribute_values.php
 * until its production preflight is complete (see the remediation tracker).
 * Every test below directly exercises the real unique indexes, so on this
 * branch they must also skip when run against real MySQL, rather than
 * failing unexpectedly for lack of the index. The checkpoint branch
 * (backup/shoppydrive-remediation-checkpoint-2026-11-01) keeps this file
 * complete and unmodified, with the migration present.
 */
if (! function_exists('med06MigrationExcludedFromIntegrationBranch')) {
    function med06MigrationExcludedFromIntegrationBranch(): bool
    {
        return glob(database_path('migrations/*_add_unique_indexes_to_variant_skus_and_attribute_values.php')) === [];
    }
}

if (! defined('MED06_DEFERRED_SKIP_REASON')) {
    define('MED06_DEFERRED_SKIP_REASON', 'MED-06 deferred: unique indexes/migration intentionally excluded from this integration branch.');
}

/**
 * MED-06: SQLite compares bytes, so only MySQL shows what the index really
 * rejects under utf8mb4_unicode_ci.
 */
test('the sku index follows the column collation', function (string $first, string $second) {
    ProductVariant::factory()->create(['sku' => $first]);

    expect(fn () => ProductVariant::factory()->create(['sku' => $second]))->toThrow(UniqueConstraintViolationException::class);
})->with([
    'case' => ['ABC-1', 'abc-1'],
    'accent' => ['CAFE-1', 'CAFÉ-1'],
    'trailing space' => ['PAD-1', 'PAD-1 '],
])->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'requires real MySQL')
    ->skip(fn () => med06MigrationExcludedFromIntegrationBranch(), MED06_DEFERRED_SKIP_REASON);

test('the attribute value index matches what validation already rejects', function (string $first, string $second, bool $collides) {
    $size = Attribute::create(['name' => 'Size', 'type' => AttributeType::SELECT]);
    AttributeValue::create(['attribute_id' => $size->id, 'value' => $first]);

    $attempt = fn () => AttributeValue::create(['attribute_id' => $size->id, 'value' => $second]);

    $collides ? expect($attempt)->toThrow(UniqueConstraintViolationException::class) : expect($attempt()->exists)->toBeTrue();
})->with([
    'case' => ['XL', 'xl', true],
    'Bangla and Latin digits' => ['৪২', '42', true],
    'Bangla chandrabindu' => ['চা', 'চাঁ', true],
    'different Bangla letters' => ['স', 'শ', false],
    'different Bangla vowels' => ['কি', 'কী', false],
])->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'requires real MySQL')
    ->skip(fn () => med06MigrationExcludedFromIntegrationBranch(), MED06_DEFERRED_SKIP_REASON);

test('the database refuses exact duplicates and allows many NULL SKUs', function () {
    $size = Attribute::create(['name' => 'Size', 'type' => AttributeType::SELECT]);
    AttributeValue::create(['attribute_id' => $size->id, 'value' => 'M']);
    ProductVariant::factory()->create(['sku' => 'EXACT-1']);
    ProductVariant::factory()->count(3)->create(['sku' => null]);

    expect(fn () => ProductVariant::factory()->create(['sku' => 'EXACT-1']))->toThrow(UniqueConstraintViolationException::class);
    expect(fn () => AttributeValue::create(['attribute_id' => $size->id, 'value' => 'M']))->toThrow(UniqueConstraintViolationException::class);
    expect(ProductVariant::whereNull('sku')->count())->toBe(3);
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'requires real MySQL')
    ->skip(fn () => med06MigrationExcludedFromIntegrationBranch(), MED06_DEFERRED_SKIP_REASON);

/** argv: product id, attribute id, rounds, start time. Tries to claim every round's SKU and value. */
const UNIQUE_RACE_CONTENDER = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== 'shoppydrive_test_loyalty') {
    fwrite(STDERR, 'Refusing to run against this database.');
    exit(3);
}

$won = 0;

for ($i = 0; $i < (int) $argv[3]; $i++) {
    usleep((int) max(0, ((float) $argv[4] + $i * 0.05 - microtime(true)) * 1000000));

    foreach ([
        fn () => App\Models\ProductVariant::create(['product_id' => (int) $argv[1], 'sku' => "RACE-SKU-{$i}", 'price' => 1, 'stock_quantity' => 1, 'attributes' => [], 'color_value_id' => null, 'size_value_id' => null]),
        fn () => App\Models\AttributeValue::create(['attribute_id' => (int) $argv[2], 'value' => "Race {$i}"]),
    ] as $claim) {
        try {
            $claim();
            $won++;
        } catch (Illuminate\Database\UniqueConstraintViolationException) {
            // The other process got there first.
        }
    }
}

echo $won;
PHP;

test('two processes racing for the same SKUs and values each get exactly one row', function () {
    if (env('UNIQUE_RACE_TEST_DATABASE') !== 'shoppydrive_test_loyalty') {
        test()->markTestSkipped('Set UNIQUE_RACE_TEST_DATABASE to the disposable test database.');
    }

    // Leaves RefreshDatabase's wrapping transaction so both processes see these rows.
    DB::purge('mysql');

    expect(DB::connection()->getDatabaseName())->toBe('shoppydrive_test_loyalty');

    $product = Product::factory()->create();
    $size = Attribute::create(['name' => 'Size', 'type' => AttributeType::SELECT]);
    $rounds = 40;
    $start = microtime(true) + 6;

    try {
        $contenders = collect([1, 2])->map(fn () => Process::path(base_path())
            ->env(['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'shoppydrive_test_loyalty', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'])
            ->timeout(60)
            ->start(['php', '-r', UNIQUE_RACE_CONTENDER, (string) $product->id, (string) $size->id, (string) $rounds, (string) $start]));

        $results = $contenders->map(fn ($process) => $process->wait());

        $results->each(fn ($result) => expect($result->exitCode())->toBe(0, $result->errorOutput()));

        $wins = $results->map(fn ($result) => (int) $result->output());

        expect($wins->sum())->toBe($rounds * 2);
        expect($wins->min())->toBeGreaterThan(0);
        expect(ProductVariant::where('product_id', $product->id)->count())->toBe($rounds);
        expect(AttributeValue::where('attribute_id', $size->id)->count())->toBe($rounds);
    } finally {
        ProductVariant::where('product_id', $product->id)->delete();
        AttributeValue::where('attribute_id', $size->id)->delete();
        $size->delete();
        $product->forceDelete();
    }
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'requires real MySQL')
    ->skip(fn () => med06MigrationExcludedFromIntegrationBranch(), MED06_DEFERRED_SKIP_REASON);
