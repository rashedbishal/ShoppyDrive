<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\ReturnStatus;
use App\Exceptions\CouldNotRecordRefundException;
use App\Http\Controllers\Controller;
use App\Mail\ReturnApprovedMail;
use App\Mail\ReturnRejectedMail;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Services\CourierEvidenceChain;
use App\Services\InventoryService;
use App\Services\RefundCalculator;
use App\Services\RefundService;
use App\Support\AdminListUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class ReturnController extends Controller
{
    public function index(Request $request, RefundCalculator $calculator, CourierEvidenceChain $courierEvidence): Response|RedirectResponse
    {
        $returns = ReturnRequest::with(['user:id,name,email', 'order'])
            ->when($request->search, fn ($q) => $q->whereHas('order', fn ($oq) => $oq->where('order_number', 'like', "%{$request->search}%"))
                ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$request->search}%")))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $pastLastPage = AdminListUrl::redirectIfPastLastPage($returns, $request);

        if ($pastLastPage !== null) {
            return $pastLastPage;
        }

        // PAY-RETURN-001: the real maximum (or why no refund can be recorded) for approved returns.
        $returns->getCollection()->each(function (ReturnRequest $returnRequest) use ($calculator, $courierEvidence): void {
            $order = $returnRequest->order;
            $maximum = $returnRequest->status === ReturnStatus::APPROVED && $order !== null ? $calculator->returnMaximumPaisa($order, $returnRequest) : null;
            $courier = $returnRequest->status === ReturnStatus::APPROVED && $order !== null ? $courierEvidence->eligibility($order) : null;

            $returnRequest->setAttribute('max_refund', $maximum === null ? null : RefundCalculator::toTaka($maximum));
            $returnRequest->setAttribute('refund_blocked_reasons', $courier === null || $courier['eligible'] ? [] : $courier['reasons']);
            $order?->setVisible(['id', 'order_number', 'total']);
        });

        return Inertia::render('admin/returns/index', [
            'returns' => $returns,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    /**
     * PAY-RETURN-001: locks the parent Order *before* the ReturnRequest —
     * matching ReturnRequestController::store()'s existing Order-first
     * convention, and required so this action can never deadlock against
     * markRefunded()'s full-return check, which (while already holding
     * this same Order lock) locks every sibling ReturnRequest row for the
     * order, potentially including the very row this action also locks.
     * If every return-processing action locks Order first, two such
     * actions on the same order simply serialize on that lock instead of
     * racing for each other's rows in opposite order.
     *
     * Approving restocks the exact returned quantities (see
     * InventoryService::restoreForReturnRequest()) — a return can no
     * longer be approved once its parent order has itself already been
     * cancelled or refunded directly (the admin's whole-order path),
     * since restocking against a dead order has nothing meaningful left
     * to reconcile and would only confuse the admin UI.
     */
    public function approve(ReturnRequest $returnRequest): RedirectResponse
    {
        // Locked read-then-write inside a transaction: two admins clicking
        // Approve on the same request at the same moment must not both
        // succeed (which would queue the approval email twice, or
        // double-restock). The second request blocks on the lock, then
        // re-reads the now-committed status once the first finishes and
        // correctly bails out below.
        $result = DB::transaction(function () use ($returnRequest): string {
            $lockedOrder = Order::whereKey($returnRequest->order_id)->lockForUpdate()->first();
            $fresh = ReturnRequest::whereKey($returnRequest->id)->lockForUpdate()->first();

            if ($fresh === null || $fresh->status !== ReturnStatus::PENDING) {
                return 'invalid';
            }

            if ($lockedOrder === null || in_array($lockedOrder->status, [OrderStatus::CANCELLED, OrderStatus::REFUNDED], true)) {
                return 'order_closed';
            }

            $fresh->update([
                'status' => ReturnStatus::APPROVED,
                'resolved_at' => now(),
            ]);

            app(InventoryService::class)->restoreForReturnRequest($fresh);
            $this->logReturnAction($fresh, 'return.approved', ReturnStatus::PENDING);

            if ($fresh->user?->email) {
                Mail::to($fresh->user->email)->send((new ReturnApprovedMail($fresh))->afterCommit());
            }

            return 'approved';
        }, 3);

        return match ($result) {
            'approved' => back()->with('success', 'Return request approved.'),
            'order_closed' => back()->with('error', 'This order has already been cancelled or refunded — this return request can no longer be approved.'),
            default => back()->with('error', 'Only a pending return request can be approved.'),
        };
    }

    public function reject(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $rejected = DB::transaction(function () use ($returnRequest, $validated): bool {
            // PAY-RETURN-001: locks Order first purely for lock-order
            // consistency with approve()/markRefunded() — this action
            // doesn't read or act on Order state, but keeping every
            // return-processing action on the same Order-then-ReturnRequest
            // order means none of them can ever deadlock against the others.
            Order::whereKey($returnRequest->order_id)->lockForUpdate()->first();
            $fresh = ReturnRequest::whereKey($returnRequest->id)->lockForUpdate()->first();

            if ($fresh === null || $fresh->status !== ReturnStatus::PENDING) {
                return false;
            }

            $fresh->update([
                'status' => ReturnStatus::REJECTED,
                'admin_note' => $validated['admin_note'] ?? null,
                'resolved_at' => now(),
            ]);
            $this->logReturnAction($fresh, 'return.rejected', ReturnStatus::PENDING);

            if ($fresh->user?->email) {
                Mail::to($fresh->user->email)->send((new ReturnRejectedMail($fresh))->afterCommit());
            }

            return true;
        });

        if (! $rejected) {
            return back()->with('error', 'Only a pending return request can be rejected.');
        }

        return back()->with('success', 'Return request rejected.');
    }

    /**
     * Records that the admin has already refunded the customer through
     * whatever channel they actually used (bKash send-money, bank transfer,
     * etc.) — this app has no live payment-provider refund API for any of
     * its payment methods (COD, mobile banking, ZiniPay), so there is
     * nothing here that could move money automatically. Mirrors the same
     * manual-record pattern Admin\OrderController::markRefunded() already
     * uses for direct order refunds. This action only ever *records* a
     * refund the admin confirms already happened elsewhere — it never
     * triggers one, and the customer-facing "Refunded" status only ever
     * appears after an admin has called this.
     */
    public function markRefunded(Request $request, ReturnRequest $returnRequest, RefundService $refunds): RedirectResponse
    {
        // PAY-RETURN-001: the ceiling (discounted value of the returned units,
        // within what the customer actually paid excluding shipping, and the
        // order's shared refund ledger), payment collection and courier
        // verification are all checked under the order lock by RefundService.
        $validated = $request->validate([
            'refund_amount' => ['required', 'numeric', 'min:0'],
            'refund_method' => ['required', 'string', 'max:100'],
        ]);

        try {
            $refunds->recordReturnRefund(Auth::guard('admin')->user(), $returnRequest, $validated['refund_amount'], $validated['refund_method']);
        } catch (CouldNotRecordRefundException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Return marked as refunded.');
    }

    private function logReturnAction(ReturnRequest $returnRequest, string $event, ReturnStatus $previous): void
    {
        activity('orders')
            ->causedBy(Auth::guard('admin')->user())
            ->performedOn($returnRequest)
            ->event($event)
            ->withProperties([
                'order_id' => $returnRequest->order_id,
                'return_request_id' => $returnRequest->id,
                'previous_status' => $previous->value,
                'resulting_status' => $returnRequest->status->value,
            ])
            ->log("Return request #{$returnRequest->id} {$returnRequest->status->value}.");
    }
}
