<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exceptions\PermanentDeletionRefusedException;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\User;
use App\Services\CustomerErasureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Admin › Security › "Permanently delete a customer" (CustomerErasureService):
 * find a customer, see what would be removed, confirm with the mother
 * admin's password, then confirm again with it to delete. Mother admin
 * only, enforced by the `admin.mother` route group.
 */
class CustomerErasureController extends Controller
{
    private const SEARCH_LIMIT = 20;

    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->validate(['q' => ['nullable', 'string', 'max:100']])['q']);
        $like = '%'.addcslashes($term, '%_\\').'%';

        $customers = User::withTrashed()
            ->when($term !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('id', ctype_digit($term) ? (int) $term : 0)))
            ->withCount(['orders' => fn ($query) => $query->withTrashed()])
            // A typed customer number lists that customer first, ahead of
            // others whose phone or email merely contains the digits.
            ->when(ctype_digit($term), fn ($query) => $query->orderByRaw('id = ? desc', [(int) $term]))
            ->latest('id')
            ->limit(self::SEARCH_LIMIT)
            ->get(['id', 'name', 'email', 'phone', 'anonymized_at', 'deleted_at'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'orders_count' => $user->orders_count,
                'anonymized' => $user->anonymized_at !== null,
            ]);

        return response()->json(['customers' => $customers]);
    }

    public function summary(int $user, CustomerErasureService $erasure): JsonResponse
    {
        return response()->json($erasure->summary($this->customer($user)));
    }

    public function confirm(Request $request, int $user, CustomerErasureService $erasure): JsonResponse
    {
        $password = (string) $request->validate(['password' => ['required', 'string']])['password'];

        try {
            $erasure->confirm($request, $this->motherAdmin(), $this->customer($user), $password);
        } catch (PermanentDeletionRefusedException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => [$e->field => [$e->getMessage()]]], 422);
        }

        return response()->json(['confirmed' => true, 'expires_in' => CustomerErasureService::MARKER_TTL_SECONDS]);
    }

    public function destroy(Request $request, int $user, CustomerErasureService $erasure): RedirectResponse
    {
        $password = (string) $request->validate(['password' => ['required', 'string']])['password'];
        $customer = $this->customer($user);

        try {
            $result = $erasure->erase($request, $this->motherAdmin(), $customer, $password);
        } catch (PermanentDeletionRefusedException $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }

        return back()->with('success', "Customer #{$user} and all their data were permanently deleted ({$result['orders']} ".($result['orders'] === 1 ? 'order' : 'orders').", {$result['files']} ".($result['files'] === 1 ? 'file' : 'files').').');
    }

    private function customer(int $id): User
    {
        return User::withTrashed()->findOrFail($id);
    }

    private function motherAdmin(): Admin
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        return $admin;
    }
}
