<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

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

        // Hard delete: orders.user_id and social_accounts.user_id both cascade
        // on delete at the DB level, so this removes everything atomically
        // and frees the email for reuse (a soft delete would leave both
        // dangling, which is what caused the social-login 500s).
        $user->forceDelete();

        return redirect()->route('admin.customers.index')->with('success', 'Customer deleted successfully.');
    }
}
