<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\ImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AccountProfileController extends Controller
{
    /**
     * Persists name/phone/avatar from the account profile form.
     *
     * Email is intentionally never written here, even though the request
     * validates its shape: the UI exposes it read-only ("Contact support to
     * change your email") and this endpoint isn't the place to decide what
     * changing it should do (email-verification reset, notification
     * delivery) — that belongs in a dedicated, support-mediated flow.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $data = [
            'name' => $request->validated('name'),
            'phone' => $request->validated('phone'),
        ];

        if ($request->hasFile('avatar')) {
            $this->deleteStoredAvatar($user->avatar);
            $data['avatar'] = '/storage/'.app(ImageOptimizer::class)->store($request->file('avatar'), 'avatars');
        } elseif ($request->boolean('remove_avatar')) {
            $this->deleteStoredAvatar($user->avatar);
            $data['avatar'] = null;
        }

        $user->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profile updated.']);

        return back();
    }

    /**
     * Mirrors Admin\SettingController's logo-replace convention: stored
     * values are "/storage/..." paths, so the disk-relative portion is
     * whatever follows that prefix.
     */
    private function deleteStoredAvatar(?string $current): void
    {
        if ($current && str_starts_with($current, '/storage/')) {
            Storage::disk('public')->delete(substr($current, strlen('/storage/')));
        }
    }
}
