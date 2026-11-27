<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use App\Rules\WebVideo;
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

    /** IMG-F3: the poster is a still image (any format ImageOptimizer turns into WebP). */
    private const string POSTER_EXTENSIONS = 'jpg,jpeg,png,webp';

    private const int POSTER_MAX_KILOBYTES = 5120;

    /** DISC-71: the hero is the first thing a phone loads, so its video is held to far less than an image. */
    private const VIDEO_MAX_MEGABYTES = 2;

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
            'image' => ['required', 'file', 'mimes:'.self::MEDIA_EXTENSIONS, 'max:'.self::MEDIA_MAX_KILOBYTES, new WebVideo(self::VIDEO_MAX_MEGABYTES, requireH264: false)],
            'poster' => $request->hasFile('poster') ? ['image', 'mimes:'.self::POSTER_EXTENSIONS, 'max:'.self::POSTER_MAX_KILOBYTES] : ['nullable'],
            'primary_cta_label' => ['nullable', 'string', 'max:40'],
            'primary_cta_href' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'between:-9999,9999'],
            'stats' => ['nullable', 'array', 'max:3'],
            'stats.*.label' => ['required', 'string', 'max:30'],
            'stats.*.value' => ['required', 'string', 'max:30'],
        ]);

        $validated['image'] = '/storage/'.app(ImageOptimizer::class)->store($request->file('image'), 'hero-slides');
        $validated = $this->withPoster($request, $validated);

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
                ? ['required', 'file', 'mimes:'.self::MEDIA_EXTENSIONS, 'max:'.self::MEDIA_MAX_KILOBYTES, new WebVideo(self::VIDEO_MAX_MEGABYTES, requireH264: false)]
                : ['nullable'],
            'poster' => $request->hasFile('poster') ? ['image', 'mimes:'.self::POSTER_EXTENSIONS, 'max:'.self::POSTER_MAX_KILOBYTES] : ['nullable'],
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

        $heroSlide->update($this->withPoster($request, $validated));

        return redirect()->route('admin.hero-slides.index')->with('success', 'Hero slide updated.');
    }

    public function destroy(HeroSlide $heroSlide): RedirectResponse
    {
        $heroSlide->delete();

        return redirect()->route('admin.hero-slides.index')->with('success', 'Hero slide deleted.');
    }

    /**
     * IMG-F3: a new poster upload is stored (optimised, with its smaller
     * copies); a field sent empty removes the current one (its file is then
     * cleaned up by CleansUpReplacedFiles); anything else (the current URL
     * echoed back by the form) leaves it as it is and is never stored.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function withPoster(Request $request, array $validated): array
    {
        if ($request->hasFile('poster')) {
            $validated['poster'] = '/storage/'.app(ImageOptimizer::class)->store($request->file('poster'), 'hero-slides');
        } elseif ($request->exists('poster') && $request->input('poster') === null) {
            $validated['poster'] = null;
        } else {
            unset($validated['poster']);
        }

        return $validated;
    }
}
