<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Support\AdminSections;
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
        $this->authorizeMotherOnly('create new admin accounts');

        return Inertia::render('admin/admins/create', [
            'sectionCatalog' => AdminSections::grouped(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeMotherOnly('create new admin accounts');

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'permissions' => ['nullable', 'array'],
        ]);

        Admin::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'permissions' => AdminSections::sanitize($request->input('permissions', [])),
        ]);

        return redirect()->route('admin.admins.index')->with('success', 'Admin account created successfully.');
    }

    public function edit(Admin $admin): Response
    {
        $this->authorizeManageTarget($admin);

        return Inertia::render('admin/admins/edit', [
            'adminUser' => $admin->only(['id', 'name', 'email', 'is_mother', 'permissions']),
            'sectionCatalog' => AdminSections::grouped(),
        ]);
    }

    public function update(Request $request, Admin $admin): RedirectResponse
    {
        $this->authorizeManageTarget($admin);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email,'.$admin->id],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'permissions' => ['nullable', 'array'],
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        // Only the mother admin may change section permissions, and never
        // the mother's own (which has no meaning — she always has full
        // access) — so a non-mother editing their own account can never
        // grant themselves access this way, mirroring is_mother's mass
        // assignment protection.
        $currentAdmin = Auth::guard('admin')->user();
        if ($currentAdmin->is_mother && ! $admin->is_mother) {
            $permissions = AdminSections::sanitize($request->input('permissions', []));

            // A never-granted admin stores null, and saving the form with
            // nothing ticked is not a change worth an Audit Trail entry.
            if (($admin->permissions ?? []) != $permissions) {
                $data['permissions'] = $permissions;
            }
        }

        $admin->update($data);

        if ($admin->wasChanged('password')) {
            $admin->logPasswordChange();
        }

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

        if (! $currentAdmin->is_mother) {
            return back()->with('error', 'Only the mother admin can delete other admin accounts.');
        }

        $admin->delete();

        return redirect()->route('admin.admins.index')->with('success', 'Admin account deleted successfully.');
    }

    /**
     * Only the mother admin may create, or manage other admins' accounts —
     * this app has no intermediate role tier (see Admin::$fillable's
     * is_mother note), so "mother manages everyone, everyone else manages
     * only themselves" is the only policy the existing schema actually
     * expresses, not a newly invented one.
     */
    private function authorizeMotherOnly(string $action): void
    {
        $currentAdmin = Auth::guard('admin')->user();

        if (! $currentAdmin->is_mother) {
            abort(403, "Only the mother admin can {$action}.");
        }
    }

    /**
     * Any admin may view/edit their own account. Editing someone else's
     * requires being the mother admin, and the mother's own account can
     * never be edited by anyone but herself — preserved from the
     * pre-existing check.
     */
    private function authorizeManageTarget(Admin $target): void
    {
        $currentAdmin = Auth::guard('admin')->user();

        if ($currentAdmin->id === $target->id) {
            return;
        }

        if ($target->is_mother) {
            abort(403, 'The mother admin account cannot be modified by other admins.');
        }

        if (! $currentAdmin->is_mother) {
            abort(403, 'Only the mother admin can edit other admin accounts.');
        }
    }
}
