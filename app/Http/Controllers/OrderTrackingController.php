<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderTrackingController extends Controller
{
    /**
     * Guests who skip email at checkout (when guest checkout is enabled)
     * only have a phone number on file, so this matches by order number
     * plus either the email or the phone number stored in the order's
     * shipping-address snapshot — not just email like before.
     */
    public function show(Request $request): Response
    {
        $order = null;
        $error = null;

        $orderNumber = $request->get('order_number');
        $email = $request->get('email');
        $phone = $request->get('phone');

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
            'order' => $order,
            'error' => $error,
            'prefill_order_number' => $orderNumber ?? '',
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
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return substr($digits, -10);
    }
}
