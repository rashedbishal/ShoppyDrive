<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Concerns\UpdatesStatusInBulk;
use App\Http\Controllers\Controller;
use App\Models\LandingPage;
use App\Models\Product;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LandingPageController extends Controller
{
    use UpdatesStatusInBulk;

    /**
     * FSA-08: a landing page has no variant selector, and
     * InventoryService::decrementForOrderItem() always throws
     * InsufficientStockException for a variant product ordered with no
     * variant_id — every order placed through a landing page for such a
     * product fails deterministically, with mobile banking having already
     * collected payment first. Block it at the one place landing pages are
     * created or repointed at a product, rather than touching the funnel
     * itself or the payment flow.
     */
    private function productHasVariantsRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (Product::whereKey($value)->whereHas('variants')->exists()) {
                $fail('This product has colour/size variants, which landing pages don\'t support yet. Choose a variant-less product, or add a variant selector to the landing-page funnel before using this one.');
            }
        };
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
        $products = Product::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'price']);

        return Inertia::render('admin/landing-pages/create', ['products' => $products]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/', 'unique:landing_pages,slug'],
            'product_id' => ['required', 'integer', 'exists:products,id', $this->productHasVariantsRule()],
            'is_active' => ['boolean'],
        ]);

        $page = LandingPage::create([
            'title' => $request->title,
            'slug' => $request->slug,
            'product_id' => $request->product_id,
            'is_active' => $request->boolean('is_active', true),
            'sections' => LandingPage::defaultSections(),
        ]);

        return redirect()->route('admin.landing-pages.edit', $page)
            ->with('success', 'Landing page created. Now customise the sections below.');
    }

    public function edit(LandingPage $landingPage): Response
    {
        $products = Product::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'price']);

        return Inertia::render('admin/landing-pages/edit', [
            'page' => $landingPage->load('product:id,name,price,free_delivery'),
            'sections' => $landingPage->getSections(),
            'products' => $products,
        ]);
    }

    public function update(Request $request, LandingPage $landingPage): RedirectResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/', Rule::unique('landing_pages', 'slug')->ignore($landingPage->id)],
            'product_id' => ['required', 'integer', 'exists:products,id', $this->productHasVariantsRule()],
            'is_active' => ['boolean'],
            'sections' => ['nullable', 'array'],
            'sections.description.custom_content' => ['nullable', 'string', 'max:20000'],
        ]);

        $landingPage->update([
            'title' => $request->title,
            'slug' => $request->slug,
            'product_id' => $request->product_id,
            'is_active' => $request->boolean('is_active'),
            'sections' => $request->input('sections', $landingPage->sections),
        ]);

        return back()->with('success', 'Landing page saved.');
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
