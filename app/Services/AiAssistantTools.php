<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BundleDiscountType;
use App\Exceptions\ReturnQuantityExceededException;
use App\Models\Address;
use App\Models\Blog;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\FlashSaleItem;
use App\Models\HomepageSetting;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ProductVariant;
use App\Models\PromotionalBanner;
use App\Models\ReturnRequest;
use App\Models\Review;
use App\Models\Setting;
use App\Models\SubCategory;
use App\Models\User;
use App\Models\Wishlist;
use App\Support\BangladeshDistricts;
use App\Support\BangladeshiPhone;
use App\Support\CartManager;
use App\Support\ProductSearchQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The only things the AI assistant is allowed to do. Each method here is
 * the single place its corresponding tool's logic lives — the model never
 * touches the database directly, only ever gets back exactly what one of
 * these methods decides to return, under the same visibility/ownership
 * rules the rest of the storefront already enforces.
 *
 * Cart and coupon methods go through the exact same CartManager/Coupon
 * logic the storefront's own Add to Cart button and /coupon/validate
 * endpoint use — never a second, AI-only code path for anything that moves
 * money or stock. The assistant never places an order itself: it only ever
 * modifies the customer's real cart, the same one checkout reads from, and
 * leaves the actual order submission (address, payment method) to the
 * existing checkout page.
 */
class AiAssistantTools
{
    private const SEARCH_COLUMNS = ['id', 'name', 'slug', 'price', 'compare_price', 'short_description', 'stock_quantity'];

    /** available_stock sums variant stock when variants are loaded — without this, a variant product would wrongly show its own (often-unused) stock_quantity, usually 0. */
    private const SEARCH_WITH = ['variants:id,product_id,stock_quantity'];

    /** Keeps a hallucinated or malicious quantity from doing anything unusual — same spirit as PendingCodLimit's per-product cap. */
    private const MAX_CART_QUANTITY = 20;

    public function __construct(
        private readonly CartManager $cartManager,
        private readonly OrderCancellationService $cancellation,
        private readonly ReturnRequestService $returns,
        private readonly LoyaltyPointsService $loyalty,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function searchProducts(string $query): array
    {
        $products = Product::query()
            ->visible()
            ->where(fn ($q) => ProductSearchQuery::apply($q, $query))
            ->with(self::SEARCH_WITH)
            ->limit(5)
            ->get(self::SEARCH_COLUMNS);

        if ($products->isEmpty()) {
            $products = Product::query()
                ->visible()
                ->where(fn ($q) => $this->fuzzyNameMatch($q, $query))
                ->with(self::SEARCH_WITH)
                ->limit(5)
                ->get(self::SEARCH_COLUMNS);
        }

        if ($products->isEmpty()) {
            return ['found' => false, 'message' => 'No matching products found.'];
        }

        return ['found' => true, 'products' => $this->mapProductsForDisplay($products)];
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    private function mapProductsForDisplay($products): array
    {
        $flashPrices = FlashSaleItem::activePricesForProducts($products->pluck('id')->all());

        return $products->map(function (Product $p) use ($flashPrices) {
            $flash = $flashPrices[$p->id] ?? null;

            return [
                'name' => $p->name,
                'url' => route('product.show', $p->slug),
                'price' => (float) $p->price,
                'compare_price' => $p->compare_price ? (float) $p->compare_price : null,
                'on_flash_sale' => $flash !== null,
                'flash_sale_price' => $flash['price'] ?? null,
                'in_stock' => $p->available_stock > 0,
                'stock_count' => $p->available_stock,
                'short_description' => $p->short_description,
            ];
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function checkProductAvailability(string $productName): array
    {
        $product = $this->resolveProductByName(
            $productName,
            ['id', 'name', 'slug', 'price', 'stock_quantity'],
            ['variants' => fn ($q) => $q->select(['id', 'product_id', 'price', 'stock_quantity', 'attributes'])],
        );

        if ($product === null) {
            return ['found' => false, 'message' => 'No product found with that name.'];
        }

        if ($product->variants->isEmpty()) {
            $flash = FlashSaleItem::findActiveFor($product->id, null);

            return [
                'found' => true,
                'name' => $product->name,
                'url' => route('product.show', $product->slug),
                'price' => (float) $product->price,
                'on_flash_sale' => $flash !== null,
                'flash_sale_price' => $flash !== null ? (float) $flash->sale_price : null,
                'in_stock' => $product->available_stock > 0,
                'stock_count' => $product->available_stock,
            ];
        }

        // PERF-001: one batched lookup instead of one per variant — same
        // answer findActiveFor($product->id, $v->id) gives for each.
        $flashItems = FlashSaleItem::findActiveForVariants($product->id, $product->variants->pluck('id')->all())['variants'];

        return [
            'found' => true,
            'name' => $product->name,
            'url' => route('product.show', $product->slug),
            'has_variants' => true,
            // Checked per variant, not once for the whole product — a
            // variant-specific FlashSaleItem (unused by the admin UI today,
            // but fully supported by findActiveFor()) always wins over a
            // product-wide one, the same tie-break CartManager/InventoryService
            // already use, so this can never disagree with what checkout
            // would actually charge.
            'variants' => $product->variants->map(function ($v) use ($flashItems) {
                $flash = $flashItems[$v->id] ?? null;

                return [
                    'attributes' => $v->attributes,
                    'price' => (float) $v->price,
                    'on_flash_sale' => $flash !== null,
                    'flash_sale_price' => $flash !== null ? (float) $flash->sale_price : null,
                    'in_stock' => $v->stock_quantity > 0,
                    'stock_count' => $v->stock_quantity,
                ];
            })->values()->all(),
        ];
    }

    /**
     * Guests must prove ownership with the order number plus the email or
     * phone on file — the exact same check OrderTrackingController uses.
     * A logged-in customer skips that proof entirely but can only ever
     * reach their own orders, never anyone else's, via $authUser below.
     *
     * @return array<string, mixed>
     */
    public function lookupOrder(?User $authUser, ?string $orderNumber, ?string $email, ?string $phone): array
    {
        if ($authUser !== null) {
            $query = Order::where('user_id', $authUser->id);

            $order = $orderNumber !== null
                ? $query->where('order_number', $orderNumber)->first()
                : $query->latest()->first();

            if ($order === null) {
                return ['found' => false, 'message' => $orderNumber !== null ? 'No order with that number found on this account.' : 'No orders found on this account yet.'];
            }

            return $this->orderPayload($order);
        }

        if ($orderNumber === null || ($email === null && $phone === null)) {
            return ['found' => false, 'message' => 'Ask the customer for their order number plus the email or phone used at checkout.'];
        }

        $order = Order::where('order_number', $orderNumber)->first();

        if ($order === null) {
            return ['found' => false, 'message' => 'No order found with that order number.'];
        }

        if (! $this->matchesContact($order, $email, $phone)) {
            return ['found' => false, 'message' => "The email or phone doesn't match our records for this order number. Never reveal order details without a match."];
        }

        return $this->orderPayload($order);
    }

    /**
     * Logged-in customers only — a guest proving ownership via order
     * number + email/phone can already *view* their order through
     * lookupOrder(), but cancelling is a mutation, and this app's
     * customer-facing cancel route (routes/account.php) has always
     * required a real login, not just a matched order number. Goes
     * through the exact same OrderCancellationService::cancelAsCustomer()
     * that route uses — same PENDING-only, no-courier-involvement rule,
     * same stock/loyalty/coupon reversal via Order::booted() — so this is
     * never a second, looser way to cancel an order.
     *
     * @return array<string, mixed>
     */
    public function cancelOrder(User $user, string $orderNumber): array
    {
        $order = Order::where('user_id', $user->id)->where('order_number', $orderNumber)->first();

        if ($order === null) {
            return ['success' => false, 'message' => 'No order with that number found on this account.'];
        }

        $error = $this->cancellation->cancelAsCustomer($order, 'Cancelled by customer via AI assistant.');

        if ($error !== null) {
            return ['success' => false, 'message' => $error];
        }

        return ['success' => true, 'message' => "Order {$order->order_number} has been cancelled."];
    }

    /**
     * Only ever surfaces promotions already shown publicly on the homepage
     * (active flash sales, active promotional banners, the free-delivery
     * announcement) — deliberately never a raw Coupon code, since some
     * coupons are meant for a targeted campaign rather than blanket
     * disclosure to anyone who asks, and there's no "public" flag on
     * Coupon today to tell those apart.
     *
     * @return array<string, mixed>
     */
    public function getCurrentOffers(): array
    {
        $flashSaleItems = FlashSaleItem::query()
            ->whereNull('variant_id')
            ->whereHas('flashSale', function ($query) {
                $query->where('is_active', true)
                    ->where('starts_at', '<=', now())
                    ->where('ends_at', '>=', now());
            })
            ->with(['product' => fn ($q) => $q->visible(), 'flashSale'])
            ->get()
            ->filter(fn (FlashSaleItem $item): bool => $item->product !== null
                && ($item->stock_limit === null || $item->sold_count < $item->stock_limit))
            ->sortBy('sale_price')
            ->take(5)
            ->map(fn (FlashSaleItem $item): array => [
                'product_name' => $item->product->name,
                'url' => route('product.show', $item->product->slug),
                'original_price' => (float) $item->product->price,
                'sale_price' => (float) $item->sale_price,
                'ends_at' => $item->flashSale->ends_at->toDateTimeString(),
            ])
            ->values()->all();

        $banners = PromotionalBanner::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['title', 'description'])
            ->map(fn (PromotionalBanner $b): array => ['title' => $b->title, 'description' => $b->description])
            ->values()->all();

        $announcement = HomepageSetting::where('section_key', 'announcement_bar')
            ->where('is_visible', true)
            ->value('title');

        // Same guard as the homepage and the dedicated /bundle-deals page:
        // excludes a bundle whose component product was deleted/deactivated
        // since being added, and one left with fewer than 2 buyable
        // components isn't really a "bundle" anymore.
        $bundles = ProductBundle::active()
            ->with(['items' => fn ($q) => $q->whereHas('product', fn ($pq) => $pq->visible())->with('product:id,name')])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (ProductBundle $b): bool => $b->items->count() >= 2)
            ->map(fn (ProductBundle $b): array => [
                'name' => $b->name,
                'url' => route('bundles.index'),
                'discount_type' => $b->discount_type->value,
                'bundle_price' => $b->discount_type === BundleDiscountType::FixedPrice ? (float) $b->bundle_price : null,
                'products' => $b->items->pluck('product.name')->filter()->values()->all(),
            ])
            ->values()->all();

        if ($flashSaleItems === [] && $banners === [] && $announcement === null && $bundles === []) {
            return ['found' => false, 'message' => 'No active offers or flash sales right now.'];
        }

        return [
            'found' => true,
            'announcement' => $announcement,
            'flash_sale_items' => $flashSaleItems,
            'promotions' => $banners,
            'bundle_deals' => $bundles,
        ];
    }

    /**
     * Backed by the same admin-editable Page rows the storefront's own
     * footer links to — never invented, never paraphrased from anywhere
     * else, so the assistant can't state a policy the store hasn't
     * actually published.
     *
     * @return array<string, mixed>
     */
    public function getStorePolicy(string $topic): array
    {
        $slugsByTopic = [
            'privacy' => 'privacy-policy',
            'shipping' => 'shipping-policy',
            'delivery' => 'shipping-policy',
            'returns' => 'return-policy',
            'return' => 'return-policy',
            'refund' => 'refund-policy',
            'refunds' => 'refund-policy',
            'terms' => 'terms',
            'faq' => 'faq',
        ];

        $slug = $slugsByTopic[strtolower(trim($topic))] ?? null;

        if ($slug === null) {
            return [
                'found' => false,
                'message' => 'Unknown policy topic. Valid topics: '.implode(', ', array_unique(array_values($slugsByTopic))),
            ];
        }

        $page = Page::where('slug', $slug)->where('is_published', true)->first(['title', 'content']);

        if ($page === null) {
            return ['found' => false, 'message' => 'This store has not published that policy yet.'];
        }

        $content = trim(strip_tags((string) $page->content));

        return [
            'found' => true,
            'title' => $page->title,
            // Capped so one long legal page can't dominate the tool-call
            // budget of the conversation — plenty for the model to
            // summarize from; it's told to point to the URL for the rest.
            'content' => mb_strlen($content) > 3000 ? mb_substr($content, 0, 3000).'…' : $content,
            'url' => route($slug),
        ];
    }

    /**
     * Active, non-deleted top-level categories only — SoftDeletes' own
     * global scope already drops trashed rows; `active()` drops a
     * deactivated one on top of that. Same two conditions every storefront
     * category listing already enforces.
     *
     * @return array<string, mixed>
     */
    public function listCategories(): array
    {
        $categories = Category::active()->orderBy('sort_order')->get(['id', 'name', 'slug']);

        if ($categories->isEmpty()) {
            return ['found' => false, 'message' => 'No categories found.'];
        }

        return [
            'found' => true,
            'categories' => $categories->map(fn (Category $c) => [
                'name' => $c->name,
                'url' => route('category.show', $c->slug),
            ])->values()->all(),
        ];
    }

    /**
     * A sub-category under a deactivated (or deleted) parent category is
     * excluded even if the sub-category row itself is active — same rule
     * ProductListingController::category() enforces before letting a URL
     * resolve, so this can never claim a sub-category exists when visiting
     * it would 404.
     *
     * @return array<string, mixed>
     */
    public function listSubCategories(string $categoryName): array
    {
        $category = $this->resolveActiveCategory($categoryName);

        if ($category === null) {
            return ['found' => false, 'message' => 'No category found with that name.'];
        }

        $subCategories = SubCategory::active()->where('category_id', $category->id)->orderBy('sort_order')->get(['name', 'slug']);

        if ($subCategories->isEmpty()) {
            return ['found' => false, 'message' => "{$category->name} has no sub-categories."];
        }

        return [
            'found' => true,
            'category' => $category->name,
            'sub_categories' => $subCategories->map(fn (SubCategory $sc) => [
                'name' => $sc->name,
                'url' => route('category.show', $sc->slug),
            ])->values()->all(),
        ];
    }

    /**
     * Products in a category or (if given) specifically a sub-category —
     * always through Product::visible(), the same scope every other
     * product-facing tool uses, so a deactivated/deleted product, or one
     * whose own category/sub-category/brand was deactivated, can never
     * appear here even if the category name itself resolves fine.
     *
     * @return array<string, mixed>
     */
    public function listProductsInCategory(string $categoryName, ?string $subCategoryName): array
    {
        $category = $this->resolveActiveCategory($categoryName);

        if ($category === null) {
            return ['found' => false, 'message' => 'No category found with that name.'];
        }

        $query = Product::query()->visible()->where('category_id', $category->id);
        $scopeLabel = $category->name;

        if ($subCategoryName !== null) {
            $subCategory = $this->resolveActiveSubCategory($category->id, $subCategoryName);

            if ($subCategory === null) {
                return ['found' => false, 'message' => "No sub-category matching \"{$subCategoryName}\" found under {$category->name}."];
            }

            $query->where('sub_category_id', $subCategory->id);
            $scopeLabel = "{$category->name} / {$subCategory->name}";
        }

        $products = $query->with(self::SEARCH_WITH)->limit(10)->get(self::SEARCH_COLUMNS);

        if ($products->isEmpty()) {
            return ['found' => false, 'message' => "No products currently available in {$scopeLabel}."];
        }

        return ['found' => true, 'scope' => $scopeLabel, 'products' => $this->mapProductsForDisplay($products)];
    }

    /**
     * @return array<string, mixed>
     */
    public function listBrands(): array
    {
        $brands = Brand::active()->orderBy('name')->get(['id', 'name', 'slug']);

        if ($brands->isEmpty()) {
            return ['found' => false, 'message' => 'No brands found.'];
        }

        return [
            'found' => true,
            'brands' => $brands->map(fn (Brand $b) => [
                'name' => $b->name,
                'url' => route('brand.show', $b->slug),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function listProductsByBrand(string $brandName): array
    {
        $brand = $this->resolveActiveBrand($brandName);

        if ($brand === null) {
            return ['found' => false, 'message' => 'No brand found with that name.'];
        }

        $products = Product::query()->visible()->where('brand_id', $brand->id)->with(self::SEARCH_WITH)->limit(10)->get(self::SEARCH_COLUMNS);

        if ($products->isEmpty()) {
            return ['found' => false, 'message' => "No products currently available from {$brand->name}."];
        }

        return ['found' => true, 'brand' => $brand->name, 'products' => $this->mapProductsForDisplay($products)];
    }

    /**
     * Same category match ProductController's own "You might also like"
     * section uses (same category_id, excludes itself) — handy for
     * suggesting an alternative when the named product is out of stock or
     * just to answer "what else is like this?".
     *
     * @return array<string, mixed>
     */
    public function getRelatedProducts(string $productName): array
    {
        $product = $this->resolveProductByName($productName, ['id', 'name', 'category_id']);

        if ($product === null) {
            return ['found' => false, 'message' => 'No product found with that name.'];
        }

        $related = Product::query()
            ->visible()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->with(self::SEARCH_WITH)
            ->limit(5)
            ->get(self::SEARCH_COLUMNS);

        if ($related->isEmpty()) {
            return ['found' => false, 'message' => "Nothing else similar to {$product->name} right now."];
        }

        return ['found' => true, 'product' => $product->name, 'related_products' => $this->mapProductsForDisplay($related)];
    }

    /**
     * Only ever approved reviews — the same `is_approved` flag every other
     * reviews_count/average_rating computation in this app (HomeController,
     * WishlistController) already filters on.
     *
     * @return array<string, mixed>
     */
    public function getProductReviews(string $productName): array
    {
        $product = $this->resolveProductByName($productName, ['id', 'name']);

        if ($product === null) {
            return ['found' => false, 'message' => 'No product found with that name.'];
        }

        $approved = Review::where('product_id', $product->id)->where('is_approved', true);
        $reviewsCount = (clone $approved)->count();

        if ($reviewsCount === 0) {
            return ['found' => true, 'product' => $product->name, 'reviews_count' => 0, 'average_rating' => null, 'reviews' => []];
        }

        return [
            'found' => true,
            'product' => $product->name,
            'reviews_count' => $reviewsCount,
            'average_rating' => round((float) (clone $approved)->avg('rating'), 1),
            'reviews' => (clone $approved)->latest()->limit(5)->get(['rating', 'title', 'body'])
                ->map(fn (Review $r) => ['rating' => $r->rating, 'title' => $r->title, 'body' => $r->body])
                ->values()->all(),
        ];
    }

    /**
     * The same three admin-curated homepage sections HomeController reads —
     * "featured" is Product::featured() directly; "new_arrivals"/
     * "best_sellers" are an admin-picked, ordered product_ids list stored
     * in that section's HomepageSetting.metadata, same as the homepage.
     *
     * @return array<string, mixed>
     */
    public function getFeaturedProducts(string $section): array
    {
        $section = strtolower(trim($section));

        if (! in_array($section, ['featured', 'new_arrivals', 'best_sellers'], true)) {
            return ['found' => false, 'message' => 'Unknown section. Valid sections: featured, new_arrivals, best_sellers'];
        }

        if ($section === 'featured') {
            $products = Product::visible()->featured()->with(self::SEARCH_WITH)->limit(8)->get(self::SEARCH_COLUMNS);
        } else {
            $ids = HomepageSetting::where('section_key', $section)->first()?->metadata['product_ids'] ?? [];
            $products = $ids === [] ? collect() : Product::visible()->whereIn('id', $ids)->with(self::SEARCH_WITH)->get(self::SEARCH_COLUMNS);
        }

        if ($products->isEmpty()) {
            return ['found' => false, 'message' => 'Nothing in that section right now.'];
        }

        return ['found' => true, 'section' => $section, 'products' => $this->mapProductsForDisplay($products)];
    }

    /**
     * The exact same baseline rate Setting::shippingRateFor() computes for
     * checkout — only the inside-Dhaka-city-corporation pair gets the
     * lower rate, everything else (including an invented city) pays the
     * normal one, by design (see BangladeshDistricts's own docblock). This
     * never checks whether the customer's *actual* cart would qualify for
     * free delivery — that depends on live cart contents and is checkout's
     * job to compute; this only states the flat rate and the threshold.
     *
     * @return array<string, mixed>
     */
    public function estimateShippingCost(string $district, bool $insideDhakaCity): array
    {
        $matchedDistrict = collect(BangladeshDistricts::ALL)->first(fn (string $d): bool => strcasecmp($d, trim($district)) === 0);

        if ($matchedDistrict === null) {
            return ['found' => false, 'message' => "\"{$district}\" isn't a recognized Bangladeshi district."];
        }

        $city = ($matchedDistrict === 'Dhaka' && $insideDhakaCity) ? 'Inside Dhaka City Corporation' : null;
        $threshold = (float) (HomepageSetting::where('section_key', 'announcement_bar')->first()?->metadata['free_delivery_amount'] ?? 0);

        return [
            'found' => true,
            'district' => $matchedDistrict,
            'shipping_cost' => Setting::shippingRateFor($matchedDistrict, $city),
            'free_delivery_threshold' => $threshold > 0 ? $threshold : null,
        ];
    }

    /**
     * Logged-in only — points_balance/referral_code are account data, not
     * public information.
     *
     * @return array<string, mixed>
     */
    public function getAccountInfo(User $user): array
    {
        return [
            'found' => true,
            'points_balance' => (int) $user->points_balance,
            'referral_code' => $user->referral_code,
        ];
    }

    /**
     * lookup_order only ever reaches one order (by number, or the single
     * latest) — this is the "show me my recent orders" case the account
     * page's own order history already covers.
     *
     * @return array<string, mixed>
     */
    public function listMyOrders(User $user): array
    {
        $orders = Order::where('user_id', $user->id)->latest()->limit(10)->get(['order_number', 'status', 'total', 'created_at']);

        if ($orders->isEmpty()) {
            return ['found' => false, 'message' => 'No orders found on this account yet.'];
        }

        return [
            'found' => true,
            'orders' => $orders->map(fn (Order $o) => [
                'order_number' => $o->order_number,
                'status' => $o->status->value,
                'total' => (float) $o->total,
                'placed_at' => $o->created_at?->toDateString(),
            ])->values()->all(),
        ];
    }

    /**
     * Wraps LoyaltyPointsService::claimDailyLogin() exactly — already
     * idempotent per calendar day (Asia/Dhaka) and already computes the
     * streak-day point value, so there's nothing left for this to decide.
     *
     * @return array<string, mixed>
     */
    public function claimDailyLoginBonus(User $user): array
    {
        $result = $this->loyalty->claimDailyLogin($user);

        if (! $result['awarded']) {
            return [
                'success' => false,
                'message' => $result['claimedToday']
                    ? "Already claimed today — day {$result['streakDay']} of the streak."
                    : "Couldn't claim the daily login bonus right now.",
            ];
        }

        return [
            'success' => true,
            'message' => "Claimed {$result['points']} points for day {$result['streakDay']} of the login streak.",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function searchBlogPosts(string $query): array
    {
        $posts = Blog::published()
            ->where(fn ($q) => $q->where('title', 'like', "%{$query}%")->orWhere('excerpt', 'like', "%{$query}%"))
            ->latest('published_at')
            ->limit(3)
            ->get(['title', 'slug', 'excerpt']);

        if ($posts->isEmpty()) {
            return ['found' => false, 'message' => 'No blog posts found on that topic.'];
        }

        return [
            'found' => true,
            'posts' => $posts->map(fn (Blog $p) => [
                'title' => $p->title,
                'excerpt' => $p->excerpt,
                'url' => route('blog.show', $p->slug),
            ])->values()->all(),
        ];
    }

    /**
     * Logged-in only — a customer's saved delivery addresses are account
     * data, never looked up for anyone but the account itself.
     *
     * @return array<string, mixed>
     */
    public function listSavedAddresses(User $user): array
    {
        $addresses = Address::where('user_id', $user->id)->get();

        if ($addresses->isEmpty()) {
            return ['found' => false, 'message' => 'No saved addresses on this account.'];
        }

        return [
            'found' => true,
            'addresses' => $addresses->map(fn (Address $a) => [
                'label' => $a->label,
                'name' => $a->name,
                'phone' => $a->phone,
                'address_line1' => $a->address_line1,
                'city' => $a->city,
                'is_default' => $a->is_default,
            ])->values()->all(),
        ];
    }

    /**
     * Mirrors WishlistController::toggle()'s add path exactly
     * (firstOrCreate on user_id+product_id) — no second wishlist mechanism.
     *
     * @return array<string, mixed>
     */
    public function addToWishlist(User $user, string $productName): array
    {
        $product = $this->resolveProductByName($productName, ['id', 'name']);

        if ($product === null) {
            return ['success' => false, 'message' => 'No product found with that name.'];
        }

        Wishlist::firstOrCreate(['user_id' => $user->id, 'product_id' => $product->id]);

        return ['success' => true, 'message' => "Added {$product->name} to the wishlist."];
    }

    /**
     * @return array<string, mixed>
     */
    public function removeFromWishlist(User $user, string $productName): array
    {
        $product = $this->resolveProductByName($productName, ['id', 'name']);

        if ($product === null) {
            return ['success' => false, 'message' => 'No product found with that name.'];
        }

        $deleted = Wishlist::where('user_id', $user->id)->where('product_id', $product->id)->delete();

        if ($deleted === 0) {
            return ['success' => false, 'message' => "{$product->name} isn't on the wishlist."];
        }

        return ['success' => true, 'message' => "Removed {$product->name} from the wishlist."];
    }

    /**
     * @return array<string, mixed>
     */
    public function viewWishlist(User $user): array
    {
        // isPubliclyVisible() reads category/subCategory/brand, so this
        // can't limit the eager-loaded columns to just what's displayed
        // below — a restricted list excludes the FK columns those
        // relations need, and isPubliclyVisible() silently returns false
        // for every product as a result.
        $items = Wishlist::where('user_id', $user->id)
            ->with('product')
            ->get()
            ->filter(fn (Wishlist $w) => $w->product !== null && $w->product->isPubliclyVisible());

        if ($items->isEmpty()) {
            return ['found' => false, 'message' => 'The wishlist is empty.'];
        }

        return [
            'found' => true,
            'items' => $items->map(fn (Wishlist $w) => [
                'product_name' => $w->product->name,
                'price' => (float) $w->product->price,
                'url' => route('product.show', $w->product->slug),
            ])->values()->all(),
        ];
    }

    /**
     * Only ever as much as ReturnRequestService::eligibilityError() and
     * ::submit() already allow — same 7-day-after-delivery window, same
     * race-safe remaining-quantity check, same ReturnRequest row shape.
     * Defaults to every still-eligible item on the order when the customer
     * doesn't name one, since "I want to return my order" is the common
     * case and a chat interface is a poor place to pick exact line items.
     *
     * @return array<string, mixed>
     */
    public function submitReturnRequest(User $user, string $orderNumber, string $reason, ?string $productName): array
    {
        $order = Order::where('user_id', $user->id)->where('order_number', $orderNumber)->first();

        if ($order === null) {
            return ['success' => false, 'message' => 'No order with that number found on this account.'];
        }

        $eligibilityError = $this->returns->eligibilityError($order);

        if ($eligibilityError !== null) {
            return ['success' => false, 'message' => $eligibilityError];
        }

        $orderItems = $order->items()->get();

        if ($productName !== null) {
            $matched = $orderItems->first(fn ($item) => str_contains(strtolower($item->product_name), strtolower($productName)));

            if ($matched === null) {
                return ['success' => false, 'message' => "No item matching \"{$productName}\" found on this order."];
            }

            $orderItems = collect([$matched]);
        }

        $committed = ReturnRequest::committedQuantitiesByOrderItem($order);
        $items = $orderItems
            ->map(fn ($item) => ['order_item_id' => $item->id, 'quantity' => $item->quantity - ($committed[$item->id] ?? 0)])
            ->filter(fn (array $item): bool => $item['quantity'] > 0)
            ->values()->all();

        if ($items === []) {
            return ['success' => false, 'message' => 'There is nothing left eligible to return on this order.'];
        }

        try {
            $this->returns->submit($order, $user, $reason, $items);
        } catch (ReturnQuantityExceededException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true, 'message' => 'Your return request has been submitted. We\'ll review it and get back to you.'];
    }

    /**
     * Adds to the customer's real, server-side cart — the exact same
     * CartManager::syncUserCart() the storefront's Add to Cart button and
     * `cart.sync` use, merged in (never replacing what's already there).
     * Guest carts live only in the browser's own local state, which this
     * server-side code has no access to, so this is logged-in customers
     * only — callers must check that before reaching here.
     *
     * @return array<string, mixed>
     */
    public function addToCart(User $user, string $productName, int $quantity, ?string $variantDescription): array
    {
        $quantity = max(1, min(self::MAX_CART_QUANTITY, $quantity));

        $product = $this->resolveProductByName(
            $productName,
            ['id', 'name', 'slug', 'price', 'stock_quantity'],
            ['variants' => fn ($q) => $q->select(['id', 'product_id', 'price', 'stock_quantity', 'attributes'])],
        );

        if ($product === null) {
            return ['success' => false, 'message' => 'No product found with that name.'];
        }

        $variantId = null;

        if ($product->variants->isNotEmpty()) {
            $variant = $variantDescription !== null ? $this->matchVariant($product, $variantDescription) : null;

            if ($variant === null) {
                return [
                    'success' => false,
                    'message' => $variantDescription === null
                        ? 'This product has multiple options — ask the customer which one before adding it.'
                        : "Couldn't match \"{$variantDescription}\" to one of this product's options — ask the customer to pick from the list.",
                    'available_options' => $product->variants->pluck('attributes')->values()->all(),
                ];
            }

            $variantId = $variant->id;
        }

        $cart = $this->cartManager->syncUserCart($user, [[
            'product_id' => $product->id,
            'variant_id' => $variantId,
            'quantity' => $quantity,
        ]], merge: true);

        return [
            'success' => true,
            'message' => "Added {$quantity} × {$product->name} to the cart.",
            'cart_summary' => $this->cartSummary($cart),
            'cart' => $this->cartManager->serialize($cart),
        ];
    }

    /**
     * Sets one product's line to an exact quantity (0 removes it) — the
     * same full-cart-replace primitive (`syncUserCart(..., merge: false)`)
     * the cart page's own quantity controls use, since CartManager has no
     * "patch one line" method; this just resolves which line to change by
     * product name first, keeping every other line byte-for-byte as it was.
     *
     * @return array<string, mixed>
     */
    public function setCartItemQuantity(User $user, string $productName, int $quantity): array
    {
        $quantity = max(0, min(self::MAX_CART_QUANTITY, $quantity));

        $cart = $this->cartManager->getOrCreateUserCart($user);
        $cart->load(['items.product', 'items.variant']);

        $product = $this->resolveProductByName($productName, ['id', 'name']);

        if ($product === null) {
            return ['success' => false, 'message' => 'No product found with that name.'];
        }

        $matchingLines = $cart->items->filter(fn ($item) => $item->product_id === $product->id);

        if ($matchingLines->isEmpty()) {
            return ['success' => false, 'message' => "{$product->name} isn't currently in the cart."];
        }

        if ($matchingLines->contains(fn ($item) => $item->bundle_id !== null)) {
            return ['success' => false, 'message' => 'That item is part of a bundle — ask the customer to manage it from the cart page instead.'];
        }

        if ($matchingLines->count() > 1) {
            return [
                'success' => false,
                'message' => "There's more than one {$product->name} line in the cart (different options) — ask the customer which one before changing it.",
            ];
        }

        $payload = $cart->items
            ->map(fn ($item) => $item->product_id === $product->id
                ? ($quantity > 0 ? ['product_id' => $item->product_id, 'variant_id' => $item->variant_id, 'quantity' => $quantity, 'bundle_id' => $item->bundle_id, 'bundle_unit_quantity' => $item->bundle_unit_quantity] : null)
                : ['product_id' => $item->product_id, 'variant_id' => $item->variant_id, 'quantity' => $item->quantity, 'bundle_id' => $item->bundle_id, 'bundle_unit_quantity' => $item->bundle_unit_quantity])
            ->filter()
            ->values()
            ->all();

        $updatedCart = $this->cartManager->syncUserCart($user, $payload, merge: false);

        return [
            'success' => true,
            'message' => $quantity > 0 ? "Updated {$product->name} to {$quantity}." : "Removed {$product->name} from the cart.",
            'cart_summary' => $this->cartSummary($updatedCart),
            'cart' => $this->cartManager->serialize($updatedCart),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function viewCart(User $user): array
    {
        return ['found' => true, 'cart_summary' => $this->cartSummary($this->cartManager->getOrCreateUserCart($user))];
    }

    /**
     * Validates against this cart's own real, server-computed items and
     * subtotal — never a client-submitted one — using the exact same
     * Coupon::eligibilityError()/eligibleLines() the storefront's
     * /coupon/validate preview and actual checkout both use. Only ever
     * applies a code the customer already named; the assistant never
     * suggests one on its own (see getCurrentOffers's own docblock for why).
     *
     * @return array<string, mixed>
     */
    public function applyCouponToCart(User $user, string $code): array
    {
        $coupon = Coupon::where('code', strtoupper(trim($code)))->first();

        if ($coupon === null) {
            return ['success' => false, 'message' => 'Invalid coupon code.'];
        }

        $cart = $this->cartManager->getOrCreateUserCart($user);
        $cart->load(['items.product.category', 'items.product.subCategory', 'items.product.brand', 'items.variant']);
        $items = $this->cartManager->validItems($cart);

        if ($items->isEmpty()) {
            return ['success' => false, 'message' => 'The cart is empty — add something first.'];
        }

        if ($coupon->appliesToEverything()) {
            $subtotal = (float) $items->sum(fn ($item) => (float) $item->price * $item->quantity);
            $error = $coupon->eligibilityError($subtotal, $user->id);
        } else {
            $lines = $items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'subtotal' => (float) $item->price * $item->quantity,
            ])->values()->all();

            $eligibleLines = $coupon->eligibleLines($lines);

            if ($eligibleLines === []) {
                return ['success' => false, 'message' => Coupon::NOT_APPLICABLE_MESSAGE];
            }

            $error = $coupon->eligibilityError((float) array_sum(array_column($eligibleLines, 'subtotal')), $user->id);
        }

        if ($error !== null) {
            return ['success' => false, 'message' => $error];
        }

        $cart->update(['coupon_id' => $coupon->id]);
        $cart = $cart->fresh(['coupon']);

        return [
            'success' => true,
            'message' => "Coupon \"{$coupon->code}\" applied.",
            'cart_summary' => $this->cartSummary($cart),
            'cart' => $this->cartManager->serialize($cart),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function removeCouponFromCart(User $user): array
    {
        $cart = $this->cartManager->getOrCreateUserCart($user);

        if ($cart->coupon_id === null) {
            return ['success' => false, 'message' => 'No coupon is currently applied.'];
        }

        $cart->update(['coupon_id' => null]);

        return [
            'success' => true,
            'message' => 'Coupon removed.',
            'cart_summary' => $this->cartSummary($cart->fresh()),
            'cart' => $this->cartManager->serialize($cart),
        ];
    }

    /**
     * A small, chat-sized summary of the cart for the model to describe —
     * deliberately not the full CartManager::serialize() shape (product
     * images, full card data, …), which would cost real tokens on every
     * single cart-tool call for data the model never needs to read aloud.
     * The full serialize() payload still goes back to the browser (under
     * the separate 'cart' key, which AiAssistantService strips back out
     * before this summary reaches the model's own conversation history).
     *
     * @return array<string, mixed>
     */
    private function cartSummary(Cart $cart): array
    {
        $items = $this->cartManager->validItems($cart->relationLoaded('items') ? $cart : $cart->fresh(['items.product', 'items.variant']));

        return [
            'items' => $items->map(fn ($item) => [
                'product_name' => $item->product->name,
                'variant' => $item->variant?->attributes,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->price,
                'line_subtotal' => (float) $item->price * $item->quantity,
            ])->values()->all(),
            'item_count' => (int) $items->sum('quantity'),
            'subtotal' => (float) $items->sum(fn ($item) => (float) $item->price * $item->quantity),
            'coupon_code' => $cart->coupon?->code,
            'cart_url' => route('cart.index'),
            'checkout_url' => route('checkout.index'),
        ];
    }

    /**
     * @param  array<string, mixed>  $with
     */
    private function resolveProductByName(string $name, array $columns, array $with = []): ?Product
    {
        $product = Product::query()
            ->visible()
            ->where(fn ($q) => ProductSearchQuery::apply($q, $name, includeShortDescription: false))
            ->with($with)
            ->first($columns);

        if ($product === null) {
            $product = Product::query()
                ->visible()
                ->where(fn ($q) => $this->fuzzyNameMatch($q, $name))
                ->with($with)
                ->first($columns);
        }

        return $product;
    }

    /**
     * Matches free-text like "red" or "large, blue" against a variant's
     * `attributes` JSON (e.g. {"color":"Red","size":"L"}) by comparing both
     * sides with whitespace/punctuation stripped — good enough for the
     * short, informal phrases a chat customer actually types, without
     * needing to know this product's specific attribute schema up front.
     */
    private function matchVariant(Product $product, string $description): ?ProductVariant
    {
        $needle = strtolower(preg_replace('/[^a-z0-9]+/i', '', $description) ?? '');

        if ($needle === '') {
            return null;
        }

        return $product->variants->first(function (ProductVariant $variant) use ($needle): bool {
            $haystack = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) json_encode($variant->attributes)) ?? '');

            return str_contains($haystack, $needle);
        });
    }

    /**
     * ProductSearchQuery's FULLTEXT matching (PERF-007) is tuned for the
     * high-volume storefront search and, by its own documented trade-off,
     * won't match a customer's glued-together typing ("tshirt") against a
     * spaced/hyphenated product name ("T-Shirt"): MySQL's own tokenizer
     * indexes "T-Shirt" as "shirt" (the "t" is dropped, too short), and a
     * literal prefix search for "tshirt" never reaches it. This assistant's
     * search volume is a tiny fraction of the storefront's (one lookup per
     * chat message, already rate-limited), so unlike the shared search —
     * where this exact trade-off is accepted rather than engineered around
     * — it can afford a slower, more permissive fallback: strip spaces and
     * hyphens from both sides and compare as plain substrings.
     */
    private function fuzzyNameMatch(Builder $query, string $term): Builder
    {
        $normalized = strtolower(preg_replace('/[\s\-\']+/', '', $term) ?? '');

        if ($normalized === '') {
            return $query->whereRaw('1 = 0');
        }

        // Apostrophes matter here too — "Mens Fashion" (as typed by a
        // customer, or by the model relaying it) wouldn't otherwise match
        // a category literally named "Men's Fashion".
        return $query->whereRaw(
            "REPLACE(REPLACE(REPLACE(LOWER(name), ' ', ''), '-', ''), '''', '') LIKE ?",
            ['%'.$normalized.'%'],
        );
    }

    /** Plain substring match first (cheap, exact), then the same punctuation-stripped fallback product search uses. */
    private function resolveActiveCategory(string $name): ?Category
    {
        return Category::active()->where('name', 'like', "%{$name}%")->first(['id', 'name'])
            ?? Category::active()->where(fn ($q) => $this->fuzzyNameMatch($q, $name))->first(['id', 'name']);
    }

    private function resolveActiveSubCategory(int $categoryId, string $name): ?SubCategory
    {
        return SubCategory::active()->where('category_id', $categoryId)->where('name', 'like', "%{$name}%")->first(['id', 'name'])
            ?? SubCategory::active()->where('category_id', $categoryId)->where(fn ($q) => $this->fuzzyNameMatch($q, $name))->first(['id', 'name']);
    }

    private function resolveActiveBrand(string $name): ?Brand
    {
        return Brand::active()->where('name', 'like', "%{$name}%")->first(['id', 'name'])
            ?? Brand::active()->where(fn ($q) => $this->fuzzyNameMatch($q, $name))->first(['id', 'name']);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(Order $order): array
    {
        $courierName = $order->pathao_consignment_id !== null ? 'Pathao'
            : ($order->steadfast_consignment_id !== null ? 'Steadfast' : null);

        $latestReturn = $order->latestReturnRequest;

        return [
            'found' => true,
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'placed_at' => $order->created_at?->toDateString(),
            'total' => (float) $order->total,
            'items' => $order->items->map(fn ($item) => [
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
            ])->values()->all(),
            // null unless an actual consignment id exists — never invent a
            // courier for an order that hasn't shipped yet.
            'courier' => $courierName,
            'return_request_status' => $latestReturn?->status->value,
        ];
    }

    private function matchesContact(Order $order, ?string $email, ?string $phone): bool
    {
        $onFileEmail = $order->shipping_address['email'] ?? '';
        if ($email && $onFileEmail !== '' && strtolower($onFileEmail) === strtolower($email)) {
            return true;
        }

        $onFilePhone = $order->shipping_address['phone'] ?? '';
        if ($phone && $onFilePhone !== '' && $this->normalizePhone($onFilePhone) === $this->normalizePhone($phone)) {
            return true;
        }

        return false;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) BangladeshiPhone::normalize($phone)) ?? '';

        return substr($digits, -10);
    }
}
