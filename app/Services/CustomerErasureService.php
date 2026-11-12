<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\PermanentDeletionRefusedException;
use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
use App\Support\MotherPasswordGate;
use App\Support\UnusedUploadRemover;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin › Security › "Permanently delete a customer": erases one customer
 * and everything tied to them, for database clean-up. The owner's decisions:
 *
 *  - Every order goes, whatever its state, with its items, history,
 *    payments, refunds, returns, coupon uses, courier submissions and —
 *    deliberately overriding DB-001's order-history protection and
 *    PAY-RETURN-001's append-only courier evidence, for this one action —
 *    its courier delivery evidence and delivery verifications. Those two
 *    tables are removed with the query builder: their models refuse
 *    deletion on purpose, and only the classes listed in
 *    CourierDeliveryEvidenceArchTest may use them.
 *  - An unfinished order (pending, processing, shipped) puts its stock back
 *    first, exactly as a cancellation would (InventoryService, idempotent,
 *    respects lost/restock-skipped orders), and every order releases its
 *    coupon use (CouponService). No emails, no loyalty changes.
 *  - Customers they referred keep their accounts (only the link is
 *    cleared); points other customers already earned stay.
 *  - The customer's avatar and review photos leave the disk after commit.
 *  - A minimal audit-trail entry remains: which admin, when, the customer's
 *    id and how much was removed — no name, email, phone or address. Their
 *    older audit-trail entries are not touched.
 *
 * Two separate mother-admin password checks, like permanent order
 * deletion: confirm() stores a single-use, 5-minute session marker bound
 * to this customer and admin; erase() spends it and checks the password
 * again. Both share MotherPasswordGate's wrong-password lockout.
 */
class CustomerErasureService
{
    public const MARKER_TTL_SECONDS = 300;

    private const SESSION_KEY = 'customer_erasure_confirmation';

    /** Orders whose stock is still out of inventory (cancelled/refunded ones already gave it back). */
    private const UNFINISHED_STATUSES = [OrderStatus::PENDING, OrderStatus::PROCESSING, OrderStatus::SHIPPED];

    public function __construct(
        private readonly InventoryService $inventory,
        private readonly CouponService $coupons,
        private readonly UnusedUploadRemover $uploads,
        private readonly ReviewImageStorage $reviewImages,
    ) {}

    /**
     * What erasing this customer would remove, for the admin to see first.
     *
     * @return array{orders: int, unfinished_orders: int, payments: int, refunds: int, returns: int, reviews: int, addresses: int, files: int}
     */
    public function summary(User $user): array
    {
        $orderIds = $this->orderIds($user);

        return [
            'orders' => count($orderIds),
            'unfinished_orders' => Order::withTrashed()->whereKey($orderIds)
                ->whereIn('status', array_map(fn (OrderStatus $status) => $status->value, self::UNFINISHED_STATUSES))->count(),
            'payments' => DB::table('payments')->whereIn('order_id', $orderIds)->count(),
            'refunds' => DB::table('order_refunds')->whereIn('order_id', $orderIds)->count(),
            'returns' => DB::table('return_requests')->where(fn ($q) => $q->where('user_id', $user->id)->orWhereIn('order_id', $orderIds))->count(),
            'reviews' => DB::table('reviews')->where(fn ($q) => $q->where('user_id', $user->id)->orWhereIn('order_id', $orderIds))->count(),
            'addresses' => DB::table('addresses')->where('user_id', $user->id)->count(),
            'files' => count($this->filesOf($user, $orderIds)),
        ];
    }

    /**
     * @throws PermanentDeletionRefusedException
     */
    public function confirm(Request $request, Admin $admin, User $user, string $password): void
    {
        $this->assertMother($admin);
        $request->session()->forget(self::SESSION_KEY);
        $this->refuseWhileLocked($admin);

        if (! MotherPasswordGate::verify($admin, $password)) {
            throw new PermanentDeletionRefusedException('The password is incorrect.');
        }

        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'expires_at' => now()->addSeconds(self::MARKER_TTL_SECONDS)->getTimestamp(),
        ]);
    }

    /**
     * @return array{orders: int, files: int}
     *
     * @throws PermanentDeletionRefusedException
     */
    public function erase(Request $request, Admin $admin, User $user, string $password): array
    {
        $this->assertMother($admin);

        // Pulled, not read: whatever happens next, this confirmation is spent.
        $marker = $request->session()->pull(self::SESSION_KEY);

        if (! is_array($marker)
            || ($marker['user_id'] ?? null) !== $user->id
            || ($marker['admin_id'] ?? null) !== $admin->id
            || ($marker['expires_at'] ?? 0) < now()->getTimestamp()) {
            throw new PermanentDeletionRefusedException('Confirm with your password first. The confirmation lasts 5 minutes and is used once.', 'confirmation');
        }

        $this->refuseWhileLocked($admin);

        if (! MotherPasswordGate::verify($admin, $password)) {
            throw new PermanentDeletionRefusedException('The password is incorrect.');
        }

        $result = DB::transaction(function () use ($user): array {
            $locked = User::withTrashed()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $orderIds = $this->orderIds($locked);

            // Stock and coupon first, while the orders still exist.
            foreach (Order::withTrashed()->whereKey($orderIds)->orderBy('id')->get() as $order) {
                if (in_array($order->status, self::UNFINISHED_STATUSES, true)) {
                    $this->inventory->restoreForOrder($order);
                }

                $this->coupons->release($order);
            }

            [$avatar, $reviewPhotos] = $this->filesOf($locked, $orderIds, split: true);
            $returnIds = DB::table('return_requests')
                ->where(fn ($q) => $q->where('user_id', $locked->id)->orWhereIn('order_id', $orderIds))
                ->pluck('id');

            DB::table('order_refunds')->where(fn ($q) => $q->whereIn('order_id', $orderIds)->orWhereIn('return_request_id', $returnIds))->delete();
            DB::table('return_requests')->whereIn('id', $returnIds)->delete();
            DB::table('payments')->whereIn('order_id', $orderIds)->delete();
            DB::table('coupon_uses')->where(fn ($q) => $q->where('user_id', $locked->id)->orWhereIn('order_id', $orderIds))->delete();
            DB::table('courier_delivery_evidence')->whereIn('order_id', $orderIds)->delete();
            DB::table('order_delivery_verifications')->whereIn('order_id', $orderIds)->delete();
            DB::table('courier_submissions')->whereIn('order_id', $orderIds)->delete();
            DB::table('meta_tracking_events')->where(fn ($q) => $q->where('user_id', $locked->id)->orWhereIn('order_id', $orderIds))->delete();
            DB::table('reviews')->where(fn ($q) => $q->where('user_id', $locked->id)->orWhereIn('order_id', $orderIds))->delete();
            DB::table('referral_point_transactions')->where('user_id', $locked->id)->delete();
            DB::table('users')->where('referred_by_id', $locked->id)->update(['referred_by_id' => null]);
            DB::table('users')->where('id', $locked->id)->update(['referral_discount_used_order_id' => null]);

            // Items and status history go with each order (cascade).
            DB::table('orders')->whereIn('id', $orderIds)->delete();

            DB::table('incomplete_orders')->where('user_id', $locked->id)->delete();
            DB::table('pending_checkouts')->where('user_id', $locked->id)->delete();
            DB::table('notifications')->where('notifiable_type', User::class)->where('notifiable_id', $locked->id)->delete();
            DB::table('sessions')->where('user_id', $locked->id)->delete();

            if (filled($locked->email)) {
                DB::table('password_reset_tokens')->where('email', $locked->email)->delete();
                DB::table('newsletter_subscribers')->where('email', $locked->email)->delete();
                DB::table('contact_messages')->where('email', $locked->email)->delete();
            }

            // Addresses, carts, wishlist, questions, votes and social logins
            // go with the account (cascade).
            DB::table('users')->where('id', $locked->id)->delete();

            // Files leave the disk only once all of the above has committed.
            $this->uploads->removeIfUnused($avatar);
            DB::afterCommit(fn () => $this->reviewImages->delete($reviewPhotos));

            return ['orders' => count($orderIds), 'files' => count(array_filter([$avatar, ...$reviewPhotos]))];
        });

        activity('admin')
            ->causedBy($admin)
            ->event('customer.erased')
            ->withProperties(['user_id' => $user->id] + $result)
            ->log("Customer #{$user->id} and all their data were permanently deleted ({$result['orders']} orders).");

        return $result;
    }

    /** @return list<int> */
    private function orderIds(User $user): array
    {
        return Order::withTrashed()->where('user_id', $user->id)->orderBy('id')->pluck('id')->all();
    }

    /**
     * The customer's uploaded files: their avatar and the photos on reviews
     * they wrote or that belong to their orders.
     *
     * @param  list<int>  $orderIds
     * @return ($split is true ? array{0: string|null, 1: list<string>} : list<string>)
     */
    private function filesOf(User $user, array $orderIds, bool $split = false): array
    {
        $avatar = is_string($user->avatar) && str_starts_with($user->avatar, '/storage/') ? $user->avatar : null;
        $photos = DB::table('reviews')
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhereIn('order_id', $orderIds))
            ->pluck('images')
            ->flatMap(fn (?string $images) => (array) json_decode((string) $images, true))
            ->filter(fn ($photo) => is_string($photo))
            ->values()
            ->all();

        return $split ? [$avatar, $photos] : array_values(array_filter([$avatar, ...$photos]));
    }

    private function assertMother(Admin $admin): void
    {
        if (! $admin->is_mother) {
            abort(403);
        }
    }

    /**
     * @throws PermanentDeletionRefusedException
     */
    private function refuseWhileLocked(Admin $admin): void
    {
        if (MotherPasswordGate::isLocked($admin)) {
            throw new PermanentDeletionRefusedException(MotherPasswordGate::lockedMessage($admin));
        }
    }
}
