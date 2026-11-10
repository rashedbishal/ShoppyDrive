<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

/**
 * Admin-controlled display order (Admin > Settings > Display Order) for
 * every storefront product/brand listing that has no customer-facing sort
 * control of its own — unlike the category/search listing pages, which
 * keep their own dedicated sort dropdown and are untouched by this.
 *
 * Each scenario below is independently set to "descending" (newest first —
 * by published_at/created_at for products, created_at for everything else)
 * or "random" (re-shuffled on every page load, via the database's own
 * random ordering — not cached or fixed for the day). Stored as one
 * `Setting` row per scenario, keyed `display_order.<scenario>`, so each is
 * a single indexed point-lookup — cheap enough not to need caching, and
 * these sections are read at most once or twice per request.
 */
class DisplayOrderSettings
{
    public const DESCENDING = 'descending';

    public const RANDOM = 'random';

    /** @var list<string> */
    public const ORDERS = [self::DESCENDING, self::RANDOM];

    public const DEFAULT = self::DESCENDING;

    /**
     * scenario key => admin-facing label, grouped for the settings page in
     * this same order. New Arrivals/Best Sellers/Home Page Brands are still
     * admin-curated *lists* (which products/brands appear is unchanged) —
     * this only decides the order the curated set displays in. Flash Sale
     * Items and Featured Products are each shared by two pages (home page
     * and the dedicated /flash-sales page) — one setting governs both.
     *
     * @var array<string, string>
     */
    public const SCENARIOS = [
        'featured_products' => 'Featured Products (Home & Flash Sales page)',
        'flash_sale_items' => 'Flash Sale Items (Home & Flash Sales page)',
        'new_arrivals' => 'New Arrivals (Home page)',
        'best_sellers' => 'Best Sellers (Home page)',
        'homepage_brands' => 'Brands section (Home page)',
        'bundle_deals' => 'Bundle Deals (Home page & /bundle-deals)',
        'brands_listing' => 'All Brands (/brands page)',
        'brand_detail_products' => "A brand's product list (/brand/{slug})",
        'related_products' => 'Related Products (product page)',
        'wishlist' => 'Wishlist',
    ];

    public static function orderFor(string $scenario): string
    {
        $value = Setting::where('key', "display_order.{$scenario}")->first()?->value;

        return in_array($value, self::ORDERS, true) ? $value : self::DEFAULT;
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        $rows = Setting::whereIn('key', array_map(
            fn (string $scenario): string => "display_order.{$scenario}",
            array_keys(self::SCENARIOS),
        ))->get()->keyBy('key');

        return collect(self::SCENARIOS)->keys()->mapWithKeys(function (string $scenario) use ($rows): array {
            $value = $rows->get("display_order.{$scenario}")?->value;

            return [$scenario => in_array($value, self::ORDERS, true) ? $value : self::DEFAULT];
        })->all();
    }

    /**
     * Applies the admin's chosen order to a not-yet-executed query —
     * Random uses the database's own random ordering (so pagination, where
     * it exists, naturally re-shuffles on every request too); Descending
     * applies each given column, in order, descending — e.g.
     * ['published_at', 'created_at'] so two rows published in the same
     * call still tie-break consistently — then `id` descending always,
     * last, as a final tiebreaker: two rows created within the same
     * second (routine in a batch import or a fast test) would otherwise
     * tie on every timestamp column and fall back to the database's own
     * unspecified order.
     *
     * Accepts a plain query builder or a relation's query (eager-load
     * constraint closures, e.g. `'items' => fn ($query) => ...`, receive a
     * `Relation`, not a `Builder` — both support the same ordering calls).
     *
     * @param  list<string>  $descendingColumns
     */
    public static function applyToQuery(Builder|Relation $query, string $scenario, array $descendingColumns = ['created_at']): Builder|Relation
    {
        if (self::orderFor($scenario) === self::RANDOM) {
            return $query->inRandomOrder();
        }

        foreach ([...$descendingColumns, 'id'] as $column) {
            $query->orderByDesc($column);
        }

        return $query;
    }

    /**
     * Same decision, for a collection that's already been fetched (e.g. an
     * admin-curated id list resolved to models, or an eager-loaded
     * relation) — $sortKey resolves the value Descending sorts by, highest
     * first, with `id` as a final tiebreaker (PHP compares the two-element
     * array element-by-element, same as applyToQuery()'s extra `id`
     * ORDER BY column) for two items tied on $sortKey — same reasoning as
     * applyToQuery()'s own `id` tiebreaker.
     *
     * @template TItem of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<array-key, TItem>  $items
     * @param  (callable(TItem): mixed)  $sortKey
     * @return Collection<int, TItem>
     */
    public static function applyToCollection(Collection $items, string $scenario, callable $sortKey): Collection
    {
        return self::orderFor($scenario) === self::RANDOM
            ? $items->shuffle()->values()
            : $items->sortByDesc(fn ($item) => [$sortKey($item), $item->getKey()])->values();
    }
}
