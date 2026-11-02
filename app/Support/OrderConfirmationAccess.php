<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;
use Illuminate\Http\Request;

/**
 * FSA-03: who may open `order.confirmation`. An order number is not a
 * secret (it's printed in emails, SMS and courier labels), so knowing one
 * must not reveal the order. Access is the logged-in owner, or the browser
 * session that just placed or paid for the order — every path that
 * redirects to the confirmation page calls grant() first. A grant expires
 * after GRANT_TTL_MINUTES; after that a guest uses order tracking, which
 * asks for the order's phone or email.
 */
final class OrderConfirmationAccess
{
    private const SESSION_KEY = 'order_confirmation_access';

    private const GRANT_TTL_MINUTES = 1440;

    private const MAX_GRANTS = 20;

    public static function grant(Request $request, string $orderNumber): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $now = now()->getTimestamp();

        /** @var array<string, int> $grants */
        $grants = array_filter(
            (array) $request->session()->get(self::SESSION_KEY, []),
            fn (mixed $expiresAt): bool => is_int($expiresAt) && $expiresAt > $now,
        );

        unset($grants[$orderNumber]);
        $grants[$orderNumber] = $now + self::GRANT_TTL_MINUTES * 60;

        $request->session()->put(self::SESSION_KEY, array_slice($grants, -self::MAX_GRANTS, preserve_keys: true));
    }

    public static function allows(Request $request, Order $order): bool
    {
        $user = $request->user();

        if ($user !== null && $order->user_id !== null && (int) $order->user_id === (int) $user->getAuthIdentifier()) {
            return true;
        }

        if (! $request->hasSession()) {
            return false;
        }

        $expiresAt = $request->session()->get(self::SESSION_KEY.'.'.$order->order_number);

        return is_int($expiresAt) && $expiresAt > now()->getTimestamp();
    }

    /**
     * Exactly the fields the confirmation page renders — never the phone
     * number, items, totals breakdown, tokens or courier ids.
     *
     * @return array{order_number: string, status: string, payment_method: string, total: float, created_at: string|null, shipping_address: array<string, mixed>}
     */
    public static function payload(Order $order): array
    {
        $address = (array) $order->shipping_address;

        return [
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'payment_method' => $order->payment_method->value,
            'total' => (float) $order->total,
            'created_at' => $order->created_at?->toISOString(),
            'shipping_address' => [
                'name' => $address['name'] ?? null,
                'email' => $address['email'] ?? null,
                'address_line1' => $address['address_line1'] ?? null,
                'address_line2' => $address['address_line2'] ?? null,
                'city' => $address['city'] ?? null,
                'state' => $address['state'] ?? null,
                'postal_code' => $address['postal_code'] ?? null,
            ],
        ];
    }
}
