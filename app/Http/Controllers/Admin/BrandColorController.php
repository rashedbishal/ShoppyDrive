<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\BrandColor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin › Settings › Brand Colour (owner decisions 2026-11-14): the mother
 * admin picks one colour for the CMS, the storefront's brand spots and the
 * Classic theme. Mother-only end to end (route middleware `admin.mother`),
 * like Security, since it changes the whole site.
 */
class BrandColorController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/settings/brand-color', [
            'brandColor' => BrandColor::hex(),
            'defaultColor' => BrandColor::DEFAULT,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'brand_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [
            'brand_color.regex' => 'Choose a colour, or enter a hex code like #1d4ed8.',
        ]);

        BrandColor::save($validated['brand_color']);

        return back()->with('success', 'Brand colour saved.');
    }

    public function destroy(): RedirectResponse
    {
        BrandColor::reset();

        return back()->with('success', 'Brand colour reset to the default red.');
    }
}
