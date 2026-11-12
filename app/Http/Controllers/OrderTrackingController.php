<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\BangladeshiPhone;
use App\Support\SecurityRateLimits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderTrackingController extends Controller
{
    /**
     * Verifies the order number plus whichever of email/phone was supplied
     * and, if they match, returns the full order. The frontend only ever
     * sends email/phone once `checkRequirements()` (below) has told it the
     * order exists and which of those fields it can actually be verified
     * with — guests who skipped email at checkout (when guest checkout is
     * enabled) only have a phone number on file.
     */
    public function show(Request $request): Response
    {
        $order = null;
        $error = null;

        // BE-001: a list (e.g. `phone[]=x`) instead of text used to crash the
        // match below; anything that isn't text is treated as not given.
        // Over-long input can never match a real order, so it counts as not given.
        $orderNumber = $this->boundedInput($request, 'order_number', 40) ?: null;
        $email = $this->boundedInput($request, 'email', 150) ?: null;
        $phone = $this->boundedInput($request, 'phone', 20) ?: null;

        if ($orderNumber && ($email || $phone)) {
            $found = Order::where('order_number', $orderNumber)
                ->with(['items', 'statusHistories' => fn ($q) => $q->orderByDesc('created_at')])
                ->first();

            if (! $found) {
                $error = 'No order found with that order number. Please check and try again.';
            } elseif ($this->matches($found, $email, $phone)) {
                $order = $found;
            } else {
                $error = 'The email or phone number does not match our records for this order.';
            }
        } elseif ($orderNumber) {
            $error = 'Please also enter the email or phone number used for this order.';
        }

        return Inertia::render('order/TrackOrder', [
            'order' => $order ? $this->payload($order) : null,
            'error' => $error,
            'prefill_order_number' => $orderNumber ?? '',
        ]);
    }

    /**
     * SEC-006: the raw model was sent here directly, with only guest_token
     * hidden — anyone holding the order number and the phone/email on the
     * parcel label (both printed on the courier's own label) got back the
     * buyer's IP, browser, full address, and every status-history note,
     * some of which name the admin who actioned it (e.g.
     * RefundService::record()'s "Marked as refunded by {admin name}...").
     * Exactly the fields TrackOrder.tsx renders, nothing else — same
     * pattern as OrderConfirmationAccess::payload() for the confirmation
     * page. account/OrderDetail.tsx (logged-in, the order's own owner)
     * still gets the raw model and its history notes — a separate,
     * narrower concern (the customer seeing staff names on their own
     * order) left untouched here.
     *
     * @return array<string, mixed>
     */
    private function payload(Order $order): array
    {
        return [
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'created_at' => $order->created_at?->toISOString(),
            'total' => (float) $order->total,
            'tracking_number' => $order->tracking_number,
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'subtotal' => (float) $item->subtotal,
            ])->values()->all(),
            'status_histories' => $order->statusHistories->map(fn ($history) => [
                'status' => $history->status,
                'created_at' => $history->created_at?->toISOString(),
            ])->values()->all(),
        ];
    }

    /**
     * Polled live as the customer types the order number (see
     * TrackOrder.tsx), so the email/phone field(s) can appear instantly
     * instead of waiting for a separate "Find order" step. Only reports
     * which field(s) exist on file — never the order itself.
     */
    public function checkRequirements(Request $request): JsonResponse
    {
        $orderNumber = $this->boundedInput($request, 'order_number', 40); // BE-001

        if ($orderNumber === '') {
            return response()->json(['found' => false]);
        }

        $order = Order::where('order_number', $orderNumber)->first();

        if (! $order) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'requires' => [
                'email' => filled($order->shipping_address['email'] ?? null),
                'phone' => filled($order->shipping_address['phone'] ?? null),
            ],
        ]);
    }

    private function matches(Order $order, ?string $email, ?string $phone): bool
    {
        $onFileEmail = $order->shipping_address['email'] ?? '';
        if ($email && $onFileEmail !== '' && strtolower($onFileEmail) === strtolower($email)) {
            return true;
        }

        $onFilePhone = $order->shipping_address['phone'] ?? '';
        if ($phone && $onFilePhone !== '' && $this->normalizePhone($onFilePhone) === $this->normalizePhone($phone)) {
            return true;
        }

        return false;
    }

    /**
     * Collapses to the last 10 digits so "01700000000", "+8801700000000",
     * and "1700000000" all compare equal regardless of how either side
     * typed the country code or leading zero.
     */
    private function normalizePhone(string $phone): string
    {
        // FE-012: Bangla digits (০–৯) become 0–9 first.
        $digits = preg_replace('/\D+/', '', (string) BangladeshiPhone::normalize($phone)) ?? '';

        return substr($digits, -10);
    }

    /**
     * Text input of at most $max characters; longer values are treated as empty.
     */
    private function boundedInput(Request $request, string $key, int $max): string
    {
        $value = SecurityRateLimits::textInput($request, $key);

        return mb_strlen($value) > $max ? '' : $value;
    }
}
