<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\FlashSaleItem;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PendingCheckout;
use App\Models\ReceivedSms;
use App\Models\Setting;
use App\Services\InventoryService;
use App\Services\ShippingCostCalculator;
use App\Support\CartManager;
use App\Support\OrderNumberGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LandingPageController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly CartManager $cartManager,
        private readonly ShippingCostCalculator $shippingCalculator,
    ) {}

    public function show(string $slug): Response|RedirectResponse
    {
        // The promoted product can be deleted/deactivated after the landing
        // page link is already out in ads — without this, an old link would
        // 500 instead of 404 when `page->product` turns out to be null.
        $page = LandingPage::where('slug', $slug)
            ->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->active()->published())
            ->with([
                // PERF-001: variants.media — ProductVariant's appended
                // `image` accessor otherwise lazy-loads it per variant.
                'product' => fn ($q) => $q->with(['media', 'variants.media']),
            ])
            ->firstOrFail();

        return Inertia::render('landing/LandingPage', [
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'sections' => $page->getSections(),
            ],
            'product' => $this->serializeProduct($page->product),
            'bkashNumber' => Setting::find('bkash_number')?->value,
            'nagadNumber' => Setting::find('nagad_number')?->value,
            'siteName' => Setting::find('site_name')?->value ?? config('app.name'),
            'siteLogo' => Setting::find('logo')?->value,
            // Populated after mobile banking order submission
            'pendingPayment' => session('lp_pending_payment'),
            // A fresh, unguessable per-render token the order form submits
            // back — see placeOrder()'s idempotency handling (CART-01). A
            // page reload issues a new one; only a retry of this exact
            // render's form carries the same token.
            'orderToken' => (string) Str::uuid(),
        ]);
    }

    /**
     * How long a successful submission's outcome (which order/payment
     * session it produced) stays cached, so a retry of the same form
     * render — arriving after the original request already finished — is
     * redirected to that same destination instead of creating anything
     * new. Matches `PendingCheckout`'s own existing 2-hour expiry
     * convention rather than inventing a new window.
     */
    private const ORDER_TOKEN_RESULT_TTL_HOURS = 2;

    /**
     * How long a request will wait for a lock held by a concurrent
     * submission of the exact same token before giving up and reporting
     * "still processing" rather than guessing. COD/mobile-banking order
     * creation here is a handful of queries — well under this.
     */
    private const ORDER_TOKEN_LOCK_WAIT_SECONDS = 10;

    public function placeOrder(Request $request, string $slug): RedirectResponse
    {
        $page = LandingPage::where('slug', $slug)
            ->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->active()->published())
            ->with('product')
            ->firstOrFail();
        $product = $page->product;

        // FSA-21: accept the common country-code/separator variants a
        // customer might type (+8801..., 8801..., spaces, hyphens) but
        // always validate and store the plain local 01XXXXXXXXX shape —
        // matching the strict rule every other order-creation path
        // (PlaceOrderRequest, ConvertIncompleteOrderRequest,
        // TrackIncompleteOrderRequest) already enforces. Only
        // whitespace/hyphens are stripped, never every non-digit
        // character, so malformed input can't be reshaped into something
        // that happens to pass.
        $request->merge(['phone' => self::normalizeBangladeshiPhone($request->string('phone')->toString())]);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^01[3-9][0-9]{8}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'payment_method' => ['required', 'in:cod,mobile_banking'],
            'provider' => ['required_if:payment_method,mobile_banking', 'nullable', 'in:bkash,nagad'],
            // Missing/malformed must fail validation here, never silently
            // skip the idempotency check below (CART-01).
            'order_token' => ['required', 'uuid'],
        ], [
            'phone.regex' => 'Enter a valid 11-digit Bangladeshi phone number (e.g. 01712345678).',
        ]);

        $orderToken = $request->string('order_token')->toString();
        $resultKey = "lp-order-result:{$orderToken}";

        if (($cached = Cache::get($resultKey)) !== null) {
            return $this->redirectForCachedLandingOrder($cached, $slug);
        }

        $lock = Cache::lock("lp-order-lock:{$orderToken}", self::ORDER_TOKEN_LOCK_WAIT_SECONDS * 3);

        try {
            return $lock->block(self::ORDER_TOKEN_LOCK_WAIT_SECONDS, function () use ($request, $page, $product, $slug, $resultKey) {
                // The other holder may have finished and released between
                // our first check above and actually acquiring the lock.
                if (($cached = Cache::get($resultKey)) !== null) {
                    return $this->redirectForCachedLandingOrder($cached, $slug);
                }

                return $this->completeLandingPageOrder($request, $page, $product, $slug, $resultKey);
            });
        } catch (LockTimeoutException) {
            // Someone else is actively processing this exact submission and
            // hasn't finished yet — never fall through to creating a second
            // order/PendingCheckout; ask the customer to wait rather than
            // guess at what happened.
            return back()
                ->withErrors(['order_token' => 'Your order is still being processed. Please wait a moment and try again.'])
                ->withInput();
        }
    }

    /**
     * FSA-21: collapses +880/880 country-code prefixes and strips spaces
     * and hyphens so a customer typing an international-looking format
     * still lands on the plain local 01XXXXXXXXX shape the regex above
     * (and every other order-creation path) expects. Deliberately does
     * NOT strip arbitrary non-digit characters — doing so could turn
     * clearly-invalid input (e.g. embedded letters) into something that
     * coincidentally matches the regex; anything left over after this
     * targeted normalization simply fails validation as-is.
     */
    private static function normalizeBangladeshiPhone(string $raw): string
    {
        $value = str_replace([' ', '-'], '', trim($raw));

        if (str_starts_with($value, '+880')) {
            return '0'.substr($value, 4);
        }

        if (str_starts_with($value, '880')) {
            return '0'.substr($value, 3);
        }

        return $value;
    }

    private function redirectForCachedLandingOrder(array $cached, string $slug): RedirectResponse
    {
        if ($cached['type'] === 'cod') {
            return redirect()->route('lp.confirmation', $cached['guest_token']);
        }

        session()->flash('lp_pending_payment', [
            'token' => $cached['pending_token'],
            'amount' => $cached['amount'],
            'order_number' => $cached['order_number'],
            'provider' => $cached['provider'],
        ]);

        return redirect()->route('lp.show', $slug);
    }

    private function completeLandingPageOrder(Request $request, LandingPage $page, mixed $product, string $slug, string $resultKey): RedirectResponse
    {
        $qty = (int) $request->quantity;
        $price = $this->cartManager->resolvePrice($product->id, null);
        $subtotal = round($price * $qty, 2);
        $shippingCost = $this->shippingCalculator->forItemsWithoutBundles(
            ['state' => $request->state, 'city' => $request->city],
            [['free_delivery' => (bool) $product->free_delivery, 'free_delivery_min_qty' => $product->free_delivery_min_qty, 'quantity' => $qty]],
            $subtotal,
        );
        $total = $subtotal + $shippingCost;

        $shippingAddress = [
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email ?? '',
            'address_line1' => $request->address_line1,
            'address_line2' => $request->address_line2 ?? '',
            'city' => $request->city,
            'state' => $request->state,
            'postal_code' => $request->postal_code ?? '',
            'country' => 'Bangladesh',
        ];

        $itemData = [
            'product_id' => $product->id,
            'variant_id' => null,
            'product_name' => $product->name,
            'sku' => $product->sku ?? '',
            'price' => $price,
            'quantity' => $qty,
            'subtotal' => $subtotal,
            'product_snapshot' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'price' => $price,
                'image' => $product->media->where('is_primary', true)->first()?->path
                    ?? $product->media->first()?->path,
            ],
        ];

        if ($request->payment_method === 'cod') {
            $guestToken = Str::random(40);
            $orderNumber = OrderNumberGenerator::generate();

            try {
                $order = DB::transaction(function () use ($page, $shippingAddress, $itemData, $subtotal, $shippingCost, $total, $guestToken, $orderNumber) {
                    $order = Order::create([
                        'order_number' => $orderNumber,
                        'user_id' => auth()->id(),
                        'landing_page_id' => $page->id,
                        'guest_token' => $guestToken,
                        'status' => 'pending',
                        'payment_status' => 'unpaid',
                        'payment_method' => PaymentMethod::COD->value,
                        'subtotal' => $subtotal,
                        'shipping_cost' => $shippingCost,
                        'discount' => 0,
                        'tax' => 0,
                        'total' => $total,
                        'shipping_address' => $shippingAddress,
                        'billing_address' => $shippingAddress,
                    ]);

                    $order->statusHistories()->create(['status' => 'pending', 'notes' => 'Order placed via landing page.']);

                    $flashSaleItemId = $this->inventory->decrementForOrderItem($order, $itemData['product_id'], $itemData['variant_id'], $itemData['quantity'], (float) $itemData['price']);
                    $order->items()->create([...$itemData, 'flash_sale_item_id' => $flashSaleItemId]);

                    return $order;
                });
            } catch (InsufficientStockException $e) {
                // Not cached as a result — a failed attempt must not poison
                // this token; the customer (or a genuine retry of it) can
                // still try again once stock is available.
                return back()->withErrors(['quantity' => $e->getMessage()])->withInput();
            }

            Cache::put($resultKey, ['type' => 'cod', 'guest_token' => $guestToken], now()->addHours(self::ORDER_TOKEN_RESULT_TTL_HOURS));

            return redirect()->route('lp.confirmation', $guestToken);
        }

        // Mobile banking
        // BIZ-006: nothing else here ever checked stock before this point —
        // decrementForOrderItem() only runs once the customer has already
        // paid and submitted their TrxID (below, in submitPayment()).
        try {
            $this->inventory->assertAvailable($itemData['product_id'], $itemData['variant_id'], $itemData['quantity'], (float) $itemData['price']);
        } catch (InsufficientStockException $e) {
            return back()->withErrors(['quantity' => $e->getMessage()])->withInput();
        }

        $guestToken = Str::random(40);
        $orderNumber = OrderNumberGenerator::generate();
        $pendingToken = (string) Str::uuid();

        PendingCheckout::create([
            'token' => $pendingToken,
            'user_id' => auth()->id(),
            'landing_page_id' => $page->id,
            'guest_token' => $guestToken,
            'checkout_data' => [
                'order_number' => $orderNumber,
                'shipping_address' => $shippingAddress,
                'items' => [$itemData],
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingCost,
                'discount' => 0,
                'coupon_id' => null,
                'coupon_code' => null,
                'tax' => 0,
                'total' => $total,
                'provider' => $request->provider,
                'landing_page_id' => $page->id,
                'guest_token' => $guestToken,
            ],
            'expires_at' => now()->addHours(2),
        ]);

        Cache::put($resultKey, [
            'type' => 'mobile_banking',
            'pending_token' => $pendingToken,
            'amount' => $total,
            'order_number' => $orderNumber,
            'provider' => $request->provider,
        ], now()->addHours(self::ORDER_TOKEN_RESULT_TTL_HOURS));

        // Flash payment context and return to the same landing page
        // so the TxnID step happens inline without leaving the page.
        session()->flash('lp_pending_payment', [
            'token' => $pendingToken,
            'amount' => $total,
            'order_number' => $orderNumber,
            'provider' => $request->provider,
        ]);

        return redirect()->route('lp.show', $slug);
    }

    public function payment(string $slug, string $token): Response|RedirectResponse
    {
        $pending = PendingCheckout::where('token', $token)->first();

        if (! $pending || $pending->isExpired()) {
            return redirect()->route('lp.show', $slug)->with('error', 'Payment session expired. Please try again.');
        }

        if ($pending->order_number !== null) {
            $guestToken = $pending->guest_token ?? $pending->checkout_data['guest_token'] ?? null;
            if ($guestToken) {
                return redirect()->route('lp.confirmation', $guestToken);
            }
        }

        return Inertia::render('landing/LandingPayment', [
            'token' => $token,
            'slug' => $slug,
            'amount' => $pending->checkout_data['total'],
            'orderNumber' => $pending->checkout_data['order_number'],
            'provider' => $pending->checkout_data['provider'],
            'bkashNumber' => Setting::find('bkash_number')?->value,
            'nagadNumber' => Setting::find('nagad_number')?->value,
            'siteName' => Setting::find('site_name')?->value ?? config('app.name'),
            'siteLogo' => Setting::find('logo')?->value,
        ]);
    }

    public function submitPayment(Request $request, string $slug, string $token): RedirectResponse
    {
        $request->validate([
            'transaction_id' => ['required', 'string', 'min:4', 'max:40'],
        ]);

        $pending = PendingCheckout::where('token', $token)->first();

        if (! $pending || $pending->isExpired()) {
            return redirect()->route('lp.show', $slug)->with('error', 'Payment session expired.');
        }

        if ($pending->order_number !== null) {
            $guestToken = $pending->guest_token ?? $pending->checkout_data['guest_token'] ?? null;
            if ($guestToken) {
                return redirect()->route('lp.confirmation', $guestToken);
            }
        }

        $transactionId = strtoupper(trim($request->transaction_id));
        $provider = $pending->checkout_data['provider'];
        $expectedAmount = (float) $pending->checkout_data['total'];
        $guestToken = $pending->guest_token ?? $pending->checkout_data['guest_token'];

        try {
            $order = DB::transaction(function () use ($pending, $provider, $transactionId, $expectedAmount, $guestToken): Order {
                $freshPending = PendingCheckout::lockForUpdate()->find($pending->id);

                if ($freshPending === null || $freshPending->isExpired()) {
                    throw new \RuntimeException('expired');
                }

                if ($freshPending->order_number !== null) {
                    return Order::where('order_number', $freshPending->order_number)->firstOrFail();
                }

                // See MobilePaymentController::submit() — the row is locked
                // regardless of match status so a unique DB constraint on
                // (provider, parsed_transaction_id) lets us tell "already
                // consumed" apart from "never existed" for the same reasons.
                $sms = ReceivedSms::lockForUpdate()
                    ->where('parsed_transaction_id', $transactionId)
                    ->where('provider', $provider)
                    ->first();

                if ($sms && $sms->is_matched) {
                    throw new \RuntimeException('already_used');
                }

                if (! $sms) {
                    $existsOtherProvider = ReceivedSms::where('parsed_transaction_id', $transactionId)
                        ->where('is_matched', false)->exists();
                    throw new \RuntimeException($existsOtherProvider ? 'wrong_provider' : 'not_found');
                }

                // See MobilePaymentController::submit() — a matching
                // transaction ID alone is not proof of payment for the
                // right amount; an unparseable SMS amount must fail
                // closed, not be treated as "nothing to contradict the
                // claim."
                if ($sms->parsed_amount === null) {
                    throw new \RuntimeException('amount_unavailable');
                }

                if (abs($sms->parsed_amount - $expectedAmount) > 1) {
                    throw new \RuntimeException('amount_mismatch:'.$sms->parsed_amount);
                }

                $data = $freshPending->checkout_data;
                $providerLabel = $provider === 'bkash' ? 'Bkash' : 'Nagad';

                $order = Order::create([
                    'order_number' => $data['order_number'],
                    'user_id' => $freshPending->user_id,
                    'landing_page_id' => $data['landing_page_id'] ?? $freshPending->landing_page_id,
                    'guest_token' => $guestToken,
                    'status' => 'processing',
                    'payment_status' => PaymentStatus::PAID->value,
                    'payment_method' => PaymentMethod::MobileBanking->value,
                    'subtotal' => $data['subtotal'],
                    'shipping_cost' => $data['shipping_cost'],
                    'discount' => $data['discount'],
                    'coupon_id' => $data['coupon_id'],
                    'coupon_code' => $data['coupon_code'],
                    'tax' => $data['tax'],
                    'total' => $data['total'],
                    'shipping_address' => $data['shipping_address'],
                    'billing_address' => $data['shipping_address'],
                ]);

                $order->statusHistories()->create([
                    'status' => 'processing',
                    'notes' => "Payment confirmed via {$providerLabel}. TrxID: {$transactionId}",
                ]);

                foreach ($data['items'] as $item) {
                    $flashSaleItemId = $this->inventory->decrementForOrderItem($order, $item['product_id'], $item['variant_id'], $item['quantity'], (float) $item['price']);
                    $order->items()->create([...$item, 'flash_sale_item_id' => $flashSaleItemId]);
                }

                Payment::create([
                    'order_id' => $order->id,
                    'method' => PaymentMethod::MobileBanking->value,
                    'amount' => $data['total'],
                    'status' => PaymentStatus::PAID,
                    'transaction_id' => $transactionId,
                    'gateway_response' => ['provider' => $provider, 'sms_id' => $sms->id],
                ]);

                $sms->update(['is_matched' => true, 'matched_order_id' => $order->id]);
                $freshPending->update(['order_number' => $order->order_number]);

                Log::info('[LandingPage] Order confirmed', ['order_number' => $order->order_number]);

                return $order;
            });
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage();
            $providerLabel = $provider === 'bkash' ? 'Bkash' : 'Nagad';

            // Re-flash the payment context so the inline payment UI reappears
            $pendingFlash = [
                'token' => $token,
                'amount' => $expectedAmount,
                'order_number' => $pending->checkout_data['order_number'],
                'provider' => $provider,
            ];

            if ($msg === 'expired') {
                return redirect()->route('lp.show', $slug)->with('error', 'Payment session expired. Please place your order again.');
            }

            session()->flash('lp_pending_payment', $pendingFlash);

            return match (true) {
                $e instanceof InsufficientStockException => redirect()->route('lp.show', $slug)
                    ->withErrors(['transaction_id' => $msg]),
                $msg === 'not_found' => redirect()->route('lp.show', $slug)
                    ->withErrors(['transaction_id' => "Transaction ID not found in our {$providerLabel} records. Please double-check the ID from your SMS."]),
                $msg === 'wrong_provider' => redirect()->route('lp.show', $slug)
                    ->withErrors(['transaction_id' => 'This Transaction ID belongs to a different payment provider.']),
                $msg === 'already_used' => redirect()->route('lp.show', $slug)
                    ->withErrors(['transaction_id' => 'This Transaction ID has already been used to pay for another order.']),
                $msg === 'amount_unavailable' => redirect()->route('lp.show', $slug)
                    ->withErrors(['transaction_id' => "We couldn't automatically verify the amount for this transaction. Please contact support with your Transaction ID so we can confirm it manually."]),
                str_starts_with($msg, 'amount_mismatch:') => redirect()->route('lp.show', $slug)
                    ->withErrors(['transaction_id' => 'Amount mismatch. Expected ৳'.$expectedAmount.' but SMS shows ৳'.substr($msg, 16).'.']),
                default => redirect()->route('lp.show', $slug)->with('error', 'Something went wrong. Please contact support.'),
            };
        }

        return redirect()->route('lp.confirmation', $guestToken);
    }

    public function confirmation(string $guestToken): Response|RedirectResponse
    {
        $order = Order::where('guest_token', $guestToken)->with('items')->first();

        if (! $order) {
            abort(404);
        }

        return Inertia::render('landing/LandingConfirmation', [
            // SEC-006: the raw model was sent here directly — guest_token
            // itself (already a separate prop below), ip_address, every
            // status-history note (some name the admin who actioned it),
            // and whatever else isn't $hidden on Order. Exactly the fields
            // LandingConfirmation.tsx renders instead.
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'payment_status' => $order->payment_status->value,
                'payment_method' => $order->payment_method->value,
                'subtotal' => (float) $order->subtotal,
                'shipping_cost' => (float) $order->shipping_cost,
                'discount' => (float) $order->discount,
                'total' => (float) $order->total,
                'shipping_address' => array_intersect_key(
                    (array) $order->shipping_address,
                    array_flip(['name', 'phone', 'address_line1', 'address_line2', 'city', 'state']),
                ),
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'price' => (float) $item->price,
                    'quantity' => $item->quantity,
                    'subtotal' => (float) $item->subtotal,
                ])->values()->all(),
            ],
            'guestToken' => $guestToken,
            'siteName' => Setting::find('site_name')?->value ?? config('app.name'),
            'siteLogo' => Setting::find('logo')?->value,
        ]);
    }

    public function downloadInvoice(string $guestToken): HttpResponse
    {
        $order = Order::where('guest_token', $guestToken)->with('items')->firstOrFail();

        // LP-INVOICE-STATUS-001: the same two rules account.php's invoice
        // route already enforces — an invoice must never imply payment was
        // received for an order that was cancelled/refunded (order status
        // stays authoritative) or for a COD order not yet delivered. The
        // guest_token itself remains this route's access control, unchanged.
        $status = $order->status->value;
        $isCod = $order->payment_method->value === 'cod';

        if (in_array($status, ['cancelled', 'refunded'], true)) {
            abort(403, 'Invoice is not available for cancelled or refunded orders.');
        }

        if ($isCod && $status !== OrderStatus::DELIVERED->value) {
            abort(403, 'Invoice is only available after delivery for Cash on Delivery orders.');
        }

        $pdf = Pdf::loadView('invoices.order', ['order' => $order]);

        return $pdf->download("invoice-{$order->order_number}.pdf");
    }

    /**
     * BIZ-016: this page showed the plain price/compare_price unconditionally
     * — placeOrder() below already charges the correct, flash-sale-aware
     * price via CartManager::resolvePrice() (line ~207), so a product on an
     * active flash sale was charged correctly but *displayed* at the
     * regular price, including in this page's own on-screen subtotal
     * preview (LandingPage.tsx's `product.price * qty`). `price` here is
     * now the same effective price placeOrder() would charge, and
     * `compare_price` is whichever of the stored compare_price or the
     * regular price is actually higher than that, so the existing
     * strike-through already works without any extra frontend field.
     */
    private function serializeProduct(mixed $product): array
    {
        $flashItem = FlashSaleItem::findActiveFor($product->id, null);
        $effectivePrice = (float) ($flashItem?->sale_price ?? $product->price);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'short_description' => $product->short_description,
            'price' => $effectivePrice,
            'compare_price' => $product->compare_price && (float) $product->compare_price > $effectivePrice
                ? $product->compare_price
                : ((float) $product->price > $effectivePrice ? $product->price : null),
            'in_flash_sale' => $flashItem !== null,
            'flash_sale_price' => $flashItem?->sale_price,
            'free_delivery' => $product->free_delivery,
            'free_delivery_min_qty' => $product->free_delivery_min_qty,
            'media' => $product->media->map(fn ($m) => [
                'id' => $m->id,
                'path' => $m->path,
                'type' => $m->type,
                'is_primary' => $m->is_primary,
            ])->values(),
            'variants' => $product->variants->map(function ($v) use ($product) {
                $variantFlashItem = FlashSaleItem::findActiveFor($product->id, $v->id);
                $regularVariantPrice = (float) ($v->price ?? $product->price);
                $effectiveVariantPrice = (float) ($variantFlashItem?->sale_price ?? $regularVariantPrice);

                return [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'price' => $effectiveVariantPrice,
                    'stock_quantity' => $v->stock_quantity,
                    'attributes' => $v->attributes,
                    'in_flash_sale' => $variantFlashItem !== null,
                    'flash_sale_price' => $variantFlashItem?->sale_price,
                ];
            })->values(),
        ];
    }
}
