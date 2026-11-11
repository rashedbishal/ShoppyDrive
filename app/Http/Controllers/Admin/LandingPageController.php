<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Concerns\UpdatesStatusInBulk;
use App\Http\Controllers\Controller;
use App\Models\LandingPage;
use App\Models\Product;
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
