<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthController extends Controller
{
    public function showLogin(): Response|\Inertia\Response
    {
        if (Auth::guard('admin')->check()) {
            return Inertia::location(route('admin.dashboard'));
        }

        return Inertia::render('auth/admin-login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Invalid admin credentials.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        $admin = Auth::guard('admin')->user();

        activity('admin')
            ->causedBy($admin)
            ->event('login')
            ->log("Admin {$admin->name} ({$admin->email}) logged in.");

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        activity('admin')
            ->causedBy($admin)
            ->event('logout')
            ->log("Admin {$admin->name} ({$admin->email}) logged out.");

        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // SEC-021: must come after invalidate() above — that flushes all
        // session attributes, so calling this any earlier (e.g. from a
        // Logout event listener, which fires before invalidate() here)
        // would just get wiped before the next request ever reads it.
        Inertia::clearHistory();

        return redirect()->route('admin.login');
    }
}
