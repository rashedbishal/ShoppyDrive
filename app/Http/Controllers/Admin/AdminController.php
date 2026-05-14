<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function index(): Response
    {
        $admins = Admin::orderByDesc('is_mother')->orderBy('created_at')->get(['id', 'name', 'email', 'is_mother', 'created_at']);

        return Inertia::render('admin/admins/index', [
            'admins' => $admins,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/admins/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        Admin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('admin.admins.index')->with('success', 'Admin account created successfully.');
    }

    public function edit(Admin $admin): Response
    {
        $currentAdmin = Auth::guard('admin')->user();

        if ($admin->is_mother && $currentAdmin->id !== $admin->id) {
            abort(403, 'The mother admin account cannot be modified by other admins.');
        }

        return Inertia::render('admin/admins/edit', [
            'adminUser' => $admin->only(['id', 'name', 'email', 'is_mother']),
        ]);
    }

    public function update(Request $request, Admin $admin): RedirectResponse
    {
        $currentAdmin = Auth::guard('admin')->user();

        if ($admin->is_mother && $currentAdmin->id !== $admin->id) {
            abort(403, 'The mother admin account cannot be modified by other admins.');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email,'.$admin->id],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $admin->update($data);

        return redirect()->route('admin.admins.index')->with('success', 'Admin account updated successfully.');
    }

    public function destroy(Admin $admin): RedirectResponse
    {
        $currentAdmin = Auth::guard('admin')->user();

        if ($admin->id === $currentAdmin->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($admin->is_mother) {
            return back()->with('error', 'The mother admin account cannot be deleted.');
        }

        $admin->delete();

        return redirect()->route('admin.admins.index')->with('success', 'Admin account deleted successfully.');
    }
}
