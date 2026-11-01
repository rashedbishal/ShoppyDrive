<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Contracts\Process\InvokedProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * MED-08: real concurrent processes against real InnoDB. Each contender loops
 * over the same lines at shared clock slots, so the pair genuinely collide
 * many times, with no lock held artificially to force an ordering. SQLite
 * cannot show any of this. Same safety gating as
 * LoyaltyPointsConcurrencyRaceTest; purging the connection leaves
 * RefreshDatabase's wrapping transaction so the other processes see the rows.
 */
function cartRaceMonitor(): ?PDO
{
    $database = env('CART_RACE_TEST_DATABASE');

    if (! $database || preg_match('/^shoppydrive_test_[a-z0-9_]+$/', $database) !== 1) {
        test()->markTestSkipped('Set CART_RACE_TEST_DATABASE (matching ^shoppydrive_test_[a-z0-9_]+$) to a disposable MySQL database.');

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

const CART_RACE_BOOTSTRAP = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (preg_match('/^shoppydrive_test_[a-z0-9_]+$/', Illuminate\Support\Facades\DB::connection()->getDatabaseName()) !== 1) {
    fwrite(STDERR, 'Refusing to run against this database.');
    exit(3);
}
PHP;

/**
 * argv: user id, mode (add|sync), JSON lines, start time, seconds per slot.
 * `sync` writes every line so far, as the browser's full-cart sync does.
 */
const CART_RACE_CONTENDER = CART_RACE_BOOTSTRAP.<<<'PHP'

$user = App\Models\User::findOrFail((int) $argv[1]);
$lines = json_decode($argv[3], true);
$manager = $app->make(App\Support\CartManager::class);

foreach ($lines as $index => $line) {
    usleep((int) max(0, ((float) $argv[4] + $index * (float) $argv[5] - microtime(true)) * 1000000));

    $argv[2] === 'add'
        ? $manager->syncUserCart($user, [$line], merge: true)
        : $manager->syncUserCart($user, array_slice($lines, 0, $index + 1));
}
PHP;

const CART_RACE_CHECKOUT = CART_RACE_BOOTSTRAP.<<<'PHP'

$user = App\Models\User::findOrFail((int) $argv[1]);
Illuminate\Support\Facades\Auth::guard('web')->setUser($user);

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(Illuminate\Http\Request::create('/checkout', 'POST', json_decode($argv[2], true)));

echo $response->getStatusCode();
PHP;

function startCartRaceProcess(string $script, array $arguments): InvokedProcess
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
 * @param  array<int, array<string, mixed>>  $lines
 * @param  array{0: string, 1: string}  $modes
 */
function raceCartWrites(User $user, array $lines, array $modes): void
{
    $start = microtime(true) + 6;
    $encoded = json_encode($lines);

    $processes = array_map(fn (string $mode) => startCartRaceProcess(CART_RACE_CONTENDER, [$user->id, $mode, $encoded, $start, 0.15]), $modes);

    foreach ($processes as $process) {
        $result = $process->wait();

        expect($result->exitCode())->toBe(0, $result->errorOutput());
    }
}

function cartRaceUser(): User
{
    return User::factory()->create();
}

function cleanUpCartRace(User $user, array $products): void
{
    CartItem::whereIn('cart_id', Cart::where('user_id', $user->id)->pluck('id'))->delete();
    Cart::where('user_id', $user->id)->delete();
    $user->delete();

    foreach ($products as $product) {
        ProductVariant::where('product_id', $product->id)->delete();
        $product->forceDelete();
    }
}

it('keeps one row per line when two processes add the same simple products', function () {
    if (cartRaceMonitor() === null) {
        return;
    }

    $user = cartRaceUser();
    $products = Product::factory()->count(25)->create(['stock_quantity' => 50]);
    $lines = $products->map(fn (Product $product) => ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1])->all();

    try {
        raceCartWrites($user, $lines, ['add', 'add']);

        $rows = CartItem::whereIn('cart_id', Cart::where('user_id', $user->id)->pluck('id'))->get();

        expect(Cart::where('user_id', $user->id)->count())->toBe(1);
        expect($rows)->toHaveCount(25);
        expect($rows->every(fn (CartItem $row) => $row->quantity === 2))->toBeTrue();
    } finally {
        cleanUpCartRace($user, $products->all());
    }
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'requires real MySQL');

it('keeps one row per line when two processes add the same variants', function () {
    if (cartRaceMonitor() === null) {
        return;
    }

    $user = cartRaceUser();
    $product = Product::factory()->create();
    $variants = ProductVariant::factory()->count(25)->create(['product_id' => $product->id, 'stock_quantity' => 50]);
    $lines = $variants->map(fn (ProductVariant $variant) => ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1])->all();

    try {
        raceCartWrites($user, $lines, ['add', 'add']);

        $rows = CartItem::whereIn('cart_id', Cart::where('user_id', $user->id)->pluck('id'))->get();

        expect($rows)->toHaveCount(25);
        expect($rows->every(fn (CartItem $row) => $row->quantity === 2))->toBeTrue();
    } finally {
        cleanUpCartRace($user, [$product]);
    }
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'requires real MySQL');

it('keeps one row per line when a full cart sync races an add', function () {
    if (cartRaceMonitor() === null) {
        return;
    }

    $user = cartRaceUser();
    $products = Product::factory()->count(25)->create(['stock_quantity' => 50]);
    $lines = $products->map(fn (Product $product) => ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1])->all();

    try {
        raceCartWrites($user, $lines, ['sync', 'add']);

        $rows = CartItem::whereIn('cart_id', Cart::where('user_id', $user->id)->pluck('id'))->get();

        expect(Cart::where('user_id', $user->id)->count())->toBe(1);
        expect($rows->groupBy('product_id')->map->count()->max())->toBe(1);
    } finally {
        cleanUpCartRace($user, $products->all());
    }
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'requires real MySQL');

it('refuses a real checkout when the cart changes while it waits for the customer lock', function () {
    $monitor = cartRaceMonitor();

    if ($monitor === null) {
        return;
    }

    $params = config('database.connections.mysql');
    $holder = new PDO("mysql:host={$params['host']};port={$params['port']};dbname={$params['database']};charset=utf8mb4", $params['username'], $params['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $user = cartRaceUser();
    $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10]);
    $cart = Cart::create(['user_id' => $user->id]);
    $item = $cart->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 100]);

    try {
        $holder->exec('START TRANSACTION');
        $holder->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE')->execute([$user->id]);
        $holder->prepare('UPDATE cart_items SET quantity = 3 WHERE id = ?')->execute([$item->id]);

        $checkout = startCartRaceProcess(CART_RACE_CHECKOUT, [$user->id, json_encode([
            'name' => 'Race Buyer',
            'email' => $user->email,
            'phone' => '01700000000',
            'address_line1' => 'House 12, Road 7',
            'city' => 'Inside Dhaka City Corporation',
            'state' => 'Dhaka',
            'postal_code' => '1212',
            'country' => 'Bangladesh',
            'save_address' => false,
            'payment_method' => 'cod',
            'order_token' => (string) Str::uuid(),
        ])]);

        $blocked = false;

        for ($attempt = 0; $attempt < 150 && $checkout->running(); $attempt++) {
            if ((int) $monitor->query("SELECT COUNT(*) FROM information_schema.INNODB_TRX WHERE trx_state = 'LOCK WAIT'")->fetchColumn() > 0) {
                $blocked = true;

                break;
            }

            usleep(100_000);
        }

        $holder->exec('COMMIT');

        expect($blocked)->toBeTrue('The checkout never blocked on the customer lock.');

        $result = $checkout->wait();

        expect($result->exitCode())->toBe(0, $result->errorOutput());
        expect(trim($result->output()))->toBe('302');
        expect(Order::where('user_id', $user->id)->count())->toBe(0);
        expect($product->refresh()->stock_quantity)->toBe(10);
    } finally {
        cleanUpCartRace($user, [$product]);
    }
})->skip(fn () => DB::connection()->getDriverName() !== 'mysql', 'requires real MySQL');
