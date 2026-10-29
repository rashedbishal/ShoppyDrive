<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Mail\ReturnApprovedMail;
use App\Mail\ReturnRejectedMail;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class ReturnController extends Controller
{
    public function index(Request $request): Response
    {
        $returns = ReturnRequest::with(['user:id,name,email', 'order:id,order_number,total'])
            ->when($request->search, fn ($q) => $q->whereHas('order', fn ($oq) => $oq->where('order_number', 'like', "%{$request->search}%"))
                ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$request->search}%")))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

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
    public function markRefunded(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        // The ceiling here is the value of the returned line items only
        // (unit price × returned quantity, from the order's own item
        // prices) — it deliberately does NOT add back shipping or deduct
        // a restocking fee, since no such policy exists anywhere in this
        // app. If the business wants shipping refunded or a restocking fee
        // withheld, that's a policy decision to make explicitly, not one
        // to infer here — this cap only guards against a fat-finger amount
        // that exceeds what the returned goods were even worth.
        $maxRefund = $this->eligibleRefundAmount($returnRequest);

        $validated = $request->validate([
            'refund_amount' => ['required', 'numeric', 'min:0', 'max:'.$maxRefund],
            'refund_method' => ['required', 'string', 'max:100'],
        ]);

        $refunded = DB::transaction(function () use ($returnRequest, $validated): bool {
            // PAY-RETURN-001: Order locked first (see approve()'s
            // docblock) — this is what makes the full-return check below,
            // and the possible Order.status transition that follows it,
            // race-safe against a sibling ReturnRequest being refunded
            // concurrently on the same order.
            $lockedOrder = Order::whereKey($returnRequest->order_id)->lockForUpdate()->first();
            $fresh = ReturnRequest::whereKey($returnRequest->id)->lockForUpdate()->first();

            if ($fresh === null || $fresh->status !== ReturnStatus::APPROVED || $lockedOrder === null) {
                return false;
            }

            $fresh->update([
                'status' => ReturnStatus::REFUNDED,
                'refund_amount' => $validated['refund_amount'],
                'refund_method' => $validated['refund_method'],
                'resolved_at' => now(),
            ]);

            // PAY-RETURN-001: once every purchased unit on the order is
            // refunded (across every return request, not just this one),
            // transition the order itself to REFUNDED — never for a
            // partial return. The existing Order::booted() listener then
            // handles coupon release, referral-reward revocation, and
            // redeemed-points refund on its own; none of that is
            // duplicated here.
            if (ReturnRequest::isOrderFullyReturned($lockedOrder)) {
                $lockedOrder->update(['status' => OrderStatus::REFUNDED]);
            }

            return true;
        }, 3);

        if (! $refunded) {
            return back()->with('error', 'Only an approved return request can be marked as refunded.');
        }

        return back()->with('success', 'Return marked as refunded.');
    }

    /**
     * Deliberately doesn't trust `$returnRequest->items` to already be
     * well-formed, even though the only path that creates these rows
     * (ReturnRequestController::store()) already validates it: a
     * duplicated order_item_id line, or a stored quantity larger than
     * what was actually purchased, must never be able to inflate this
     * ceiling. Aggregates by order_item_id first, then caps each at that
     * item's real purchased quantity — the hard physical maximum.
     */
    private function eligibleRefundAmount(ReturnRequest $returnRequest): float
    {
        $orderItemsById = $returnRequest->order->items->keyBy('id');

        return collect($returnRequest->items)
            ->filter(fn (array $item) => isset($item['order_item_id'], $item['quantity']) && $orderItemsById->has($item['order_item_id']))
            ->groupBy('order_item_id')
            ->map(fn ($group) => $group->sum('quantity'))
            ->map(function (int $quantity, int $orderItemId) use ($orderItemsById): float {
                $orderItem = $orderItemsById->get($orderItemId);

                return (float) $orderItem->price * max(0, min($quantity, $orderItem->quantity));
            })
            ->sum();
    }
}
