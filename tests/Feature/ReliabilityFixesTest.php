<?php

declare(strict_types=1);

use App\Console\Commands\SyncPathaoOrderStatuses;
use App\Jobs\SendNewsletterChunk;
use App\Mail\ContactFormMail;
use App\Mail\OrderPlacedMail;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Support\FailedJobSummary;
use App\Support\LayoutCache;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function contactForm(): array
{
    return ['name' => 'Rahim', 'email' => 'rahim@example.com', 'subject' => 'Damaged parcel', 'message' => 'Please call me about my order.'];
}

function reliabilityOrder(array $attributes = []): Order
{
    return Order::create([
        'order_number' => 'ORD-REL-'.uniqid(), 'status' => 'shipped', 'payment_status' => 'unpaid', 'payment_method' => 'cod',
        'subtotal' => 500, 'shipping_cost' => 60, 'discount' => 0, 'tax' => 0, 'total' => 560,
        'shipping_address' => [], 'billing_address' => [],
        ...$attributes,
    ]);
}

test('a contact message is saved and emailed to the contact address, with reply going to the customer', function () {
    Mail::fake();
    Setting::create(['key' => 'contact_email', 'value' => 'shop@example.com', 'group' => 'general']);
    LayoutCache::forget(LayoutCache::SETTINGS);

    $this->post(route('contact.store'), contactForm())->assertSessionHas('success');

    expect(ContactMessage::where('subject', 'Damaged parcel')->exists())->toBeTrue();
    Mail::assertQueued(ContactFormMail::class, fn (ContactFormMail $mail): bool => $mail->hasTo('shop@example.com')
        && $mail->hasReplyTo('rahim@example.com'));
});

test('with no contact address set, the message goes to the mother admin', function () {
    Mail::fake();
    $mother = createMotherAdmin();

    $this->post(route('contact.store'), contactForm());

    Mail::assertQueued(ContactFormMail::class, fn (ContactFormMail $mail): bool => $mail->hasTo($mother->email));
});

test('the contact email renders the customer message safely', function () {
    $message = ContactMessage::create([...contactForm(), 'message' => '<script>alert(1)</script> Please call']);

    (new ContactFormMail($message))->assertSeeInHtml('Please call')->assertDontSeeInHtml('<script>alert(1)</script>', false);
});

test('a mail problem never loses or fails the customer message', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('mail down'));
    createMotherAdmin();

    $this->post(route('contact.store'), contactForm())->assertSessionHas('success');

    expect(ContactMessage::count())->toBe(1);
});

test('forgot password mail goes through the queue (BE-010)', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, QueuedResetPassword::class);
    expect(new QueuedResetPassword('token'))->toBeInstanceOf(ShouldQueue::class);
});

test('a loaded-ahead page is not counted as a visit, a real one is (BE-008)', function () {
    $this->get('/', ['Purpose' => 'prefetch'])->assertOk();
    expect(DB::table('daily_visits')->sum('page_view_count'))->toEqual(0);

    $this->get('/')->assertOk();
    expect(DB::table('daily_visits')->sum('page_view_count'))->toEqual(1);
});

/**
 * BE-008 retest (2026-11-09): a tester specifically called out hovering a
 * product card, which prefetches /product/{slug} — the only existing test
 * above covers the home page, not the actual page the fix needs to hold on.
 */
test('hovering a product card (a prefetch of its page) is not counted as a visit, a real one is (BE-008)', function () {
    $product = Product::factory()->create(['is_active' => true, 'published_at' => now()->subDay()]);

    $this->get(route('product.show', $product->slug), ['Purpose' => 'prefetch'])->assertOk();
    expect(DB::table('daily_visits')->sum('page_view_count'))->toEqual(0);

    $this->get(route('product.show', $product->slug))->assertOk();
    expect(DB::table('daily_visits')->sum('page_view_count'))->toEqual(1);
});

test('a courier sync stops at its time limit (BE-004)', function () {
    Setting::insert([
        ['key' => 'pathao_environment', 'value' => json_encode('sandbox'), 'group' => 'courier'],
        ['key' => 'pathao_token', 'value' => json_encode(['access_token' => 'tok', 'refresh_token' => 'ref', 'expires_at' => now()->addHours(2)->toIso8601String()]), 'group' => 'courier'],
    ]);
    foreach (range(1, 3) as $number) {
        reliabilityOrder(['pathao_consignment_id' => "P-{$number}"]);
    }
    $checks = 0;
    Http::fake(function () use (&$checks) {
        $checks++;
        $this->travel(SyncPathaoOrderStatuses::MAX_RUN_SECONDS + 1)->seconds();

        return Http::response(['data' => ['order_status_slug' => 'pending']]);
    });

    $this->artisan('pathao:sync-statuses')->assertSuccessful();

    expect($checks)->toBe(1);
});

function reliabilityFailedJob(string $class): object
{
    return (object) ['uuid' => 'u', 'queue' => 'q', 'connection' => 'database', 'failed_at' => now(), 'exception' => 'Exception: boom', 'payload' => json_encode(['displayName' => $class])];
}

test('a failed newsletter group can be retried, with a warning about duplicates (BE-012)', function () {
    $summary = FailedJobSummary::from(reliabilityFailedJob(SendNewsletterChunk::class));

    expect($summary['can_retry'])->toBeTrue()
        ->and($summary['retry_warning'])->toContain('twice');
});

test('other retryable jobs carry no duplicate warning (BE-012)', function () {
    $summary = FailedJobSummary::from(reliabilityFailedJob(OrderPlacedMail::class));

    expect($summary['can_retry'])->toBeTrue()->and($summary['retry_warning'])->toBeNull();
});

test('only the mother admin is offered the lost parcel button, and not twice (FE-004)', function () {
    $order = reliabilityOrder();

    actingAs(createAdmin(), 'admin');
    $this->get(route('admin.orders.show', $order))->assertInertia(fn (Assert $page) => $page->where('canConfirmLostParcel', false));

    actingAs(createMotherAdmin(), 'admin');
    $this->get(route('admin.orders.show', $order))->assertInertia(fn (Assert $page) => $page->where('canConfirmLostParcel', true));

    $order->forceFill(['lost_confirmed_at' => now()])->save();
    $this->get(route('admin.orders.show', $order))->assertInertia(fn (Assert $page) => $page->where('canConfirmLostParcel', false));
});
