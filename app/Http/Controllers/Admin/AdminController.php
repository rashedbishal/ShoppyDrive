<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateAdminSession;
use App\Models\Admin;
use App\Support\AdminSections;
use App\Support\PasswordPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
            // DISC-33: admins need 12+ characters; overrides the shared customer list.
            'password_requirements' => PasswordPolicy::adminRequirements(),
            'sectionCatalog' => AdminSections::grouped(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeMotherOnly('create new admin accounts');

        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:admins,email'],
            'password' => ['required', 'string', 'max:255', 'confirmed', PasswordPolicy::adminRule()],
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
            'password_requirements' => PasswordPolicy::adminRequirements(),
            'adminUser' => $admin->only(['id', 'name', 'email', 'is_mother', 'permissions']),
            'sectionCatalog' => AdminSections::grouped(),
        ]);
    }

    public function update(Request $request, Admin $admin): RedirectResponse
    {
        $this->authorizeManageTarget($admin);

        $currentAdmin = Auth::guard('admin')->user();
        $isSelfEdit = $currentAdmin->id === $admin->id;

        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:admins,email,'.$admin->id],
            'password' => ['nullable', 'string', 'max:255', 'confirmed', PasswordPolicy::adminRule()],
            // SEC-010: a left-open or hijacked session could otherwise
            // change its own password with nothing proving it's really
            // the account holder — same requirement the customer-facing
            // password change already has (PasswordUpdateRequest).
            // Resetting a *different* admin's password (the mother's
            // privileged action, not a self-edit) is unaffected.
            'current_password' => $isSelfEdit && $request->filled('password')
                ? ['required', 'string', 'current_password:admin']
                : ['sometimes'],
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

            // SEC-005: a stolen or shared-computer session kept working
            // after a password change, including via a remember-me cookie.
            // AuthenticateAdminSession (routes/admin.php) stops the session
            // side on its next request for every *other* session — but
            // when $admin is editing their own password, their current
            // request's session already stored the old hash earlier in
            // this same request, so without this it would also log itself
            // out. setRememberToken() isn't mass-assignable (by design),
            // so it's set directly rather than through $data above.
            $admin->setRememberToken(Str::random(60));
            $admin->save();

            if ($isSelfEdit) {
                $request->session()->put(AuthenticateAdminSession::SESSION_KEY, [
                    'id' => $admin->getAuthIdentifier(),
                    'hash' => $admin->password,
                ]);
            }
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
