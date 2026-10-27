# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/socialite (SOCIALITE) - v5
- laravel/wayfinder (WAYFINDER) - v0
- tightenco/ziggy (ZIGGY) - v2
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/react (INERTIA_REACT) - v3
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `fortify-development` — ACTIVATE when the user works on authentication in Laravel. This includes login, registration, password reset, email verification, two-factor authentication (2FA/TOTP/QR codes/recovery codes), profile updates, password confirmation, or any auth-related routes and controllers. Activate when the user mentions Fortify, auth, authentication, login, register, signup, forgot password, verify email, 2FA, or references app/Actions/Fortify/, CreateNewUser, UpdateUserProfileInformation, FortifyServiceProvider, config/fortify.php, or auth guards. Fortify is the frontend-agnostic authentication backend for Laravel that registers all auth routes and controllers. Also activate when building SPA or headless authentication, customizing login redirects, overriding response contracts like LoginResponse, or configuring login throttling. Do NOT activate for Laravel Passport (OAuth2 API tokens), Socialite (OAuth social login), or non-auth Laravel features.
- `laravel-best-practices` — Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, validation, error handling, queue and job configuration, route definitions, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns.
- `socialite-development` — Manages OAuth social authentication with Laravel Socialite. Activate when adding social login providers; configuring OAuth redirect/callback flows; retrieving authenticated user details; customizing scopes or parameters; setting up community providers; testing with Socialite fakes; or when the user mentions social login, OAuth, Socialite, or third-party authentication.
- `wayfinder-development` — Use this skill for Laravel Wayfinder which auto-generates typed functions for Laravel controllers and routes. ALWAYS use this skill when frontend code needs to call backend routes or controller actions. Trigger when: connecting any React/Vue/Svelte/Inertia frontend to Laravel controllers, routes, building end-to-end features with both frontend and backend, wiring up forms or links to backend endpoints, fixing route-related TypeScript errors, importing from @/actions or @/routes, or running wayfinder:generate. Use Wayfinder route functions instead of hardcoded URLs. Covers: wayfinder() vite plugin, .url()/.get()/.post()/.form(), query params, route model binding, tree-shaking. Do not use for backend-only task
- `pest-testing` — Use this skill for Pest PHP testing in Laravel projects only. Trigger whenever any test is being written, edited, fixed, or refactored — including fixing tests that broke after a code change, adding assertions, converting PHPUnit to Pest, adding datasets, and TDD workflows. Always activate when the user asks how to write something in Pest, mentions test files or directories (tests/Feature, tests/Unit, tests/Browser), or needs browser testing, smoke testing multiple pages for JS errors, or architecture tests. Covers: test()/it()/expect() syntax, datasets, mocking, browser testing (visit/click/fill), smoke testing, arch(), Livewire component tests, RefreshDatabase, and all Pest 4 features. Do not use for factories, seeders, migrations, controllers, models, or non-test PHP code.
- `inertia-react-development` — Develops Inertia.js v3 React client-side applications. Activates when creating React pages, forms, or navigation; using <Link>, <Form>, useForm, useHttp, setLayoutProps, or router; working with deferred props, prefetching, optimistic updates, instant visits, or polling; or when user mentions React with Inertia, React pages, React forms, or React navigation.
- `tailwindcss-development` — Always invoke when the user's message includes 'tailwind' in any form. Also invoke for: building responsive grid layouts (multi-column card grids, product grids), flex/grid page structures (dashboards with sidebars, fixed topbars, mobile-toggle navs), styling UI components (cards, tables, navbars, pricing sections, forms, inputs, badges), adding dark mode variants, fixing spacing or typography, and Tailwind v3/v4 work. The core use case: writing or fixing Tailwind utility classes in HTML templates (Blade, JSX, Vue). Skip for backend PHP logic, database queries, API routes, JavaScript with no HTML/CSS component, CSS file audits, build tool configuration, and vanilla CSS.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>

## Project Overview

ShoppyDrive is a Bangladesh-focused ecommerce storefront + admin panel built on the Laravel React starter kit (Laravel 13, Inertia v3, React 19, Tailwind v4). It has two front ends sharing one Inertia app: a public storefront and an `/admin` back office, each with its own auth guard.

## Common Commands

```
composer dev                              # server + queue + pail + Vite (also runs ziggy:generate)
composer test                             # config:clear + pint --test + full test suite
composer ci:check                         # eslint + prettier + tsc, then `composer test`
vendor/bin/pint --dirty --format agent    # run before finishing any PHP change
npm run lint / npm run format / npm run types:check
```

GitHub Actions `tests.yml`/`lint.yml` are disabled (`if: false`), so nothing checks changes remotely — run `composer ci:check` locally. `deploy.yml` deploys to Hostinger over SSH.

## Architecture

**Dual auth guards.** `config/auth.php` defines `web` (`App\Models\User`, customers) and `admin` (`App\Models\Admin`, staff). Admin routes live in `routes/admin.php` behind the `admin` (`EnsureAdmin`) and `redirect-customer-from-admin` middleware aliases; `RedirectAdminToDashboard` is appended globally to the web group. Exactly one `Admin` has `is_mother = true` (super admin), created/replaced only via `php artisan admin:create-mother`. `is_mother` is deliberately excluded from `$fillable` and set only via `forceFill()` in that command — never make it mass-assignable. Roles are intentionally binary: in `Admin\AdminController`, only the mother can create admins or edit/delete *other* admins; every admin can edit their own account; nobody but the mother can edit the mother. `resources/js/pages/admin/admins/index.tsx` hides unavailable actions, but enforcement is server-side. Admin login (`AdminAuthController`) is hand-rolled with `Auth::guard('admin')->attempt()`, bypassing Fortify, so it uses its own `admin-login` rate limiter (`AppServiceProvider`, `throttle:admin-login`).

**Social login** (`Auth\SocialAuthController`) uses Socialite for customer Google/Facebook OAuth (`GOOGLE_*`/`FACEBOOK_*` env vars), `web` guard only.

**Email verification is a custom 6-digit OTP, not Fortify's signed link.** `User::sendEmailVerificationNotification()` generates a hashed code (15-min expiry, 60s resend cooldown) mailed via `VerifyEmailCodeMail`; `Auth\EmailVerificationCodeController` verifies it, throttled by the `verification-code` limiter. `App\Http\Responses\LoginResponse`/`RegisterResponse` send unverified users to `verification.notice`. `account.php` and `settings.php` require `auth, verified`. Cart/checkout instead uses `checkout.access` (`EnsureCheckoutAccess`): guests pass only when the "Allow Guest Checkout" setting is on, and logged-in unverified customers are still sent to verify.

**Product variants are color×size, not generic attribute sets.** `Attribute` (`AttributeType` enum) has many `AttributeValue`s; `AttributeValue` attaches to `Product` via the `product_attribute_values` pivot (filter/display) and is referenced by `ProductVariant` through `color_value_id`/`size_value_id`. Each variant has its own price/cost/stock, an `attributes` JSON column, and `is_default`.

**Product bundles.** `ProductBundle`/`ProductBundleItem` with `BundleDiscountType` (`FixedPrice` or `FreeDelivery`). `App\Services\BundleService` is the single place bundle logic lives: `unitPriceFor` recomputes from live prices (never stored), `reconcile`/`autoAssemble` keep cart groupings valid, `itemQualifiesForFreeDelivery` feeds shipping. `App\Support\CartManager` calls it. `bundle_id` is carried onto `CartItem`/`OrderItem`; `InventoryService` still decrements stock per component item.

**Stock, flash-sale pricing, and coupon usage are enforced centrally, not inline.** `InventoryService::decrementForOrderItem()` is the single place stock and flash-sale caps are checked/decremented — called from every order-creation path (COD, ZiniPay, mobile banking, landing page, admin manual order) inside that path's `DB::transaction()` with `lockForUpdate()`. `FlashSaleItem::findActiveFor()` alone decides whether something is on flash sale; both `CartManager::resolvePrice()` and `InventoryService` use it. `CartManager::revalidatePrices()` refuses to charge a different amount than the checkout page showed. `CouponService::consume()`/`release()` count `Coupon.used_count`/`CouponUse` at actual order creation (shared eligibility check: `Coupon::eligibilityError()`) and release on cancellation/refund via the same `Order::booted()` hook that restores stock. `InventoryLog` is the audit trail. `StockReservation` is unwired scaffolding by design — stock uses direct decrement/restore, not hold-then-release.

**Returns.** Customers submit via `ReturnRequestController@store` (`routes/account.php`); admins approve/reject in `Admin\ReturnController` (`ReturnStatus`: `PENDING → APPROVED|REJECTED`), which sends `ReturnApprovedMail`/`ReturnRejectedMail`.

**Referrals and loyalty points.** Don't reimplement either inline in controllers. `App\Services\ReferralService`: `referral_code` → `referred_by_id` at registration (`CreateNewUser`/`SocialAuthController`), the referred user's one-time first-order discount, and referrer rewards (gated by the `referral_enabled` Setting). `App\Services\LoyaltyPointsService` is the general points ledger (`points_balance` + `ReferralPointTransaction`): redemption at checkout (`redeemPoints`/`debitPointsForOrder`), reversal on cancel/refund (`handleOrderCancelledOrRefunded`), registration/review/daily-login bonuses, and delayed order-earn points granted by the scheduled `rewards:grant-due` command. Both are idempotent per order — call them inside the order-creation transaction on a locked user row to avoid double-awarding.

**SSR is selectively disabled per-route.** `HandleInertiaRequests::$withoutSsr` lists request-path patterns (`$request->is()`, not component names) that skip SSR: admin, auth, account/settings, and cart/checkout (which render from the client-only zustand cart and would hydration-mismatch). `foo/*` does not match bare `/foo`, so list index routes separately; `tests/Feature/HandleInertiaRequestsSsrExclusionTest.php` asserts the intended paths match.

**Routing.** Split by domain, required from `routes/web.php`: `admin.php` (`/admin`, `admin.*`), `auth.php`, `catalog.php`, `commerce.php`, `account.php`, `settings.php`. `api.php` (search suggestions plus the ZiniPay/Steadfast/SMS-forwarder webhooks — there is no public JSON API) is registered separately via `withRouting(api: ...)`. Static pages, blog, newsletter, and landing pages are declared directly in `web.php`. Frontend uses Wayfinder (`@/actions`, `@/routes`) **and** Ziggy: `resources/js/app.tsx` sets a global `route()` preferring the live `window.Ziggy`, falling back to `resources/js/ziggy.js` — a gitignored generated file (`php artisan ziggy:generate`, run by `composer dev`/`setup`/deploy). If Vite fails to resolve `./ziggy`, regenerate it; never commit it (a committed localhost snapshot once broke production `route()` calls).

**Rate limiting is admin-tunable from Admin › Security** (`SecuritySettingsController`, mother-only to save). `App\Support\SecurityRateLimits` holds every limit as a `*_times`/`*_minutes` pair with default/min/max, stored in one `Setting` row and clamped on read, so a bad value can never disable a limit. Named limiters are registered in `AppServiceProvider` and attached per route via `throttle:<name>`; Fortify's own routes (register, forgot-password, confirm-password, profile-information) get theirs via the `fortify.throttle` middleware (`ThrottleFortifyActions`, added to `config/fortify.php` `middleware`) because Fortify registers those routes itself — attach by route name, never by mutating routes at runtime (deploy runs `route:cache`). `SecurityRateLimits::tooManyAttempts()` picks the response: JSON 429 for fetch callers, the themed 429 page for GETs (never a redirect — that would loop), toast + `rate_limit` validation error for form posts. Logged-in customers are keyed by user id, not IP (Bangladeshi mobile networks share IPs). `admin-login`, `webhook` and `sms-forwarder` limits are deliberately hardcoded, not admin-tunable; `sms-forwarder` only counts wrong-token requests so the real bKash/Nagad forwarder phone is never throttled. `cart.sync` is deliberately unthrottled. No trusted proxies are configured, so all IP-based limits assume the app sees real client IPs.

**Global middleware** (`bootstrap/app.php`): `RedirectToCanonicalDomain` (www → canonical), `TrackDailyVisit` (increments `DailyVisit` once per day per visitor via `sd_last_seen` cookie, skipping bots and `admin`/`api`/`storage`/`_boost`/`up`; feeds the admin dashboard chart, separate from consent-gated GA in `resources/js/lib/analytics.ts`), and `CaptureTrafficSource` (cookie that `Order::booted()` writes onto each order). The `blocked-ip` alias (`BlockBlockedIp`, `BlockedIp` model) is applied only to order-placement POSTs, never to payment/courier webhooks.

**Payments**:
- `ZiniPayService` — hosted gateway (invoice creation + `ZiniPayWebhookController`).
- `MobilePaymentController` + `SmsParserService` — manual bKash/Nagad flow: the customer submits a transaction ID, and an SMS forwarded with the `sms_forwarder` token (`config/services.php`) is parsed against `SmsPattern` records to auto-verify.

**Couriers.** `PathaoCourierService` and `SteadfastCourierService`, with credentials in the `Setting` model (not `.env`). Map raw courier statuses to `OrderStatus` only in `CourierOrderStatusMapper` (`PATHAO_MAP`/`STEADFAST_MAP`). Its `sync()` treats `CANCELLED`/`REFUNDED` as terminal: late courier reports are recorded but never move status or `payment_status` — this is the single choke point for all call sites. Pathao syncs via scheduled `pathao:sync-statuses`; Steadfast via `SteadfastWebhookController`, which verifies HMAC-SHA256 `X-Signature` against the `steadfast_webhook_secret` Setting and fails closed (401) if unset.

**Shipping and tax.** `CheckoutController::calculateShippingCost()` checks the free-delivery threshold from `HomepageSetting` (`announcement_bar`), then per-product `Product::free_delivery` or intact free-delivery bundles, then the admin-configurable `Setting::shippingRateFor($state, $city)` — also used by `HandleInertiaRequests` for shared props. Tax is hardcoded to `0`. `ShippingZone`/`ShippingMethod`/`ShippingRate`/`TaxRate` are unwired scaffolding.

**Content.** Static/legal pages are the admin-editable `Page` model by `slug` (`is_published`), rendered by `resources/js/pages/static/Page.tsx`. `Blog` is a separate type (`scopePublished()`), managed by `Admin\BlogController` and served at `/blog`, `/blog/{blog:slug}`.

**Orders** use `SoftDeletes`; `Admin\OrderController` has a trash flow (`orders.trashed`, `orders.restore`/`orders.forceDelete` routed `->withTrashed()`) plus manual order creation (`admin.orders.store`). Lifecycle: `OrderStatus`/`PaymentStatus`/`PaymentMethod` enums, `OrderStatusHistory`, `OrderService`.

**`PendingCheckout`** stages checkouts awaiting external confirmation (mobile banking SMS, ZiniPay webhook, landing-page/guest buy-now) before an `Order` exists; used across `CheckoutController`, `LandingPageController`, `MobilePaymentController`, `PaymentController`, `ZiniPayWebhookController`, `OrderService`.

**Abandoned checkouts.** `IncompleteOrder` is upserted by `IncompleteOrderController::store()` on a per-visit `attempt_token` once a valid phone + items exist, and deleted when an order is placed with that phone (`CheckoutController::store()`, `OrderService::createFromPending()`). Hourly `cart:remind-abandoned` dispatches `AbandonedCartReminderJob` for rows ≥1h old (deduped via `reminder_sent_at`), only when `user_id` is set — guests have phone only.

**Transactional email is dispatched centrally from `Order::booted()`**, not controllers. On `created`: `OrderPlacedMail` (customer) + `NewOrderAlertMail` (mother admin) — each order-creation path has its own idempotency guard so retries can't double-send. On status change (`wasChanged('status')`): `ShippedWithTrackingMail`/`OrderDeliveredMail`/`OrderCancelledMail`, with `OrderStatusUpdatedMail` as fallback (`processing`, `refunded`). Recipients come from the order's `user`/`shipping_address`, never the session (status changes are made by admins/webhooks). All order/admin mailables are `ShouldQueue` + `->afterCommit()`. `WelcomeMail` fires on first email verification (not registration). `ReviewApprovedMail` on `Admin\ReviewController::approve()`. `LowStockAlertMail` only on the crossing of `low_stock_threshold`. `BackupFailedMail` is an unwired stub.

**Landing pages** (`LandingPage`, `LandingPageController`, `resources/js/pages/landing/`, `/lp/{slug}`) are a separate single-product guest-checkout funnel with their own order/payment/confirmation flow.

**Homepage merchandising.** `HomepageSetting` keyed by `section_key` (`announcement_bar`, `hero_slider`, `flash_sale`, `featured_products`, `new_arrivals`, `best_sellers`, `brands`, …) with a `metadata` JSON column; `Admin\HomepageController` special-cases each section plus `reorder`. `HeroSlide` and `PromotionalBanner` have their own admin controllers.

**Scheduler** (`routes/console.php`): `pathao:sync-statuses` (15 min), `rewards:grant-due` (daily 01:00 Asia/Dhaka), `cart:remind-abandoned` (hourly). `php artisan sitemap:generate` is not scheduled — run manually or via external cron.

**Caching.** Shared layout data every page loads (settings incl. delivery rates, menu categories, homepage sections, hero slides, promo banners, popular keywords) comes from `App\Support\LayoutCache`, cached forever and cleared on save/delete/restore by the `FlushesLayoutCache` trait (each model lists its keys in `LAYOUT_CACHE_KEYS`). Bulk query-builder `update()`/`delete()` fires no model events, so any new bulk write to those tables must call `LayoutCache::forget(...)` itself (see the category/homepage reorder and keyword bulk-delete actions). **Cache plain arrays, never model objects**: `cache.serializable_classes` is `false`, so objects read back from the file store become `__PHP_Incomplete_Class` — the in-memory `array` store used in tests hides this, which is why `LayoutCacheTest` has a real file-store test. Search-bar suggestions (`/api/search/suggestions`) are cached per exact query by `App\Support\SearchSuggestionCache` (5-minute backstop TTL for scheduled `published_at`; stored JSON-round-tripped; empty and >64-char queries never stored, so junk can't fill the disk), flushed via a version stamp by the `FlushesSearchSuggestions` trait on Product/ProductMedia/Brand/Category/SubCategory — only when a column in the model's `SEARCH_SUGGESTION_FIELDS` changes, so stock decrements don't flush. The homepage featured-products and brands bulk updates flush it explicitly. `spatie/laravel-responsecache` is installed but not configured or used — there is no full-page caching (pages carry per-visitor cart/auth data). Deploy runs `cache:clear` and `event:cache`; `public/.htaccess` sets long browser caching for hashed `/build` assets and 30 days for images/fonts.

**Database**: MySQL locally; SQLite in-memory for tests (`phpunit.xml`, queue `sync`, mail `array`). Cache and sessions use the `file` driver (Hostinger shared hosting, no Redis); rate-limit counters stay on the `database` store via `cache.limiter` because file-store increments aren't atomic. Queue uses `database`. The server's `.env` is never deployed, so driver changes there are manual.

**Frontend**: shadcn/ui (`new-york`, `neutral`) in `resources/js/components/ui` + `cn()` from `resources/js/lib/utils` for shared primitives. Admin pages are visually separate from storefront pages — follow the matching `resources/js/pages/<domain>/` folder. Cross-page client state lives in zustand stores (`resources/js/stores`: `cartStore`, `wishlistStore`, `uiStore`).

**PDF invoices**: `barryvdh/laravel-dompdf` renders `invoices.order`, used from `routes/account.php` and `LandingPageController`.
