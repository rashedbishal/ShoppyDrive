<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function edit(): Response
    {
        $settings = Setting::all()->keyBy('key')->map(fn ($s) => $s->value);

        return Inertia::render('admin/settings/edit', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'site_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'currency_symbol' => ['nullable', 'string', 'max:10'],
            'logo' => ['nullable', 'string'],
            'social_links' => ['nullable', 'array'],
            'social_links.facebook' => ['nullable', 'string', 'max:500'],
            'social_links.instagram' => ['nullable', 'string', 'max:500'],
            'social_links.twitter' => ['nullable', 'string', 'max:500'],
            'google_analytics_id' => ['nullable', 'string', 'max:100'],
            'meta_pixel_id' => ['nullable', 'string', 'max:100'],
            'whatsapp_number' => ['nullable', 'string', 'max:20'],
            'messenger_link' => ['nullable', 'string', 'max:500'],
            'bkash_number' => ['nullable', 'string', 'max:20'],
            'nagad_number' => ['nullable', 'string', 'max:20'],
        ]);

        $fields = [
            'site_name', 'contact_email', 'contact_phone', 'currency_symbol',
            'logo', 'social_links', 'google_analytics_id', 'meta_pixel_id',
            'whatsapp_number', 'messenger_link', 'bkash_number', 'nagad_number',
        ];

        foreach ($fields as $key) {
            if ($request->has($key)) {
                Setting::updateOrCreate(['key' => $key], ['value' => $request->input($key), 'group' => $this->getGroup($key)]);
            }
        }

        return back()->with('success', 'Settings saved.');
    }

    private function getGroup(string $key): string
    {
        return match (true) {
            in_array($key, ['site_name', 'logo', 'currency_symbol']) => 'general',
            in_array($key, ['contact_email', 'contact_phone']) => 'contact',
            in_array($key, ['social_links']) => 'social',
            in_array($key, ['whatsapp_number', 'messenger_link']) => 'chat_widgets',
            in_array($key, ['bkash_number', 'nagad_number']) => 'payments',
            default => 'integrations',
        };
    }
}
