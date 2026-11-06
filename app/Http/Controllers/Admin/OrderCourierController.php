<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CourierEvidenceSource;
use App\Exceptions\CourierSubmissionBlockedException;
use App\Exceptions\CourierSubmissionException;
use App\Exceptions\CourierSubmissionFinalizationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConfirmCourierNotCreatedRequest;
use App\Http\Requests\Admin\ConfirmReturnedConsignmentRequest;
use App\Http\Requests\Admin\RecordCourierConsignmentRequest;
use App\Models\CourierSubmission;
use App\Models\Order;
use App\Services\CourierOrderStatusMapper;
use App\Services\CourierSubmissionService;
use App\Services\PathaoCourierService;
use App\Services\SteadfastCourierService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * MED-11 (courier-dispatch extraction only — see the remediation tracker):
 * the seven courier-dispatch actions and their one private helper, moved
 * verbatim out of Admin\OrderController. No behavior, locking, state-
 * transition, exception-handling, or flash-message logic was changed —
 * every line below is byte-identical to the methods it replaced there.
 * CRUD, refunds/cancellation, destructive deletion, and address correction
 * remain in Admin\OrderController; this is a controller-boundary move only.
 */
class OrderCourierController extends Controller
{
    public function __construct(
        private readonly PathaoCourierService $pathao,
        private readonly SteadfastCourierService $steadfast,
        private readonly CourierSubmissionService $courierSubmissions,
    ) {}

    public function sendToPathao(Order $order): RedirectResponse
    {
        return $this->sendToCourier($order, 'pathao');
    }

    public function refreshPathaoStatus(Order $order): RedirectResponse
    {
        if ($order->pathao_consignment_id === null) {
            return back()->with('error', 'This order has not been sent to Pathao yet.');
        }

        try {
            $info = $this->pathao->getOrderInfo($order->pathao_consignment_id);
        } catch (RuntimeException|ConnectionException $e) {
            return back()->with('error', 'Pathao: '.$e->getMessage());
        }

        $raw = $info['order_status_slug'] ?? $info['order_status'];
        CourierOrderStatusMapper::sync($order, 'pathao', $raw, source: CourierEvidenceSource::PATHAO_ADMIN_REFRESH);

        return back()->with('success', 'Pathao status: '.CourierOrderStatusMapper::label($raw).'.');
    }

    public function sendToSteadfast(Order $order): RedirectResponse
    {
        return $this->sendToCourier($order, 'steadfast');
    }

    public function refreshSteadfastStatus(Order $order): RedirectResponse
    {
        if ($order->steadfast_consignment_id === null) {
            return back()->with('error', 'This order has not been sent to Steadfast yet.');
        }

        try {
            $info = $this->steadfast->getOrderInfo($order->steadfast_consignment_id);
        } catch (RuntimeException|ConnectionException $e) {
            return back()->with('error', 'Steadfast: '.$e->getMessage());
        }

        CourierOrderStatusMapper::sync($order, 'steadfast', $info['status'], source: CourierEvidenceSource::STEADFAST_ADMIN_REFRESH);

        return back()->with('success', 'Steadfast status: '.CourierOrderStatusMapper::label($info['status']).'.');
    }

    /**
     * COURIER-SEND-RACE-001: claim the order (atomically, across both
     * couriers), call the courier with no lock held, record the outcome.
     * An outcome that may have created a parcel keeps the claim until an
     * admin reconciles it — it is never retried automatically.
     */
    private function sendToCourier(Order $order, string $courier): RedirectResponse
    {
        $label = $courier === 'pathao' ? 'Pathao' : 'Steadfast';

        try {
            $submission = $this->courierSubmissions->claim($order, $courier, Auth::guard('admin')->user());
        } catch (CourierSubmissionBlockedException $e) {
            return back()->with('error', $e->getMessage());
        }

        try {
            $result = $this->courierSubmissions->submit($submission, $order->fresh());
        } catch (CourierSubmissionFinalizationException $e) {
            return back()->with('error', "{$label} created consignment {$e->consignmentId}, but it could not be saved to this order. The ID has been kept on the submission — link it in the Courier panel. The order won't be sent again until then.");
        } catch (CourierSubmissionException $e) {
            if ($e->isOutcomeUnknown()) {
                return back()->with('error', "{$label} did not confirm this booking, so it may or may not have been created. Check the {$label} portal for invoice {$order->order_number}, then resolve it in the Courier panel. It won't be sent again until then.");
            }

            return back()->with('error', str_starts_with($e->getMessage(), $label) ? $e->getMessage() : "{$label}: {$e->getMessage()}");
        }

        $order->refresh();

        if ($order->{"{$courier}_consignment_id"} !== $result['consignment_id']) {
            return back()->with('error', "{$label} returned consignment {$result['consignment_id']}, but this submission had already been resolved — see the order history.");
        }

        CourierOrderStatusMapper::sync($order, $courier, $courier === 'pathao' ? $result['order_status'] : $result['status'], source: CourierEvidenceSource::COURIER_SEND);

        // BIZ-028: owner chose warn-only. A sandbox booking looks sent but no
        // rider is dispatched, so say so loudly instead of "sent for pickup".
        if ($courier === 'pathao' && PathaoCourierService::isSandbox()) {
            return back()->with('error', 'Sent to Pathao SANDBOX (test mode). This parcel will NOT be collected. Switch Pathao to Production in Admin › Courier to send real parcels.');
        }

        return back()->with('success', "Order sent to {$label} for pickup.");
    }

    public function recordCourierConsignment(RecordCourierConsignmentRequest $request, Order $order, CourierSubmission $courierSubmission): RedirectResponse
    {
        try {
            $this->courierSubmissions->recordConsignment(
                $courierSubmission,
                Auth::guard('admin')->user(),
                $request->validated('consignment_id'),
                $request->validated('tracking_code'),
                $request->validated('note'),
            );
        } catch (CourierSubmissionBlockedException $e) {
            return back()->with('error', $e->getMessage());
        }

        $order->refresh();

        if ($order->steadfast_consignment_id !== null) {
            try {
                $info = $this->steadfast->getOrderInfo($order->steadfast_consignment_id);
                CourierOrderStatusMapper::sync($order, 'steadfast', $info['status'], source: CourierEvidenceSource::COURIER_RECONCILE_REFRESH);
            } catch (RuntimeException|ConnectionException) {
                // The consignment is recorded; its status catches up on the next refresh or webhook.
            }
        }

        return back()->with('success', 'Consignment recorded. The order is now linked to it.');
    }

    public function confirmReturnedCourierConsignment(ConfirmReturnedConsignmentRequest $request, Order $order, CourierSubmission $courierSubmission): RedirectResponse
    {
        try {
            $this->courierSubmissions->confirmReturnedConsignment($courierSubmission, Auth::guard('admin')->user(), $request->validated('note'));
        } catch (CourierSubmissionBlockedException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'The order is now linked to the consignment the courier returned.');
    }

    public function confirmCourierNotCreated(ConfirmCourierNotCreatedRequest $request, Order $order, CourierSubmission $courierSubmission): RedirectResponse
    {
        try {
            $this->courierSubmissions->confirmNotCreated($courierSubmission, Auth::guard('admin')->user(), $request->validated('note'));
        } catch (CourierSubmissionBlockedException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Recorded as not created. The order can be sent to a courier again.');
    }
}
