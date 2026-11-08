<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\AiAssistantConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Talks to Groq's OpenAI-compatible chat-completions API on the customer's
 * behalf, running whichever model Admin has chosen (AiAssistantConfig::model(),
 * one of CANDIDATE_MODELS, default Llama 3.1 8B Instant). The model is never
 * given database access — it can only ask for one of the three tools below,
 * each of which goes through AiAssistantTools and the exact same
 * visibility/ownership rules the rest of the storefront already enforces. A
 * reply the model tries to make about anything outside this store is
 * refused by the system instruction, not by code — if that ever proves
 * unreliable in practice, tighten the instruction rather than trying to
 * out-guess it here.
 *
 * Unlike Gemini's old stateful "Interactions" API (one `previous_interaction_id`,
 * conversation held server-side by Google), Groq's chat-completions endpoint
 * is stateless: the *entire* prior conversation has to be resent on every
 * turn, and AiAssistantController/AiAssistantPanel.tsx hold that history
 * client-side instead of an opaque id. MAX_HISTORY_MESSAGES bounds it here
 * too, defensively, since every message in it is billed as input tokens
 * again on every single turn regardless of what the client sends.
 */
class AiAssistantService
{
    private const ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';

    private const MODELS_ENDPOINT = 'https://api.groq.com/openai/v1/models';

    private const MAX_TOOL_ROUNDTRIPS = 8;

    /** @var list<int> Worth one retry: a rate limit or a transient overload, not a bad request or bad key. */
    private const RETRYABLE_STATUSES = [429, 503];

    private const RETRY_DELAY_SECONDS = 1;

    /** Prior turns kept when building the request — oldest trimmed first. */
    private const MAX_HISTORY_MESSAGES = 20;

    public function __construct(private readonly AiAssistantTools $tools) {}

    /**
     * @param  list<array{role: string, content: string}>  $history  Prior user/assistant turns, oldest first.
     * @return array{reply: string, cart: ?array<string, mixed>}
     */
    public function respond(string $message, array $history, ?User $authUser): array
    {
        $apiKey = AiAssistantConfig::apiKey();

        if ($apiKey === '') {
            return ['reply' => "Sorry, the assistant isn't set up yet.", 'cart' => null];
        }

        $messages = [
            ['role' => 'system', 'content' => $this->systemInstruction($authUser !== null)],
            ...array_slice($history, -self::MAX_HISTORY_MESSAGES),
            ['role' => 'user', 'content' => $message],
        ];

        // Set by a cart-mutating tool this turn (add/update/apply coupon/…).
        // Carried out to the controller/frontend separately from $messages:
        // CartManager::serialize()'s full shape (images, card data, …) would
        // cost real tokens if it went back into the model's own context for
        // no benefit, and — more importantly — it must reach the browser
        // even if a later step in this same turn fails (the mutation itself
        // already happened), so every return below carries it.
        $latestCart = null;

        for ($round = 0; $round < self::MAX_TOOL_ROUNDTRIPS; $round++) {
            $response = $this->call($apiKey, $messages, $authUser !== null);

            if ($response === null) {
                return ['reply' => "Sorry, I'm having trouble right now — please try again in a moment.", 'cart' => $latestCart];
            }

            $choice = $response['choices'][0]['message'] ?? null;

            if (! is_array($choice)) {
                Log::warning('[AI assistant] Unexpected Groq response shape', ['response' => $response]);

                return ['reply' => "Sorry, I didn't catch that — could you rephrase?", 'cart' => $latestCart];
            }

            $toolCalls = $choice['tool_calls'] ?? null;

            if (! is_array($toolCalls) || $toolCalls === []) {
                $text = is_string($choice['content'] ?? null) ? trim($choice['content']) : '';

                return ['reply' => $text !== '' ? $text : "Sorry, I didn't catch that — could you rephrase?", 'cart' => $latestCart];
            }

            $messages[] = ['role' => 'assistant', 'content' => $choice['content'] ?? null, 'tool_calls' => $toolCalls];

            foreach ($toolCalls as $toolCall) {
                $name = (string) ($toolCall['function']['name'] ?? '');
                $arguments = json_decode((string) ($toolCall['function']['arguments'] ?? '{}'), true);
                $result = $this->runTool($name, is_array($arguments) ? $arguments : [], $authUser);

                if (array_key_exists('cart', $result)) {
                    $latestCart = $result['cart'];
                    unset($result['cart']);
                }

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string) ($toolCall['id'] ?? ''),
                    'content' => json_encode($result),
                ];
            }
        }

        return ['reply' => "Sorry, that's taking longer than expected — please try asking in a simpler way.", 'cart' => $latestCart];
    }

    /**
     * For Admin's model dropdown: CANDIDATE_MODELS narrowed to whatever
     * Groq's account-level catalog (`/v1/models`) actually reports as
     * available right now, so a retired or renamed model id can't linger
     * as a choice — the exact failure mode a hardcoded model name hit
     * twice already. Falls back to the full CANDIDATE_MODELS list (never
     * an empty one) when there's no key yet or the lookup fails for any
     * reason, same fail-safe spirit as the rest of this class.
     *
     * @return array<string, string> model id => display label
     */
    public function availableModels(): array
    {
        $apiKey = AiAssistantConfig::apiKey();

        if ($apiKey === '') {
            return AiAssistantConfig::CANDIDATE_MODELS;
        }

        try {
            $response = Http::withToken($apiKey)
                ->withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]])
                ->timeout(5)->get(self::MODELS_ENDPOINT);
        } catch (ConnectionException) {
            return AiAssistantConfig::CANDIDATE_MODELS;
        }

        if (! $response->successful()) {
            return AiAssistantConfig::CANDIDATE_MODELS;
        }

        $availableIds = collect($response->json('data', []))->pluck('id')->all();

        $filtered = array_intersect_key(AiAssistantConfig::CANDIDATE_MODELS, array_flip($availableIds));

        return $filtered !== [] ? $filtered : AiAssistantConfig::CANDIDATE_MODELS;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function runTool(string $name, array $arguments, ?User $authUser): array
    {
        try {
            return match ($name) {
                'search_products' => $this->tools->searchProducts((string) ($arguments['query'] ?? '')),
                'check_product_availability' => $this->tools->checkProductAvailability((string) ($arguments['product_name'] ?? '')),
                'lookup_order' => $this->tools->lookupOrder(
                    $authUser,
                    $arguments['order_number'] ?? null,
                    $arguments['email'] ?? null,
                    $arguments['phone'] ?? null,
                ),
                'get_current_offers' => $this->tools->getCurrentOffers(),
                'get_store_policy' => $this->tools->getStorePolicy((string) ($arguments['topic'] ?? '')),
                'list_categories' => $this->tools->listCategories(),
                'list_sub_categories' => $this->tools->listSubCategories((string) ($arguments['category_name'] ?? '')),
                'list_products_in_category' => $this->tools->listProductsInCategory(
                    (string) ($arguments['category_name'] ?? ''),
                    isset($arguments['sub_category_name']) ? (string) $arguments['sub_category_name'] : null,
                ),
                'list_brands' => $this->tools->listBrands(),
                'list_products_by_brand' => $this->tools->listProductsByBrand((string) ($arguments['brand_name'] ?? '')),
                'get_product_reviews' => $this->tools->getProductReviews((string) ($arguments['product_name'] ?? '')),
                'get_featured_products' => $this->tools->getFeaturedProducts((string) ($arguments['section'] ?? '')),
                'estimate_shipping_cost' => $this->tools->estimateShippingCost(
                    (string) ($arguments['district'] ?? ''),
                    (bool) ($arguments['inside_dhaka_city'] ?? false),
                ),
                'search_blog_posts' => $this->tools->searchBlogPosts((string) ($arguments['query'] ?? '')),
                'add_to_cart', 'update_cart_item', 'view_cart', 'apply_coupon', 'remove_coupon', 'cancel_order',
                'get_account_info', 'list_saved_addresses', 'add_to_wishlist', 'remove_from_wishlist', 'view_wishlist',
                'submit_return_request' => $authUser === null
                    ? ['success' => false, 'message' => 'The customer needs to be logged in for this — ask them to log in first.']
                    : $this->runAccountTool($name, $arguments, $authUser),
                default => ['found' => false, 'message' => 'Unknown tool.'],
            };
        } catch (\Throwable $e) {
            report($e);

            return ['found' => false, 'message' => 'Something went wrong looking that up.'];
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function runAccountTool(string $name, array $arguments, User $authUser): array
    {
        return match ($name) {
            'add_to_cart' => $this->tools->addToCart(
                $authUser,
                (string) ($arguments['product_name'] ?? ''),
                (int) ($arguments['quantity'] ?? 1),
                isset($arguments['variant']) ? (string) $arguments['variant'] : null,
            ),
            'update_cart_item' => $this->tools->setCartItemQuantity(
                $authUser,
                (string) ($arguments['product_name'] ?? ''),
                (int) ($arguments['quantity'] ?? 0),
            ),
            'view_cart' => $this->tools->viewCart($authUser),
            'apply_coupon' => $this->tools->applyCouponToCart($authUser, (string) ($arguments['code'] ?? '')),
            'remove_coupon' => $this->tools->removeCouponFromCart($authUser),
            'cancel_order' => $this->tools->cancelOrder($authUser, (string) ($arguments['order_number'] ?? '')),
            'get_account_info' => $this->tools->getAccountInfo($authUser),
            'list_saved_addresses' => $this->tools->listSavedAddresses($authUser),
            'add_to_wishlist' => $this->tools->addToWishlist($authUser, (string) ($arguments['product_name'] ?? '')),
            'remove_from_wishlist' => $this->tools->removeFromWishlist($authUser, (string) ($arguments['product_name'] ?? '')),
            'view_wishlist' => $this->tools->viewWishlist($authUser),
            'submit_return_request' => $this->tools->submitReturnRequest(
                $authUser,
                (string) ($arguments['order_number'] ?? ''),
                (string) ($arguments['reason'] ?? ''),
                isset($arguments['product_name']) ? (string) $arguments['product_name'] : null,
            ),
            default => ['success' => false, 'message' => 'Unknown tool.'],
        };
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @return array<string, mixed>|null
     */
    private function call(string $apiKey, array $messages, bool $isLoggedIn): ?array
    {
        $payload = [
            'model' => AiAssistantConfig::model(),
            'messages' => $messages,
            'tools' => $this->toolDeclarations($isLoggedIn),
            'tool_choice' => 'auto',
        ];

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $response = Http::withToken($apiKey)
                    // Groq's API is single-stack as far as we've seen, but this
                    // is a cheap, harmless defensive default against the same
                    // dual-stack-host-hangs-on-a-dead-IPv6-route failure mode
                    // that previously hit the old Gemini integration.
                    ->withOptions(['curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4]])
                    ->timeout(20)->post(self::ENDPOINT, $payload);
            } catch (ConnectionException $e) {
                // Already spent the full timeout budget getting here —
                // retrying a hung connection risks the web server's own
                // execution time limit, so this one isn't retried.
                Log::warning('[AI assistant] Could not reach Groq', [
                    'message' => $e->getMessage(),
                ]);

                return null;
            }

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('[AI assistant] Groq request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'attempt' => $attempt,
            ]);

            // A 429 (rate limit) or 503 (overload) can clear on its own; a
            // bad key, a malformed request, or an exhausted quota won't
            // succeed on a second try, so only these are worth retrying,
            // once.
            if (! in_array($response->status(), self::RETRYABLE_STATUSES, true) || $attempt === 2) {
                return null;
            }

            Sleep::for(self::RETRY_DELAY_SECONDS)->seconds();
        }

        return null;
    }

    private function systemInstruction(bool $isLoggedIn): string
    {
        $base = <<<'PROMPT'
            You are the shopping assistant for ShoppyDrive, a Bangladesh-based
            online store. Only discuss this store: its products, prices, stock,
            offers, orders, shipping and policies. If asked anything unrelated,
            politely decline and steer back to how you can help with the store
            — never answer the unrelated question itself.

            Never invent product details, prices, stock levels, offers, policy
            text, or order information — only state what a tool call actually
            returned. If a tool reports something was not found or did not
            match, say so plainly rather than guessing.

            When looking up an order for someone who is not logged in, you must
            have both an order number and the email or phone used at checkout
            before calling lookup_order — ask for whichever is missing first.
            Never reveal any order's details unless the tool call confirms a
            match.

            To answer about current deals, discounts or flash sales, call
            get_current_offers rather than guessing — if it reports nothing
            active, say so rather than inventing a promotion. To answer
            questions about privacy, shipping, returns, refunds, terms, or
            other store policy, call get_store_policy rather than reciting
            policy from memory, since it changes and you'd otherwise be
            guessing at this store's specific terms.

            Use list_categories, list_sub_categories, list_products_in_category,
            list_brands and list_products_by_brand to answer anything about
            what categories, sub-categories, or brands this store has, or
            what's in one — never guess a category/brand name or claim one
            exists without calling the matching tool first. If one of these
            reports nothing found, that category, sub-category or brand
            either doesn't exist or isn't currently available — say so,
            don't suggest it might exist anyway.

            Use get_product_reviews for what customers say about a product
            and its rating, get_featured_products for "what's new" or "what's
            popular", search_blog_posts for anything matching a blog article,
            and estimate_shipping_cost for a delivery-cost question — all
            rather than guessing. estimate_shipping_cost only ever states the
            flat rate and the free-delivery threshold; it has no visibility
            into any specific cart, so never claim it confirmed free delivery
            for this customer's actual order.

            Keep replies short and friendly, suited to a chat widget. This
            widget is a narrow chat bubble, not a full page — never reply
            with a markdown table (pipe characters and a header separator
            row); it doesn't fit and shows as broken text. When listing
            several products, variants, or prices, use short bullet lines
            instead, one per line (e.g. "- Red, Size L — ৳1,000 (flash
            sale)").
            PROMPT;

        if (! $isLoggedIn) {
            return $base."\n\nThis customer is browsing as a guest, not logged in. They can't add to cart, apply a coupon, or check out through you — if they want to buy something, tell them to log in first or use the site's normal Add to Cart button.";
        }

        return $base."\n\n".<<<'PROMPT'
            This customer is logged in. lookup_order can find their own orders
            without any proof of ownership — call it with no order_number to
            get their most recent order.

            You can add items to their real cart, change a quantity or remove
            an item, apply or remove a coupon code they give you, and tell
            them what's currently in their cart — always with the matching
            tool, never by assuming it worked. If add_to_cart reports multiple
            options for a product, ask which one before calling it again.

            You never place the order yourself — there is no tool for that on
            purpose. Once the cart is how the customer wants it, tell them to
            finish at checkout and give them the checkout_url a cart tool's
            result included; don't describe payment methods or delivery
            addresses yourself, since that's the checkout page's job.

            If the customer wants to cancel an order, never guess which one
            or cancel the only order you happen to already know about —
            always confirm the exact order number first, asking for it if
            they haven't given one (or using lookup_order with no number to
            find their most recent order and confirming that's the one they
            mean) before calling cancel_order. If it refuses, explain why in
            the tool's own words rather than inventing a reason.

            The same applies to submit_return_request: confirm the exact
            order number first, the same way. It only works on a delivered
            order still inside the return window, and only returns a
            specific product if the customer named one — otherwise it
            returns everything still eligible on that order, so don't call
            it unless that's really what the customer wants.

            Use get_account_info for their loyalty points balance or
            referral code, list_saved_addresses for their saved delivery
            addresses, and add_to_wishlist/remove_from_wishlist/view_wishlist
            for the wishlist — always with the matching tool, never by
            assuming or inventing a number.
            PROMPT;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function toolDeclarations(bool $isLoggedIn): array
    {
        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_products',
                    'description' => 'Search the store\'s products by name or description keywords.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Search keywords, e.g. "red shoes"'],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'check_product_availability',
                    'description' => 'Check the current price and stock for one specific product by name.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_name' => ['type' => 'string', 'description' => 'The exact or near-exact product name'],
                        ],
                        'required' => ['product_name'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'lookup_order',
                    'description' => 'Look up the status of a customer order. For a guest, order_number plus email or phone are all required before calling this.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'order_number' => ['type' => 'string', 'description' => 'e.g. ORD-20261107-AB12CD'],
                            'email' => ['type' => 'string', 'description' => 'Email used at checkout, if the customer gave one instead of a phone'],
                            'phone' => ['type' => 'string', 'description' => 'Phone used at checkout, if the customer gave one instead of an email'],
                        ],
                        'required' => [],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_current_offers',
                    'description' => 'Get the store\'s currently active flash sales and promotions. Takes no arguments.',
                    // An empty PHP array json_encodes as `[]`, not `{}`, which
                    // fails Groq's strict JSON-Schema validation for
                    // `properties` (must be an object). Force object shape.
                    'parameters' => ['type' => 'object', 'properties' => (object) []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_store_policy',
                    'description' => 'Get this store\'s published policy text on a topic, to answer questions about privacy, shipping, returns, refunds, terms, or the FAQ. Always use this instead of guessing store policy.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'topic' => ['type' => 'string', 'description' => 'One of: privacy, shipping, returns, refund, terms, faq'],
                        ],
                        'required' => ['topic'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_categories',
                    'description' => 'List this store\'s product categories. Takes no arguments.',
                    'parameters' => ['type' => 'object', 'properties' => (object) []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_sub_categories',
                    'description' => 'List the sub-categories under one category.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'category_name' => ['type' => 'string', 'description' => 'The category name, e.g. "Electronics"'],
                        ],
                        'required' => ['category_name'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_products_in_category',
                    'description' => 'List products in a category, or narrower, in one of its sub-categories.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'category_name' => ['type' => 'string', 'description' => 'The category name'],
                            'sub_category_name' => ['type' => 'string', 'description' => 'Optional — a sub-category under that category, if the customer named one'],
                        ],
                        'required' => ['category_name'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_brands',
                    'description' => 'List this store\'s product brands. Takes no arguments.',
                    'parameters' => ['type' => 'object', 'properties' => (object) []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_products_by_brand',
                    'description' => 'List products from one brand.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'brand_name' => ['type' => 'string', 'description' => 'The brand name'],
                        ],
                        'required' => ['brand_name'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_product_reviews',
                    'description' => 'Get a product\'s approved customer reviews and average rating.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_name' => ['type' => 'string', 'description' => 'The product name'],
                        ],
                        'required' => ['product_name'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_featured_products',
                    'description' => 'List products from one of this store\'s curated homepage sections.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'section' => ['type' => 'string', 'description' => 'One of: featured, new_arrivals, best_sellers'],
                        ],
                        'required' => ['section'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'estimate_shipping_cost',
                    'description' => 'Estimate the flat delivery charge to a Bangladeshi district. Does not know whether the customer\'s actual cart would qualify for free delivery — mention the threshold it reports, not a guess about their cart.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'district' => ['type' => 'string', 'description' => 'A Bangladeshi district name, e.g. "Dhaka" or "Chattogram"'],
                            'inside_dhaka_city' => ['type' => 'boolean', 'description' => 'True only if the district is Dhaka and the address is inside Dhaka City Corporation — this gets a lower rate'],
                        ],
                        'required' => ['district'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_blog_posts',
                    'description' => 'Search this store\'s published blog posts by topic.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Topic keywords'],
                        ],
                        'required' => ['query'],
                    ],
                ],
            ],
        ];

        if (! $isLoggedIn) {
            return $tools;
        }

        return [
            ...$tools,
            [
                'type' => 'function',
                'function' => [
                    'name' => 'add_to_cart',
                    'description' => "Add a product to the logged-in customer's cart. If the product has multiple options (color/size/etc.) and none was given, this returns the available options instead of guessing — ask the customer and call again with `variant`.",
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_name' => ['type' => 'string', 'description' => 'Just the bare product name, e.g. "Comfy T-Shirt" — never include price, discount, or description text here even if an earlier message mentioned them together'],
                            'quantity' => ['type' => 'integer', 'description' => 'How many to add, default 1'],
                            'variant' => ['type' => 'string', 'description' => 'e.g. "red, large" — only if the product has options and the customer specified one'],
                        ],
                        'required' => ['product_name'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'update_cart_item',
                    'description' => "Change a product's quantity in the cart, or remove it (quantity 0).",
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_name' => ['type' => 'string', 'description' => 'Just the bare product name already in the cart, e.g. "Comfy T-Shirt" — never price or description text'],
                            'quantity' => ['type' => 'integer', 'description' => 'New quantity — 0 removes it'],
                        ],
                        'required' => ['product_name', 'quantity'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'view_cart',
                    'description' => "See what's currently in the logged-in customer's cart. Takes no arguments.",
                    'parameters' => ['type' => 'object', 'properties' => (object) []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'apply_coupon',
                    'description' => 'Apply a coupon code the customer already gave you to their cart. Never suggest a code yourself — only use one the customer typed or mentioned.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'code' => ['type' => 'string', 'description' => 'The coupon code, e.g. "SAVE10"'],
                        ],
                        'required' => ['code'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'remove_coupon',
                    'description' => "Remove whatever coupon is currently applied to the customer's cart. Takes no arguments.",
                    'parameters' => ['type' => 'object', 'properties' => (object) []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'cancel_order',
                    'description' => 'Cancel one of the logged-in customer\'s own orders by its exact order number. Only works while the order is still pending and has not reached a courier — the tool reports why if it refuses. Never guess the order number: if the customer just says "cancel my order" without one, ask which order, or call lookup_order first to confirm which one they mean.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'order_number' => ['type' => 'string', 'description' => 'e.g. ORD-20261107-AB12CD'],
                        ],
                        'required' => ['order_number'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_account_info',
                    'description' => "Get the logged-in customer's loyalty points balance and referral code. Takes no arguments.",
                    'parameters' => ['type' => 'object', 'properties' => (object) []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_saved_addresses',
                    'description' => "List the logged-in customer's saved delivery addresses. Takes no arguments.",
                    'parameters' => ['type' => 'object', 'properties' => (object) []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'add_to_wishlist',
                    'description' => "Add a product to the logged-in customer's wishlist.",
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_name' => ['type' => 'string', 'description' => 'The product name'],
                        ],
                        'required' => ['product_name'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'remove_from_wishlist',
                    'description' => 'Remove a product from the wishlist.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'product_name' => ['type' => 'string', 'description' => 'The product name'],
                        ],
                        'required' => ['product_name'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'view_wishlist',
                    'description' => "See what's on the logged-in customer's wishlist. Takes no arguments.",
                    'parameters' => ['type' => 'object', 'properties' => (object) []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'submit_return_request',
                    'description' => "Submit a return request for one of the customer's own delivered orders, within the return window. Only works on a DELIVERED order within the window — the tool reports why if it refuses. Never guess the order number: ask which order, or confirm via lookup_order first. If the customer doesn't name a specific product, this returns every still-eligible item on the order.",
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'order_number' => ['type' => 'string', 'description' => 'e.g. ORD-20261107-AB12CD'],
                            'reason' => ['type' => 'string', 'description' => 'Why the customer wants to return it'],
                            'product_name' => ['type' => 'string', 'description' => 'Optional — a specific product on the order, if the customer named one instead of returning everything'],
                        ],
                        'required' => ['order_number', 'reason'],
                    ],
                ],
            ],
        ];
    }
}
