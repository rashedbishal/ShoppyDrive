<?php

namespace App\Concerns;

use App\Models\Product;
use Illuminate\Support\Collection;
use Inertia\Inertia;

trait FlashesDeletionBlockedByProducts
{
    /**
     * Flash the standard error toast for a refused delete. The "N product(s)"
     * in $message becomes a link (see useFlashToast) that opens a modal
     * listing $products, so the admin can go fix them.
     *
     * @param  Collection<int, Product>  $products
     */
    protected function flashDeletionBlockedByProducts(string $message, Collection $products): void
    {
        Inertia::flash('toast', [
            'type' => 'error',
            'message' => $message,
            'products' => $products->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
            ])->values()->all(),
        ]);
    }
}
