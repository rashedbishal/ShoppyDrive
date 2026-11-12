<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Models\Admin;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SubCategory;
use App\Support\ProductStockToken;
use Illuminate\Contracts\Process\InvokedProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * INV-STALE-STOCK-001: real separate processes against real InnoDB. An order
 * process and an admin process work on the same stock at once, and every unit
 * sold must still be gone at the end. Same safety gating as
 * LoyaltyPointsConcurrencyRaceTest; purging the connection leaves
 * RefreshDatabase's wrapping transaction so the other processes see the rows.
 */
function stockRaceConnection(): ?PDO
{
    $database = env('STOCK_RACE_TEST_DATABASE');

    if (! $database || preg_match('/^shoppydrive_test_[a-z0-9_]+$/', $database) !== 1) {
        test()->markTestSkipped('Set STOCK_RACE_TEST_DATABASE (matching ^shoppydrive_test_[a-z0-9_]+$) to a disposable MySQL database.');

        return null;
    }

    config(['database.connections.mysql.database' => $database]);
    DB::purge('mysql');

    $params = config('database.connections.mysql');

    if (! in_array($params['host'], ['127.0.0.1', 'localhost'], true)) {
        test()->markTestSkipped('Refusing a non-local MySQL host.');

        return null;
    }

    return new PDO("mysql:host={$params['host']};port={$params['port']};dbname={$params['database']};charset=utf8mb4", $params['username'], $params['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

const STOCK_RACE_BOOTSTRAP = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (preg_match('/^shoppydrive_test_[a-z0-9_]+$/', Illuminate\Support\Facades\DB::connection()->getDatabaseName()) !== 1) {
    fwrite(STDERR, 'Refusing to run against this database.');
    exit(3);
}

function saveProductForm(Illuminate\Foundation\Application $app, int $productId, array $payload): int
{
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle(Illuminate\Http\Request::create("/admin/products/{$productId}", 'PUT', $payload));

    return $response->getStatusCode() === 302 && str_contains((string) $response->headers->get('Location'), '/admin/products') && ! str_contains((string) $response->headers->get('Location'), "/{$productId}")
        ? 1
        : 0;
}
PHP;

/** argv: product id, variant id, how many, start time. Sells one unit at a time through InventoryService. */
const STOCK_RACE_ORDERS = STOCK_RACE_BOOTSTRAP.<<<'PHP'

usleep((int) max(0, ((float) $argv[4] - microtime(true)) * 1000000));
$inventory = $app->make(App\Services\InventoryService::class);
$variantId = $argv[2] === '0' ? null : (int) $argv[2];
$sold = 0;

for ($i = 0; $i < (int) $argv[3]; $i++) {
    Illuminate\Support\Facades\DB::transaction(function () use ($inventory, $argv, $variantId, $i, &$sold): void {
        $order = App\Models\Order::create([
            'order_number' => "ORD-RACE-{$argv[1]}-{$i}", 'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'cod',
            'subtotal' => 100, 'shipping_cost' => 0, 'discount' => 0, 'tax' => 0, 'total' => 100, 'shipping_address' => [], 'billing_address' => [],
        ]);

        $inventory->decrementForOrderItem($order, (int) $argv[1], $variantId, 1, 100.0);
        $sold++;
    });
    usleep(30000);
}

echo $sold;
PHP;

/**
 * argv: admin id, product id, how many, start time, JSON payload. Each round
 * opens the form, pauses as a person would, and saves back the stock it saw.
 */
const STOCK_RACE_ADMIN = STOCK_RACE_BOOTSTRAP.<<<'PHP'

Illuminate\Support\Facades\Auth::guard('admin')->setUser(App\Models\Admin::findOrFail((int) $argv[1]));
usleep((int) max(0, ((float) $argv[4] - microtime(true)) * 1000000));

$product = App\Models\Product::findOrFail((int) $argv[2]);
$payload = json_decode($argv[5], true);
$saved = 0;

for ($i = 0; $i < (int) $argv[3]; $i++) {
    $token = App\Support\ProductStockToken::for($product);
    $variant = App\Models\ProductVariant::where('product_id', $product->id)->first();
    $stock = (int) ($variant?->stock_quantity ?? App\Models\Product::whereKey($product->id)->value('stock_quantity'));

    usleep(40000);

    $form = $payload + ['stock_token' => $token, 'stock_quantity' => $stock];

    if ($variant !== null) {
        $form['variants'][0]['stock_quantity'] = $stock;
    }

    $saved += saveProductForm($app, $product->id, $form);
}

echo $saved;
PHP;

/** argv: admin id, product id, JSON form. */
const STOCK_RACE_SINGLE_SAVE = STOCK_RACE_BOOTSTRAP.<<<'PHP'

Illuminate\Support\Facades\Auth::guard('admin')->setUser(App\Models\Admin::findOrFail((int) $argv[1]));

echo saveProductForm($app, (int) $argv[2], json_decode($argv[3], true));
PHP;

function startStockRaceProcess(string $script, array $arguments): InvokedProcess
{
    return Process::path(base_path())
        ->env([
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => DB::connection()->getDatabaseName(),
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
        ])
        ->timeout(120)
        ->start(['php', '-r', $script, ...array_map('strval', $arguments)]);
}

/**
 * @return array{0: Admin, 1: Category, 2: SubCategory}
 */
function stockRaceFixtures(): array
{
    $admin = createAdmin(['name' => 'Race Admin', 'email' => 'race-'.uniqid().'@example.com', 'password' => bcrypt('password')]);
    $category = Category::factory()->create();

    return [$admin, $category, SubCategory::factory()->create(['category_id' => $category->id])];
}

/**
 * @return array<string, mixed>
 */
function stockRaceForm(Product $product): array
{
    return [
        'name' => $product->name,
        'slug' => $product->slug,
        'short_description' => 'A short description.',
        'description' => '<p>A full description.</p>',
        'category_id' => $product->category_id,
        'sub_category_id' => $product->sub_category_id,
        'price' => 100,
        'is_active' => true,
    ];
}

function cleanUpStockRace(Product $product, Admin $admin, Category $category, SubCategory $subCategory): void
{
    $orderIds = Order::where('order_number', 'like', "ORD-RACE-{$product->id}-%")->pluck('id');
    DB::table('order_status_histories')->whereIn('order_id', $orderIds)->delete();
    InventoryLog::where('product_id', $product->id)->delete();
    Order::whereIn('id', $orderIds)->forceDelete();
    ProductVariant::where('product_id', $product->id)->delete();
    $product->forceDelete();
    $subCategory->delete();
    $category->delete();
    $admin->delete();
}

it('never loses a sale when orders and admin saves of a flat product overlap', function () {
    if (stockRaceConnection() === null) {
        return;
    }

    [$admin, $category, $subCategory] = stockRaceFixtures();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 100, 'category_id' => $category->id, 'sub_category_id' => $subCategory->id]);

    try {
        $start = microtime(true) + 6;
        $orders = startStockRaceProcess(STOCK_RACE_ORDERS, [$product->id, 0, 30, $start]);
        $saves = startStockRaceProcess(STOCK_RACE_ADMIN, [$admin->id, $product->id, 25, $start, json_encode(stockRaceForm($product))]);

        $sold = $orders->wait();
        $saved = $saves->wait();

        expect($sold->exitCode())->toBe(0, $sold->errorOutput());
        expect($saved->exitCode())->toBe(0, $saved->errorOutput());
        expect((int) $sold->output())->toBe(30);
        expect($product->refresh()->stock_quantity)->toBe(70);

        // A current form still saves, so the refusals above were real conflicts.
        $current = startStockRaceProcess(STOCK_RACE_SINGLE_SAVE, [$admin->id, $product->id, json_encode(
            ['stock_quantity' => 70, 'stock_token' => ProductStockToken::for($product)] + stockRaceForm($product),
        )])->wait();

        expect($current->exitCode())->toBe(0, $current->errorOutput());
        expect(trim($current->output()))->toBe('1');
    } finally {
        cleanUpStockRace($product, $admin, $category, $subCategory);
    }
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'requires real MySQL');

it('never loses a sale when orders and admin saves of a variant product overlap', function () {
    if (stockRaceConnection() === null) {
        return;
    }

    [$admin, $category, $subCategory] = stockRaceFixtures();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 100, 'category_id' => $category->id, 'sub_category_id' => $subCategory->id, 'has_size_variant' => true]);
    $size = AttributeValue::create(['attribute_id' => Attribute::create(['name' => 'Size', 'type' => AttributeType::SELECT])->id, 'value' => 'M']);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'size_value_id' => $size->id, 'price' => 100, 'stock_quantity' => 100, 'is_default' => true, 'sku' => 'RACE-'.uniqid()]);

    try {
        $form = stockRaceForm($product) + ['has_size_variant' => true, 'variants' => [
            ['size_value_id' => $size->id, 'price' => 100, 'is_default' => true, 'sku' => $variant->sku, 'stock_quantity' => 0],
        ]];

        $start = microtime(true) + 6;
        $orders = startStockRaceProcess(STOCK_RACE_ORDERS, [$product->id, $variant->id, 30, $start]);
        $saves = startStockRaceProcess(STOCK_RACE_ADMIN, [$admin->id, $product->id, 25, $start, json_encode($form)]);

        $sold = $orders->wait();
        $saved = $saves->wait();

        expect($sold->exitCode())->toBe(0, $sold->errorOutput());
        expect($saved->exitCode())->toBe(0, $saved->errorOutput());
        expect((int) $sold->output())->toBe(30);
        expect($variant->refresh()->stock_quantity)->toBe(70);
    } finally {
        $product->attributeValues()->detach();
        cleanUpStockRace($product, $admin, $category, $subCategory);
        $size->attribute()->delete();
    }
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'requires real MySQL');

it('refuses a save that waited behind an uncommitted sale', function () {
    $holder = stockRaceConnection();

    if ($holder === null) {
        return;
    }

    $params = config('database.connections.mysql');
    $monitor = new PDO("mysql:host={$params['host']};port={$params['port']};dbname={$params['database']};charset=utf8mb4", $params['username'], $params['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    [$admin, $category, $subCategory] = stockRaceFixtures();
    $product = Product::factory()->create(['name' => 'Held Product', 'price' => 100, 'stock_quantity' => 10, 'category_id' => $category->id, 'sub_category_id' => $subCategory->id]);

    try {
        $form = ['name' => 'Stale Save', 'stock_quantity' => 50, 'stock_token' => ProductStockToken::for($product)] + stockRaceForm($product);

        $holder->exec('START TRANSACTION');
        $holder->prepare('UPDATE products SET stock_quantity = stock_quantity - 2 WHERE id = ?')->execute([$product->id]);

        $save = startStockRaceProcess(STOCK_RACE_SINGLE_SAVE, [$admin->id, $product->id, json_encode($form)]);

        $blocked = false;

        for ($attempt = 0; $attempt < 150 && $save->running(); $attempt++) {
            if ((int) $monitor->query("SELECT COUNT(*) FROM information_schema.INNODB_TRX WHERE trx_state = 'LOCK WAIT'")->fetchColumn() > 0) {
                $blocked = true;

                break;
            }

            usleep(100_000);
        }

        $holder->exec('COMMIT');

        expect($blocked)->toBeTrue('The save never blocked on the product lock.');

        $result = $save->wait();

        expect($result->exitCode())->toBe(0, $result->errorOutput());
        expect(trim($result->output()))->toBe('0');
        expect($product->refresh()->stock_quantity)->toBe(8);
        expect($product->name)->toBe('Held Product');
    } finally {
        cleanUpStockRace($product, $admin, $category, $subCategory);
    }
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'requires real MySQL');
