<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
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
    public function index(Request $request): Response
    {
        $customers = User::withCount('orders')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%")->orWhere('email', 'like', "%{$request->search}%"))
            ->latest()->paginate(20)->withQueryString();

        return Inertia::render('admin/customers/index', [
            'customers' => $customers,
            'filters' => $request->only(['search']),
        ]);
    }

    public function show(User $user): Response
    {
        return Inertia::render('admin/customers/show', [
            'customer' => $user->loadCount('orders')->load('socialAccounts'),
            'orders' => Order::where('user_id', $user->id)->latest()->limit(10)->get(['id', 'order_number', 'status', 'total', 'created_at']),
        ]);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'mother_password' => ['required', 'string'],
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

            return redirect()->route('admin.customers.index');
        }

        return redirect()->route('admin.customers.index')->with('success', 'Customer anonymized successfully. Their order and payment history has been preserved.');
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
