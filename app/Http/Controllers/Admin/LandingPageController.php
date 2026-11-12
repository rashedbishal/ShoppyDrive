<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Concerns\UpdatesStatusInBulk;
use App\Http\Controllers\Controller;
use App\Models\LandingPage;
use App\Models\Product;
use App\Services\ImageOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Inertia\Inertia;
use Inertia\Response;

class LandingPageController extends Controller
{
    use UpdatesStatusInBulk;

    /**
     * A landing page can promote any product that still exists — active or
     * switched off in the shop, with or without colour/size variants. Only a
     * deleted product is refused.
     */
    private function productExistsRule(): Exists
    {
        return Rule::exists('products', 'id')->whereNull('deleted_at');
    }

    public function index(): Response
    {
        $pages = LandingPage::with('product:id,name')
            ->latest()
            ->get(['id', 'title', 'slug', 'product_id', 'is_active', 'created_at']);

        return Inertia::render('admin/landing-pages/index', ['pages' => $pages]);
    }

    public function create(): Response
    {
        $products = Product::orderBy('name')
            ->get(['id', 'name', 'price', 'is_active']);

        return Inertia::render('admin/landing-pages/create', ['products' => $products]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/', 'unique:landing_pages,slug'],
            'product_id' => ['required', 'integer', $this->productExistsRule()],
            'is_active' => ['boolean'],
            'max_order_quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = LandingPage::create([
            'title' => $request->title,
            'slug' => $request->slug,
            'product_id' => $request->product_id,
            'is_active' => $request->boolean('is_active', true),
            'max_order_quantity' => $request->integer('max_order_quantity', 100),
            'sections' => LandingPage::defaultSections(),
        ]);

        return redirect()->route('admin.landing-pages.edit', $page)
            ->with('success', 'Landing page created. Now customise the sections below.');
    }

    public function edit(LandingPage $landingPage): Response
    {
        $products = Product::orderBy('name')
            ->get(['id', 'name', 'price', 'is_active']);

        return Inertia::render('admin/landing-pages/edit', [
            'page' => $landingPage->load('product:id,name,price,free_delivery'),
            'sections' => $landingPage->getSections(),
            'products' => $products,
            // The page's saved product: its images feed the hero carousel and
            // its variants feed the combo builder.
            'productMedia' => $landingPage->product?->media()->get(['id', 'path', 'type'])->values() ?? [],
            'productVariants' => $landingPage->product?->variants()->get(['id', 'sku', 'price', 'stock_quantity', 'attributes'])->values() ?? [],
        ]);
    }

    public function update(Request $request, LandingPage $landingPage): RedirectResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/', Rule::unique('landing_pages', 'slug')->ignore($landingPage->id)],
            'product_id' => ['required', 'integer', $this->productExistsRule()],
            'is_active' => ['boolean'],
            'max_order_quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sections' => ['nullable', 'array'],
            'sections.description.custom_content' => ['nullable', 'string', 'max:20000'],
            ...$this->sectionRules((int) $request->input('product_id')),
        ]);

        $landingPage->update([
            'title' => $request->title,
            'slug' => $request->slug,
            'product_id' => $request->product_id,
            'is_active' => $request->boolean('is_active'),
            'max_order_quantity' => $request->integer('max_order_quantity', (int) $landingPage->max_order_quantity),
            'sections' => $request->input('sections', $landingPage->sections),
        ]);

        return back()->with('success', 'Landing page saved.');
    }

    /**
     * Rules for the structured parts of the page builder. Everything else in
     * `sections` stays free-form text, as before.
     *
     * @return array<string, mixed>
     */
    private function sectionRules(int $productId): array
    {
        $variantExists = Rule::exists('product_variants', 'id')->where('product_id', $productId);

        return [
            'sections.hero.features' => ['nullable', 'array', 'max:3'],
            'sections.hero.features.*.title' => ['nullable', 'string', 'max:80'],
            'sections.hero.features.*.text' => ['nullable', 'string', 'max:200'],
            'sections.hero.carousel.animation' => ['nullable', Rule::in(['fade', 'slide', 'zoom', 'flip', 'blur'])],
            'sections.hero.carousel.interval_seconds' => ['nullable', 'numeric', 'min:2', 'max:20'],
            'sections.hero.carousel.transition_ms' => ['nullable', 'integer', 'min:200', 'max:3000'],
            'sections.hero.carousel.hidden_media_ids' => ['nullable', 'array', 'max:50'],
            'sections.hero.carousel.hidden_media_ids.*' => ['integer'],
            'sections.hero.carousel.extra_slides' => ['nullable', 'array', 'max:12'],
            'sections.hero.carousel.extra_slides.*' => ['string', 'max:500', 'regex:#^/storage/landing-slides/#'],
            'sections.delivery.areas' => ['nullable', 'array', 'max:30'],
            'sections.delivery.areas.*' => ['nullable', 'string', 'max:60'],
            'sections.contact.phone' => ['nullable', 'string', 'max:40'],
            'sections.contact.email' => ['nullable', 'email', 'max:255'],
            'sections.combos' => ['nullable', 'array', 'max:12'],
            'sections.combos.*.id' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', 'distinct'],
            'sections.combos.*.name' => ['required', 'string', 'max:80'],
            'sections.combos.*.description' => ['nullable', 'string', 'max:200'],
            'sections.combos.*.badge' => ['nullable', 'string', 'max:30'],
            'sections.combos.*.price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'sections.combos.*.items' => ['required', 'array', 'min:1', 'max:10'],
            'sections.combos.*.items.*.variant_id' => ['required', 'integer', $variantExists],
            'sections.combos.*.items.*.quantity' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }

    /** Stores one extra hero-carousel image and returns its public path for the page builder to save. */
    public function uploadSlide(Request $request, LandingPage $landingPage): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192']]);

        return response()->json([
            'path' => '/storage/'.app(ImageOptimizer::class)->store($request->file('image'), 'landing-slides'),
        ]);
    }

    public function destroy(LandingPage $landingPage): RedirectResponse
    {
        $landingPage->delete();

        return redirect()->route('admin.landing-pages.index')->with('success', 'Landing page deleted.');
    }

    public function updateStatusMany(Request $request): RedirectResponse
    {
        $result = $this->updateStatusInBulk($request, LandingPage::class);

        return redirect()->route('admin.landing-pages.index')->with('success', $result['count'].' landing page(s) '.($result['active'] ? 'activated' : 'deactivated').'.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:landing_pages,id'],
        ])['ids'];

        LandingPage::whereIn('id', $ids)->delete();

        return redirect()->route('admin.landing-pages.index')->with('success', count($ids).' landing page(s) deleted.');
    }
}
