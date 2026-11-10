<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ProductBundle;
use App\Services\BundleService;
use App\Support\DisplayOrderSettings;
use Inertia\Inertia;
use Inertia\Response;

class BundleController extends Controller
{
    public function index(): Response
    {
        $bundlesQuery = ProductBundle::query()
            ->active()
            ->with([
                // Excludes bundles whose component product was deleted/deactivated
                // since being added — otherwise `item.product` would be null and
                // crash the frontend's add-to-cart handler.
                'items' => static function ($query): void {
                    $query->whereHas('product', static fn ($productQuery) => $productQuery->visible())
                        ->with([
                            'product' => static function ($productQuery): void {
                                $productQuery->with(['brand', 'images'])->withExists('variants');
                            },
                            'variant',
                        ]);
                },
            ]);
        $bundles = DisplayOrderSettings::applyToQuery($bundlesQuery, 'bundle_deals')
            ->get()
            ->filter(fn (ProductBundle $bundle): bool => $bundle->items->count() >= 2)
            ->each(fn (ProductBundle $bundle) => $bundle->setAttribute('available_sets', app(BundleService::class)->availableSets($bundle)))
            ->values();

        return Inertia::render('catalog/BundleDeals', [
            'bundles' => $bundles,
        ]);
    }
}
