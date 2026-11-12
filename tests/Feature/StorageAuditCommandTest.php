<?php

declare(strict_types=1);

use App\Console\Commands\StorageAuditCommand;
use App\Models\Blog;
use App\Models\Brand;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

/** A file on the fake public disk, last changed $daysAgo days ago. */
function publicFile(string $path, int $daysAgo = 30): string
{
    Storage::disk('public')->put($path, 'bytes of '.$path);
    touch(Storage::disk('public')->path($path), now()->subDays($daysAgo)->getTimestamp());

    return $path;
}

function quarantineBatches(): array
{
    return Storage::disk('local')->directories(StorageAuditCommand::QUARANTINE_DIRECTORY);
}

test('the report changes nothing', function () {
    publicFile('brands/unused.webp');

    $this->artisan('storage:audit')
        ->expectsOutputToContain('Nothing was changed')
        ->assertSuccessful();

    expect(Storage::disk('public')->exists('brands/unused.webp'))->toBeTrue()
        ->and(quarantineBatches())->toBe([]);
});

test('quarantine moves only old files nothing refers to, inside the upload folders', function () {
    publicFile('brands/unused.webp');
    publicFile('brands/in-use.webp');
    publicFile('categories/in-a-post.webp');
    publicFile('logos/site-logo.webp');
    publicFile('products/new-upload.webp', daysAgo: 2);
    publicFile('newsletter-images/sent-last-month.jpg', daysAgo: 30);
    publicFile('newsletter-images/sent-long-ago.jpg', daysAgo: 120);
    publicFile('legacy/whatever.png');

    Brand::factory()->create(['logo' => '/storage/brands/in-use.webp']);
    Blog::factory()->create(['content' => '<img src="https://shop.example/storage/categories/in-a-post.webp">']);
    // A JSON value stores the slashes escaped.
    Setting::create(['key' => 'logo', 'value' => '/storage/logos/site-logo.webp', 'group' => 'general']);

    $this->artisan('storage:audit', ['--quarantine' => true])->assertSuccessful();

    $public = Storage::disk('public');
    expect($public->exists('brands/unused.webp'))->toBeFalse()
        ->and($public->exists('newsletter-images/sent-long-ago.jpg'))->toBeFalse()
        ->and($public->exists('brands/in-use.webp'))->toBeTrue()
        ->and($public->exists('categories/in-a-post.webp'))->toBeTrue()
        ->and($public->exists('logos/site-logo.webp'))->toBeTrue()
        ->and($public->exists('products/new-upload.webp'))->toBeTrue()
        ->and($public->exists('newsletter-images/sent-last-month.jpg'))->toBeTrue()
        ->and($public->exists('legacy/whatever.png'))->toBeTrue();

    $batch = quarantineBatches()[0];
    $manifest = json_decode(Storage::disk('local')->get($batch.'/manifest.json'), true);

    expect($manifest['files'])->toEqualCanonicalizing(['brands/unused.webp', 'newsletter-images/sent-long-ago.jpg'])
        ->and(Storage::disk('local')->get($batch.'/brands/unused.webp'))->toBe('bytes of brands/unused.webp');
});

test('a product photo an old order still shows is kept', function () {
    publicFile('products/ordered.webp');
    $customer = User::factory()->create();
    $product = Product::factory()->create();
    $order = Order::create([
        'order_number' => 'ORD-AUDIT-1', 'user_id' => $customer->id, 'status' => 'delivered',
        'payment_status' => 'paid', 'payment_method' => 'cod', 'subtotal' => 100, 'total' => 100,
        'shipping_address' => ['name' => 'Test', 'phone' => '01700000000'], 'billing_address' => [],
    ]);
    OrderItem::create([
        'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => 'Shoe', 'sku' => $product->sku,
        'price' => 100, 'quantity' => 1, 'subtotal' => 100,
        'product_snapshot' => ['name' => 'Shoe', 'image' => '/storage/products/ordered.webp'],
    ]);

    $this->artisan('storage:audit', ['--quarantine' => true])->assertSuccessful();

    expect(Storage::disk('public')->exists('products/ordered.webp'))->toBeTrue();
});

test('a quarantined batch can be put back, without overwriting a newer file', function () {
    publicFile('brands/a.webp');
    publicFile('brands/b.webp');
    $this->artisan('storage:audit', ['--quarantine' => true])->assertSuccessful();
    $batch = basename(quarantineBatches()[0]);

    Storage::disk('public')->put('brands/b.webp', 'uploaded since');

    $this->artisan('storage:audit', ['--restore' => $batch])
        ->expectsOutputToContain('Restored 1 files')
        ->assertSuccessful();

    expect(Storage::disk('public')->get('brands/a.webp'))->toBe('bytes of brands/a.webp')
        ->and(Storage::disk('public')->get('brands/b.webp'))->toBe('uploaded since');
});

test('restoring an unknown batch fails without touching anything', function (string $batch) {
    $this->artisan('storage:audit', ['--restore' => $batch])->assertFailed();
})->with(['2020-01-01_000000', '../../etc']);

test('purge deletes only quarantine batches older than 30 days', function () {
    $local = Storage::disk('local');
    $old = StorageAuditCommand::QUARANTINE_DIRECTORY.'/2026-01-01_000000';
    $recent = StorageAuditCommand::QUARANTINE_DIRECTORY.'/2026-11-01_000000';

    foreach ([$old => 45, $recent => 5] as $batch => $daysAgo) {
        $local->put($batch.'/manifest.json', '{"files":[]}');
        touch($local->path($batch.'/manifest.json'), now()->subDays($daysAgo)->getTimestamp());
    }

    $this->artisan('storage:audit', ['--purge' => true])->assertSuccessful();

    expect($local->exists($old))->toBeFalse()
        ->and($local->exists($recent))->toBeTrue();
});
