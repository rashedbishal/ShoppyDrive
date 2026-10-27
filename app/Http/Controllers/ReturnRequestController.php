<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\ReturnStatus;
use App\Exceptions\ReturnQuantityExceededException;
use App\Models\Order;
use App\Models\ReturnRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReturnRequestController extends Controller
{
    /**
     * How long after delivery a customer may still request a return — the
     * same 7-day window already published to customers on the product page
     * (see catalog/ProductDetail.tsx's "Return Policy" copy). Centralized
     * here so the enforced window can never drift from what's advertised.
     */
    private const ELIGIBILITY_WINDOW_DAYS = 7;

    public function store(Request $request, Order $order): RedirectResponse
    {
        abort_if($order->user_id !== $request->user()->id, 403);

        if ($order->status !== OrderStatus::DELIVERED || $order->delivered_at === null) {
            return back()->with('error', 'Only delivered orders are eligible for a return.');
        }

        if ($order->delivered_at->addDays(self::ELIGIBILITY_WINDOW_DAYS)->isPast()) {
            return back()->with('error', 'The '.self::ELIGIBILITY_WINDOW_DAYS.'-day return window for this order has passed.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => [
                'required',
                'integer',
                'distinct',
                Rule::in($order->items()->pluck('id')->all()),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            DB::transaction(function () use ($order, $request, $validated): void {
                // Lock the order row so two concurrent return submissions
                // against it can never both read the same "remaining
                // quantity" for an item and both succeed in over-returning
                // it. Matches the same lock-the-order pattern already used
                // by InventoryService/CouponService for this exact reason.
                // Committed quantities are only ever computed after this.
                Order::whereKey($order->id)->lockForUpdate()->first();

                $itemsById = $order->items()->get()->keyBy('id');
                $committed = ReturnRequest::committedQuantitiesByOrderItem($order);

                // The 'distinct' validation rule above already rejects a
                // submission with a repeated order_item_id, but that's a
                // request-shape check, not an arithmetic guarantee — this
                // aggregates requested quantity per item regardless, so
                // the remaining-quantity check itself can never be
                // bypassed by duplicate lines even if called some other
                // way that skips validation.
                $requestedByItem = collect($validated['items'])
                    ->groupBy('order_item_id')
                    ->map(fn ($group) => $group->sum('quantity'));

                foreach ($requestedByItem as $orderItemId => $requestedQuantity) {
                    $orderItem = $itemsById->get($orderItemId);

                    if ($orderItem === null) {
                        throw ReturnQuantityExceededException::forOrderItem('this item', 0);
                    }

                    $remaining = $orderItem->quantity - ($committed[$orderItemId] ?? 0);

                    if ($requestedQuantity > $remaining) {
                        throw ReturnQuantityExceededException::forOrderItem($orderItem->product_name, $remaining);
                    }
                }

                ReturnRequest::create([
                    'order_id' => $order->id,
                    'user_id' => $request->user()->id,
                    'reason' => $validated['reason'],
                    'status' => ReturnStatus::PENDING,
                    'items' => array_map(fn (array $item): array => [
                        'order_item_id' => (int) $item['order_item_id'],
                        'product_id' => $itemsById->get($item['order_item_id'])->product_id,
                        'quantity' => (int) $item['quantity'],
                        'reason' => $item['reason'] ?? null,
                    ], $validated['items']),
                ]);
            });
        } catch (ReturnQuantityExceededException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('success', 'Your return request has been submitted. We\'ll review it and get back to you.');
    }
}
