<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use App\Services\ImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HeroSlideController extends Controller
{
    /** Extensions accepted for the slide's media file — still images or a short video. */
    private const MEDIA_EXTENSIONS = 'jpg,jpeg,png,webp,gif,mp4,webm,mov';

    private const MEDIA_MAX_KILOBYTES = 15360; // 15MB

    public function index(): Response
    {
        return Inertia::render('admin/homepage/hero-slides/index', [
            'slides' => HeroSlide::orderBy('sort_order')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/homepage/hero-slides/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'eyebrow' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:200'],
            'image' => ['required', 'file', 'mimes:'.self::MEDIA_EXTENSIONS, 'max:'.self::MEDIA_MAX_KILOBYTES],
            'primary_cta_label' => ['nullable', 'string', 'max:40'],
            'primary_cta_href' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'between:-9999,9999'],
            'stats' => ['nullable', 'array', 'max:3'],
            'stats.*.label' => ['required', 'string', 'max:30'],
            'stats.*.value' => ['required', 'string', 'max:30'],
        ]);

        $validated['image'] = '/storage/'.app(ImageOptimizer::class)->store($request->file('image'), 'hero-slides');

        HeroSlide::create($validated);

        return redirect()->route('admin.hero-slides.index')->with('success', 'Hero slide created.');
    }

    public function edit(HeroSlide $heroSlide): Response
    {
        return Inertia::render('admin/homepage/hero-slides/edit', [
            'slide' => $heroSlide,
        ]);
    }

    public function update(Request $request, HeroSlide $heroSlide): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'eyebrow' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:200'],
            'image' => $request->hasFile('image')
                ? ['required', 'file', 'mimes:'.self::MEDIA_EXTENSIONS, 'max:'.self::MEDIA_MAX_KILOBYTES]
                : ['nullable'],
            'primary_cta_label' => ['nullable', 'string', 'max:40'],
            'primary_cta_href' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'between:-9999,9999'],
            'stats' => ['nullable', 'array', 'max:3'],
            'stats.*.label' => ['required', 'string', 'max:30'],
            'stats.*.value' => ['required', 'string', 'max:30'],
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = '/storage/'.app(ImageOptimizer::class)->store($request->file('image'), 'hero-slides');
        } else {
            unset($validated['image']);
        }

        $heroSlide->update($validated);

        return redirect()->route('admin.hero-slides.index')->with('success', 'Hero slide updated.');
    }

    public function destroy(HeroSlide $heroSlide): RedirectResponse
    {
        $heroSlide->delete();

        return redirect()->route('admin.hero-slides.index')->with('success', 'Hero slide deleted.');
    }
}
