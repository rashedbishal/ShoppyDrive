<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\DisplayOrderSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DisplayOrderSettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/settings/display-order', [
            'scenarios' => DisplayOrderSettings::SCENARIOS,
            'orders' => DisplayOrderSettings::ORDERS,
            'values' => DisplayOrderSettings::all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        // Allow-listed to exactly the known scenarios/orders, the same way
        // AdminSections::sanitize() allow-lists a permissions payload — a
        // crafted extra key here can never create or write an unrelated
        // Setting row.
        $validated = $request->validate([
            'values' => ['required', 'array'],
            ...collect(DisplayOrderSettings::SCENARIOS)->keys()->mapWithKeys(
                fn (string $scenario): array => ["values.{$scenario}" => ['required', Rule::in(DisplayOrderSettings::ORDERS)]]
            )->all(),
        ]);

        foreach ($validated['values'] as $scenario => $order) {
            Setting::updateOrCreate(
                ['key' => "display_order.{$scenario}"],
                ['value' => $order, 'group' => 'display_order'],
            );
        }

        $admin = $request->user('admin');

        activity('admin')
            ->causedBy($admin)
            ->event('updated')
            ->log("Admin {$admin->name} ({$admin->email}) updated the display order settings.");

        return redirect()->route('admin.settings.display-order.index')
            ->with('success', 'Display order settings saved.');
    }
}
