<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\CourierVerificationRefusedException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderDeliveryVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * PAY-RETURN-001: the mother admin's audited verification of one exact
 * consignment or failed courier submission (route: admin.mother,
 * throttle:30,15, session blocking). Everything is enforced by
 * OrderDeliveryVerificationService; passwords are never stored or flashed.
 */
class OrderDeliveryVerificationController extends Controller
{
    public function store(Request $request, Order $order, OrderDeliveryVerificationService $verifications): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'max:255'],
            'target_type' => ['required', 'in:consignment,submission'],
            'provider' => ['required', 'in:pathao,steadfast'],
            'consignment_id' => ['nullable', 'string', 'max:100'],
            'courier_submission_id' => ['nullable', 'integer'],
            'outcome' => ['required', 'in:full_delivery,not_delivered,not_created'],
            'verified_collected_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'timeline_reviewed' => ['accepted'],
            'note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        try {
            $verifications->verify(Auth::guard('admin')->user(), $order, $request, $validated);
        } catch (CourierVerificationRefusedException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        return back()->with('success', 'Delivery verification recorded.');
    }
}
