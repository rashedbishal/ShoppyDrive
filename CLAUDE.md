# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Mandatory Session-Resume Protocol (pre-launch remediation, since 2026-11-13)

Every session, before any ShoppyDrive work:

1. Read this file.
2. Read the **"PRODUCTION REMEDIATION CONTROL" section at the top of `docs/SHAPPYDRIVE_AUDIT_REMEDIATION_TRACKER.md`**: its mission, current focus, findings register, work history and deferred list.
3. Check `git status` and the current commit. Compare them with the recorded progress; report and reconcile any discrepancy rather than trusting either side.
4. Continue from the recorded current focus. Never restart the discovery audit unless the owner asks.
5. Never implement a finding before presenting it to the owner and receiving explicit approval. One finding per approval; fix only the approved scope.
6. Update that section immediately after every change.

## Mandatory ShoppyDrive Session Handoff

Before doing anything on ShoppyDrive, read these files completely, in this order:

1. `CLAUDE.md`
2. `docs/SHAPPYDRIVE_PROJECT_HANDOFF_2026-11-03.md` — current state, local commits, test results, next task
3. `docs/SHAPPYDRIVE_AUDIT_REMEDIATION_TRACKER.md` — **operational status source of truth**
4. `docs/SHOPPYDRIVE_FULL_SYSTEM_FORENSIC_AUDIT_REPORT_2026-11-02.md` — **original audit source of truth**
5. `docs/SHAPPYDRIVE_PRODUCTION_READONLY_CHECKS.sql` — verified read-only production checks

Rules:
- Never assume previous conversation memory exists. Verify Git state and the tracker yourself.
- Never repeat completed work without new evidence.
- Never push or deploy without explicit approval. Pushing `main` auto-deploys to Hostinger and runs production migrations.
- Never reset, stash, discard or overwrite user changes.
- Preserve all finding IDs and commit history.
- Never run production SQL or migrations. The user runs the read-only checks, and only with their approval.
- Ask before modifying production configuration or `.github/workflows/deploy.yml`.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.3. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

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

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

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

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>

## Project Overview

ShoppyDrive is a Bangladesh-focused ecommerce storefront + admin panel built on the Laravel React starter kit (Laravel 13, Inertia v3, React 19, Tailwind v4). It has two front ends sharing one Inertia app: a public storefront and an `/admin` back office, each with its own auth guard.

## ShoppyDrive Remediation Tracking

Findings are defined by `docs/SHOPPYDRIVE_FULL_SYSTEM_FORENSIC_AUDIT_REPORT_2026-11-02.md` (FSA-01–24) and `docs/SHAPPYDRIVE_FORENSIC_AUDIT_REPORT.txt` (CRIT/HIGH/MED/LOW). `docs/SHAPPYDRIVE_AUDIT_REMEDIATION_TRACKER.md` is the operational source of truth for status — its "Reconciled status" table at the top. Don't create other tracking documents. Every session:

1. Read the tracker's reconciled table, and the report entry for the finding you'll work on, before changing anything.
2. Run `git status` first and treat uncommitted changes as someone's work.
3. Resume from the tracker's priority order — one finding at a time. Never redo a fix the tracker records as tested or deployed.
4. Update the tracker immediately after each implementation (status, files, tests actually run, remaining work).
5. Keep finding IDs, accepted risks and recorded decisions intact; never renumber or drop entries.
6. Claim `DEPLOYED_UNVERIFIED` only with deploy-run evidence, and `VERIFIED_IN_PRODUCTION` only with production evidence supplied by the owner.

While remediation work is in flight, these hold until the user explicitly approves each step separately — approval of one step never extends to the next:
- Never stage, commit, push or deploy; pushing `main` auto-deploys to Hostinger and migrates production. Never access production or modify `deploy.yml`.
- Treat every uncommitted change as intentional. Never reset, revert, overwrite or `git stash`/`checkout` it, and never pop, drop or apply `stash@{0}`. When editing a file that already carries another task's uncommitted change, leave that change byte-for-byte intact.
- Tests run on in-memory SQLite. Any MySQL test or migration runs only on a disposable `shoppydrive_test_*` database whose identity you verified first — never the dev `ShoppyDrive` database (unless that migration was approved) or production.

MySQL-only `*ConcurrencyRaceTest.php` files skip on SQLite and call controllers directly (including inside PHP heredoc subprocess scripts), so a green SQLite suite does not cover them — grep them for call sites whenever a controller/service signature changes. On MySQL, query-builder `sum()` returns a string; cast before strict comparisons.

## Common Commands

```
composer setup                            # first run: install, .env, key, migrate, ziggy:generate, npm build
composer dev                              # server + queue + pail + Vite (also runs ziggy:generate)
php artisan test --compact tests/Feature/FooTest.php   # single file; or --filter=testName
composer test                             # config:clear + pint --test + full test suite
composer ci:check                         # eslint + prettier + tsc, then `composer test`
vendor/bin/pint --dirty --format agent    # run before finishing any PHP change
npm run lint / npm run format / npm run types:check
npm run build:ssr                         # client + SSR bundles
```

GitHub Actions `tests.yml` and `lint.yml` run on every push/PR (re-enabled; previously `if: false`). `deploy.yml` (triggers on push to `main`) runs a `checks` job (Pint + the full Pest suite) that `deploy` `needs:` — PHP checks failing blocks deployment. ESLint/Prettier/TypeScript run in `lint.yml` for visibility only; they do **not** gate `deploy.yml` (GitHub Actions has no cross-workflow `needs`), and `lint.yml` has passed on `main` since the 2026-11 cleanup (confirmed on `7d0ff65`), but it still doesn't gate deploys, so check its run rather than assume it's green. `deploy.yml` deploys to **Hostinger shared hosting** (not a VPS) over SSH/rsync and runs `php artisan migrate --force` against production as part of the post-deploy script, with no manual approval gate between a passing `checks` job and that migration running. Still run `composer ci:check` locally before pushing.

After adding or renaming any named route, regenerate the local Ziggy snapshot so SSR and the fallback path stay current:
```
php artisan ziggy:generate
```
`composer dev`/`setup`/deploy already run this, so it's rarely needed by hand. It used to be load-bearing for the browser too — a `resources/js/app.tsx` bug meant the browser-side `route()` helper silently used this stale snapshot instead of the live route list, so a brand-new route would throw `Ziggy error: route 'x' is not in the route list` from the browser alone until this was re-run, with nothing wrong in `php artisan route:list`, `curl`, or even view-source — see the Ziggy note in Architecture below. That's fixed now; the browser always reads the live list.

## Architecture

**Dual auth guards.** `config/auth.php` defines `web` (`App\Models\User`, customers) and `admin` (`App\Models\Admin`, staff). Admin routes live in `routes/admin.php` behind the `admin` (`EnsureAdmin`) and `redirect-customer-from-admin` middleware aliases; `RedirectAdminToDashboard` is appended globally to the web group. Exactly one `Admin` has `is_mother = true` (super admin), created/replaced only via `php artisan admin:create-mother`. `is_mother` is deliberately excluded from `$fillable` and set only via `forceFill()` in that command — never make it mass-assignable. Managing *other admins* is intentionally binary: in `Admin\AdminController`, only the mother can create admins or edit/delete other admins; every admin can edit their own account; nobody but the mother can edit the mother. `resources/js/pages/admin/admins/index.tsx` hides unavailable actions, but enforcement is server-side. Admin login (`AdminAuthController`) is hand-rolled with `Auth::guard('admin')->attempt()`, bypassing Fortify, so it uses its own `admin-login` rate limiter (`AppServiceProvider`, `throttle:admin-login`).

**Beyond that, access to everything else is per-section and granular.** `App\Support\AdminSections::SECTIONS` is the single catalog of gateable sections (products, orders, settings, blocked IPs, …), each listing which of `create`/`update`/`delete` it actually supports — `view` is always implied. The mother admin bypasses all of it (`Admin::canAccess()` short-circuits true); every other admin starts with no access (`permissions` column, nullable JSON) until the mother grants sections via the Permissions matrix on Admin ▸ Admins create/edit (`PermissionMatrix.tsx`), which the sidebar (`admin-layout.tsx`) also filters by. Routes are gated with the `admin.can:<section>,<ability>` middleware (`EnsureAdminPermission`, registered in `bootstrap/app.php`); `AdminSections::sanitize()` is the only path that writes the `permissions` column, allow-listing unknown sections/abilities so a crafted payload can't grant more than the catalog allows. `Settings` is one controller shared across six sections (`SettingController` gates per field group, not per route) and `Security`'s own save stays mother-only regardless of the matrix (`SecuritySettingsController`). `Admins` management and the `Dashboard` are deliberately excluded from the catalog — see the docblock on `AdminSections` for why.

**Social login** (`Auth\SocialAuthController`) uses Socialite for customer Google/Facebook OAuth (`GOOGLE_*`/`FACEBOOK_*` env vars), `web` guard only.

**The Audit Trail** (`spatie/laravel-activitylog`) logs create/update/delete on most admin-managed models (`Product`, `Category`, `SubCategory`, `Brand`, `Attribute`, `AttributeValue`, `Coupon`, `FlashSale`, `ProductBundle`, `HomepageSetting`, `HeroSlide`, `PromotionalBanner`, `Blog`, `Page`, `LandingPage`) via the shared `App\Concerns\Auditable` trait (`logFillable()->logOnlyDirty()`); `Admin` and `Setting` implement `getActivitylogOptions()` directly instead, so neither a hashed password nor a `Setting.value` (where courier/gateway/webhook secrets live) ever gets written to the log. `Order` is narrower still — `logOnly(['status'])` plus `$recordEvents = ['created', 'updated']` — capturing only the two events promised on the customer side: an order placed, and its status changing, not every field a courier webhook touches. `AdminAuthController::login()`/`logout()` write explicit entries since the admin guard's hand-rolled flow has no framework event to hook. Causer attribution (automatic via model events, or manual via `activity()->causedBy()->log()`) comes from one resolver in `AppServiceProvider::configureActivityLogCauser()` — `Auth::guard('admin')->user() ?? Auth::guard('web')->user()` — since Spatie's package only auto-resolves a single guard and this app has two. Viewing it is just another `AdminSections` entry (`audit_trail`, view-only — no create/update/delete, like `security`), gated by the normal `admin.can:audit_trail,view` middleware; clearing it (`AuditTrailController::destroy()`) is hardcoded mother-only regardless of that permission (also enforced by the `admin.mother` route middleware), the same pattern as Security's own save action, and requires re-entering her password through `MotherPasswordGate` (shared 5-wrong-passwords lockout). Order trash/restore/permanent delete are logged too (`Order::booted()`), and permanent-deletion attempts additionally go to `order_deletion_audits`, which clearing the audit trail does not touch.

**Email verification is a custom 6-digit OTP, not Fortify's signed link.** `User::sendEmailVerificationNotification()` generates a hashed code (15-min expiry, 60s resend cooldown) mailed via `VerifyEmailCodeMail`; `Auth\EmailVerificationCodeController` verifies it, throttled by the `verification-code` limiter. `App\Http\Responses\LoginResponse`/`RegisterResponse` send unverified users to `verification.notice`. `account.php` requires `auth, verified`, except its profile, email-change and connected-accounts routes, which deliberately don't (a customer who mistyped their email at sign-up must be able to correct it); `settings.php` now only redirects the old `/settings/*` URLs to the matching Account pages. Cart/checkout instead uses `checkout.access` (`EnsureCheckoutAccess`): guests pass only when the "Allow Guest Checkout" setting is on, and logged-in unverified customers are still sent to verify.

**Product variants are built from any attributes (Color, Size, Set, …).** Admin › Attributes manages `Attribute`s (name unique ignoring case — enforced by `AttributeRequest`, not an index, since fixtures/old data may hold a second "Color"; new attributes are always type select (text options) and edits never change the type, so only the built-in Color shows swatches; `has_images` = "Images per value") and their `AttributeValue`s; an attribute or value anything uses can't be deleted (`isInUse()`). A product lists its variant attributes in display order (`products.variant_attribute_ids`); each `ProductVariant` stores one value per attribute in that order (`attribute_value_ids`, identity = the sorted set; `valueIds()` falls back to `color_value_id`/`size_value_id` for rows from before) plus `attributes` labels ({"Color": "Red", "Set": "12pcs"}) that the storefront matches on by name — renaming an attribute or value rebuilds them (`VariantAttributeLabels`, from `Attribute`/`AttributeValue::booted()`). MySQL's JSON type reorders object keys, so label order isn't the product's attribute order. `product_attribute_values` pivot = the values current variants use (filter/display). The old `has_color_variant`/`has_size_variant` flags and `color_value_id`/`size_value_id` columns are kept current only for rollback, and the latter only for products built from Color/Size alone (`product_variants_product_color_size_unique` would reject Blue/M/6pcs + Blue/M/12pcs); `ProductRequest` still accepts the old Color/Size payload and translates it. Images are per attribute value (`product_media.attribute_value_id`, tag `value:{id}`, for attributes with `has_images`; a product may have several), and `ProductVariant::media()` deliberately returns the whole product's media (hidden from JSON) so `ProductVariant::image` — own image, else its values' images in attribute order — stays one eager-loaded query. Order lines keep the options bought in `product_snapshot.variant_attributes` (all order paths); `OrderItem::variantLabel()` prefers it over the live variant. Each variant has its own price/cost/stock and `is_default`.

**Product bundles.** `ProductBundle`/`ProductBundleItem` with `BundleDiscountType` (`FixedPrice` or `FreeDelivery`). `App\Services\BundleService` is the single place bundle logic lives: `unitPriceFor` recomputes from live prices (never stored), `reconcile`/`autoAssemble` keep cart groupings valid, `itemQualifiesForFreeDelivery` feeds shipping. `App\Support\CartManager` calls it. `bundle_id` is carried onto `CartItem`/`OrderItem`; `InventoryService` still decrements stock per component item.

**Stock, flash-sale pricing, and coupon usage are enforced centrally, not inline.** `InventoryService::decrementForOrderItem()` is the single place stock and flash-sale caps are checked/decremented — called from every order-creation path (COD, mobile banking, landing page, admin manual order, incomplete-order conversion) inside that path's `DB::transaction()` with `lockForUpdate()`. `FlashSaleItem::findActiveFor()` alone decides whether something is on flash sale; both `CartManager::resolvePrice()` and `InventoryService` use it. `CartManager::revalidatePrices()` refuses to charge a different amount than the checkout page showed. `CouponService::consume()`/`release()` count `Coupon.used_count`/`CouponUse` at actual order creation (shared eligibility check: `Coupon::eligibilityError()`) and release on cancellation/refund via the same `Order::booted()` hook that restores stock. `InventoryLog` is the audit trail. `StockReservation` is unwired scaffolding by design — stock uses direct decrement/restore, not hold-then-release. `PendingCodLimit` (BIZ-011) separately caps one phone number to 10 units of the same product across cash-on-delivery orders not yet handed to a courier (pending/processing, no Pathao/Steadfast consignment yet) — a COD order costs nothing to place, so without this a single fake order could tie up stock as "out of stock" until staff cancel it; call inside the order-creation transaction, before stock is decremented.

**The global `route()` helper reads the live route list via the bare lexical `Ziggy` binding, not `window.Ziggy`.** `resources/js/app.tsx` sets its own `globalThis.Ziggy` to `typeof Ziggy !== 'undefined' && Ziggy ? Ziggy : ziggyConfig` (the gitignored, generated `./ziggy` snapshot). This used to check `window.Ziggy` instead, which is never actually set: `@routes`' default `Output\Script` renders `<script>const Ziggy={...live route data...};{route.umd.js source}</script>`, and a top-level `const`/`let` in a classic script creates a lexical binding shared across every script/module in the document, not a `window` property. That bug silently fell back to the stale `./ziggy` snapshot on every page load regardless of how current the live server's route list was — a brand-new or renamed route would throw `Ziggy error: route 'x' is not in the route list` from the browser alone, with nothing wrong in `php artisan route:list`, `curl`, or even the raw page source (view-source), and no amount of hard-refreshing, incognito, or restarting the dev server fixed it, since the snapshot itself was the problem, not anything cached. Reading the bare `Ziggy` identifier instead — exactly how ziggy-js's own bundled code resolves it — fixed this for good; the `./ziggy` snapshot is now only a fallback for SSR, where no such script ran. Still worth keeping current via `php artisan ziggy:generate` (which `composer dev`/`setup`/deploy already run) for the SSR path and as a sane fallback, but the browser no longer depends on it being fresh.

**Returns.** Customers submit via `ReturnRequestController@store` (`routes/account.php`); admins approve/reject in `Admin\ReturnController` (`ReturnStatus`: `PENDING → APPROVED|REJECTED`), which sends `ReturnApprovedMail`/`ReturnRejectedMail`.

**Refunds and courier delivery verification (PAY-RETURN-001).** Every refund — return-approval (`RefundService::recordReturnRefund()`) or whole-order (`recordOrderRefund()`, from `Admin\OrderController`) — is a row in the shared `order_refunds` ledger, written under the `Order` row lock; never write refund state elsewhere. `RefundCalculator` is the only money math (integer poisha; cap = min(collected − shipping − tax, discounted product value); COD collected = `round(total)`; shipping is never refundable; a whole-order refund must equal the exact remaining amount). Loyalty `REVOKE_ORDER` reversal is proportional to the refunded product amount and `points_balance` may go negative (referral revocation must never increase points). Courier-handled orders can only be refunded after a mother-admin verification of each exact consignment (`OrderDeliveryVerificationService`, `orders.deliveryVerifications.store`) against append-only `courier_delivery_evidence` (`AppendOnly` trait; writers fixed by `tests/Unit/CourierDeliveryEvidenceArchTest.php`) plus a current readiness attestation (Admin › Courier readiness, `courierReadiness.*`, `courier:release-fingerprint`); `CourierEvidenceChain::eligibility()` fails closed on missing/contradictory evidence. Automatic courier-based refund eligibility is deliberately **not** enabled. The business rules are approved — see the backlog entry before changing any of this.

**Referrals and loyalty points.** Don't reimplement either inline in controllers. `App\Services\ReferralService`: `referral_code` → `referred_by_id` at registration (`CreateNewUser`/`SocialAuthController`), the referred user's one-time first-order discount, and referrer rewards (gated by the `referral_enabled` Setting). `App\Services\LoyaltyPointsService` is the general points ledger (`points_balance` + `ReferralPointTransaction`): redemption at checkout (`redeemPoints`/`debitPointsForOrder`), reversal on cancel/refund (`handleOrderCancelledOrRefunded`), registration/review/daily-login bonuses, and delayed order-earn points granted by the scheduled `rewards:grant-due` command. Both are idempotent per order — call them inside the order-creation transaction on a locked user row to avoid double-awarding.

**SSR is selectively disabled per-route.** `HandleInertiaRequests::$withoutSsr` lists request-path patterns (`$request->is()`, not component names) that skip SSR: admin, auth, account/settings, and cart/checkout (which render from the client-only zustand cart and would hydration-mismatch). `foo/*` does not match bare `/foo`, so list index routes separately; `tests/Feature/HandleInertiaRequestsSsrExclusionTest.php` asserts the intended paths match.

**Routing.** Split by domain, required from `routes/web.php`: `admin.php` (`/admin`, `admin.*`), `auth.php`, `catalog.php`, `commerce.php`, `account.php`, `settings.php`. `api.php` (search suggestions plus the Steadfast/SMS-forwarder webhooks — there is no public JSON API; the ZiniPay webhook was retired in BIZ-026) is registered separately via `withRouting(api: ...)`. Static pages, blog, newsletter, and landing pages are declared directly in `web.php`. Frontend uses Wayfinder (`@/actions`, `@/routes`) **and** Ziggy: `resources/js/app.tsx` sets a global `route()` preferring the live bare `Ziggy` binding (see the Ziggy note above), falling back to `resources/js/ziggy.js` — a gitignored generated file (`php artisan ziggy:generate`, run by `composer dev`/`setup`/deploy). If Vite fails to resolve `./ziggy`, regenerate it; never commit it (a committed localhost snapshot once broke production `route()` calls).

**Rate limiting is admin-tunable from Admin › Security** (`SecuritySettingsController`, mother-only to save). `App\Support\SecurityRateLimits` holds every limit as a `*_times`/`*_minutes` pair with default/min/max, stored in one `Setting` row and clamped on read, so a bad value can never disable a limit. Named limiters are registered in `AppServiceProvider` and attached per route via `throttle:<name>`; Fortify's own routes (register, forgot-password, confirm-password) get theirs via the `fortify.throttle` middleware (`ThrottleFortifyActions`, added to `config/fortify.php` `middleware`) because Fortify registers those routes itself — attach by route name, never by mutating routes at runtime (deploy runs `route:cache`). `SecurityRateLimits::tooManyAttempts()` picks the response: JSON 429 for fetch callers, the themed 429 page for GETs (never a redirect — that would loop), toast + `rate_limit` validation error for form posts. Logged-in customers are keyed by user id, not IP (Bangladeshi mobile networks share IPs). `admin-login`, `webhook` and `sms-forwarder` limits are deliberately hardcoded, not admin-tunable; `sms-forwarder` only counts wrong-token requests so the real bKash/Nagad forwarder phone is never throttled. `cart.sync` is deliberately unthrottled; the `cart.state` cross-device poll has its own hardcoded `cart-state` limit (60/min per user). No trusted proxies are configured, so all IP-based limits assume the app sees real client IPs.

**Global middleware** (`bootstrap/app.php`): `RedirectToCanonicalDomain` (www → canonical), `BlockBlockedIp` (aborts 403 for every `web`-group request from a blocked IP — every storefront, auth, account and checkout page, not just order placement — except `/admin*`, so the CMS that manages the block list can never lock itself out; payment/courier webhooks live under the separate `api` routing group and are unaffected, since they're server-to-server and don't carry the customer's own IP anyway; `BlockedIp::isBlocked()` caches the block list forever, flushed on create/delete), `TrackDailyVisit` (increments `DailyVisit` once per day per visitor via `sd_last_seen` cookie, skipping bots and `admin`/`api`/`storage`/`_boost`/`up`; feeds the admin dashboard chart, separate from consent-gated GA in `resources/js/lib/analytics.ts`), and `CaptureTrafficSource` (cookie that `Order::booted()` writes onto each order).

**Payments**:
- ZiniPay (hosted card gateway) is **retired** (BIZ-026): its callback/webhook routes, `PaymentController`, `ZiniPayWebhookController`, `ZiniPayService` and `OrderService` were removed. The `zinipay` `PaymentMethod` value stays so historical orders still display. The bKash/Nagad page's `payment.cancel` link now lives in `MobilePaymentController::cancel()`.
- `MobilePaymentController` + `SmsParserService` — manual bKash/Nagad flow: the customer submits a transaction ID, and an SMS forwarded with the `sms_forwarder` token (`config/services.php`) is parsed against `SmsPattern` records to auto-verify.

**Couriers.** Booking an order with Pathao or Steadfast goes only through `CourierSubmissionService` (COURIER-SEND-RACE-001, called from `Admin\OrderCourierController`): it locks the `Order` row and atomically claims the attempt — sendable status, no consignment yet, no other unresolved attempt — by inserting a `CourierSubmission` row with a unique `active_order_id` *before* calling the courier, so a double-click or retry can never book two consignments; the transaction commits before the network call, so no lock is held while waiting on it. An outcome that might have created a consignment anyway (timeout, dropped connection, 5xx, a crash mid-call) is recorded `UNKNOWN` and never retried automatically — only an admin reconciling it (`recordCourierConsignment`/`confirmCourierNotCreated`) can clear it — and while any submission is unresolved (`SUBMITTING`/`UNKNOWN`) the order can't be sent again to either courier, cancelled, or refunded. `PathaoCourierService` and `SteadfastCourierService` hold the actual courier credentials, in the `Setting` model (not `.env`). Map raw courier statuses to `OrderStatus` only in `CourierOrderStatusMapper` (`PATHAO_MAP`/`STEADFAST_MAP`). Its `sync()` treats `CANCELLED`/`REFUNDED` as terminal: late courier reports are recorded but never move status or `payment_status` — this is the single choke point for all call sites. Pathao syncs via scheduled `pathao:sync-statuses`; Steadfast via `SteadfastWebhookController`, which verifies HMAC-SHA256 `X-Signature` against the `steadfast_webhook_secret` Setting and fails closed (401) if unset. A mother-admin "confirmed lost" determination on a parcel (`OrderLostParcelService`, FSA-11, requires `CourierEvidenceChain::eligibility()` to already support it) only sets `lost_confirmed_at` — it never itself touches order status or issues a refund — so that the *next* cancel/refund through the existing, unchanged admin actions skips restocking goods that were never actually returned.

**Shipping and tax.** `App\Services\ShippingCostCalculator` (MED-10) is the single shipping-cost rule shared by cart checkout, the landing-page single-product checkout, and admin manual order pricing (`ManualOrderPricing`) — previously three independently-maintained copies. All three apply the same three steps: the free-delivery threshold from `HomepageSetting` (`announcement_bar`), then per-product `Product::free_delivery` or intact free-delivery bundles, then the admin-configurable `Setting::shippingRateFor($state, $city)` as a fallback — also used directly by `HandleInertiaRequests` for shared props. Only cart checkout can contain a bundle, so only it may award free delivery through `BundleService::itemQualifiesForFreeDelivery()`, exposed as its own method rather than a flag so the other two call sites can't reach it by mistake. Tax is hardcoded to `0`. `ShippingZone`/`ShippingMethod`/`ShippingRate`/`TaxRate` are unwired scaffolding.

**Content.** Static/legal pages are the admin-editable `Page` model by `slug` (`is_published`; edited in Admin › Policy & Info Pages, `Admin\PageController`, sanitized with the `pages` purifier profile — DISC-05), rendered by `resources/js/pages/static/Page.tsx`. `Blog` is a separate type (`scopePublished()`), managed by `Admin\BlogController` and served at `/blog`, `/blog/{blog:slug}`.

**Orders** use `SoftDeletes`; `Admin\OrderController` has a trash flow (`orders.trashed`, `orders.restore` routed `->withTrashed()`; only cancelled orders can be trashed) plus manual order creation (`admin.orders.store`). Order numbers come only from `App\Support\OrderNumberGenerator` (uniqueness checked across trashed orders too). Lifecycle: `OrderStatus`/`PaymentStatus`/`PaymentMethod` enums, `OrderStatusHistory`.

**Order/financial history protection (DB-001).** Permanent order deletion is **mother-admin only** — no section permission is enough, ordinary admins only trash and restore. It takes two separate password-verified requests (`orders.permanentDelete.confirm`, then `orders.forceDelete` with the password again and the exact order number) against a 5-minute single-use session marker bound to that order and admin (`OrderPermanentDeletionService`; routes use `admin.mother`, `throttle:mother-actions` and session blocking). `OrderDeletionEligibility` is the single decision: only a trashed, cancelled order with no payment, return, loyalty/referral, coupon/discount, fulfilment, courier, payment-SMS, review or unbalanced stock history qualifies (stock is fine only if the cancellation restock provably balanced it). Every attempt goes to `order_deletion_audits` (no FKs, survives clearing the audit trail, never stores passwords); `inventory_logs` are never deleted with an order and are copied into that audit. `App\Support\MotherPasswordGate` locks both steps and audit-trail clearing after 5 wrong passwords per 15 min. The database backs this up: `orders.user_id`, `payments.order_id`, `return_requests.*`, `coupon_uses.*`, `referral_point_transactions.user_id` and `users.referred_by_id`/`referral_discount_used_order_id` are RESTRICT; `orders.coupon_id` and the `created_by`/`answered_by` audit columns are SET NULL — so a raw delete of a customer or coupon with history fails rather than cascading. Coupons use `SoftDeletes` (one with usage history can't be force-deleted; `Order::coupon()` is `withTrashed()`). Customer self-deletion anonymizes any account with `User::hasRetainedHistory()` and hard-deletes only accounts with none. Customers still see their trashed cancelled orders read-only, and trashed orders count in referral/first-order checks. Migration recovery: `docs/COURIER_MIGRATION_RECOVERY_RUNBOOK.md` §7.

**Cross-device cart sync.** `CartManager::version()` hashes a cart's rows (product, variant, bundle, quantity) plus its coupon — computed from the rows, not a counter, so every write path changes it — and `serialize()` includes it as `version`. A logged-in full-cart save sends `base_version`; `syncUserCart(..., expectedVersion:)` checks it under the user+cart locks and `CartController::sync()` answers 409 with the current cart when stale (the login merge is never checked). `app.tsx` then re-applies this tab's own changes on top (`rebaseOntoServerCart()`, measured against the persisted `serverBase`) and saves again, and polls `cart.state` every 15 s while visible and on focus — paused on checkout/order/landing/admin pages and whenever local changes are unsaved. The same poll returns the account's `wishlist_ids` (applied unless a heart click happened since the poll left or is still saving; the account Wishlist page then partially reloads its product list), a `storage` listener syncs the wishlist between same-browser tabs, and a heart click sends `wishlisted` (the chosen state, idempotent) to `wishlist.toggle` so a device showing an older wishlist can't flip a product the wrong way; without it the endpoint still toggles.

**`PendingCheckout`** stages checkouts awaiting external confirmation (mobile banking SMS, landing-page/guest buy-now) before an `Order` exists; used across `CheckoutController`, `LandingPageController`, `MobilePaymentController`.

**Abandoned checkouts.** `IncompleteOrder` is upserted by `IncompleteOrderController::store()` on a per-visit `attempt_token` once a valid phone + items exist, and deleted when an order is placed with that phone (`CheckoutController::store()`, `MobilePaymentController::submit()`). Hourly `cart:remind-abandoned` dispatches `AbandonedCartReminderJob` for rows ≥1h old (deduped via `reminder_sent_at`), only when `user_id` is set — guests have phone only.

**Transactional email is dispatched centrally from `Order::booted()`**, not controllers. On `created`: `OrderPlacedMail` (customer) + `NewOrderAlertMail` (mother admin) — each order-creation path has its own idempotency guard so retries can't double-send. On status change (`wasChanged('status')`), the customer gets only `OrderDeliveredMail` (DELIVERED) or `OrderCancelledMail` (CANCELLED): owner policy 2026-11-14, customers receive exactly three order emails (placed, delivered, cancelled) and nothing for pending, processing, shipped or refunded, from any source (courier sync, admin or customer action). `ShippedWithTrackingMail`/`OrderStatusUpdatedMail` are no longer sent (the classes remain only so old failed jobs still display in `FailedJobSummary`); don't reintroduce them without the owner's approval. The admin `CancelledOrderAlertMail` is unchanged. Recipients come from the order's `user`/`shipping_address`, never the session (status changes are made by admins/webhooks). All order/admin mailables are `ShouldQueue` + `->afterCommit()`. `WelcomeMail` fires on first email verification (not registration). `ReviewApprovedMail` on `Admin\ReviewController::approve()`. `LowStockAlertMail` only on the crossing of `low_stock_threshold`. `BackupFailedMail` is an unwired stub.

**Landing pages** (`LandingPage`, `LandingPageController`, `resources/js/pages/landing/`, `/lp/{slug}`) are a separate single-product guest-checkout funnel with their own order/payment/confirmation flow.

**AI assistant.** A third button in `SocialChatWidget` (alongside WhatsApp/Messenger) opens `AiAssistantPanel`, a chat widget backed by Groq's OpenAI-compatible chat-completions API running Llama 3.1 8B (`llama-3.1-8b-instant`). `App\Support\AiAssistantConfig` holds the Groq API key and an on/off switch in `Setting`, never `.env`; only the switch (not the key) is shared to the frontend, via `ai_assistant_enabled` in `LayoutCache`'s allow-list and `HandleInertiaRequests`. Admin's own page is `Admin\AiAssistantSettingsController` (`ai_assistant` in `AdminSections`). The model is never given database access: `App\Services\AiAssistantService` runs the system-instruction/tool-calling loop against Groq and hands every tool call to `App\Services\AiAssistantTools`, whose three methods are the *only* things the assistant can do — search visible products, check one product's stock/price, or look up an order (a guest must match order number plus email/phone, exactly like `OrderTrackingController`; a logged-in customer can only ever reach their own). `routes/commerce.php`'s `assistant.chat` endpoint is open to guests and customers alike, throttled by its own hardcoded `ai-assistant` limiter (protects Groq's free-tier rate limit, not a per-customer business rule). Unlike Gemini's old stateful Interactions API (one opaque `previous_interaction_id`, conversation held server-side by Google), Groq's endpoint is stateless, so `AiAssistantPanel.tsx` resends the trailing slice of its own `sessionStorage` message history (capped client- and server-side at `MAX_HISTORY_MESSAGES`) on every turn instead of an id — `AiAssistantController` validates that array's shape (`role`, `content`) before it reaches the model.

**Homepage merchandising.** `HomepageSetting` keyed by `section_key` (`announcement_bar`, `hero_slider`, `flash_sale`, `featured_products`, `new_arrivals`, `best_sellers`, `brands`, …) with a `metadata` JSON column; `Admin\HomepageController` special-cases each section plus `reorder`. `HeroSlide` and `PromotionalBanner` have their own admin controllers.

**Scheduler** (`routes/console.php`, all previously stale here — verify against the file itself, not this list, before relying on it again): `pathao:sync-statuses` (every 15 min), `rewards:grant-due` (daily 01:00 Asia/Dhaka), `cart:remind-abandoned` (hourly), `pending-checkouts:prune` (daily 02:00 Asia/Dhaka), `newsletter:prune-attachments` (daily 03:00 Asia/Dhaka), `data:prune` (daily 03:30 Asia/Dhaka), `cache:prune-expired` (hourly — DB-004, split out of `data:prune` since rate-limit cache rows expire within minutes, not days), `queue:prune-failed --hours=720` (daily 03:40), `queue:prune-batches ... --hours=720` (daily 03:45), `activitylog:clean --force` (daily 03:50), `sitemap:generate` (daily 04:00), `steadfast:sync-statuses` (every 30 min), `meta:dispatch-pending` (every 10 min — Meta Conversions API outbox sweeper), the System Health heartbeats `health:schedule-check-heartbeat`/`health:queue-check-heartbeat` (every minute), `app:drain-queue` (every minute, after every task that queues jobs so they go out in the same run — see its own note below), and `health:check` (every minute, the only task after the drain so it sees that run's queue heartbeat; DISC-37, see System Health below). Every `withoutOverlapping()` call passes `false` as its second argument; see the comment at the top of `routes/console.php` for why (Hostinger's `pcntl_signal()` is disabled).

**System Health (DISC-37).** `spatie/laravel-health` checks (database, disk via PHP's own functions — `App\Support\Health\DiskSpaceCheck`, since shared hosting may block `df` — debug mode, environment, scheduler, queue, cache, optimized app, database size) are registered in `AppServiceProvider::configureHealthChecks()` and run every minute; results go to `storage/app/private/health.json`, a failure emails the mother admin at most hourly (`MotherAdminHealthNotifiable`), and Admin › System Health (`SystemHealthController`, `admin.mother`) shows them. The scheduler/queue heartbeats use the `database` cache store so deploy's `cache:clear` never looks like an outage. The package's own result routes are never registered — keep `oh_dear_endpoint` disabled. Horizon and Pulse were deliberately not added (no Redis or long-running processes on Hostinger).

**Caching.** Shared layout data every page loads (settings incl. delivery rates, menu categories, homepage sections, hero slides, promo banners, popular keywords) comes from `App\Support\LayoutCache`, cached forever and cleared on save/delete/restore by the `FlushesLayoutCache` trait (each model lists its keys in `LAYOUT_CACHE_KEYS`). Bulk query-builder `update()`/`delete()` fires no model events, so any new bulk write to those tables must call `LayoutCache::forget(...)` itself (see the category/homepage reorder and keyword bulk-delete actions). **Cache plain arrays, never model objects**: `cache.serializable_classes` is `false`, so objects read back from the file store become `__PHP_Incomplete_Class` — the in-memory `array` store used in tests hides this, which is why `LayoutCacheTest` has a real file-store test. Search-bar suggestions (`/api/search/suggestions`) are cached per exact query by `App\Support\SearchSuggestionCache` (5-minute backstop TTL for scheduled `published_at`; stored JSON-round-tripped; empty and >64-char queries never stored, so junk can't fill the disk), flushed via a version stamp by the `FlushesSearchSuggestions` trait on Product/ProductMedia/Brand/Category/SubCategory — only when a column in the model's `SEARCH_SUGGESTION_FIELDS` changes, so stock decrements don't flush. The homepage featured-products and brands bulk updates flush it explicitly. The product-listing filter options (categories/brands with product counts, attributes, max price — identical for every visitor and listing page) come from `App\Support\CatalogFilterCache`, flushed by the `FlushesCatalogFilters` trait (`CATALOG_FILTER_FIELDS` per model) and explicitly after the category reorder, attribute-value reorder and homepage brands bulk updates; it expires exactly at the next scheduled `published_at` (1-hour backstop). When testing bulk-write cache clears, test each bulk action in isolation — a later action's clear masks an earlier one's missing clear. **The home page sections (`HomeController`) are deliberately uncached** — product cards show live stock ("Out of stock"/"Only N left") and the flash sale shows live sold counts, but cancellation restores stock and `sold_count` via query-builder `increment`/`decrement` in `InventoryService` that fire no model events, so a cache could show stale stock; don't cache them unless those writes are first made to signal changes. There is no full-page caching (pages carry per-visitor cart/auth data). Deploy runs `cache:clear` and `event:cache`; `public/.htaccess` sets long browser caching for hashed `/build` assets and 30 days for images/fonts.

**Database**: MySQL locally; SQLite in-memory for tests (`phpunit.xml`, queue `sync`, mail `array`). Cache and sessions use the `file` driver (Hostinger shared hosting, no Redis); rate-limit counters stay on the `database` store via `cache.limiter` because file-store increments aren't atomic. Queue uses `database`. The server's `.env` is never deployed, so driver changes there are manual.

**Database backups (OPS-008).** Admin › Database backup is mother-admin only end to end — a backup holds every customer's details, password hashes, and the courier/gateway credentials stored in `Setting`, and a restore can change any of them, so both the export and the restore's confirm step require her password again through `MotherPasswordGate` (same gate as order permanent-deletion and audit-trail clearing). `DatabaseBackupExporter` streams every table (`DatabaseBackupFormat::tables()`) as a custom data-only text format, 1,000 rows at a time inside one consistent snapshot, so export memory never scales with database size; `DatabaseBackupRestorer` reverses it. The format deliberately excludes `cache`/`sessions`/`jobs`/`failed_jobs`/`password_reset_tokens`/`migrations` (restoring these would resurrect old logins and half-finished jobs), and treats `courier_delivery_evidence`/`order_deletion_audits` as append-only — a restore may add a missing row but never rewrites an existing one, preserving PAY-RETURN-001/DB-001 guarantees. The file is valid MySQL so phpMyAdmin could import it if the site were down, but the app itself never executes it as SQL — uploads are parsed line-by-line and rejected if a line isn't the exact shape `encodeRow()` writes.

**Frontend**: shadcn/ui (`new-york`, `neutral`) in `resources/js/components/ui` + `cn()` from `resources/js/lib/utils` for shared primitives. Admin pages are visually separate from storefront pages — follow the matching `resources/js/pages/<domain>/` folder. Cross-page client state lives in zustand stores (`resources/js/stores`: `cartStore`, `wishlistStore`, `uiStore`).

**Sitemap**: `php artisan sitemap:generate` (`App\Console\Commands\GenerateSitemapCommand`, using `spatie/laravel-sitemap`) rebuilds the public sitemap from active categories/products/pages — scheduled daily at 04:00 Asia/Dhaka (OPS-001; see the Scheduler entry above for the full list).

**PDF invoices**: `barryvdh/laravel-dompdf` renders `invoices.order`, used from `routes/account.php` and `LandingPageController`.
