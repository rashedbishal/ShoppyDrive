<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\HomepageSetting;
use App\Models\LandingPage;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PendingCheckout;
use App\Models\ReceivedSms;
use App\Models\Setting;
use App\Services\InventoryService;
use App\Support\CartManager;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
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
                'product' => fn ($q) => $q->with(['media', 'variants']),
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
        ]);
    }

    public function placeOrder(Request $request, string $slug): RedirectResponse
    {
        $page = LandingPage::where('slug', $slug)
            ->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->active()->published())
            ->with('product')
            ->firstOrFail();
        $product = $page->product;

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'payment_method' => ['required', 'in:cod,mobile_banking'],
            'provider' => ['required_if:payment_method,mobile_banking', 'nullable', 'in:bkash,nagad'],
        ]);

        $qty = (int) $request->quantity;
        $price = $this->cartManager->resolvePrice($product->id, null);
        $subtotal = round($price * $qty, 2);
        $shippingCost = $this->calculateShipping($product, $request->state, $request->city, $subtotal);
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
            $orderNumber = $this->generateOrderNumber();

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
                return back()->withErrors(['quantity' => $e->getMessage()])->withInput();
            }

            return redirect()->route('lp.confirmation', $guestToken);
        }

        // Mobile banking
        $guestToken = Str::random(40);
        $orderNumber = $this->generateOrderNumber();
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

                if ($sms->parsed_amount !== null && abs($sms->parsed_amount - $expectedAmount) > 1) {
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
            'order' => $order,
            'guestToken' => $guestToken,
            'siteName' => Setting::find('site_name')?->value ?? config('app.name'),
            'siteLogo' => Setting::find('logo')?->value,
        ]);
    }

    public function downloadInvoice(string $guestToken): HttpResponse
    {
        $order = Order::where('guest_token', $guestToken)->with('items')->firstOrFail();

        $pdf = Pdf::loadView('invoices.order', ['order' => $order]);

        return $pdf->download("invoice-{$order->order_number}.pdf");
    }

    private function calculateShipping(mixed $product, string $state, string $city, float $subtotal): float
    {
        $bar = HomepageSetting::where('section_key', 'announcement_bar')->first();
        $threshold = (float) ($bar?->metadata['free_delivery_amount'] ?? 0);

        if ($threshold > 0 && $subtotal >= $threshold) {
            return 0.0;
        }

        if ($product->free_delivery) {
            return 0.0;
        }

        return Setting::shippingRateFor($state, $city);
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-'.date('Ymd').'-'.strtoupper(Str::random(6));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }

    private function serializeProduct(mixed $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'short_description' => $product->short_description,
            'price' => $product->price,
            'compare_price' => $product->compare_price,
            'free_delivery' => $product->free_delivery,
            'free_delivery_min_qty' => $product->free_delivery_min_qty,
            'media' => $product->media->map(fn ($m) => [
                'id' => $m->id,
                'path' => $m->path,
                'type' => $m->type,
                'is_primary' => $m->is_primary,
            ])->values(),
            'variants' => $product->variants->map(fn ($v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'price' => $v->price ?? $product->price,
                'stock_quantity' => $v->stock_quantity,
                'attributes' => $v->attributes,
            ])->values(),
        ];
    }
}
