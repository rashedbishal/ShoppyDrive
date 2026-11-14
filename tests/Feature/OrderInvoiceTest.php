<?php

declare(strict_types=1);

/*
 * DISC-38 (owner decisions 2026-11-14): one invoice for every order except a
 * cancelled/refunded one, COD included from placement; "Paid Amount" once
 * paid, otherwise "Payment Amount"; emailed with the placed and delivered
 * emails; admin customer/parcel copies (parcel only after a courier booking);
 * guest downloads from the confirmation page and from Track Order.
 */

use App\Mail\OrderDeliveredMail;
use App\Mail\OrderPlacedMail;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Support\InvoiceLanguageToFont;
use App\Support\OrderInvoice;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Mail::fake();
});

/**
 * @param  array<string, mixed>  $attributes
 */
function disc38Order(array $attributes = []): Order
{
    $order = Order::create(array_merge([
        'order_number' => 'ORD-DISC38-'.uniqid(),
        'user_id' => null,
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'payment_method' => 'cod',
        'subtotal' => 500,
        'shipping_cost' => 60,
        'discount' => 0,
        'tax' => 0,
        'total' => 560,
        'shipping_address' => ['name' => 'Disc Buyer', 'phone' => '01712345678', 'email' => 'buyer@example.com', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka'],
        'billing_address' => ['name' => 'Disc Buyer', 'phone' => '01712345678', 'email' => 'buyer@example.com', 'address_line1' => 'House 1', 'city' => 'Dhaka', 'state' => 'Dhaka'],
    ], $attributes));

    OrderItem::create([
        'order_id' => $order->id, 'product_id' => Product::factory()->create()->id, 'product_name' => 'Disc Shirt', 'sku' => 'DISC-1',
        'price' => 500, 'quantity' => 1, 'subtotal' => 500, 'product_snapshot' => [],
    ]);

    return $order;
}

function disc38InvoiceHtml(Order $order, string $copy = OrderInvoice::CUSTOMER_COPY): string
{
    return view('invoices.order', [
        'order' => $order->load(['items.product', 'items.variant', 'user']),
        'amountLabel' => OrderInvoice::amountLabel($order),
        'parcel' => $copy === OrderInvoice::PARCEL_COPY ? OrderInvoice::parcelDetails($order) : null,
    ])->render();
}

function assertDisc38Pdf(TestResponse $response, string $filename): void
{
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toContain($filename)
        ->and($response->getContent())->toStartWith('%PDF-');
}

// ── The rule and the wording ────────────────────────────────────────────────

test('every order has an invoice except a cancelled or refunded one', function (string $status, bool $available) {
    expect(OrderInvoice::isAvailable(disc38Order(['status' => $status])))->toBe($available);
})->with([
    ['pending', true], ['processing', true], ['shipped', true], ['delivered', true],
    ['cancelled', false], ['refunded', false],
]);

test('a paid order says Paid Amount, an unpaid one Payment Amount', function () {
    $paid = disc38Order(['payment_method' => 'mobile_banking', 'payment_status' => 'paid']);
    $cod = disc38Order();
    $codDelivered = disc38Order(['status' => 'delivered', 'payment_status' => 'paid']);

    expect(disc38InvoiceHtml($paid))->toContain('Paid Amount (BDT)')->not->toContain('Payment Amount')
        ->and(disc38InvoiceHtml($cod))->toContain('Payment Amount (BDT)')->not->toContain('Paid Amount')
        ->and(disc38InvoiceHtml($codDelivered))->toContain('Paid Amount (BDT)');
});

test('the parcel copy adds the courier and consignment ID; the customer copy does not', function (array $courier, string $name, string $id) {
    $order = disc38Order(['status' => 'processing', ...$courier]);

    expect(disc38InvoiceHtml($order, OrderInvoice::PARCEL_COPY))->toContain('PARCEL COPY')->toContain($name)->toContain($id)
        ->and(disc38InvoiceHtml($order))->not->toContain('PARCEL COPY')->not->toContain($id);
})->with([
    'Steadfast' => [['steadfast_consignment_id' => 'SF-777', 'steadfast_tracking_code' => 'TRK-777'], 'Steadfast', 'SF-777'],
    'Pathao' => [['pathao_consignment_id' => 'PT-888'], 'Pathao', 'PT-888'],
]);

test('the PDF is one A4 page', function () {
    $pdf = OrderInvoice::content(disc38Order());

    expect($pdf)->toStartWith('%PDF-')
        // A4 portrait in PDF points.
        ->and($pdf)->toMatch('/MediaBox\s*\[\s*0\.?0*\s+0\.?0*\s+595\.28\d*\s+841\.89\d*\s*\]/');
});

// ── Emails ──────────────────────────────────────────────────────────────────

test('the order placed and delivered emails carry the PDF invoice', function () {
    $order = disc38Order(['status' => 'delivered', 'payment_status' => 'paid']);

    foreach ([new OrderPlacedMail($order), new OrderDeliveredMail($order)] as $mail) {
        $attachments = $mail->attachments();

        expect($attachments)->toHaveCount(1)
            ->and($attachments[0]->as)->toBe("invoice-{$order->order_number}.pdf")
            ->and($attachments[0]->mime)->toBe('application/pdf');
    }

    expect((new OrderPlacedMail($order))->render())->toContain('Your invoice is attached');
});

test('a cash on delivery order placed email already carries the invoice', function () {
    expect((new OrderPlacedMail(disc38Order()))->attachments())->toHaveCount(1);
});

test('a cancelled order email carries no invoice', function () {
    $order = disc38Order(['status' => 'cancelled']);

    expect((new OrderPlacedMail($order))->attachments())->toBeEmpty()
        ->and((new OrderPlacedMail($order))->render())->not->toContain('Your invoice is attached');
});

// ── Admin ───────────────────────────────────────────────────────────────────

test('an admin with orders access downloads the customer copy', function () {
    $order = disc38Order();

    assertDisc38Pdf(actingAs(createMotherAdmin(), 'admin')->get(route('admin.orders.invoice', $order)), "invoice-{$order->order_number}.pdf");
});

test('the parcel copy downloads only after the courier booking', function () {
    $order = disc38Order(['status' => 'processing']);
    $admin = createMotherAdmin();

    actingAs($admin, 'admin')->get(route('admin.orders.invoice', ['order' => $order, 'copy' => 'parcel']))->assertNotFound();

    $order->update(['steadfast_consignment_id' => 'SF-999']);

    assertDisc38Pdf(actingAs($admin, 'admin')->get(route('admin.orders.invoice', ['order' => $order, 'copy' => 'parcel'])), "parcel-invoice-{$order->order_number}.pdf");
});

test('the admin order page offers the parcel copy only after the courier booking', function () {
    $order = disc38Order(['status' => 'processing']);
    $admin = createMotherAdmin();

    actingAs($admin, 'admin')->get(route('admin.orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('invoice', ['available' => true, 'parcel' => false]));

    $order->update(['pathao_consignment_id' => 'PT-1']);

    actingAs($admin, 'admin')->get(route('admin.orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('invoice', ['available' => true, 'parcel' => true]));
});

test('a cancelled order has no admin invoice, either copy', function () {
    $order = disc38Order(['status' => 'cancelled', 'steadfast_consignment_id' => 'SF-1']);
    $admin = createMotherAdmin();

    actingAs($admin, 'admin')->get(route('admin.orders.invoice', $order))->assertForbidden();
    actingAs($admin, 'admin')->get(route('admin.orders.invoice', ['order' => $order, 'copy' => 'parcel']))->assertForbidden();
    actingAs($admin, 'admin')->get(route('admin.orders.show', $order))
        ->assertInertia(fn (Assert $page) => $page->where('invoice', ['available' => false, 'parcel' => false]));
});

test('an admin without orders access cannot download an invoice', function () {
    $staff = Admin::create(['name' => 'No Orders', 'email' => 'no-orders-disc38@example.com', 'password' => 'password', 'permissions' => ['products' => ['view']]]);

    actingAs($staff, 'admin')->get(route('admin.orders.invoice', disc38Order()))->assertForbidden();
});

test('an unknown copy type is refused', function () {
    actingAs(createMotherAdmin(), 'admin')->get(route('admin.orders.invoice', ['order' => disc38Order(), 'copy' => 'secret']))->assertSessionHasErrors('copy');
});

// ── Guests: confirmation page ───────────────────────────────────────────────

test('the guest session that placed the order downloads its invoice from the confirmation page', function () {
    $order = disc38Order();

    $this->withSession(['order_confirmation_access' => [$order->order_number => now()->addHour()->getTimestamp()]]);

    $this->get(route('order.confirmation', $order))
        ->assertInertia(fn (Assert $page) => $page->where('order.invoice_available', true));
    assertDisc38Pdf($this->get(route('order.invoice', $order)), "invoice-{$order->order_number}.pdf");
});

test('anyone else gets a 404 from the confirmation invoice route', function () {
    $this->get(route('order.invoice', disc38Order()))->assertNotFound();
});

test('the logged-in owner downloads from the confirmation invoice route too', function () {
    $user = User::factory()->create();
    $order = disc38Order(['user_id' => $user->id]);

    assertDisc38Pdf(actingAs($user)->get(route('order.invoice', $order)), "invoice-{$order->order_number}.pdf");
});

// ── Guests: Track Order ─────────────────────────────────────────────────────

test('a successful Track Order lookup includes a signed invoice link that downloads', function () {
    $order = disc38Order();

    $url = null;
    $this->get(route('order.track', ['order_number' => $order->order_number, 'phone' => '01712345678']))
        ->assertInertia(function (Assert $page) use (&$url) {
            $page->whereNot('order.invoice_url', null);
            $url = $page->toArray()['props']['order']['invoice_url'];
        });

    assertDisc38Pdf($this->get($url), "invoice-{$order->order_number}.pdf");
});

test('a failed lookup or a cancelled order gives no invoice link', function () {
    $order = disc38Order();
    $cancelled = disc38Order(['status' => 'cancelled']);

    $this->get(route('order.track', ['order_number' => $order->order_number, 'phone' => '01799999999']))
        ->assertInertia(fn (Assert $page) => $page->where('order', null));
    $this->get(route('order.track', ['order_number' => $cancelled->order_number, 'phone' => '01712345678']))
        ->assertInertia(fn (Assert $page) => $page->where('order.invoice_url', null));
});

test('the Track Order invoice route refuses an unsigned or expired link', function () {
    $order = disc38Order();

    $this->get(route('order.track.invoice', $order))->assertForbidden();
    $this->get(URL::temporarySignedRoute('order.track.invoice', now()->subMinute(), ['order' => $order->order_number]))->assertForbidden();
});

// ── Customer account pages ──────────────────────────────────────────────────

test('the account order list and detail use the shared invoice rule', function () {
    $list = (string) file_get_contents(resource_path('js/pages/account/Orders.tsx'));
    $detail = (string) file_get_contents(resource_path('js/pages/account/OrderDetail.tsx'));
    $helper = (string) file_get_contents(resource_path('js/lib/order-invoice.ts'));

    expect($list)->toContain('orderHasInvoice(order) && (')->toContain("'account.orders.invoice'")
        ->and($detail)->toContain('orderHasInvoice(order)')->not->toContain("order.payment_method === 'cod'")
        ->and($helper)->toContain("order.status !== 'cancelled'")->toContain("order.status !== 'refunded'");
});

// ── DISC-40: Bangla text, rendered with mPDF and Noto Sans Bengali ─────────

function disc40PageCount(string $pdf): int
{
    return preg_match_all('/\/Type\s*\/Page(?!s)\b/', $pdf);
}

test('Bangla in the address is drawn with the bundled Noto Sans Bengali font', function () {
    $address = ['name' => 'সাদমান ওমর', 'phone' => '01712345678', 'address_line1' => 'বাড়ি ১২, মিরপুর', 'city' => 'কিশোরগঞ্জ', 'state' => 'ব্রাহ্মণবাড়িয়া'];
    $pdf = OrderInvoice::content(disc38Order(['shipping_address' => $address, 'billing_address' => $address]));

    expect($pdf)->toContain('NotoSansBengali')
        ->and(disc40PageCount($pdf))->toBe(1);
});

test('an English-only invoice does not embed the Bangla font', function () {
    expect(OrderInvoice::content(disc38Order()))->not->toContain('NotoSansBengali');
});

test('the Bangla language maps to Noto Sans Bengali; others keep mPDF defaults', function () {
    $fonts = new InvoiceLanguageToFont;

    expect($fonts->getLanguageOptions('bn', false))->toBe([false, 'notosansbengali'])
        ->and($fonts->getLanguageOptions('ben', false))->toBe([false, 'notosansbengali'])
        ->and($fonts->getLanguageOptions('hi', false)[1])->not->toBe('notosansbengali');
});

test('a long order paginates normally instead of running away', function () {
    $order = disc38Order();

    for ($i = 0; $i < 40; $i++) {
        OrderItem::create([
            'order_id' => $order->id, 'product_id' => Product::factory()->create()->id, 'product_name' => "Long Item {$i}", 'sku' => "LONG-{$i}",
            'price' => 10, 'quantity' => 1, 'subtotal' => 10, 'product_snapshot' => [],
        ]);
    }

    expect(disc40PageCount(OrderInvoice::content($order->fresh())))->toBeGreaterThanOrEqual(2)->toBeLessThanOrEqual(4);
});

test('the bundled Bangla font and its licence are in the project', function () {
    foreach (['NotoSansBengali-Regular.ttf', 'NotoSansBengali-Bold.ttf', 'OFL.txt'] as $file) {
        expect(resource_path("fonts/noto-sans-bengali/{$file}"))->toBeFile();
    }
});
