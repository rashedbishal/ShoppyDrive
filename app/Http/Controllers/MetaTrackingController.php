<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MetaTrackingEventRequest;
use App\Models\Coupon;
use App\Models\Product;
use App\Services\FacebookConversionsApiService;
use App\Services\MetaEventRecorder;
use App\Support\CartManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * Receives the six storefront Meta events (everything except Purchase) with
 * the same event id the page just pushed to GTM, and records them for the
 * Conversions API. Product names, prices and totals are looked up here from
 * the database (CartManager::resolvePrice(), the same pricing the cart and
 * checkout use); the browser only says which products and how many.
 *
 * Always answers 202 for a well-formed request, recorded or not (switched
 * off, duplicate, unknown products): tracking must never turn into an
 * error the shopper sees.
 */
class MetaTrackingController extends Controller
{
    public function __invoke(MetaTrackingEventRequest $request, MetaEventRecorder $recorder, CartManager $cart): JsonResponse
    {
        $eventName = $request->string('event_name')->value();

        $customData = $eventName === 'Search'
            ? $this->searchData($request->string('search_string')->value(), $this->requestedItems($request))
            : $this->productData($eventName, $this->requestedItems($request), $request->input('coupon'), $cart);

        $recorded = $customData !== null && $recorder->recordBrowserEvent(
            $eventName,
            $request->string('event_id')->value(),
            $customData,
            $request->string('event_source_url')->value(),
            $request,
        ) !== null;

        return response()->json(['recorded' => $recorded], 202);
    }

    /**
     * @return Collection<int, array{product_id: int, variant_id: ?int, bundle_id: ?int, quantity: int}>
     */
    private function requestedItems(MetaTrackingEventRequest $request): Collection
    {
        return collect((array) $request->validated('items', []))->map(fn (array $item): array => [
            'product_id' => (int) $item['product_id'],
            'variant_id' => isset($item['variant_id']) ? (int) $item['variant_id'] : null,
            'bundle_id' => isset($item['bundle_id']) ? (int) $item['bundle_id'] : null,
            'quantity' => (int) ($item['quantity'] ?? 1),
        ]);
    }

    /**
     * @param  Collection<int, array{product_id: int, variant_id: ?int, bundle_id: ?int, quantity: int}>  $items
     * @return array<string, mixed>|null null when none of the products exist
     */
    private function productData(string $eventName, Collection $items, mixed $couponCode, CartManager $cart): ?array
    {
        $products = Product::query()->whereIn('id', $items->pluck('product_id'))->get(['id', 'name'])->keyBy('id');
        $contents = [];

        foreach ($items as $item) {
            if (! $products->has($item['product_id'])) {
                continue;
            }

            try {
                $price = $cart->resolvePrice($item['product_id'], $item['variant_id'], $item['bundle_id']);
            } catch (ModelNotFoundException) {
                // A variant that isn't this product's: report nothing for it.
                continue;
            }

            $contents[] = [
                'id' => (string) $item['product_id'],
                'quantity' => $item['quantity'],
                'item_price' => round($price, 2),
            ];
        }

        if ($contents === []) {
            return null;
        }

        $value = array_sum(array_map(fn (array $line): float => $line['item_price'] * $line['quantity'], $contents));

        $data = [
            'currency' => FacebookConversionsApiService::CURRENCY,
            'value' => round($value, 2),
            'content_type' => 'product',
            'content_ids' => array_values(array_unique(array_column($contents, 'id'))),
            'contents' => $contents,
        ];

        if (count($data['content_ids']) === 1) {
            $data['content_name'] = (string) $products[(int) $data['content_ids'][0]]->name;
        }

        if ($eventName === 'InitiateCheckout') {
            $data['num_items'] = array_sum(array_column($contents, 'quantity'));

            $coupon = is_string($couponCode) && $couponCode !== ''
                ? Coupon::query()->where('code', $couponCode)->value('code')
                : null;

            if ($coupon !== null) {
                $data['coupon'] = $coupon;
            }
        }

        return $data;
    }

    /**
     * @param  Collection<int, array{product_id: int, variant_id: ?int, bundle_id: ?int, quantity: int}>  $items
     * @return array<string, mixed>
     */
    private function searchData(string $searchString, Collection $items): array
    {
        $productIds = Product::query()
            ->visible()
            ->whereIn('id', $items->pluck('product_id')->take(20))
            ->pluck('id')
            ->map(fn (int $id): string => (string) $id)
            ->values()
            ->all();

        return array_filter([
            'search_string' => trim($searchString),
            'content_type' => $productIds === [] ? null : 'product',
            'content_ids' => $productIds === [] ? null : $productIds,
        ], fn ($value): bool => $value !== null);
    }
}
