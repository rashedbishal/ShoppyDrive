<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\PathaoCourierService;
use App\Services\SteadfastCourierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class SettingController extends Controller
{
    public function __construct(
        private readonly PathaoCourierService $pathao,
        private readonly SteadfastCourierService $steadfast,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('admin/settings/edit', [
            'settings' => $this->settingsProp(),
        ]);
    }

    public function socialConfig(): Response
    {
        return Inertia::render('admin/settings/social', [
            'settings' => $this->settingsProp(),
        ]);
    }

    public function courierConfig(): Response
    {
        return Inertia::render('admin/settings/courier', [
            'settings' => $this->settingsProp(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'site_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_address' => ['nullable', 'string', 'max:500'],
            'currency_symbol' => ['nullable', 'string', 'max:10'],
            'logo' => $request->hasFile('logo') ? ['image', 'max:2048'] : ['nullable'],
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
            'google_client_id' => ['nullable', 'string', 'max:255'],
            'google_client_secret' => ['nullable', 'string', 'max:255'],
            'google_redirect_uri' => ['nullable', 'string', 'max:500'],
            'facebook_client_id' => ['nullable', 'string', 'max:255'],
            'facebook_client_secret' => ['nullable', 'string', 'max:255'],
            'facebook_redirect_uri' => ['nullable', 'string', 'max:500'],
            'pathao_environment' => ['nullable', 'in:sandbox,production'],
            'pathao_client_id' => ['nullable', 'string', 'max:255'],
            'pathao_client_secret' => ['nullable', 'string', 'max:255'],
            'pathao_username' => ['nullable', 'email', 'max:255'],
            'pathao_password' => ['nullable', 'string', 'max:255'],
            'pathao_store_id' => ['nullable', 'string', 'max:50'],
            'steadfast_api_key' => ['nullable', 'string', 'max:255'],
            'steadfast_secret_key' => ['nullable', 'string', 'max:255'],
            'steadfast_webhook_secret' => ['nullable', 'string', 'max:255'],
            'steadfast_webhook_url' => ['nullable', 'string', 'max:500'],
        ]);

        // Handle logo upload separately
        if ($request->hasFile('logo')) {
            $existing = Setting::where('key', 'logo')->first();
            if ($existing?->value && str_starts_with((string) $existing->value, '/storage/')) {
                Storage::disk('public')->delete(substr((string) $existing->value, strlen('/storage/')));
            }

            $path = $request->file('logo')->store('logos', 'public');
            Setting::updateOrCreate(['key' => 'logo'], ['value' => '/storage/'.$path, 'group' => 'general']);
        }

        $fields = [
            'site_name', 'contact_email', 'contact_phone', 'contact_address', 'currency_symbol',
            'social_links', 'google_analytics_id', 'meta_pixel_id',
            'whatsapp_number', 'messenger_link', 'bkash_number', 'nagad_number',
            'google_client_id', 'google_client_secret', 'google_redirect_uri',
            'facebook_client_id', 'facebook_client_secret', 'facebook_redirect_uri',
            'pathao_environment', 'pathao_client_id', 'pathao_client_secret',
            'pathao_username', 'pathao_password', 'pathao_store_id',
            'steadfast_api_key', 'steadfast_secret_key', 'steadfast_webhook_secret', 'steadfast_webhook_url',
        ];

        foreach ($fields as $key) {
            if ($request->has($key)) {
                Setting::updateOrCreate(['key' => $key], ['value' => $request->input($key), 'group' => $this->getGroup($key)]);
            }
        }

        return back()->with('success', 'Settings saved.');
    }

    /**
     * List the merchant's Pathao stores so the admin can pick one instead of
     * typing a store_id by hand (per Pathao's "Get Merchant Store Info" API).
     * Requires Client ID/Secret and account email/password to already be saved.
     */
    public function pathaoStores(): JsonResponse
    {
        try {
            $stores = $this->pathao->getStores();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['stores' => $stores]);
    }

    /**
     * Confirm the configured Steadfast Api Key/Secret Key work by fetching
     * the merchant's current balance.
     */
    public function steadfastBalance(): JsonResponse
    {
        try {
            $balance = $this->steadfast->getBalance();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($balance);
    }

    /** @return Collection<string, mixed> */
    private function settingsProp(): Collection
    {
        return Setting::all()->keyBy('key')->map(fn ($s) => $s->value);
    }

    private function getGroup(string $key): string
    {
        return match (true) {
            in_array($key, ['site_name', 'logo', 'currency_symbol']) => 'general',
            in_array($key, ['contact_email', 'contact_phone', 'contact_address']) => 'contact',
            in_array($key, ['social_links']) => 'social',
            in_array($key, ['whatsapp_number', 'messenger_link']) => 'chat_widgets',
            in_array($key, ['bkash_number', 'nagad_number']) => 'payments',
            in_array($key, ['google_client_id', 'google_client_secret', 'google_redirect_uri', 'facebook_client_id', 'facebook_client_secret', 'facebook_redirect_uri']) => 'social_login',
            in_array($key, ['pathao_environment', 'pathao_client_id', 'pathao_client_secret', 'pathao_username', 'pathao_password', 'pathao_store_id']) => 'courier',
            in_array($key, ['steadfast_api_key', 'steadfast_secret_key', 'steadfast_webhook_secret', 'steadfast_webhook_url']) => 'courier',
            default => 'integrations',
        };
    }
}
