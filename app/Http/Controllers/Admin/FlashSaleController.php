<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FlashSaleRequest;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FlashSaleController extends Controller
{
    public function index(Request $request): Response
    {
        $flashSales = FlashSale::withCount('items')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->latest()->paginate(20)->withQueryString();

        return Inertia::render('admin/flash-sales/index', [
            'flashSales' => $flashSales,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/flash-sales/create', [
            'products' => Product::active()->orderBy('name')->get(['id', 'name', 'price', 'sku']),
        ]);
    }

    public function store(FlashSaleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'] ?? [];
        unset($data['items']);

        $data['is_active'] = $data['is_active'] ?? false;
        $flashSale = FlashSale::create($data);

        foreach ($items as $item) {
            FlashSaleItem::create([
                'flash_sale_id' => $flashSale->id,
                'product_id' => $item['product_id'],
                'sale_price' => $item['sale_price'],
                'stock_limit' => $item['stock_limit'],
            ]);
        }

        return redirect()->route('admin.flash-sales.index')->with('success', 'Flash sale created.');
    }

    public function edit(FlashSale $flashSale): Response
    {
        return Inertia::render('admin/flash-sales/edit', [
            'flashSale' => $flashSale->load(['items.product:id,name,price,sku']),
            'products' => Product::active()->orderBy('name')->get(['id', 'name', 'price', 'sku']),
        ]);
    }

    public function update(FlashSaleRequest $request, FlashSale $flashSale): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'] ?? null;
        unset($data['items']);

        $data['is_active'] = $data['is_active'] ?? false;
        $flashSale->update($data);

        if ($items !== null) {
            $flashSale->items()->delete();
            foreach ($items as $item) {
                FlashSaleItem::create([
                    'flash_sale_id' => $flashSale->id,
                    'product_id' => $item['product_id'],
                    'sale_price' => $item['sale_price'],
                    'stock_limit' => $item['stock_limit'],
                ]);
            }
        }

        return redirect()->route('admin.flash-sales.index')->with('success', 'Flash sale updated.');
    }

    public function destroy(FlashSale $flashSale): RedirectResponse
    {
        $flashSale->items()->delete();
        $flashSale->delete();

        return redirect()->route('admin.flash-sales.index')->with('success', 'Flash sale deleted.');
    }
}
