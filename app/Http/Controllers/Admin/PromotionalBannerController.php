<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromotionalBanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class PromotionalBannerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/homepage/promotional-banners/index', [
            'banners' => PromotionalBanner::orderBy('sort_order')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/homepage/promotional-banners/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', function ($attribute, $value, $fail): void {
                if ($value !== null && ! is_string($value) && ! ($value instanceof UploadedFile)) {
                    $fail("The {$attribute} must be an image file.");
                }
            }],
            'href' => ['nullable', 'string', 'max:255'],
            'badge' => ['nullable', 'string', 'max:100'],
            'accent' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer'],
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = '/storage/'.$request->file('image')->store('promotional-banners', 'public');
        } else {
            $validated['image'] = '/placeholder.svg';
        }

        PromotionalBanner::create($validated);

        return redirect()->route('admin.promotional-banners.index')->with('success', 'Promotional banner created.');
    }

    public function edit(PromotionalBanner $promotionalBanner): Response
    {
        return Inertia::render('admin/homepage/promotional-banners/edit', [
            'banner' => $promotionalBanner,
        ]);
    }

    public function update(Request $request, PromotionalBanner $promotionalBanner): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', function ($attribute, $value, $fail): void {
                if ($value !== null && ! is_string($value) && ! ($value instanceof UploadedFile)) {
                    $fail("The {$attribute} must be an image file.");
                }
            }],
            'href' => ['nullable', 'string', 'max:255'],
            'badge' => ['nullable', 'string', 'max:100'],
            'accent' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer'],
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = '/storage/'.$request->file('image')->store('promotional-banners', 'public');
        } else {
            unset($validated['image']);
        }

        $promotionalBanner->update($validated);

        return redirect()->route('admin.promotional-banners.index')->with('success', 'Promotional banner updated.');
    }

    public function destroy(PromotionalBanner $promotionalBanner): RedirectResponse
    {
        $promotionalBanner->delete();

        return redirect()->route('admin.promotional-banners.index')->with('success', 'Promotional banner deleted.');
    }
}
