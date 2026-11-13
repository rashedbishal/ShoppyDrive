<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\ReturnQuantityExceededException;
use App\Models\Order;
use App\Services\ReturnRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReturnRequestController extends Controller
{
    public function store(Request $request, Order $order, ReturnRequestService $returns): RedirectResponse
    {
        // DISC-10: 404, so another customer's order number reveals nothing.
        abort_if($order->user_id !== $request->user()->id, 404);

        $eligibilityError = $returns->eligibilityError($order);

        if ($eligibilityError !== null) {
            return back()->with('error', $eligibilityError);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => [
                'required',
                'integer',
                'distinct',
                Rule::in($order->items()->pluck('id')->all()),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.reason' => ['nullable', 'string', 'max:300'],
        ]);

        try {
            $returns->submit($order, $request->user(), $validated['reason'], $validated['items']);
        } catch (ReturnQuantityExceededException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('success', 'Your return request has been submitted. We\'ll review it and get back to you.');
    }
}
