<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\PermanentDeletionRefusedException;
use App\Models\Admin;
use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\OrderDeletionAudit;
use App\Support\MotherPasswordGate;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * DB-001 (L7): permanently deleting an order takes two separate requests by
 * the mother admin, both verified on the server.
 *
 *  1. confirm(): password checked (Hash::check via MotherPasswordGate); on success a session
 *     marker is stored, bound to this order id and this admin id, valid for
 *     MARKER_TTL_SECONDS.
 *  2. delete(): the marker is pulled (so it is single-use and invalidated on
 *     every outcome), must match the same order and admin and be unexpired;
 *     the password is checked again and the exact order number must be
 *     typed. Eligibility is then re-checked under the order's row lock and
 *     the order deleted, with its audit snapshot, in the same transaction.
 *
 * Every attempt — confirmed, deleted, refused or failed — is recorded in
 * order_deletion_audits. Passwords are never stored, logged or flashed.
 */
class OrderPermanentDeletionService
{
    public const MARKER_TTL_SECONDS = 300;

    private const SESSION_KEY = 'order_permanent_deletion_confirmation';

    public function __construct(private readonly OrderDeletionEligibility $eligibility) {}

    /**
     * @throws PermanentDeletionRefusedException
     */
    public function confirm(Request $request, Admin $admin, Order $order, string $password): void
    {
        $this->assertMother($admin);
        $request->session()->forget(self::SESSION_KEY);
        $this->refuseWhileLocked($request, $admin, $order, 'confirm');

        if (! MotherPasswordGate::verify($admin, $password)) {
            $this->audit($request, $admin, $order, 'confirm', 'wrong_password');

            throw new PermanentDeletionRefusedException('The password is incorrect.');
        }

        $reasons = $this->eligibility->check($order->id);

        if ($reasons !== []) {
            $this->audit($request, $admin, $order, 'confirm', 'refused_ineligible', $reasons);

            throw new PermanentDeletionRefusedException($this->refusalMessage($reasons), 'order');
        }

        $request->session()->put(self::SESSION_KEY, [
            'order_id' => $order->id,
            'admin_id' => $admin->id,
            'expires_at' => now()->addSeconds(self::MARKER_TTL_SECONDS)->getTimestamp(),
        ]);

        $this->audit($request, $admin, $order, 'confirm', 'confirmed');
    }

    /**
     * @throws PermanentDeletionRefusedException
     */
    public function delete(Request $request, Admin $admin, Order $order, string $password, string $typedOrderNumber): void
    {
        $this->assertMother($admin);

        // Pulled, not read: whatever happens next, this marker is spent.
        // (The routes use session blocking, so two concurrent requests
        // can't both read it before it is removed.)
        $marker = $request->session()->pull(self::SESSION_KEY);
        $this->refuseWhileLocked($request, $admin, $order, 'delete');
        $markerProblem = match (true) {
            ! is_array($marker) => 'missing',
            (int) ($marker['admin_id'] ?? 0) !== $admin->id => 'other_admin',
            (int) ($marker['order_id'] ?? 0) !== $order->id => 'other_order',
            (int) ($marker['expires_at'] ?? 0) < now()->getTimestamp() => 'expired',
            default => null,
        };

        if ($markerProblem !== null) {
            $this->audit($request, $admin, $order, 'delete', 'invalid_confirmation', [['code' => "confirmation_{$markerProblem}", 'message' => 'No valid first-step password confirmation for this order.']]);

            throw new PermanentDeletionRefusedException('Your first password confirmation for this order is missing or has expired. Start again from the first step.', 'confirmation');
        }

        if (! MotherPasswordGate::verify($admin, $password)) {
            $this->audit($request, $admin, $order, 'delete', 'wrong_password');

            throw new PermanentDeletionRefusedException('The password is incorrect. Start again from the first step.');
        }

        if ($typedOrderNumber !== $order->order_number) {
            $this->audit($request, $admin, $order, 'delete', 'order_number_mismatch');

            throw new PermanentDeletionRefusedException('The order number does not match. Start again from the first step.', 'order_number');
        }

        try {
            $reasons = DB::transaction(function () use ($request, $admin, $order): array {
                $locked = Order::withTrashed()->whereKey($order->id)->lockForUpdate()->first();

                if ($locked === null) {
                    return [['code' => 'not_found', 'message' => 'The order no longer exists.']];
                }

                $reasons = $this->eligibility->reasonsForLockedOrder($locked);

                if ($reasons !== []) {
                    return $reasons;
                }

                $snapshot = $this->snapshot($locked);
                $locked->forceDelete();
                $this->audit($request, $admin, null, 'delete', 'deleted', null, $snapshot);

                return [];
            }, 3);
        } catch (QueryException $e) {
            $this->audit($request, $admin, $order, 'delete', 'refused_database_constraint', [['code' => 'database_constraint', 'message' => 'The database refused the deletion because related history still exists.']]);

            throw new PermanentDeletionRefusedException('The database refused to delete this order because related history still exists.', 'order');
        }

        if ($reasons !== []) {
            $this->audit($request, $admin, $order, 'delete', 'refused_ineligible', $reasons);

            throw new PermanentDeletionRefusedException($this->refusalMessage($reasons), 'order');
        }
    }

    /**
     * The current, unexpired first-step confirmation of this admin, if any.
     *
     * @return array{order_id: int, expires_at: int}|null
     */
    public function pendingConfirmation(Request $request, Admin $admin): ?array
    {
        $marker = $request->session()->get(self::SESSION_KEY);

        if (! is_array($marker) || (int) ($marker['admin_id'] ?? 0) !== $admin->id || (int) ($marker['expires_at'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        return ['order_id' => (int) $marker['order_id'], 'expires_at' => (int) $marker['expires_at']];
    }

    /**
     * For the rate limiter's refusal response, which runs before the controller.
     */
    public function recordRateLimited(Request $request, Admin $admin, Order $order, string $step): void
    {
        $request->session()->forget(self::SESSION_KEY);
        $this->audit($request, $admin, $order, $step, 'rate_limited');
    }

    /**
     * @throws PermanentDeletionRefusedException
     */
    private function refuseWhileLocked(Request $request, Admin $admin, Order $order, string $step): void
    {
        if (MotherPasswordGate::isLocked($admin)) {
            $this->audit($request, $admin, $order, $step, 'rate_limited');

            throw new PermanentDeletionRefusedException(MotherPasswordGate::lockedMessage($admin));
        }
    }

    private function assertMother(Admin $admin): void
    {
        if (! $admin->is_mother) {
            abort(403, 'Only the mother admin can permanently delete orders.');
        }
    }

    /**
     * @param  list<array{code: string, message: string}>  $reasons
     */
    private function refusalMessage(array $reasons): string
    {
        return 'This order cannot be permanently deleted: '.implode(' ', array_column($reasons, 'message'));
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Order $order): array
    {
        return [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'customer_id' => $order->user_id,
            'order_status' => $order->status?->value,
            'payment_status' => $order->payment_status?->value,
            'payment_method' => $order->payment_method?->value,
            'subtotal' => $order->subtotal,
            'discount' => $order->discount,
            'shipping_cost' => $order->shipping_cost,
            'total' => $order->total,
            'item_count' => (int) $order->items()->sum('quantity'),
            'order_created_at' => $order->created_at,
            'order_deleted_at' => $order->deleted_at,
            // The inventory_logs rows themselves are never deleted (no FK to
            // orders); this copy keeps them tied to the deleted order's record.
            'inventory_movements' => InventoryLog::where('reference_type', Order::class)
                ->where('reference_id', $order->id)
                ->orderBy('id')
                ->get(['id', 'product_id', 'variant_id', 'type', 'quantity', 'created_at'])
                ->map(fn (InventoryLog $log) => [
                    'inventory_log_id' => $log->id,
                    'product_id' => $log->product_id,
                    'variant_id' => $log->variant_id,
                    'type' => $log->type->value,
                    'quantity' => $log->quantity,
                    'logged_at' => $log->created_at?->toIso8601String(),
                ])->all(),
        ];
    }

    /**
     * @param  list<array{code: string, message: string}>|null  $reasons
     * @param  array<string, mixed>|null  $snapshot
     */
    private function audit(Request $request, Admin $admin, ?Order $order, string $step, string $outcome, ?array $reasons = null, ?array $snapshot = null): void
    {
        OrderDeletionAudit::create([
            ...($snapshot ?? ($order !== null ? $this->snapshot($order) : [])),
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'admin_email' => $admin->email,
            'step' => $step,
            'outcome' => $outcome,
            'refusal_reasons' => $reasons,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);
    }
}
