<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Order;
use App\Models\ReferralPointTransaction;
use App\Models\User;
use App\Services\LoyaltyPointsService;
use App\Support\AdminListUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CustomerController extends Controller
{
    /** A customer who ordered within this many days counts as active. */
    private const ACTIVE_ORDER_DAYS = 90;

    /** A customer who joined within this many days counts as active even without an order. */
    private const NEW_ACCOUNT_DAYS = 30;

    public function index(Request $request): Response|RedirectResponse
    {
        $search = trim((string) $request->input('search'));
        $status = in_array($request->input('status'), ['active', 'inactive', 'deleted'], true) ? $request->input('status') : '';
        $verified = in_array($request->input('verified'), ['yes', 'no'], true) ? $request->input('verified') : '';
        $orders = in_array($request->input('orders'), ['with', 'none'], true) ? $request->input('orders') : '';

        $customers = User::withCount('orders')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->when($status !== '', fn ($q) => $this->applyStatusFilter($q, $status))
            ->when($verified === 'yes', fn ($q) => $q->whereNotNull('email_verified_at'))
            ->when($verified === 'no', fn ($q) => $q->whereNull('email_verified_at'))
            ->when($orders === 'with', fn ($q) => $q->has('orders'))
            ->when($orders === 'none', fn ($q) => $q->doesntHave('orders'))
            ->latest()->paginate(20)->withQueryString();

        $pastLastPage = AdminListUrl::redirectIfPastLastPage($customers, $request);

        if ($pastLastPage !== null) {
            return $pastLastPage;
        }

        return Inertia::render('admin/customers/index', [
            'customers' => $customers,
            'filters' => $request->only(['search', 'status', 'verified', 'orders']),
        ]);
    }

    /**
     * Customer status. There is no on/off switch on an account, so it is read
     * from what the account has done: "deleted" is an anonymized or removed
     * account; "active" is a live account that ordered in the last 90 days or
     * joined in the last 30; "inactive" is a live account that did neither.
     *
     * @param  Builder<User>  $query
     */
    private function applyStatusFilter(Builder $query, string $status): void
    {
        if ($status === 'deleted') {
            $query->withTrashed()->where(fn ($q) => $q->whereNotNull('anonymized_at')->orWhereNotNull('deleted_at'));

            return;
        }

        $recentlyJoined = now()->subDays(self::NEW_ACCOUNT_DAYS);
        $recentOrder = fn ($q) => $q->where('created_at', '>=', now()->subDays(self::ACTIVE_ORDER_DAYS));

        $query->whereNull('anonymized_at');

        if ($status === 'active') {
            $query->where(fn ($q) => $q->whereHas('orders', $recentOrder)->orWhere('created_at', '>=', $recentlyJoined));

            return;
        }

        $query->whereDoesntHave('orders', $recentOrder)->where('created_at', '<', $recentlyJoined);
    }

    public function show(User $user, LoyaltyPointsService $loyalty): Response
    {
        return Inertia::render('admin/customers/show', [
            'customer' => [
                ...$user->loadCount('orders')->load('socialAccounts')->toArray(),
                // anonymization_snapshot itself is $hidden (a raw password
                // hash plus redacted PII) — only whether one exists at all
                // reaches the frontend, to decide if Restore can be offered.
                'can_restore' => $user->anonymized_at !== null && $user->anonymization_snapshot !== null,
            ],
            'orders' => Order::where('user_id', $user->id)->latest()->limit(10)->get(['id', 'order_number', 'status', 'total', 'created_at']),
            'points' => $this->pointsSummary($user, $loyalty),
        ]);
    }

    /**
     * DISC-13: read-only reward points for support staff. The spendable
     * figure is the same LoyaltyPointsService::availablePoints() checkout
     * uses; nothing here changes points.
     *
     * @return array{balance: int, spendable: int, history: list<array{id: int, date: string|null, type: string, label: string, points: int, note: string|null, order_number: string|null}>}
     */
    private function pointsSummary(User $user, LoyaltyPointsService $loyalty): array
    {
        $history = $user->pointTransactions()
            ->with(['order' => fn ($query) => $query->withTrashed()->select(['id', 'order_number'])])
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (ReferralPointTransaction $row): array => [
                'id' => $row->id,
                'date' => $row->created_at?->toIso8601String(),
                'type' => $row->type->value,
                'label' => self::POINT_TYPE_LABELS[$row->type->value] ?? $row->type->value,
                'points' => (int) $row->points,
                'note' => $row->note,
                'order_number' => $row->order?->order_number,
            ])
            ->all();

        return [
            'balance' => (int) $user->points_balance,
            'spendable' => $loyalty->availablePoints($user),
            'history' => $history,
        ];
    }

    /** @var array<string, string> */
    private const POINT_TYPE_LABELS = [
        'earn_order' => 'Earned from order',
        'earn_referral' => 'Referral reward',
        'earn_registration' => 'Signup bonus',
        'earn_review' => 'Review bonus',
        'earn_daily_login' => 'Daily login',
        'redeem' => 'Redeemed at checkout',
        'revoke' => 'Referral reward reversed',
        'refund' => 'Redeemed points refunded',
        'revoke_order' => 'Order points reversed (refund)',
        'refund_partial' => 'Redeemed points refunded (partial return)',
    ];

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'mother_password' => ['required', 'string', 'max:255'],
        ]);

        $mother = Admin::where('is_mother', true)->first();

        if (! $mother || ! Hash::check($request->string('mother_password'), $mother->password)) {
            return back()->withErrors(['mother_password' => 'Incorrect mother admin password.']);
        }

        $avatarToClean = $user->avatar;

        if (! $user->anonymize(Auth::guard('admin')->user())) {
            return back()->with('error', 'This customer has already been anonymized.');
        }

        $avatarWarning = $this->cleanUpAvatar($avatarToClean, $user->id);

        if ($avatarWarning !== null) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => $avatarWarning]);

            return AdminListUrl::redirect('admin.customers.index');
        }

        return AdminListUrl::redirect('admin.customers.index')->with('success', 'Customer anonymized successfully. Their order and payment history has been preserved.');
    }

    /**
     * Undo a mistaken anonymize() — restores everything it captured before
     * overwriting/deleting it. Same mother-password gate as destroy(),
     * since this reactivates the customer's full PII and login ability.
     */
    public function restore(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'mother_password' => ['required', 'string', 'max:255'],
        ]);

        $mother = Admin::where('is_mother', true)->first();

        if (! $mother || ! Hash::check($request->string('mother_password'), $mother->password)) {
            return back()->withErrors(['mother_password' => 'Incorrect mother admin password.']);
        }

        if (! $user->restoreFromAnonymization(Auth::guard('admin')->user())) {
            return back()->with('error', 'This customer cannot be restored (not anonymized, or anonymized before this feature existed).');
        }

        return redirect()->route('admin.customers.show', $user)->with('success', 'Customer restored successfully.');
    }

    /**
     * Delete the customer's avatar file from the public disk, strictly after
     * the anonymization transaction has already committed — a database
     * transaction cannot roll back a filesystem delete, so this runs as a
     * separate, best-effort step. Returns a user-facing warning message if
     * cleanup failed (or null if it succeeded or there was nothing to do);
     * either way, the anonymization itself is already complete.
     */
    private function cleanUpAvatar(?string $stored, int $userId): ?string
    {
        $relativePath = $this->safeAvatarDiskPath($stored);

        if ($relativePath === null) {
            return null;
        }

        try {
            if (Storage::disk('public')->exists($relativePath)
                && ! Storage::disk('public')->delete($relativePath)) {
                throw new \RuntimeException("Avatar delete() returned false for user #{$userId}, path: {$relativePath}");
            }

            return null;
        } catch (Throwable $e) {
            report($e);

            return 'Customer anonymized successfully — access has been revoked and order history preserved. However, their old avatar image could not be removed from storage and may need manual cleanup.';
        }
    }

    /**
     * Restricts avatar cleanup to exactly the directory AccountProfileController
     * ever stores avatars under, and rejects any path-traversal segment —
     * stricter than AccountProfileController::deleteStoredAvatar()'s own
     * "/storage/" prefix check, since this path isn't tied to the request
     * that uploaded it.
     */
    private function safeAvatarDiskPath(?string $stored): ?string
    {
        if (! $stored || ! str_starts_with($stored, '/storage/avatars/')) {
            return null;
        }

        $relative = substr($stored, strlen('/storage/'));

        if (str_contains($relative, '..')) {
            return null;
        }

        return $relative;
    }
}
