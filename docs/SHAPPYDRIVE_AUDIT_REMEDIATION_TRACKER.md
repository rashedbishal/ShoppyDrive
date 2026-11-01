# ShoppyDrive Audit Remediation Tracker

Source of truth for the forensic audit findings (CRIT/HIGH/MED/LOW IDs). `docs/MASTER_FIXING_TRACKER.md` remains the record of earlier remediation work and its evidence; this file links to it instead of copying it.

**Last updated:** 2026-11-01. **Git baseline:** `main` = `origin/main` = `f36ee18`; one stash (`stash@{0}`). CRIT-01, HIGH-01, HIGH-02, HIGH-03 and REF-CAP-TZ-001 changes are uncommitted on top of this.

> **Authoritative source:** `docs/SHAPPYDRIVE_FORENSIC_AUDIT_REPORT.txt`. All findings were reconciled against it and the code at the current working tree on 2026-11-01. Completed findings are not reopened without new evidence.

**Statuses:** NOT_STARTED, INVESTIGATING, IN_PROGRESS, BLOCKED, PARTIALLY_COMPLETED, COMPLETED, ACCEPTED_RISK, NOT_APPLICABLE. Never mark COMPLETED without implementation and verification evidence. `COMPLETED (LOCAL IMPLEMENTATION)` means verified locally and not deployed.

**Priority order:** see "Remaining priority order". LOW findings only when justified.

## Findings

| ID | Severity | Issue | Status | Implementation approach | Files modified | Tests performed | Verification evidence | Completion date | Remaining work |
|---|---|---|---|---|---|---|---|---|---|
| CRIT-01 | Critical | Loyalty points double-spend across overlapping pending checkouts | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | Derived reservation: `pending_checkouts.points_reserved`, available = balance minus active reservations. See CRIT-01 notes | See CRIT-01 notes | 21/21 MySQL reservation tests passed, twice. MySQL concurrency tests 2/2 passed. Checkout regression: 336 passed, 10 skipped, 1 unrelated failure (fixed since by REF-CAP-TZ-001) | See CRIT-01 notes | 2026-11-01 (local) | See deployment checklist |
| HIGH-01 | High | Admin account management is completely unaudited | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | Spatie activity logging on `Admin` with an attribute allow-list, plus a value-free password event. See HIGH-01 notes | See HIGH-01 notes | `AdminAuditLogTest` 16/16 on SQLite and MySQL. Admin regression sweep 205 tests: 180 passed, 25 skipped (MySQL-only) | See HIGH-01 notes | 2026-11-01 (local) | See deployment checklist |
| HIGH-03 | High | Primary wishlist entry point is structurally broken: `/wishlist` rendered the page with no data | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | `/wishlist` redirects signed-in customers to `account.wishlist`; guests get a guest page. See HIGH-03 notes | See HIGH-03 notes | `WishlistEntryTest` 10/10 on SQLite and MySQL. Sweep 157 tests: 147 passed, 10 skipped (MySQL-only). `tsc`, ESLint, Prettier, production build passed | See HIGH-03 notes | 2026-11-01 (local) | See deployment checklist |
| HIGH-02 | High | Variant product stock inconsistency: orders decrement variants, listings read the stale parent `stock_quantity` | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | Computed `Product::available_stock` from already-loaded variants, plus an `inStock` filter scope. See HIGH-02 notes | See HIGH-02 notes | `ProductAvailableStockTest` 25/25 on SQLite and MySQL. Sweep 539 tests: 529 passed, 10 skipped (MySQL-only). `tsc`, ESLint, Prettier, production build passed | See HIGH-02 notes | 2026-11-01 (local) | See deployment checklist |
| MED-01 | Medium | Admin manual orders accepted any item price with no catalog check | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | Server-calculated pricing with a mandatory reason for every deviation. See MED-01 notes | See MED-01 notes | `ManualOrderPricingTest` and `CreateManualOrderTest` on SQLite and MySQL | See MED-01 notes | 2026-11-01 (local) | See deployment checklist |
| MED-02 | Medium | Late non-terminal Steadfast webhook could move an order backward | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | Forward-only rule for Steadfast inside `CourierOrderStatusMapper::sync()`. Pathao deliberately unchanged. See MED-02 notes | See MED-02 notes | 51 focused tests on SQLite and MySQL. 2 two-process MySQL race tests. Courier sweep 408 tests: 381 passed, 27 skipped (MySQL-only) | See MED-02 notes | 2026-11-01 (local) | See deployment checklist |
| MED-03 | Medium | Permanently failed queued jobs were invisible | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | Permission-gated admin page with redacted summaries, allow-listed retry, audited delete and a dashboard count. See MED-03 notes | See MED-03 notes | `FailedJobManagementTest` 19/19 on SQLite. With `DrainQueueCommandTest` 28/28 on MySQL. Sweep 333/333 | See MED-03 notes | 2026-11-01 (local) | See deployment checklist |
| MED-04 | Medium | SMS regex unvalidated, errors suppressed, content length unbounded | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | Save-time validation, bounded execution, safe logging, oversize guard and audit logging. See MED-04 notes | See MED-04 notes | `SmsPatternSafetyTest` 30/30 on SQLite. With `MobilePaymentMatchingTest` 44/44 on MySQL. Sweep 277/277 | See MED-04 notes | 2026-11-01 (local) | See deployment checklist |
| MED-05 | Medium | Admin-destructive actions missing from the audit trail | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | Explicit admin-action logging and `Auditable`, written atomically. See MED-05 notes | See MED-05 notes | `RemainingAdminAuditTest` 19/19 on SQLite, and with related tests 29/29 on disposable MySQL. Sweep 1425 passed, 12 skipped | See MED-05 notes | 2026-11-01 (local) | See deployment checklist |
| MED-06 | Medium | No DB uniqueness on variant SKU or attribute values | BLOCKED (DEFERRED BY USER) | Application code and disposable MySQL verification completed. Production preflight deferred. **Migration NOT SAFE TO DEPLOY.** See MED-06 notes | See MED-06 notes | 24/24 on MySQL 8.0.46, including a two-process race. 14/14 on in-memory SQLite. Sweep 1406 passed, 10 skipped | See MED-06 notes | — | Production duplicate and schema preflight, then a user decision. Do not run the migration on dev or production |
| MED-07 | Medium | Admin product save was not transactional | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | Single transaction with upload cleanup on failure. Old files deliberately kept. See MED-07 notes | See MED-07 notes | `ProductSaveAtomicityTest` 11/11 on SQLite. With `ProductVariantManagementTest` 29/29 on MySQL. Sweep 613/613 | See MED-07 notes | 2026-11-01 (local) | See deployment checklist |
| MED-08 | Medium | Concurrent cart writes could create duplicate rows and double-charge a line | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | Serialized cart writes with row locks, duplicate consolidation, and a locked checkout re-check. No migration. See MED-08 notes | See MED-08 notes | `CartConcurrencyTest` 18/18 on SQLite. `CartConcurrencyRaceTest` 4/4 multi-process on MySQL. SQLite regression 367/367. MySQL focused 29/29 | See MED-08 notes | 2026-11-01 (local) | See deployment checklist |
| MED-09 | Medium | Money cast to float across models | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | **Root cause confirmed, one concrete site fixed, no broad retrofit performed (deliberately, per the report's own recommendation).** `Order`/`OrderItem`/`CartItem`/`Product`/`ProductVariant`/`Payment` all cast exact `decimal(10,2)` columns to PHP `'float'`, exposing any later PHP-side arithmetic/comparison to IEEE-754 noise. Searched every `!==`/`===` against a money field in `app/Http/Controllers`, `app/Support`, `app/Services`: the only genuine value comparison (not a null-check) was `CartManager::revalidatePrices()` (`app/Support/CartManager.php:462`): `round((float) $item->price, 2) !== $current`, a strict comparison of two independently-computed floats. Fixed to compare in integer poisha instead: `(int) round((float) $item->price * 100) !== (int) round($current * 100)` — the exact convention already established and reviewed at `app/Services/ManualOrderPricing.php:93-96` (`differs()`), not a new pattern. `InventoryService.php:283`'s tolerance-band comparison, `ManualOrderPricing.php`, and every other float-cast model are explicitly unchanged — no broad retrofit, matching the report's own "no broad retrofit recommended given blast radius" | `app/Support/CartManager.php`, `tests/Feature/CartPriceRevalidationTest.php` (new) | `CartPriceRevalidationTest`: 4/4 on in-memory SQLite, 10 assertions (the same effective price via a different calculation path — `199.99 * 0.85` vs a flat `169.99` — is not a false change; a genuine one-poisha change is still detected; a genuine large change is still detected; an unchanged price leaves the item untouched). With the pre-existing `CartConcurrencyTest` and `BundlePricingTest` (both already exercise `revalidatePrices()`): 28/28, 0 failed | Pint passed. **Empirically could not construct a currency-scale value pair where the OLD `round(,2)` comparison actually disagreed** (brute-forced 100,000 cent values against an alternate float path; also checked a percentage-discount-vs-flat-price pair — all agreed after rounding) — confirms the report's own "PROBABLE/THEORETICAL... no concrete failing value combination demonstrated" classification exactly. The fix's value is correctness-by-construction (exact integer equality) rather than reliance on `round()`'s internal behavior, not the resolution of an observed live failure. MySQL/full suite not run: no driver-specific behavior, pure PHP-side comparison logic | 2026-11-01 (local) | None |
| MED-10 | Medium | Shipping calculation duplicated in three controllers | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | **Corrected architecture note:** the audit's "three controllers" was already stale — MED-01 had already routed `Admin\IncompleteOrderController` through `ManualOrderPricing::shippingCost()`, leaving two independent copies (`CheckoutController`, `LandingPageController`) plus one already-shared method, not three. New `app/Services/ShippingCostCalculator.php` centralizes the common three-step rule (subtotal threshold → per-item `free_delivery`/`free_delivery_min_qty` → `Setting::shippingRateFor()` fallback) behind two explicitly-named public methods — `forCartWithBundles()` (checkout only, also checks `BundleService::itemQualifiesForFreeDelivery()`) and `forItemsWithoutBundles()` (landing page and manual orders — structurally has no bundle parameter to pass, so bundle behavior cannot leak in). `CheckoutController` and `LandingPageController` now call the new service directly, replacing their private methods; `ManualOrderPricing::shippingCost()` is kept as a thin delegating wrapper (same public signature) so `Admin\IncompleteOrderController`'s existing call site is untouched. `CheckoutController`'s now-unused `BundleService $bundles` injection and `HomepageSetting` import were removed as a direct consequence, not separate scope creep | `app/Services/ShippingCostCalculator.php` (new), `app/Http/Controllers/CheckoutController.php`, `app/Http/Controllers/LandingPageController.php`, `app/Services/ManualOrderPricing.php`, `tests/Feature/ShippingCostCalculatorTest.php` (new) | `ShippingCostCalculatorTest`: 8/8 on in-memory SQLite, 11 assertions (threshold reached/not reached; a flagged product with no minimum always qualifies; the minimum-quantity boundary is inclusive, one below is not; a bundle-qualified item gets free delivery in the checkout context; the identical product does not qualify through the no-bundle context, since there is no parameter to carry that information; inside/outside Dhaka rates; empty cart and empty item list both fall back to the rate, not free). **All three pre-existing suites, written against the old triplicated code, pass unchanged against the new shared service:** `ShippingRateCheckoutTest`, `LandingPageShippingTest`, `Admin/IncompleteOrderManagementTest` — 18/18, 0 failed. Broader sweep including bundle checkout/auto-assemble, guest/buy-now checkout, manual order pricing, and MED-09's cart-price test: 82/82, 297 assertions, 0 failed | Pint passed (auto-fixed import ordering/fully-qualified-strict-types in the new test file, nothing behavioral). MySQL/full suite not run: no driver-specific behavior, and the regression sweep above already covers every call site and the bundle interaction directly | 2026-11-01 (local) | None found. The one documented behavior simplification: `CheckoutController`'s old `static $threshold` per-process memoization (never actually exercised — only ever called once per request) was not replicated into the stateless service, since removing it changes nothing observable; noted here rather than left silent |
| MED-11 | Medium | `Admin\OrderController` is 604 lines and 20 actions | COMPLETED (LOCAL IMPLEMENTATION, courier-dispatch extraction only — see notes). Deployment: NOT DEPLOYED | **Scope note:** the full five-responsibility split the original finding described (CRUD, refunds, destructive deletion, address correction, courier dispatch) was deliberately NOT done — only the self-contained courier-dispatch cluster (the lowest-coupling, highest-line-count slice identified during feasibility review) was extracted, as a separately-scoped, separately-approved task. Refunds/cancellation, destructive deletion, CRUD, and address correction all remain in `Admin\OrderController`, exactly as before. New `app/Http/Controllers/Admin/OrderCourierController.php` holds the 7 courier actions (`sendToPathao`, `refreshPathaoStatus`, `sendToSteadfast`, `refreshSteadfastStatus`, `recordCourierConsignment`, `confirmReturnedCourierConsignment`, `confirmCourierNotCreated`) and their one private helper (`sendToCourier`), moved verbatim — confirmed byte-identical to the originals. `routes/admin.php`'s 7 routes changed only their controller-class reference; names, URIs, `AdminSections::middleware('orders','update')`, and `->scopeBindings()` are all unchanged. No business logic, locking, transaction, or `CourierSubmissionService` state-transition code was touched — confirmed by `git diff` showing `app/Services/CourierSubmissionService.php` untouched | `app/Http/Controllers/Admin/OrderCourierController.php` (new), `app/Http/Controllers/Admin/OrderController.php`, `routes/admin.php`, `tests/Feature/CourierSendConcurrencyRaceTest.php` (one-line fix, see below) | **SQLite: 128 tests passed, 586 assertions, 0 failed** (`CourierSubmissionTest`, `CourierSubmissionHardeningTest`, `PathaoCourierOrderTest`, `SteadfastCourierOrderTest`, `AdminPermissionsTest`, `CancelDeliveredOrderTest`, `MarkOrderRefundedTest`, `OrderTrashTest`, `OrderShippingAddressUpdateTest`, `CreateManualOrderTest`, `OrderCancellationAtomicityTest`) | Pint passed. **MySQL history, documented precisely:** the real-subprocess `CourierSendConcurrencyRaceTest` was run once against disposable `shoppydrive_test_loyalty` immediately after the extraction and failed — 10 of 15 cases, all tracing to one cause: `tests/Feature/CourierSendConcurrencyRaceTest.php`'s `COURIER_RACE_SENDER_SCRIPT` hardcoded `App\Http\Controllers\Admin\OrderController::class` for the two send actions, a test-harness coupling the route/frontend/middleware feasibility review had no way to surface (it bypasses HTTP routing entirely — a real OS subprocess, not a request). Fixed with a one-line change to that constant (`OrderController::class` → `OrderCourierController::class`), nothing else in the test touched. **This fix was not re-verified against real MySQL — no second MySQL run was performed.** The decision not to rerun rests on: the fix changes only which class is resolved (the moved methods are byte-identical, `CourierSubmissionService` untouched); every one of the 10 original failures traced to this exact single cause with no divergent symptom (no deadlock, no double-create, no wrong final state); and the 128/128 SQLite result exercises all 7 moved actions through real HTTP requests. Two leftover rows from the failed run (1 `orders`, 1 `courier_submissions`) remain untouched in `shoppydrive_test_loyalty` | 2026-11-01 (local) | **A future MySQL concurrency run is the one piece of evidence this entry does not have.** If `CourierSendConcurrencyRaceTest` is ever rerun, it should pass given the analysis above, but that is reasoned confidence, not a fresh observed result — do not treat this entry as having one. The full MED-11 refactor (refunds/cancellation, destructive deletion, CRUD, address correction) remains undone and is a separate future decision, not implied by this entry |
| MED-12 | Medium | Invoice download branches untested | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | Test-only. See MED-12 and MED-13 notes | `tests/Feature/InvoiceDownloadTest.php` | 25/25 on in-memory SQLite. Sweep 1465 passed, 14 skipped | See MED-12 and MED-13 notes | 2026-11-01 (local) | None |
| MED-13 | Medium | Wishlist feature untested | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | Tests added, and the 2 add-all bugs they exposed were fixed. See MED-12 and MED-13 notes | See the add-all bug section below | `WishlistActionsTest` and `WishlistEntryTest` 40/40 on in-memory SQLite, no skips. Sweep 1480 passed, 12 skipped | See MED-12 and MED-13 notes | 2026-11-01 (local) | See deployment checklist |
| MED-14 | Medium | MySQL and SQLite LIKE differ for non-ASCII text | COMPLETED (LOCAL IMPLEMENTATION, test-only). Deployment: NOT DEPLOYED | **Test-only — no application behavior or application code changed.** Followed the audit's own recommended action literally: added a targeted MySQL test proving `utf8mb4_unicode_ci` case-folds accented Latin letters (É/é) in `LIKE`, which SQLite's ASCII-only case-folding cannot. Bengali deliberately excluded from every case-folding assertion — it has no case distinction, so it was never a valid test case for this finding | `tests/Feature/SearchCaseFoldingMysqlTest.php` (new). No application code, migrations, or configuration touched | **SQLite: 2 passed, 3 assertions** (ASCII case-insensitive baseline; a surgical test isolating only the accented letter's case — everything else held constant — confirms it does not match, the documented SQLite limitation). MySQL group correctly skips under SQLite. **MySQL (disposable `shoppydrive_test_med14`): 5 passed, 59 assertions** (ASCII parity; the same isolated accented-letter case change matches in both directions under real `utf8mb4_unicode_ci`, with a decoy-exclusion control; the customer-facing `/api/search/suggestions` brand `LIKE` path; the admin `BrandController` single-field `LIKE` filter; the admin `CustomerController` multi-field OR `LIKE` filter). SQLite group correctly skips under MySQL | Pint passed. Before interpreting any MySQL assertion, the test itself verifies — via `information_schema`/`SHOW SESSION VARIABLES`, not assumed — that session `character_set_client`/`connection`/`results` are all `utf8mb4` and that `brands.name`/`users.name`/`users.email` column collation is `utf8mb4_unicode_ci`; independently re-confirmed by a separate read-only query during this verification. Run against the disposable `shoppydrive_test_med14` database (newly created for this work, user-authorized; distinct from `shoppydrive_test_loyalty`'s known leftover rows, which were never touched). Runtime: 80.2s | 2026-11-01 (local) | None — the audit's own recommended action is done. Re-open only if a future non-ASCII search/filter call site is added that isn't represented by this test's sample (direct query, customer search, two admin filters) |
| MED-15 | Medium | Deploy didn't verify runtime `APP_DEBUG` or `APP_ENV` | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | Runtime check over SSH before `rsync` and after `config:cache`. See MED-15 notes | `.github/workflows/deploy.yml` | Local simulation of 12 cases, no SSH | See MED-15 notes | 2026-11-01 (local) | Separate approval to deploy. First real run is the first proof on Hostinger |
| MED-16 | Medium | No security headers | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | Global `SecurityHeaders` middleware. No CSP or HSTS. See MED-16 notes | See MED-16 notes | `SecurityHeadersTest` 18/18 on SQLite. Sweep 1392 passed, 2 skipped, 0 failed | See MED-16 notes | 2026-11-01 (local) | Confirm headers on production after deploy. See deployment checklist |
| MED-17 | Medium | ZiniPay webhook unsigned | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | Removed only the dead `ZiniPayService::createInvoice()` method (and its docblock). The unsigned-webhook risk itself is unchanged and stays low by existing design: `ZiniPayWebhookController`/`PaymentController` never trust the inbound payload, they re-verify by calling `ZiniPayService::verifyPayment()`. Confirmed before deleting: zero references to `createInvoice`, no `ZiniPayService::class` string usage, no quoted/dynamic method-name reference, across `app/`, `routes/`, `tests/`, `resources/`, `config/`, `database/`. Both controllers that inject `ZiniPayService` call only `verifyPayment()`. `OrderService::createFromPending()` takes already-verified payment data as a parameter and has no dependency on `createInvoice()` — confirmed independent | `app/Services/ZiniPayService.php` | `LoyaltyPointsReservationTest` (the only existing test exercising `OrderService::createFromPending()`): 21/21 passed, 97 assertions, 0 failed. No dedicated ZiniPay-webhook test file exists in the suite (confirmed via exhaustive search) and none was added for a pure dead-code deletion, per the no-unnecessary-new-tests instruction | Pint passed. MySQL/full suite/frontend checks/build deliberately not run — a confirmed-unreachable method's removal has no behavior to verify beyond "nothing that used to compile now fails to," already covered | 2026-11-01 (local) | None |
| MED-18 | Medium | `SESSION_SECURE_COOKIE` has no default | BLOCKED | User must check the production `.env`. Code default is an option after that | — | None | Still exists in `config/session.php:172`, and it's absent from `.env.example`. Production value unknown. **Partially de-risked by live evidence (see MED-18A): Symfony's Secure-cookie auto-detection already correctly reflects the real request scheme in production regardless of this env var's literal value** — but the literal value itself remains unverified, no server access | — | Risk: Medium if unset. Complexity: Small |
| MED-18A | Medium | Production serves full content over plain HTTP with no redirect to HTTPS — a distinct finding from MED-18, surfaced by live read-only verification, not the original audit | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | New `App\Http\Middleware\RedirectToHttps`, registered in `bootstrap/app.php`'s `web` group right after `RedirectToCanonicalDomain`. Production-only (`config('app.env') === 'production'`), redirects any non-secure request to the equivalent `https://` URL (308, preserving method/path/query), using only the trusted `config('app.url')` host — never the request's own `Host` header — for the redirect target. Does not read or trust `X-Forwarded-Proto`; relies on `$request->isSecure()`, which was directly confirmed live to already reflect the true scheme correctly (see notes). See MED-18A notes | `app/Http/Middleware/RedirectToHttps.php` (new), `bootstrap/app.php` | `tests/Feature/RedirectToHttpsTest.php` (new): 7 passed, 11 assertions (plain HTTP→HTTPS redirect with path/query preserved; POST preserved via 308; an HTTPS request isn't redirected again; local and testing environments are left alone; a spoofed `X-Forwarded-Proto` can't bypass enforcement; a spoofed `Host` header can't redirect to an attacker-controlled domain). `RedirectToCanonicalDomainTest.php` (adjacent, unchanged): 4/4, confirming no interaction regression. `HandleInertiaRequestsSsrExclusionTest` + the Blocked-IP test file (middleware-pipeline-adjacent sanity check): 7/7 | Pint passed, no changes needed. MySQL/full suite/build deliberately not run — pure PHP middleware logic with no driver-specific or frontend-facing behavior | 2026-11-01 (local) | **NOT DEPLOYED.** See MED-18A notes for the explicit HSTS decision (deferred) and remaining infrastructure verification needed before/after deploy |
| MED-19 | Medium | SMS amount tolerance is a flat ৳1 | ACCEPTED_RISK | No code change. Investigated and accepted — see MED-19 notes | — | None (investigation only: re-confirmed both call sites and the existing rejection-case test coverage) | Confirmed still exists, unchanged, at both `MobilePaymentController.php:143` and `LandingPageController.php:389` — identical `abs($sms->parsed_amount - $expectedAmount) > 1` check. Maximum exposure is capped at ৳1 per order. See MED-19 notes | 2026-11-01 (accepted) | Re-open only if a concrete business rule for a percentage-based tolerance (with an explicit floor) is defined |
| MED-20 | Medium | Expired PendingCheckout rows never pruned | PARTIALLY_COMPLETED (LOCAL IMPLEMENTATION, Tier 1 only). Deployment: NOT DEPLOYED | New `pending-checkouts:prune` command, scheduled `->dailyAt('02:00')->timezone('Asia/Dhaka')->withoutOverlapping(1440, false)` matching this project's other scheduled commands. **Tier 1 only:** deletes rows with `order_number IS NOT NULL` and `expires_at` more than 24h in the past, in chunks of 200 (`chunkById`), after confirming the referenced `Order` actually exists; a converted row whose `Order` is missing is preserved and logged (`Log::warning`) as an anomaly rather than guessed at. **Tier 2 (rows with `order_number IS NULL`) is deliberately never touched by this command** — see below | `app/Console/Commands/PrunePendingCheckoutsCommand.php` (new), `routes/console.php`, `tests/Feature/Admin/PrunePendingCheckoutsCommandTest.php` (new) | `PrunePendingCheckoutsCommandTest`: 5/5 on in-memory SQLite, 12 assertions (a converted record past 24h is pruned; a converted record under 24h is preserved; a record with no `order_number` is preserved no matter how old; a converted record whose `Order` no longer exists is preserved and logged; the command is safe to run twice in a row) | Pint passed. No payment, checkout, webhook or loyalty logic was touched. MySQL/full suite not run: pure Eloquent query + delete, no driver-specific behavior. `php artisan schedule:list` confirms the new entry registers without error | 2026-11-01 (local, Tier 1 only) | **Tier 2 is deferred, not fixed, and documented as such — see the note below.** See deployment checklist for Tier 1 |
| WISHLIST-GUEST | Medium | Guests visiting `/wishlist` received an account layout with blank user details and login-gated navigation. Separate related Medium in the report with no MED ID; linked to HIGH-03 | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | Delivered by the HIGH-03 change, no separate implementation | See HIGH-03 notes | Covered by `WishlistEntryTest` | See HIGH-03 notes | 2026-11-01 (local) | See deployment checklist |
| LOW-01 | Low | `Order::$fillable` includes financial and idempotency fields | ACCEPTED_RISK | No code change. Investigated and accepted — see LOW-01 notes | — | None (investigation only: grep of all `Order::create()` call sites, `app/Models/Order.php` inspection) | All 7 current `Order::create()` call sites freshly inspected 2026-11-01: every one builds a hand-written array literal; none spread `$request->all()` or raw validated input. `order_number` is always `OrderNumberGenerator::generate()` or a value traced back to it via `PendingCheckout.checkout_data` — never caller-supplied. See LOW-01 notes | 2026-11-01 (accepted) | Re-open if a future `Order::create()` call site stops building an explicit array (e.g. starts spreading request/validated input) |
| LOW-02 | Low | Variant attributes JSON duplicates IDs | ACCEPTED_RISK | No code change. Investigated and accepted — see LOW-02 notes | — | None (investigation only: traced the one write site in `Admin\ProductController::syncVariants()` and every backend/frontend read site of `ProductVariant.attributes`) | Confirmed reachable, not just theoretical: renaming an `AttributeValue` via `Admin\AttributeValueController::update()` leaves every variant's snapshotted label stale until that product is next saved; `Admin\ProductController::syncVariants()` always re-derives the snapshot fresh on save. Impact is cosmetic/display-only — no price, stock, SKU, financial, security, or order-integrity effect found. See LOW-02 notes | 2026-11-01 (accepted) | Re-open if a read site starts trusting the snapshot for anything beyond display (e.g. pricing, stock, or order-integrity logic) |
| LOW-03 | Low | Dashboard low-stock uses summed parent stock | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | New `Product::scopeLowStock()`: for a variant product, flags it if ANY variant's own `stock_quantity` is at or below THAT variant's own `low_stock_threshold` (matching `InventoryService::maybeAlertLowStock`'s real-time per-variant check, including its "a NULL threshold never alerts" rule); for a variant-less product, unchanged (parent stock vs parent threshold). Replaces the old single `whereColumn('stock_quantity','<=','low_stock_threshold')` on the parent, which summed all variants and compared against only one threshold. `DashboardController` now calls `->lowStock()` for both `low_stock_count` and `lowStockProducts`; displayed columns (`stock_quantity`, `low_stock_threshold`) are still the parent's own values, unchanged | `app/Models/Product.php`, `app/Http/Controllers/Admin/DashboardController.php`, `tests/Feature/Admin/DashboardLowStockTest.php` (new) | `DashboardLowStockTest`: 7/7 on in-memory SQLite, 71 assertions (a low variant hidden behind a healthy sum; no single variant breaching its own threshold despite a low parent sum; per-variant thresholds, not one shared value; variant-less products; a NULL variant threshold never flags; a straightforward variant-less case unchanged; inactive products still excluded). With `DashboardRevenueTest`, `UnseenOrdersTest`, `ProductAvailableStockTest` (shared `Product` model / `admin.dashboard` route coverage): 40/40, 0 failed | Pint passed. Pure SQL `EXISTS`/`whereColumn` logic, identical on SQLite/MySQL/MariaDB — not MySQL-tested, nothing here is MySQL-specific. `tsc`/build not run: no frontend prop shape changed. Full suite not re-run: change is isolated to one model scope and its two call sites, both with direct and shared-model regression coverage | 2026-11-01 (local) | **Known residual gap, not fixed here:** the dashboard's low-stock *list* still displays the parent's own `stock_quantity`/`low_stock_threshold` columns, which can be a stale snapshot for a variant product even though the *flag* is now correct — an admin clicking through still needs the product's edit page for the real per-variant numbers. Fixing the list's displayed numbers would need a frontend/shape change and was out of scope for the smallest correct fix. See deployment checklist |
| LOW-04 | Low | Dead TypeScript interfaces | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | Removed the three unused interfaces (`ShippingZone`, `ShippingMethod`, `TaxRate`) from `resources/js/types/index.ts`. Reconfirmed zero references anywhere else in `resources/js` before deleting. `ShippingRate` (a different, separate interface sitting between two of these) was left untouched | `resources/js/types/index.ts` | None — a pure type deletion with zero references has no runtime behavior to test, consistent with this project's own "styling/structural-only changes don't need new tests" convention | `tsc --noEmit`: clean, 0 errors. ESLint/Prettier/build/Pest/MySQL deliberately not run — nothing PHP or runtime changed | 2026-11-01 (local) | None |
| LOW-05 | Low | No per-route permission rejection test | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | **Test-only — no application code, routes, or `AdminSections` changed.** `AdminPermissionsTest` already proved the gating mechanism correct via 4 representative sections (orders, customers, categories, blocked_ips); the actual gap was that the other ~24 sections' route *wiring* was never checked, so a mistyped or copy-pasted `AdminSections::middleware(...)` call would pass every existing test silently. Closed via pure route/middleware introspection (`Route::getRoutes()`), not per-route HTTP enumeration — no HTTP requests, no database writes, no model factories, and the check never goes stale as routes are added, renamed or removed | `tests/Feature/Admin/AdminRoutePermissionWiringTest.php` (new) | **2/2 passed, 917 assertions, 315ms, on in-memory SQLite.** Test A: every `admin.*`-named route not in a 20-route exception list (verified individually against its actual route definition, not assumed from comments — see the test file's own inline rationale per entry) carries exactly one `admin.can:<section>,<ability>` middleware entry. Test B: every such entry's section exists in `AdminSections::SECTIONS` and its ability is in `AdminSections::abilitiesFor($section)`; a malformed entry (wrong token count) fails explicitly rather than being silently skipped. Both tests additionally fail if any exemption-list entry no longer matches a real route name (catches a stale exception), and refuse to pass on an empty route set (catches an accidentally-over-broad filter) | Pint passed (auto-fixed import ordering and fully-qualified-strict-types on the new file, nothing behavioral — re-ran after, still 2/2). MySQL/full suite/frontend checks/build deliberately not run — pure route-table introspection, no driver-specific or frontend-facing behavior. No actual route-wiring defect was found; both tests passed cleanly against the current route table | 2026-11-01 (local) | None — closes the finding as described. Re-open only if a future admin route is added that neither carries `admin.can` nor is added to the documented exception list with a verified reason |
| LOW-06 | Low | No sitemap command test | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | Test-only, no command change: inspection found no genuine bug (the relative paths `GenerateSitemapCommand` passes to `Url::create()` are absolutized by Laravel's `url()` helper inside the vendor package's own Blade template, not a defect) | `tests/Feature/SitemapGenerationTest.php` (new) | `SitemapGenerationTest`: 6/6 on in-memory SQLite, 29 assertions (well-formed XML with a `urlset` root; the homepage entry's priority/changefreq; an active category included and an inactive one excluded; a published active product included; unpublished, future-published and inactive products all excluded; a published page included and an unpublished one excluded) | Pint passed. The command writes a real file to `public_path()`; every test cleans it up in `afterEach()`, confirmed absent afterward. MySQL/full suite/frontend checks/build deliberately not run — no genuine reason emerged; this is pure Eloquent scope + XML output, identical on every supported driver | 2026-11-01 (local) | None |
| LOW-07 | Low | `verify-email.tsx` uses a light theme | NOT_APPLICABLE — audit premise incorrect, see Corrections below | No fix: the premise does not hold against the actual code | — | Not applicable | `verify-email.tsx` already matches three sibling pages exactly; see Corrections | — | None |
| LOW-08 | Low | Dashboard revenue includes unpaid and cancelled orders | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | `Admin\DashboardController::collectedRevenueQuery()` filters `payment_status = paid` and `status != cancelled` before summing; applied to today/yesterday revenue and the 7-day chart. A fully refunded order is already excluded because `RefundService` moves its `payment_status` off `paid` — no separate refunded check needed. Order-count stats (`today_orders`, etc.) are a different metric and were left as-is. No migration | `app/Http/Controllers/Admin/DashboardController.php`, `tests/Feature/Admin/DashboardRevenueTest.php` (new) | `DashboardRevenueTest`: 4/4 on in-memory SQLite, 34 assertions. With `UnseenOrdersTest`, `AdminPermissionsTest`, `RoleBasedRedirectTest`, `HandleInertiaRequestsSsrExclusionTest` (every test touching `admin.dashboard`): 35/35, 0 failed | Pint passed. Pure SQL `WHERE` logic, identical on SQLite/MySQL/MariaDB — not MySQL-tested, since nothing here is MySQL-specific. `tsc`/build not run: no frontend types or shapes changed. Full suite not re-run: change is isolated to one controller method with its own direct test coverage | 2026-11-01 (local) | See deployment checklist |
| LOW-09 | Low | Order number uniqueness check race | ACCEPTED_RISK | No code change. Investigated and accepted — see LOW-09 notes | — | None (investigation only: re-read `OrderNumberGenerator.php` and confirmed the DB-level unique constraint) | Confirmed still exists, unchanged: `OrderNumberGenerator::generate()` is a plain check-then-act loop with no lock, backstopped by a genuine `->unique()` column (`orders` table migration), which includes trashed rows. See LOW-09 notes | 2026-11-01 (accepted) | Re-open only if `order_number` generation is ever moved off a DB-unique-backstopped column, or if daily order volume grows enough to warrant revisiting the collision math |
| LOW-10 | Low | No lock timeout for production migrations | NOT_STARTED | Review alongside the CRIT-01 migration, which is a quick additive change | — | Not applicable | Investigated (read-only) 2026-11-01 — still exists, unchanged: `.github/workflows/deploy.yml:256` (`php artisan migrate --force`) carries no timeout wrapper or lock/statement timeout of any kind. See LOW-10 notes for root cause, the proposed approach, and why it was deferred rather than implemented | — | Risk: Low. Complexity: Small. **DEFERRED 2026-11-01 — no implementation approved.** Needs a decided timeout value (depends on realistic migration duration) and confirmation that Hostinger's shell supports the proposed mechanism before any change. See LOW-10 notes |

## CRIT-01 notes

**Verified 2026-11-01 by reading code at `f36ee18`; no test reproduces it yet.**

- `CheckoutController::resolveFinalDiscount()` (`app/Http/Controllers/CheckoutController.php:397`) clamps redemption to the current `points_balance`. It ignores points already promised to other unpaid pending checkouts.
- `initiateMobileBanking()` (`:461`) stores `points_redeemed` and the discounted `total` in `checkout_data` for 2 hours. No points are debited or reserved.
- `debitPointsForOrder()` (`app/Services/LoyaltyPointsService.php:108`) decrements unconditionally. Its callers, `MobilePaymentController` (`:175`) and `OrderService::createFromPending()` (`:79`), do not recheck the balance.
- `users.points_balance` is signed (migration `2026_10_30_214612`), so the database will not stop a negative balance.
- **Result:** with 100 points, two pending checkouts can each redeem 100. Paying both grants both discounts and leaves the balance at -100. A COD order placed while a checkout is pending has the same effect.
- **Already covered, do not rebuild:** one checkout token cannot create two pending checkouts (CHECKOUT-COD-002), and one pending checkout cannot create two orders (row lock plus `order_number` check). Each order's debit runs once. The gap is across different checkouts.
- **Tests:** no overlapping-pending-checkout test was found in `tests/Feature/LoyaltyPoints*.php` or the mobile payment tests.

**Revalidating the balance at payment is rejected.** By then the customer has already sent the discounted amount by bKash or Nagad, and `expectedAmount` is fixed. A failed check would leave money received with no order, or force us to honour a discount the points no longer cover.

**Implemented: a derived reservation, with no separate release step.**
- `availablePoints()` = `points_balance` minus the sum of `points_reserved` on pending checkouts where `order_number` is null and `expires_at` has not passed. Conversion, cancellation (row deleted) and expiry end a reservation by state alone, so no scheduler is needed and nothing can be released twice. Refund and reversal code is unchanged.
- The mobile-banking checkout now reserves inside a transaction that locks the user first. COD and the checkout page use `availablePoints()`.
- **Concurrency:** the user lock must be the first statement in the transaction, so the plain `SUM` that follows sees all earlier commits under REPEATABLE READ. The COD path's lock moved above the address save for this reason. The sum is deliberately not a locking read, which avoids gap locks and deadlocks between users.
- **Payment paths** (`MobilePaymentController::submit()`, `OrderService::createFromPending()`) lock the user before the pending row, closing the race at the exact moment of expiry.
- **Cancellation:** `MobilePayment.tsx` shows a cancel link on the existing `payment.cancel` route, only when points are held. Without it, a customer who backs out would lose those points for up to 2 hours.
- No new index: the `user_id` foreign key index covers the query, which replaces the composite index suggested earlier. The landing-page flow does not redeem points and is unchanged.

**Files modified:** `app/Http/Controllers/CheckoutController.php`, `app/Http/Controllers/MobilePaymentController.php`, `app/Models/PendingCheckout.php`, `app/Services/LoyaltyPointsService.php`, `app/Services/OrderService.php`, `resources/js/pages/checkout/MobilePayment.tsx`, `database/migrations/2026_10_31_193742_add_points_reserved_to_pending_checkouts_table.php`, `tests/Feature/LoyaltyPointsReservationTest.php` (new), `tests/Feature/LoyaltyPointsConcurrencyRaceTest.php`.

**Verification evidence (2026-11-01, against `f36ee18` plus these changes):**
- `LoyaltyPointsReservationTest` on SQLite: 21/21 passed.
- Same file on MySQL 8.0.46, `shoppydrive_test_loyalty`: 21/21 passed, 97 assertions, twice. It includes the migration's up, down and backfill.
- Separate-process race tests on MySQL (contender verified blocked on the user lock): 2/2 passed. Run with `--filter`; the other 8 tests in that file were not rerun.
- With the reservation subtraction disabled, 9 of the 21 tests fail, so the tests detect the bug.
- Every test file touching checkout or pending checkouts on SQLite (26 files, 347 tests): 336 passed, 10 skipped (MySQL-only), 1 failed. The failure is listed below and is not caused by this change.
- Pint passed. `tsc` and ESLint on `MobilePayment.tsx` passed. Prettier passed.
- Not run: the full suite, and the `LoyaltyPointsConcurrencyRaceTest` deadlock-retry test. The real-browser flow was not exercised.

**Residual risks:**
- **Negative balance after revocation:** if a referral or refund revocation lowers the balance after points were reserved, paying that checkout can still take the balance negative. The customer has already paid, and negative balances from revocation are an approved rule (PAY-RETURN-001). Checkout redemption alone can no longer cause it.
- **Coupons and referral discounts** are not reserved. Overlapping pending checkouts may have the same class of problem and it was not assessed here.
- **Overlaps already open at deploy time:** the migration backfills them, but two existing pending checkouts that already share points can both still be paid. The window closes within 2 hours.

**Production preflight (read-only, before the migration):** this lists users whose open pending checkouts promise more points than they hold.
```sql
SELECT p.user_id, u.points_balance,
       SUM(JSON_EXTRACT(p.checkout_data, '$.points_redeemed')) AS promised
FROM pending_checkouts p JOIN users u ON u.id = p.user_id
WHERE p.order_number IS NULL AND p.expires_at >= NOW()
GROUP BY p.user_id, u.points_balance
HAVING promised > u.points_balance;
```

## HIGH-01 notes

**Verified 2026-11-01 against `f36ee18` plus these changes.** `Admin` had no activity logging, although `CLAUDE.md` described it as having some.

- **Coverage:** create, update (name, email, permissions, `is_mother`) and delete are logged under the `admin` log name. The causer is the acting admin (console runs have none) and the subject is the target admin. Deleted admins stay identifiable through the description and `old` properties.
- **Secrets:** `getActivitylogOptions()` uses an allow-list, so a password hash cannot reach the log. Password changes get a separate `password_changed` event with no value.
- **Password logging lives at the call sites**, not in a model event, because the guard can rewrite the hash at login when the hashing cost changes, which is not a person changing a password. The call sites are `AdminController::update()` and `admin:create-mother`, the only two paths that change a password.
- **Permissions:** `old` and `attributes` hold the full before and after, and `permission_changes` holds `granted` and `revoked`. The Audit Trail description names the changed fields; the page does not display properties.
- **No-op saves:** `AdminController::update()` now skips writing `permissions` when they are unchanged. A never-granted admin keeps `null` instead of becoming `[]`, which `canAccess()` treats identically, so saving an untouched form writes no entry.
- **Authorization is unchanged.**

**Files modified:** `app/Models/Admin.php`, `app/Http/Controllers/Admin/AdminController.php`, `app/Console/Commands/CreateMotherAdminCommand.php`, `tests/Feature/Admin/AdminAuditLogTest.php` (new). No migration.

**Verification evidence:**
- `AdminAuditLogTest`: 16/16 passed on SQLite and on MySQL 8.0.46 (`shoppydrive_test_loyalty`). It covers each operation, 7 failed-operation cases writing no entry, a no-op save, no password value or hash in the log, and the console path.
- Every test file touching admin accounts (10 files, 205 tests): 180 passed, 25 skipped (MySQL-only), 0 failed.
- Reverting to `logFillable()` fails the secrets test, and removing the unchanged-permissions check fails the no-op tests.
- Pint passed. Not run: the full suite.

**Residual risks:**
- An admin change and its audit entry are not written in one transaction. If the activity insert fails after an update, the change stands unlogged and the request errors. This matches every other audited model.
- `AuditTrailController::destroy()` lets the mother clear these entries, by existing design, and writes a `cleared` entry.
- The login guard's hash rewrite is deliberately not logged.

## HIGH-03 notes

**Verified 2026-11-01 against `f36ee18` plus these changes.**

- **What was actually broken:** `/wishlist` rendered `account/Wishlist` with no data, and `app-header.tsx` links there. The storefront's `Header.tsx`, which most visitors see, already linked to the working `/account/wishlist`, so it only failed guests, who were sent straight to login.
- **Fix:** `WishlistController::entry()` redirects signed-in customers to `account.wishlist`, so `index()` stays the only place that builds the list. Guests get `account/GuestWishlist`, outside the account layout, which shows their browser-saved count and sign-in, register and shopping links. `Header.tsx` now links to `wishlist.index`, and `app-header.tsx` already did.
- **Sign-in link:** it points at `account.wishlist`, so the `auth` redirect remembers the destination. The existing login merge moves guest items onto the account.
- **SSR:** `/wishlist` is excluded because the guest count only exists in localStorage.
- **Decision:** guests see a count, not product cards. Showing cards needs a new endpoint and product serialization that isn't shared yet, which is more than the smallest safe fix.
- **Unverified customers** following the link are sent to email verification, like every other account page.

**Files modified:** `app/Http/Controllers/WishlistController.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `routes/commerce.php`, `resources/js/components/layout/Header.tsx`, `resources/js/pages/account/GuestWishlist.tsx` (new), `tests/Feature/WishlistEntryTest.php` (new), `tests/Feature/HandleInertiaRequestsSsrExclusionTest.php`.

**Verification evidence:**
- `WishlistEntryTest`: 10/10 passed on SQLite and on MySQL 8.0.46. It covers guest and signed-in entry, list ownership, unverified customers, return after sign-in, toggle, sync, clear, add-all-to-cart and guest rejection. The last group had no tests before.
- With the old route restored, 3 of the 10 fail.
- Sweep of every wishlist, Inertia-middleware and referral test file (8 files, 157 tests): 147 passed, 10 skipped (MySQL-only), 0 failed.
- `tsc`, ESLint, Prettier and `npm run build` passed. Not exercised in a real browser.

**User decisions:** the count-only guest page is approved. No endpoint or product fetching will be added for guest cards.

**Limits of the wishlist evidence:** the login return is tested. The guest merge is browser logic in `app.tsx` and `AppLayout.tsx`, read but not run; only its `wishlist.sync` endpoint is tested. The Create Account link does not return to the wishlist, because registration goes through email verification first; the merge still applies on the next storefront page. The browser walkthrough below covers these.

## HIGH-02 notes

**Verified 2026-11-01 against `f36ee18` plus these changes.**

- **Cause confirmed:** `InventoryService` decrements `product_variants.stock_quantity` only. For a variant product, `products.stock_quantity` is a sum written on admin save, so it goes stale after every order. Parent-based stock appeared on product cards, the Compare page, the product page before a variant is selected, and both `in_stock` filters.
- **Fix:** `Product::available_stock` is appended on serialization. It sums already-loaded variants and otherwise returns `stock_quantity`, so it runs no query. Every storefront product query already loads `variants`. `scopeInStock()` gives the listing and search filters the same rule.
- **Frontend:** `ProductCard`, `Compare` and `ProductDetail` read `available_stock`. `CartDrawer` caps quantity by the selected variant's stock, matching `Cart.tsx`.
- **Quick-add:** `HomeController` and `WishlistController` blanked `variants` after loading them, so quick-add from those surfaces created a variant-less line that checkout rejects. They now send them. This adds payload but no queries, because `toArray()` already serialized them.
- **Decision:** when the default variant is sold out but others are not, the card opens the product page instead of silently adding a different size or colour.
- **Unchanged:** `InventoryService`, `Admin\ProductController`, locking, and bundle logic. Bundle pages do not show stock.

**Files modified:** `app/Models/Product.php`, `app/Http/Controllers/HomeController.php`, `app/Http/Controllers/WishlistController.php`, `app/Http/Controllers/ProductListingController.php`, `app/Http/Controllers/SearchController.php`, `resources/js/components/shared/ProductCard.tsx`, `resources/js/components/layout/CartDrawer.tsx`, `resources/js/pages/catalog/ProductDetail.tsx`, `resources/js/pages/compare/Compare.tsx`, `resources/js/types/index.ts`, `tests/Feature/ProductAvailableStockTest.php` (new). No migration.

**Verification evidence:**
- `ProductAvailableStockTest`: 25/25 passed on SQLite and on MySQL 8.0.46. Cases: variants in stock, all sold out, some sold out, stale zero parent, no variants, stock after a real order, and no query when variants are unloaded. Surfaces: homepage, listing, search, brand, flash sales, related products, product page and wishlist.
- With the old parent-only value restored, 16 of 25 fail. With the old filters restored, 2 of 25 fail.
- Sweep of every test file touching stock or variants (42 files, 539 tests): 529 passed, 10 skipped (MySQL-only), 0 failed.
- `tsc` passed, ESLint 0 errors (3 existing `ProductDetail.tsx` warnings), Prettier passed, `npm run build` passed.
- Not run: a real browser walkthrough. React cards are checked by types and lint only, as the project has no JS test runner.

## MED-01 notes

**User decision:** staff may use custom pricing, but every deviation from the system-calculated value needs a mandatory reason and an Audit Trail entry. This covers item price, discount and shipping. `orders.create` is unchanged.

- **Calculated values:** `App\Services\ManualOrderPricing`. Item prices use `CartManager::resolvePrice()`, as checkout does, covering flash sales and variants. Manual orders carry no `bundle_id`, so a component is priced standalone and a client-supplied `bundle_id` is ignored. Calculated discount is 0, because manual orders skip coupons, referrals and loyalty. Shipping uses the existing threshold, per-product and location rules on the catalog subtotal.
- **Enforcement:** `CreateOrderRequest::after()` recalculates server-side and compares in poisha. It rejects a variant from another product, which was previously accepted. Money fields now allow at most 2 decimals and an upper limit. Any client catalog price or override flag is ignored.
- **Audit:** one `pricing_override` entry in the `orders` log per order, only when something deviates, written inside the order transaction. It holds actor, order, product and variant, calculated and final item price, calculated and final shipping and discount, and reasons. Reasons are the only free text, and staff should not enter credentials there.
- **Form:** `create-order-modal.tsx` starts from a read-only `admin.orders.pricingPreview` response and shows a reason field only when a value changes. It no longer calculates shipping in the browser, which had missed per-product free delivery. The preview needs `orders.create` and decides nothing.
- **Shipping rule moved, not copied:** `IncompleteOrderController` now calls `ManualOrderPricing::shippingCost()`, so MED-10 gained no fourth copy. Its behaviour is unchanged.
- **Not changed:** coupon, promotion, checkout and shipping-rate logic, and historical orders. No migration.
- **Incomplete-order conversion** prices on the server with no client price or discount, so this finding does not apply. It uses variant or product price without flash-sale pricing, which is recorded under Observations.

**Files modified:** `app/Services/ManualOrderPricing.php` (new), `app/Http/Requests/Admin/CreateOrderRequest.php`, `app/Http/Controllers/Admin/OrderController.php`, `app/Http/Controllers/Admin/IncompleteOrderController.php`, `routes/admin.php`, `resources/js/components/admin/create-order-modal.tsx`, `tests/Feature/Admin/ManualOrderPricingTest.php` (new), `tests/Feature/Admin/IncompleteOrderManagementTest.php` (comment only).

**Verification evidence:**
- `ManualOrderPricingTest`: 22/22 passed on SQLite. With `CreateManualOrderTest`: 30/30 passed on MySQL 8.0.46.
- Comparing against `Product.price` only fails 2 of 22. Removing reason enforcement fails 7 of 22.
- Sweep of every test file touching order creation, checkout, incomplete orders, shipping rates and the Audit Trail (31 files): 388/388 passed.
- `tsc`, ESLint, Prettier and `npm run build` passed. Pint passed.
- Not run: the full suite, and a real browser walkthrough of the create-order form.

## MED-02 notes

**Verified 2026-11-01.** Before the fix, a late `pending_parcels` returned a shipped order to `pending`, and the customer could then cancel it, which restores stock and releases coupon and points. A test reproduced this.

- **Architecture:** no broader change needed. Raw status (`steadfast_status`, `pathao_order_status`) was already separate from `orders.status`, every report already gets a history note and append-only evidence row, and `sync()` is the single entry point, locking the order row in a transaction with its side effects.
- **Fix:** `FORWARD_ONLY_STATUSES` (pending, processing, shipped). For Steadfast, a report mapping to an earlier status changes nothing internal. It still updates the raw field, writes a history note ("status left unchanged", plus any `tracking_message`) and an `OBSERVATION` evidence row. No side effects fire, because `orders.status` never changes.
- **Pathao is deliberately excluded:** it is polled, so each report is current state rather than a delayed event, and `on_hold` and similar statuses after shipping are real. Its behaviour is unchanged and tested.
- **Covers all Steadfast sources:** webhook, admin refresh and dispatch. So a `processing` order sent to Steadfast now stays `processing`; `in_review` previously moved it back to `pending`.
- **Unchanged:** forward moves, delivered and cancelled reports (a genuine courier cancellation of a shipped order still runs its side effects once), terminal handling and the webhook signature check. No migration, and historical orders are untouched.

**Files modified:** `app/Services/CourierOrderStatusMapper.php`, `tests/Feature/SteadfastWebhookTest.php`, `tests/Feature/Services/CourierOrderStatusMapperTest.php`, `tests/Feature/LoyaltyPointsConcurrencyRaceTest.php`.

**Verification evidence:**
- `SteadfastWebhookTest` and `CourierOrderStatusMapperTest`: 51/51 on SQLite. On MySQL 8.0.46: 50 passed, 1 skipped. The skip is an existing test for a duplicate consignment, which MySQL's unique index makes impossible.
- Two-process MySQL race tests, with the contender confirmed blocked on the order lock: 2/2. A stale webhook behind the shipping transaction is refused, and a duplicate behind the first writes one report.
- With the rule removed, 6 of 51 focused tests and the stale race test fail.
- Courier sweep (23 files, 408 tests): 381 passed, 27 skipped (MySQL-only), 0 failed. Pint passed.
- Side effects asserted unchanged: stock, `stock_restored_at`, points, `points_refunded_at`, coupon usage and `coupon_released_at`.
- Not run: the full suite, and live Steadfast traffic.

**Limitations:**
- **Full webhook payload is not stored for staff.** It stays in `Log::info`. Staff see the raw status, `tracking_message`, history and evidence. Showing the whole payload would need a schema change to append-only evidence, and it was not needed for this fix.
- **Duplicates are detected against the last raw status.** A replay after a different status (A, B, A) writes one more observation, and internal status and side effects stay safe. Payloads carry no confirmed event ID or timestamp to do better.
- **The raw field shows the last report received,** so after a stale event it shows the stale value until the next report. Internal status stays correct, and history shows the sequence. Terminal orders already behave this way.
- **Steadfast's real ordering behaviour is unverified,** as it needs live traffic. The fix does not depend on it.

## MED-08 notes

**Root cause.** Four separate gaps, confirmed with real processes:
1. `updateOrCreate()` reads then inserts with nothing serializing the two steps, and no unique index can stop it, because `variant_id` and `bundle_id` are usually NULL and NULLs never collide.
2. `Cart::firstOrCreate()` has the same race and `carts.user_id` isn't unique, so one customer could get two carts.
3. `BundleService::autoAssemble()` writes rows and runs on page loads, so two views could split one row from stale copies. The report missed this.
4. Checkout priced a cart read before any lock, then built the order from that in-memory copy. The report missed this too.

With the locks removed, two processes produced 2 carts for one customer and 50 rows where 25 belonged.

**Affected entry points:** `cart.sync` (replace and merge), `cart.store`, wishlist add-all-to-cart, login merge, `checkout.store`'s own sync, and bundle assembly on page loads and the shared Inertia cart prop.

**Fix:**
- `CartManager::writeUserCart()` locks the user row, then the cart row, in a transaction. The user row is the lock because the cart row may not exist yet. It is the first statement, keeping the CRIT-01 snapshot rule. `writeSessionCart()` locks the cart row for guests. Lock order is always user, then cart.
- `mergeDuplicateRows()` runs in each locked write and collapses rows by product, variant and bundle into the oldest. Quantities are added, capped at stock, never below the largest row. `withoutDuplicateRows()` covers a checkout with no submitted items.
- `lockForCheckout()` runs inside both checkout transactions, right after the purchaser lock, and compares locked rows to what was priced. A mismatch throws `CartChangedException`: no order, no stock change, an error message, and the token can retry.
- `autoAssemble()` does a dry run first, so a cart with nothing to assemble takes no lock. When it must write, it locks the cart and re-reads.
- **No migration:** locks serialize writers, and consolidation handles rows that already exist. A unique index would not have worked.
- **Unchanged:** stock validation at checkout, pricing, coupon, payment and loyalty logic. The cart still accepts quantities above stock, as before; `InventoryService` enforces stock at order time.

**Files modified:** `app/Support/CartManager.php`, `app/Services/BundleService.php`, `app/Http/Controllers/CheckoutController.php`, `app/Exceptions/CartChangedException.php` (new), `tests/Feature/CartConcurrencyTest.php` (new), `tests/Feature/CartConcurrencyRaceTest.php` (new).

**Verification evidence:**
- SQLite: `CartConcurrencyTest` 18/18. Regression across 29 cart, checkout and bundle files: 367/367.
- MySQL 8.0.46 (`shoppydrive_test_loyalty`): `CartConcurrencyRaceTest` 4/4, using real separate processes. Two adds of the same 25 simple products, two adds of the same 25 variants, a full sync racing adds, and a real HTTP checkout blocked on the customer lock while the cart changed. `CartConcurrencyTest`, `CartPersistenceTest`, `BundleAutoAssembleTest` and `BundleCheckoutTest`: 29/29.
- With the locks and consolidation removed, all 3 write races fail.
- Pint passed. No frontend changed.
- Not run: the full suite, a real browser walkthrough, and a concurrent multi-process page-load test for `autoAssemble` (it is covered by a stale-copies test instead).

**Limitations:**
- **Guests:** a guest's first two simultaneous writes can each create a cart. That leaves an unused cart, not a duplicated line, and guests only write at checkout.
- **Existing second carts:** a customer who already has two carts keeps both. Reads and writes use one, so items in the other stay unused. Production not checked.
- **Lost updates:** a full sync is last-write-wins, so a racing add can be overwritten. The cart stays valid, but that add's quantity may not survive.
- **Display:** duplicate rows show until the next write or checkout collapses them.
- **Refusals:** a customer changing their cart at the exact moment of checkout now sees an error and must retry, instead of being charged from stale data.
- **Pending mobile payments:** the order uses the snapshot from checkout, so later cart changes don't affect it. That is existing behaviour, and the cart is still cleared on payment.

## MED-07 notes

**Root cause:** `Admin\ProductController::store()` and `update()` ran `Product::create/update()`, `syncVariants()` and `syncMedia()` as separate statements. `syncMedia()` deleted all media rows first, so a failure could leave a product without media, or half-saved variants, with uploaded files left behind.

**Writes mapped:** `products`, `product_variants` (updateOrCreate and unreferenced deletes), `product_attribute_values`, `product_media`, and audit entries from `Auditable`. Tags and categories are not written by this flow beyond product columns. Stock is written on variants and the denormalized product columns.

**Fix:**
- `saveAtomically()` wraps all of those in one `DB::transaction()`. An update also locks the product row, so two saves can't interleave media rebuilds.
- `storeUploads()` runs before the transaction, so no database lock is held during file I/O. Each stored path is tracked and removed if anything fails, including a later upload. A failed store now throws; before, it saved a broken `/storage/` path.
- Search and catalog caches flush again after commit, because the model events fire before it.
- Not retried on deadlock: a retry would reuse a model that believes the rolled-back attributes are saved. A failure surfaces as an error with the previous data intact.
- No migration. Validation, permissions, inventory and storefront logic are unchanged.

**Filesystem behaviour:**
| Situation | New upload | Old file |
|---|---|---|
| Failed create or update | Removed | Kept |
| Successful update | Kept | Kept |

**User decision (2026-11-01):** keep old media files for now, and do not delete them automatically after a successful product update. `syncMedia()` has never deleted files, and order lines keep the path in `product_snapshot.image`, shown on `admin/orders/show`. Safe cleanup may be considered later as a separate task, with reference checks across order snapshots and other products.

**Files modified:** `app/Http/Controllers/Admin/ProductController.php`, `tests/Feature/Admin/ProductSaveAtomicityTest.php` (new).

**Verification evidence:**
- `ProductSaveAtomicityTest`: 11/11 on SQLite. With `ProductVariantManagementTest`: 29/29 on MySQL 8.0.46.
- Failures injected while creating and updating variants and while creating media, including on the last media row.
- With the transaction removed, 6 of 11 fail. With file cleanup removed, 6 of 11 fail.
- Sweep of every product, variant, media, inventory and catalog cache test file (46 files, excluding MySQL-only race files): 613/613.
- Pint passed. No frontend changed.
- Not run: the full suite, a browser walkthrough, and concurrent admin saves on MySQL.

**Limitations:**
- Old files remain on disk by user decision, so unused files accumulate.
- If file cleanup itself fails after a database failure, that upload is left behind unreferenced. It harms nothing.
- A process killed between storing uploads and committing leaves the same kind of unreferenced file.

## MED-04 notes

**Flow verified:** the forwarder posts to `/api/sms/receive`, `SmsParserService` extracts provider, transaction ID and amount, and `MobilePaymentController` and `LandingPageController` match a customer's submission on provider, transaction ID and amount, with row locks and the `is_matched` check. That logic is unchanged.

**Fix:**
- `App\Support\SmsRegex` is the only place a pattern runs. It applies a 100,000 backtrack limit around each match, restored afterwards. Measured on PHP here, a catastrophic pattern stops in about 2 ms with JIT off and under 1 ms with it on.
- `App\Rules\SafeSmsRegex` rejects, on create and update: invalid syntax, an unescaped `/` or injected modifier, no capture group, and patterns that exhaust the limit on 2,000-character probes. Probes include the regex's own text, because PCRE otherwise skips matching when a required literal is absent.
- A regex that can't run, including a legacy one, yields null, and the existing payment checks then refuse it: no transaction ID gives `not_found`, no amount gives `amount_unavailable`. The failure is logged with pattern ID, field and PCRE error only, never SMS content.
- SMS content over 2,000 characters is acknowledged and ignored, with only its length logged. It is acknowledged because the forwarder retries on anything else.
- `SmsPattern` uses `Auditable`, recording admin and before/after configuration for create, update, delete and status changes. Incoming SMS content is never in these entries.
- `destroyMany()` deletes one at a time. The old bulk delete skipped model events, so a deleted pattern stayed active in the 5-minute cache and could not be audited.
- Regex validation is now 255 characters, matching the column, in the controller and form. 500 characters would have failed on save in MySQL strict mode.

**No migration.** The cached pattern shape gains `id`, and old entries expire within 5 minutes.

**Files modified:** `app/Support/SmsRegex.php` (new), `app/Rules/SafeSmsRegex.php` (new), `app/Services/SmsParserService.php`, `app/Http/Controllers/SmsTestController.php`, `app/Http/Controllers/Admin/SmsPatternController.php`, `app/Models/SmsPattern.php`, `resources/js/pages/admin/sms-patterns/index.tsx`, `tests/Feature/SmsPatternSafetyTest.php` (new).

**Verification evidence:**
- `SmsPatternSafetyTest`: 30/30 on SQLite. With `MobilePaymentMatchingTest`: 44/44 on MySQL 8.0.46.
- Payment safety, through the real forwarder and payment endpoints: broken legacy transaction regex, amount regex or both, wrong amount, wrong transaction and wrong provider all left 0 orders. A valid bKash and a valid Nagad payment each paid an order. A duplicate SMS stored one record, and a second customer reusing it was refused.
- Removing save-time validation fails 8 of 30. Removing the controller oversize guard fails 1. Removing audit logging fails 3. Removing the regex text from the probes fails 1.
- Sweep of every SMS, mobile payment and Audit Trail test file (12 files, excluding MySQL-only race files): 277/277.
- `tsc`, ESLint, Prettier and Pint passed.
- Not run: the full suite, and real forwarded SMS traffic.

**Limitations:**
- **Phone numbers are not verified.** The flow never collected or compared a sender number, so a test records that behaviour. The requested phone test could not be a rejection test. Adding phone matching would be a payment rule change.
- **Existing production patterns are unchecked.** A legacy bad pattern fails closed, but payments then need manual handling until it is fixed. See the checklist.
- **A failing pattern does not fall through.** The first pattern whose identifier matches is used, as before, so a broken one blocks a later valid one with the same identifier.
- **Probes are heuristic.** A slow pattern can still be saved. The runtime limit is the real protection, and it limits backtracking steps, not wall-clock time. If `ini_set` is disabled on the host, PHP's default limit of 1,000,000 applies, which stays bounded at about 20 ms measured here without JIT.
- **Other SMS logs:** `SmsTestController` still logs sender and transaction ID at info level, as it did before. It is listed under Observations.

## MED-03 notes

**Root cause:** `app:drain-queue` runs `queue:work --once --tries=3`, which writes exhausted jobs to `failed_jobs`, but no code read that table. Failures only reached the application log, and there was no listener, alert or page.

**What is queued:** notification emails and `AbandonedCartReminderJob`. No order, payment, refund or stock operation runs on the queue, so a retry cannot repeat one. `Order::booted()` sends the order emails after the order work is already committed.

**Retry allow-list (`FailedJobSummary::RETRYABLE_JOBS`):** `OrderPlacedMail`, `NewOrderAlertMail`, `ShippedWithTrackingMail`, `OrderDeliveredMail`, `OrderCancelledMail`, `OrderStatusUpdatedMail`, `ReturnApprovedMail`, `ReturnRejectedMail`, `ReviewApprovedMail`, `WelcomeMail`, `LowStockAlertMail`, `AbandonedCartReminderJob`.
- Each only sends a message. The job re-checks `reminder_sent_at`, so after a claim it does nothing.
- A resent email is possible if the mail server accepted it before the failure, and the confirmation says so.
- Emails read current order state, so a late retry shows current data, not the state at the original failure.
- **Not retryable:** `AbandonedCartReminderMail`, because the job that checks a reminder is still wanted has already passed, and every class not listed, including unknown or malformed payloads. Nothing retries automatically.

**Implementation:**
- `App\Support\FailedJobSummary` JSON-decodes the payload for metadata only and never unserializes it.
- `Admin\FailedJobController`, the `admin/failed-jobs/index` page, and a new `failed_jobs` permission section: `view` to see, `update` to retry, `delete` to remove a record. The mother admin bypasses as usual. Other admins have no access until granted.
- Retry locks the row and runs Laravel's `queue:retry` in the same transaction, so a double submit queues once. Retry and delete require a confirmation dialog and a server-side `confirmed` field, and both are audited with admin, UUID, job name and queue.
- There is no clear-all action. Records stay until retried or deleted individually.
- The dashboard shows a failed-job count, only to staff who can open the page.
- No migration, as `failed_jobs` already exists. Queue, drain and job behaviour are unchanged.

**Redaction:** the page never receives the payload, recipients, stack trace, or file paths. The error is the exception class plus its first line, cut at `(Connection:`, `SQL:` and ` in /`, with emails, URLs, phone numbers, IP addresses, passwords, tokens, long secrets and numbers of 6 or more digits replaced, and limited to 240 characters.

**Files modified:** `app/Support/FailedJobSummary.php` (new), `app/Http/Controllers/Admin/FailedJobController.php` (new), `app/Support/AdminSections.php`, `app/Http/Controllers/Admin/DashboardController.php`, `routes/admin.php`, `resources/js/pages/admin/failed-jobs/index.tsx` (new), `resources/js/layouts/admin-layout.tsx`, `resources/js/pages/admin/dashboard.tsx`, `tests/Feature/Admin/FailedJobManagementTest.php` (new).

**Verification evidence:**
- `FailedJobManagementTest`: 19/19 on SQLite. With `DrainQueueCommandTest`: 28/28 on MySQL 8.0.46.
- End to end with a real queued `OrderPlacedMail` and a real worker: the send failed and was recorded, a retry failed and stayed visible, a third attempt succeeded, and exactly 1 email was sent.
- With redaction off, 2 of 19 fail. With the allow-list off, 5 of 19 fail.
- Sweep of queue, permission, dashboard and mail test files (22 files, excluding MySQL-only race files): 333/333.
- `tsc`, ESLint, Prettier, `npm run build` and Pint passed.
- Not run: the full suite, a real browser walkthrough, and real SMTP failures.

**Limitations:**
- **No push alert:** staff see failures on the dashboard or page, not by email. An alert email would likely fail the same way during a mail outage.
- **Redaction is pattern-based.** A customer's name in an exception would not be caught. The first line of real mail and SQL errors is covered, but unusual text could pass.
- **Limited context:** the order or recipient is not shown, because reading it needs the payload. `max_tries` is often blank, because tries are set on the worker, and attempt counts aren't stored in `failed_jobs`.
- **Concurrent retries on MySQL** rely on the row lock. The double-submit test is sequential.

## MED-16 notes

**Headers:** `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`.

**Placement:** `App\Http\Middleware\SecurityHeaders`, added with `$middleware->prepend()` in `bootstrap/app.php`. As the outermost global middleware it covers web, API, `/up`, redirects, blocked-IP 403s, and rendered 404 and 500 pages.

**Compatibility checked:**
- **Framing:** no `<iframe>` or cross-window messaging exists in `resources/`. A test now fails if one is added. Payment and social login redirect rather than frame.
- **Referrer:** this matches modern browser defaults. Same-site referrers stay intact. `CaptureTrafficSource` reads the incoming `Referer`, which the linking site's policy controls. External sites receive the origin only.
- **Conflicts:** no existing code or `.htaccess` rule sets these headers, and the app sends one value of each. Hostinger's own server configuration can't be seen from here.
- **API, webhooks, PDF invoices, file and streamed responses:** tested, unchanged apart from the added headers.

**Files modified:** `app/Http/Middleware/SecurityHeaders.php` (new), `bootstrap/app.php`, `tests/Feature/SecurityHeadersTest.php` (new). No migration.

**Verification evidence:**
- `SecurityHeadersTest`: 18/18 on SQLite. It covers storefront, login, register, checkout, account, admin, guests redirected, 404, 500, blocked-IP 403, `/up`, JSON API, webhook, PDF invoice, file and streamed downloads.
- Removing the middleware fails 16 of 18. Moving it to the web group only fails 7.
- Broad sweep of 144 of 160 test files (every feature file making HTTP requests, excluding MySQL-only race files): 1392 passed, 2 skipped, 0 failed. This is not the full suite. Pint passed.
- MySQL was not run, as this change has no database behaviour.

**Limitations:**
- **Static files get no headers.** Apache serves `/storage` uploads and `/build` assets without reaching PHP, so `nosniff` doesn't apply to them. Covering them needs an `.htaccess` change, which was out of scope.
- **External embedding is blocked.** Anything outside the site that frames a page, such as a third-party preview tool, will now fail. Nothing in the repository does this, and external use can't be seen from here.
- **Production not verified.** A CDN or Hostinger could add or alter headers.

**Separate considerations, not implemented:**
- **HSTS:** needs HTTPS enforcement and proxy setup confirmed first, alongside MED-18. A wrong setting can lock visitors out for the length of the policy.
- **Content-Security-Policy:** would also provide `frame-ancestors`, the modern replacement for `X-Frame-Options`. It needs care around Google Analytics and inline scripts.

## MED-15 notes

**Status: implemented in `deploy.yml` locally, not committed or deployed, by user approval on 2026-11-01.** The user confirmed in Hostinger's terminal that production reports `APP_ENV=production` and `APP_DEBUG=false`. The production `.env` is unchanged, and production was not contacted from this session. The earlier proposal to add both keys to the existing grep loop was withdrawn: that loop reads file text rather than the values Laravel uses, and it runs after the upload, when the new code is already live.

**Existing deployment flow (`.github/workflows/deploy.yml`):**
1. A push to `main` starts `checks` (Pint and Pest on a `.env.example` copy, which says nothing about production).
2. `deploy` waits for the `production` Environment, if required reviewers are configured, then builds on the GitHub runner: `composer install --no-dev`, Ziggy, `npm run build`.
3. It connects to Hostinger over SSH using `SSH_PRIVATE_KEY`, `SSH_HOST`, `SSH_PORT`, `SSH_USER`, `DEPLOY_PATH`, with `StrictHostKeyChecking=no` (accepted risk M1).
4. `rsync --delete` uploads directly into `DEPLOY_PATH`. **There are no releases, symlink or activation step, so files are live as they land.**
5. The server `.env` sits at `DEPLOY_PATH/.env`. It is preserved by `--exclude='.env'` and is managed only by the user. Nothing uploads or overwrites it.
6. A post-deploy SSH script then checks `.env` exists and greps 5 keys, clears caches, runs `migrate --force`, and rebuilds caches.
7. A `curl` of `/up` confirms the app boots.

**Answers to the architecture questions:**
- **Can GitHub Actions read production `.env`?** No, and it must not. A check has to run on the server, in Laravel, over the existing SSH access.
- **Can Laravel run a check there?** Yes. The workflow already runs artisan over SSH, as `migrate --force` and others do.
- **Can a deploy be stopped before going live?** Only before step 4. There is no later activation point, so the check must run before `rsync`.

**The cached config trap:** `bootstrap/cache/config.php` is what the live site uses, and `rsync` excludes it. A plain check before upload reads that cache, not `.env`, and step 6's `config:cache` then adopts whatever `.env` says. Setting `APP_CONFIG_CACHE` to a path that doesn't exist makes Laravel read `.env` for that one command, without clearing or writing anything.

**Implemented approach:**
1. **New step before `rsync`:** over SSH, run the framework's `about` command with the cache bypassed, and have PHP read its JSON. Output is one pass or fail line. A failure exits before `rsync`, so nothing is uploaded and the site is untouched.
   ```
   APP_CONFIG_CACHE=storage/framework/deploy-check-no-config-cache.php \
     php artisan about --only=environment --json 2>/dev/null \
     | php -r '$a = json_decode(stream_get_contents(STDIN), true)["environment"] ?? []; exit(($a["environment"] ?? null) === "production" && ($a["debug_mode"] ?? null) === false ? 0 : 1);'
   ```
2. **Second check after `config:cache`:** the same command without the bypass, confirming what the site now runs. A failure here marks the deploy failed, but cannot stop it, because the code is already live.

It uses `about` rather than a new command, because the pre-upload step runs the code already on the server, which wouldn't contain a new command on the first deploy.

**Local proof (this PC only, not production):** production with debug off passes. Debug on, or any other environment, fails. `APP_DEBUG=FALSE` and `0` read as false. Empty output, garbage output and a missing artisan all fail closed. It passes with no `composer` on the PATH. `about --only=environment` returns no secrets (name, versions, environment, debug mode, URL, maintenance mode, timezone, locale), and the check prints none of it. A cached production config with a debug `.env` wrongly passes a plain check and correctly fails with the bypass. No file was created at the bypass path.

**Implementation, `.github/workflows/deploy.yml` only (59 insertions, 0 deletions):**
- A job-level `VERIFY_RUNTIME_SCRIPT` holds the check once, so no secrets and no nested quoting are involved. Its modes are `env-file` and `active-config`.
- `Verify production runtime before deploying` runs after `Setup SSH agent` and before `Sync files to server`.
- `Verify production runtime after deploying` runs after the post-deploy tasks and before the health check. If it fails, the health check is skipped and the run fails.
- Both steps set `shell: bash`, which gives `pipefail` on the runner, and the remote script runs `set -euo pipefail`. `ssh` returns the remote exit code.
- **Additional guards found during implementation:** Laravel defaults to `production` with debug off, so a missing `.env` could look like a pass. The `env-file` mode requires `.env`, and `active-config` requires `bootstrap/cache/config.php`.
- `2>/dev/null` keeps Laravel's error output out of the logs, and the script prints only its own fixed messages.

**Verification evidence (local, no SSH, no Hostinger):** the script was extracted from the parsed YAML and piped to `bash -s` under `bash -eo pipefail`, as the runner does, with `ssh` replaced by a local shell. `bash -n` and YAML parsing passed, and step order was confirmed.
- Real Laravel: production with debug off passed. Debug on, staging, and the local `.env` failed.
- Stubbed directories: valid output with `.env` passed, and valid `active-config` with a cache passed. A missing `.env`, a missing cache, valid JSON followed by a non-zero exit (`pipefail`), garbage output, a fatal error and a bad path all failed.
- A secret in an exception message did not reach the output.
- Not tested: real `ssh`, GitHub Actions itself, Hostinger's PHP, or `active-config` against real Laravel with a real config cache.

**Limitations:**
- **The first real run is the first proof on Hostinger.** The user's check used plain output, and `--json` with the cache bypass hasn't run there. A PHP warning printed to stdout would break the JSON and fail closed, blocking the deploy with the site unchanged.
- **Remote `bash` is required.** The existing post-deploy script already relies on bash brace expansion.
- **The pre-upload check runs old code.** If artisan cannot start on the server, as in the earlier stale `packages.php` failure, deploys are blocked until that is fixed by hand. This is a safe failure, but it is a new way for a deploy to stop.
- **Point-in-time only.** It runs at deploy time, so an `.env` edit afterwards is not noticed until the next deploy. Cached config keeps the site on its old values until then.
- **CLI, not web.** It checks the PHP CLI. The web app reads the cached config that CLI builds, so the values match, but a web-only override in server configuration would not be seen.
- **The post-upload check cannot roll back.** That needs DEPLOY-SAFETY-001 Tier 2.

## MED-06 notes

**Status: BLOCKED (DEFERRED BY USER), not COMPLETED.** Application code and disposable MySQL verification are completed. The production duplicate and schema verification is deferred by user decision on 2026-11-01. The migration has not run on the dev or production databases, and no data was cleaned.

> **NOT SAFE TO DEPLOY.** `database/migrations/2026_10_31_223219_add_unique_indexes_to_variant_skus_and_attribute_values.php` stays in place but must not reach production until the preflight is completed. `deploy.yml` runs `php artisan migrate --force` automatically, so pushing this file to `main` could run it on production. If production holds duplicates, it would fail after the new code is already live. Leave it out of any commit to `main`, or complete the preflight first.

**Root cause:** both columns relied on check-then-insert validation, so two simultaneous saves could each pass and insert a duplicate.

**Schema findings:** both columns are `varchar(255)` with collation `utf8mb4_unicode_ci`. `sku` is nullable, `value` is not. Neither table uses soft deletes. No import path exists: the only writers are `ProductController::syncVariants()` and `AttributeValueController`. The dev database has 0 variants and 9 attribute values with no duplicates, so it says nothing about production.

**Collation decision:** no collation change. Verified on MySQL 8.0.46 with read-only comparisons: `a`=`A`, `e`=`é`, `a`=`a ` (trailing space), `চা`=`চাঁ`, `দু`=`দুঃ`, `৪২`=`42`, while distinct Bangla letters and vowels (`স`/`শ`, `কি`/`কী`, `ব`/`র`) stay different.
- **attribute_values:** `Rule::unique` already compares through this collation on MySQL, so the index rejects exactly what validation rejects today. Two values differing only by chandrabindu or visarga are already refused. That is existing behaviour, recorded rather than changed.
- **SKUs:** codes, so this equality is suitable. Validation only folds case, so accent-only or similar collisions reach the index and get the friendly error.
- **SQLite differs:** it compares bytes, so case-only collisions aren't rejected by the index there. Only MySQL tests prove collation.

**Migration `2026_10_31_223219_add_unique_indexes_to_variant_skus_and_attribute_values`:**
- Adds `product_variants_sku_unique` and `attribute_values_attribute_id_value_unique`.
- Checks for duplicates first and throws with the first ID of each group, before any ALTER. It never changes data or picks a survivor. Each ALTER commits on its own, so this prevents a half-applied state, and each index is skipped if it already exists.
- `down()` drops both. Existing non-unique indexes stay.
- Multiple NULL SKUs are intentional.

**Application changes:**
- `ProductController::prepareVariantRows()` runs first in `syncVariants()`: it deletes unreferenced retired variants and clears each SKU about to change, so SKU swaps and takeovers don't hit the index mid-save. It runs inside the MED-07 transaction.
- A retired variant still referenced keeps its SKU, and reusing that SKU is refused.
- Store and update turn a SKU unique violation into a friendly `variants` error. Other unique violations are rethrown. `AttributeValueController` does the same for `value`.

**Files modified:** `database/migrations/2026_10_31_223219_add_unique_indexes_to_variant_skus_and_attribute_values.php` (new), `app/Http/Controllers/Admin/ProductController.php`, `app/Http/Controllers/Admin/AttributeValueController.php`, `tests/Feature/Admin/VariantAndAttributeUniquenessTest.php` (new), `tests/Feature/Admin/VariantAndAttributeUniquenessMysqlTest.php` (new).

**MySQL verification (2026-11-01, user-approved, disposable database only):**
- **Target confirmed through Laravel:** connection `mysql`, database `shoppydrive_test_loyalty`, host `127.0.0.1`, config not cached, MySQL 8.0.46. Checked again before the tests.
- **Migration:** ran in 78.76 ms. Rollback ran in 68.97 ms and left 0 index rows. Reapply ran in 74.80 ms. After the test run's fresh schema, the status is Ran in batch 1 with no pending migrations.
- **Indexes:** `product_variants_sku_unique` is unique BTREE on `sku`. `attribute_values_attribute_id_value_unique` is unique BTREE on `attribute_id, value`. Neither uses a prefix length.
- **Tests: 24/24 passed, 65 assertions.** That is 10 in `VariantAndAttributeUniquenessMysqlTest` and 14 in `VariantAndAttributeUniquenessTest`.
- **Collation cases (8/8):** `sku` rejects case, accent and trailing-space variants. `attribute_values` rejects `XL`/`xl`, `৪২`/`42` and `চা`/`চাঁ`, and accepts `স`/`শ` and `কি`/`কী`.
- **Also passed on MySQL:** exact duplicate SKU and attribute value rejected, 3 NULL SKUs allowed, SKU swap and takeover, retired SKU, friendly errors, rollback and reapply, refusal with IDs and no change, and resuming with one index present.
- **Race:** two separate processes tried the same 40 SKUs and 40 values at shared moments, 160 attempts in all. Exactly 80 succeeded, one per SKU and value, both processes won some, and the rest were clean unique violations.
- **Dev database:** `ShoppyDrive` was compared before and after, and is identical: 130 migrations, 0 new indexes, 0 variants, 9 attribute values.
- The race test runs at the database level. The friendly error path after a lost race is covered by the simulated tests.

**Verification evidence (in-memory SQLite):**
- `VariantAndAttributeUniquenessTest`: 14/14. It covers index rejection, multiple NULL SKUs, existing validation messages, SKU swap, takeover, resave, retired SKU, simulated race for both tables, other unique errors rethrown, rollback, refusal with IDs and no change, and resuming with one index present.
- Removing the pre-pass fails 2 of 14. Removing the friendly catch fails 2 of 14.
- Sweep of 146 of 162 test files: 1406 passed, 10 skipped (MySQL-only), 0 failed. Not the full suite. Pint passed.
- The SQL below ran as SELECT only against the dev database to confirm its syntax.

**Production read-only check (the user runs this; rows in sections 2, 4 or 6 need a decision):**
```sql
-- 1. Server and column details
SELECT VERSION() AS server_version, @@innodb_default_row_format AS default_row_format;
SELECT table_name, column_name, column_type, collation_name
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND ((table_name = 'product_variants' AND column_name = 'sku')
    OR (table_name = 'attribute_values' AND column_name = 'value'));

-- 2. Duplicate SKUs, as the unique index would see them
SELECT pv.sku, COUNT(*) AS rows_found, GROUP_CONCAT(pv.id ORDER BY pv.id) AS variant_ids,
       GROUP_CONCAT(DISTINCT pv.product_id ORDER BY pv.product_id) AS product_ids
FROM product_variants pv
WHERE pv.sku IS NOT NULL
GROUP BY pv.sku
HAVING COUNT(*) > 1;

-- 3. What references each duplicate variant
SELECT pv.id AS variant_id, pv.product_id, pv.sku, pv.stock_quantity,
       (SELECT COUNT(*) FROM order_items x WHERE x.variant_id = pv.id) AS order_items,
       (SELECT COUNT(*) FROM cart_items x WHERE x.variant_id = pv.id) AS cart_items,
       (SELECT COUNT(*) FROM product_media x WHERE x.variant_id = pv.id) AS media,
       (SELECT COUNT(*) FROM flash_sale_items x WHERE x.variant_id = pv.id) AS flash_sale_items,
       (SELECT COUNT(*) FROM product_bundle_items x WHERE x.variant_id = pv.id) AS bundle_items
FROM product_variants pv
JOIN (SELECT sku FROM product_variants WHERE sku IS NOT NULL GROUP BY sku HAVING COUNT(*) > 1) d ON d.sku = pv.sku
ORDER BY pv.sku, pv.id;

-- 4. Duplicate attribute values, as the unique index would see them
SELECT av.attribute_id, av.value, COUNT(*) AS rows_found, GROUP_CONCAT(av.id ORDER BY av.id) AS value_ids
FROM attribute_values av
GROUP BY av.attribute_id, av.value
HAVING COUNT(*) > 1;

-- 5. What references each duplicate attribute value
SELECT av.id AS value_id, av.attribute_id, av.value,
       (SELECT COUNT(*) FROM product_attribute_values x WHERE x.attribute_value_id = av.id) AS products,
       (SELECT COUNT(*) FROM product_variants x WHERE x.color_value_id = av.id OR x.size_value_id = av.id) AS variants
FROM attribute_values av
JOIN (SELECT attribute_id, value FROM attribute_values GROUP BY attribute_id, value HAVING COUNT(*) > 1) d
  ON d.attribute_id = av.attribute_id AND d.value = av.value
ORDER BY av.attribute_id, av.value, av.id;

-- 6. Blank SKUs: more than one fails the index, because '' is not NULL
SELECT COUNT(*) AS blank_skus FROM product_variants WHERE sku IS NOT NULL AND TRIM(sku) = '';
```

**Data cleanup requirements:** unknown until the query is run. Any duplicates need a person to choose. Nothing should be merged or deleted automatically, because variants can be referenced by orders and carts.

**Risks and limitations:**
- **Duplicates in production would block the migration after the code is live.** The deploy runs `migrate --force` after `rsync`, so the check must run before pushing. The migration fails cleanly, but the deploy would fail at that step.
- **Locking:** adding each index rebuilds or scans its table. Expected to be brief at this size, but untimed on Hostinger.
- **Index size:** 255 utf8mb4 characters is 1,020 bytes, fine on DYNAMIC row format and possibly too long on old COMPACT tables. The query reports the server and row format. Compatibility with the production server is unconfirmed.
- **Retired SKUs:** a retired variant holds its SKU permanently while referenced.
- **Existing behaviour:** a chandrabindu or visarga-only difference can't be stored as separate values.

## MED-05 notes

**Sub-items:**
| Sub-item | Status | Delivered by |
|---|---|---|
| ReturnRequest approve, reject | Done before, not reopened | `logReturnAction` (PAY-RETURN-001) |
| SmsPattern create, update, delete, bulk | Done before, not reopened | MED-04 |
| Review approve, reject, delete | Done | `Admin\ReviewController` |
| BlockedIp create, delete | Done | `Auditable` on `BlockedIp` |
| ContactMessage delete, clear-all | Done | `Admin\ContactMessageController` |
| Newsletter subscriber delete | Done | `Admin\NewsletterController` |
| PopularKeyword create, update, delete, bulk | Done | `Auditable` on `PopularKeyword` |

**Implementation:**
- `BlockedIp` and `PopularKeyword` are only written by admins, so they use `Auditable`.
- `Review`, `ContactMessage` and `NewsletterSubscriber` are created by customers, so model-level auditing would log customer activity. `App\Concerns\RecordsAdminActions` records only the admin's action. A test confirms customer submissions write nothing to the trail.
- **Data:** reviews record ID, product, rating and approval change, never the text or author. Contact messages and subscribers record ID and a masked email such as `r*****@example.com`, never the content or token. Clear-all records a count. Blocked IPs record the address and reason, because the address is the point of the record.
- **Atomic:** each action and its entry share a `DB::transaction()`, so a failed audit write rolls the action back.
- **No duplicates:** approval logs only a real change, read under a row lock. Rejecting a pending review changes no column, so the review's last recorded decision separates a real rejection from a repeat click. An empty clear-all writes nothing.
- **Bulk keyword delete** now deletes one at a time, because the bulk query skipped model events.
- **Forever caches:** the block list and keyword cache are flushed again after commit, because the model events fire before it and a reader could otherwise store old data permanently.

**Files modified:** `app/Concerns/RecordsAdminActions.php` (new), `app/Http/Controllers/Admin/ReviewController.php`, `app/Http/Controllers/Admin/BlockedIpController.php`, `app/Http/Controllers/Admin/ContactMessageController.php`, `app/Http/Controllers/Admin/NewsletterController.php`, `app/Http/Controllers/Admin/PopularKeywordController.php`, `app/Models/BlockedIp.php`, `app/Models/PopularKeyword.php`, `tests/Feature/Admin/RemainingAdminAuditTest.php` (new). No migration.

**Verification evidence:**
- `RemainingAdminAuditTest`: 19/19 on in-memory SQLite. Atomic rollback is covered for 7 actions.
- With `BlockedIpManagementTest` and `ContactMessagesTest`: 29/29 on MySQL 8.0.46, database `shoppydrive_test_loyalty`, confirmed before running.
- Removing the repeat-rejection guard, a transaction, or the one-at-a-time bulk delete each fails its test.
- Sweep of 147 of 163 test files: 1425 passed, 12 skipped, 0 failed. Not the full suite. Pint passed.
- The dev database was compared to its baseline and is unchanged.

**Limitations:**
- **Other admin actions not listed in MED-05 remain unaudited,** such as newsletter sends and customer deletion. These are outside this finding.
- **Clearing the audit trail erases these entries too,** by existing design, and writes a `cleared` entry.
- **Reject after clearing:** the repeat check reads the trail, so after the trail is cleared, one repeat rejection could log again.
- **Review deletion by a customer** or other non-admin route is not covered, as none exists in the admin flow.
- **Bulk keyword changes** write one entry per row.

## MED-12 and MED-13 notes

**Test-only work. No application code changed.**

**MED-12 coverage (`InvoiceDownloadTest`, 25 cases):** a real PDF response (content type, attachment name, `%PDF-` content). Delivered COD downloads; pending, processing and shipped COD are refused. Prepaid mobile banking and ZiniPay download before delivery. Cancelled and refunded are refused for every payment method. Other customer's order, guest order through an account, logged-out, unverified, unknown order number and trashed order. Invoice content, isolation from other orders, variant attributes, deleted variant and product, and the guest landing page token.

**MED-13 cases added (`WishlistActionsTest`, 17 cases):** toggle-off, ownership on toggle, sync and clear, toggle and sync validation including the 200 limit, no duplicate rows on sync, empty add-all, merging with an existing cart, the JSON response, unverified access, and deleted products on the wishlist page. `WishlistEntryTest` already covered the rest.

**Verification evidence:**
- Focused: `InvoiceDownloadTest`, `WishlistActionsTest` and `WishlistEntryTest`: 50 passed, 2 skipped, 0 failed on in-memory SQLite.
- Removing the ownership check fails 2 invoice tests, the COD rule fails 3, and the cancelled rule fails 4. `routes/account.php` was restored byte-identical.
- Sweep of 149 of 165 test files: 1465 passed, 14 skipped, 0 failed on the clean rerun. Not the full suite. Pint passed.
- **MySQL was not run.** These tests cover no MySQL-specific logic, so no MySQL database was touched. The CRIT-01 and MED-06 migrations stay pending on dev and production. In-memory SQLite runs all migrations temporarily, as every test does.

**Intermittent failure:** one sweep run errored once in `GuestCheckoutTest` ("No query results for model Order"). It passed 3 of 3 runs alone, and the rerun of the sweep was clean. The symptom matches TEST-FLAKE-001, recorded earlier for `BuyNowCheckoutTest`. Root cause unconfirmed.

**Uncovered:** concurrent wishlist requests, a real browser download, and a failed PDF render.

## Bugs exposed by MED-13, fixed locally

Both fixed by user decision on 2026-11-01, locally verified and not deployed.

| ID | Root cause | Before | After | Status |
|---|---|---|---|---|
| WISHLIST-ADDALL-VARIANT-001 | `addAllToCart` sent `variant_id: null` for every row and then emptied the wishlist. `InventoryService` refuses a variant-less line for a product with variants | Cart line with no variant, item removed from the wishlist, checkout said "out of stock" with stock available | Stays on the wishlist, no cart line, customer told to choose a size or colour | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED |
| WISHLIST-ADDALL-DELETED-001 | `CartManager::resolvePrice()` calls `findOrFail()`, so one soft-deleted product turned the whole action into a 404 | 404, nothing added | Skipped and counted, others added, wishlist record kept | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED |

**Implementation:**
- `WishlistController::addAllToCart` now locks the user row and, in one transaction, classifies each row, adds active, published, variant-less products through `syncUserCart(merge: true)`, and removes only those rows from the wishlist. A failed cart write or wishlist removal rolls back the lot. A double submit finds nothing left to add.
- Inactive, unpublished and scheduled products count as unavailable, because the storefront can't sell them. Previously they were added. This follows the user's "deleted or unavailable" decision.
- Out-of-stock simple products are still added, leaving checkout to refuse them, as before.
- JSON adds `added`, `skipped_needs_options` and `skipped_unavailable`. `cart` is null when nothing was added. The message stays `All items added to cart` when everything was added. An empty wishlist says so.
- The redirect flashes `success` only when everything was added, otherwise `info`. `Wishlist.tsx` now shows the server's message; it used to always say "All items added to cart".

**Files modified:** `app/Http/Controllers/WishlistController.php`, `resources/js/pages/account/Wishlist.tsx`, `tests/Feature/WishlistActionsTest.php`, `tests/Feature/WishlistEntryTest.php` (fixture only: 2 products now published).

**Test change to note:** the skipped test "a variant product moved to the cart can be checked out" contradicted the approved decision, so it was rewritten as "a variant product stays on the wishlist and never reaches the cart". The deleted-product test was unskipped and extended.

**Verification evidence:**
- `WishlistActionsTest` and `WishlistEntryTest`: 40/40 on in-memory SQLite, no skips. Covers empty, variant-only, deleted-only and mixed wishlists, exact counts and messages, unlisted products, cart merging, repeat runs, rollback from either failure, checkout of a simple product, and out-of-stock handling.
- Removing the transaction fails 1 test. Removing every row instead of only added ones fails 2. Adding variant products fails 4.
- Sweep of 149 of 165 test files: 1480 passed, 12 skipped, 0 failed. Not the full suite.
- `tsc`, ESLint, Prettier, `npm run build` and Pint passed.
- **MySQL was not run,** as the change uses no database-specific behaviour. The lock is the same pattern verified for MED-08.
- Not run: a real browser check of the message, and concurrent add-all processes.

**Remaining risks:**
- **Skipped rows stay on the wishlist.** A deleted product is hidden on the wishlist page but still counts, so it reappears in the skipped count on each Add All, and the customer can't remove it from that page.
- **Header count:** it includes hidden deleted rows, so it can exceed what the page shows.
- **Wishlist ID sync:** `wishlist_ids` in shared props still includes skipped rows, which is correct, as they remain saved.

## Full verification, 2026-11-01

Verification only, on the combined local work at `f36ee18` plus the uncommitted changes. No application code or database content changed.

| Check | Result |
|---|---|
| Discovery | 165 test files, all executed, none excluded |
| Full suite, in-memory SQLite (`php artisan test`) | 1586 tests: 1537 passed, 0 failed, 49 skipped, 0 incomplete, 6893 assertions |
| MySQL 8.0.46, `shoppydrive_test_loyalty`, the 8 files holding those 49 skips | 64 tests: 57 passed, 7 failed, all 7 in `ProductSearchTest` |
| All 49 tests SQLite skipped | Ran on MySQL and passed |
| Race files | Courier 15/15, Loyalty 12/12, Cart 4/4, Stock 3/3, Return 2/2, Coupon 1/1, Uniqueness 10/10 |
| `tsc`, Prettier, Pint, `npm run build` | Passed |
| ESLint | 0 errors, 7 warnings across 230 files |

The rest of the suite was not run on MySQL. The working-tree fingerprint and dev database snapshot were identical before and after. No intermittent failure occurred in this run. TEST-FLAKE-001 stays open and unconfirmed.

**`ProductSearchTest` on MySQL (7 failures).** The file is written for SQLite, and normally runs only there, which is why CI never saw these.
- **1 real bug:** autocomplete, see SEARCH-AUTOCOMPLETE-FT-001.
- **1 by design:** the test asserts the non-MySQL fallback.
- **1 documented trade-off:** `leath` mid-word, which `ProductSearchQuery` documents as not matching on FULLTEXT.
- **4 consistent with a test limitation, unproven for these cases:** FULLTEXT can't see rows uncommitted in the test transaction, as shown during HIGH-02.
- A baseline run on a copy of `f36ee18` was not a clean comparison, because that copy had its own setup failures. It did not prove which failures predate this work.

## Newly found during full verification

| ID | Severity | Issue | Status |
|---|---|---|---|
| SEARCH-AUTOCOMPLETE-FT-001 | High (customer-facing, production risk) | `/api/search/suggestions` returns 500 on MySQL for searches of 3 or more usable characters | IN_PROGRESS: code written, MySQL verification NOT finished. Not a regression |

**Evidence:** `SearchController::suggestions()` calls `ProductSearchQuery::apply(..., includeShortDescription: false)`, which generates `MATCH(name)`. The only FULLTEXT index is `products_name_short_description_fulltext` on `(name, short_description)`, and MySQL needs an index with exactly the queried columns. A read-only query on the dev schema confirmed it: `MATCH(name, short_description)` works, and `MATCH(name)` gives `ERROR 1191 Can't find FULLTEXT index matching the column list`.

**Origin:** commit `71c65b5`, already deployed. Neither `ProductSearchQuery` nor the suggestions code was changed by this remediation. SQLite uses a LIKE fallback, so no test run or CI job could see it.

**Production:** not verified. It should apply if production has the same index. Checking needs a request to the live site or a read-only query, by the user.

**Chosen fix, no migration:** `ProductSearchQuery::apply()` always uses `MATCH(name, short_description)`, the only FULLTEXT index, and for a name-only search also requires each word in `name` by LIKE. This keeps the intended name-only suggestions. A bare `MATCH(name, short_description)` would stop the 500 but also match descriptions, and a new `name` index would need a migration. The main search query is unchanged in meaning, though it is now wrapped in a nested group.

**Also found:** the 500 fails the whole response, so brand and category suggestions vanish too, and `SearchBar.tsx` treats any failure as no results, which is why nobody noticed. The two `on MySQL` tests in `ProductSearchTest` are `expect(true)` placeholders and verify nothing.

**Files modified:** `app/Support/ProductSearchQuery.php`, `tests/Feature/SearchSuggestionsTest.php` (new).

**Evidence so far:**
- SQLite: `SearchSuggestionsTest` and `ProductSearchTest`: 18 passed, 11 skipped (9 MySQL-only here, 2 placeholders), 0 failed.
- **MySQL: NOT verified.** The `SearchSuggestionsTest` MySQL cases have never completed. Each rebuilds the schema, and the runs were stopped at a 700-second limit with no result. The fix must not be called working on MySQL until they finish. Run them one at a time or with a longer limit, on `shoppydrive_test_loyalty` only, after confirming the target.
- The planned comparison against the original bug and a bare MATCH was not completed either.
- Not yet run after this fix: static checks, build, or the full suite.


## Observations

Found during remediation and kept separate from the audit findings. None are in scope without an impact assessment.

- **Wishlist remove button (needs browser check):** `Wishlist.tsx` updates the store but does not reload the `wishlist` prop, so a removed item may stay visible until the page reloads.
- **Compare page:** the `/compare` route passes no `products`, while selections live in `uiStore`. It may always show empty, like the old `/wishlist`.
- **Variant image queries:** `ProductVariant::getImageAttribute()` reads `media`, and listings do not eager-load `variants.media`, so each variant likely adds a query. Not measured, and not added by HIGH-02.
- **Admin dashboard low-stock figures:** these use stale parent stock. Tracked as LOW-03.
- **LP-INVOICE-STATUS-001: guest invoice has no cancelled or refunded restriction (UNRESOLVED, security and business-rule review needed).** Not fixed, and not assumed to be intentional. `routes/account.php:86` refuses cancelled and refunded orders with `abort(403, 'Invoice is not available for cancelled or refunded orders.')`, and `:90` refuses undelivered COD. `LandingPageController::downloadInvoice()` (`app/Http/Controllers/LandingPageController.php:490`), routed at `routes/web.php:60` as `lp.invoice`, only looks up `guest_token` with `firstOrFail()` and renders the PDF, so neither rule applies. Access needs the private token and is throttled by `invoice-download`. Reached from `resources/js/pages/landing/LandingConfirmation.tsx:41`.
- **Customers can cancel pending orders after courier booking (found during MED-02):** `routes/account.php` lets a customer cancel any `pending` order, including one with a consignment, and the route takes no row lock. A cancelled order would restore stock while a parcel may still be moving. Not fixed by MED-02 or MED-08, and needs a business decision.
- **Steadfast has no `shipped` mapping:** orders only reach `shipped` by admin action.
- **SMS success logs contain payment details:** `SmsTestController` logs sender, transaction ID and amount at info level, and `SteadfastWebhookController` logs full payloads. Existing behaviour.
- **Incomplete-order conversion pricing:** `IncompleteOrderController` uses variant or product price and does not apply an active flash sale, unlike checkout. Not assessed further.

## Unresolved issues found during MED-07

Tracked separately from the audit findings. Neither is resolved.

| ID | Issue | Status | Risk | Assessment |
|---|---|---|---|---|
| INV-STALE-STOCK-001 | The admin product form wrote the stock it loaded, so an order placed while it was open was overwritten | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | Medium-High, inventory integrity | Signed stock token verified under row locks. See notes below |
| MEDIA-VIDEO-TYPE-001 | A kept media path is re-saved with `type=image`, so an existing video becomes `image` | **CODE FIX COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED. Historical data repair: UNRESOLVED, NOT APPROVED — separate scope.** | Low-Medium | Root cause and fix confirmed — see MEDIA-VIDEO-TYPE-001 notes. The code path is closed; any already-mistyped rows in a real database are untouched and remain a distinct, separately-approved future task |

**INV-STALE-STOCK-001 notes**

- **Root cause:** the form submits absolute stock values, while `InventoryService` changes stock underneath it. `update()` wrote what the form loaded, with no comparison. Writers are only `InventoryService` (order placement, cancellation and refund restock, return restock) and the admin form. Refunds and cancellations reach it through `Order::booted()`.
- **Fix:** `App\Support\ProductStockToken`. `edit()` gives the form an HMAC, keyed with the app key, of the product's and all variants' stock. It holds no values and can't be forged. On save, inside the MED-07 transaction, `lockAndVerify()` locks the product and variant rows, recomputes the HMAC from the locked values and compares. A mismatch throws `StaleProductStockException` before any write, so product, variants, media, audit and `inventory_logs` are untouched, and new uploads are removed by the MED-07 cleanup. The admin sees an error asking them to reload.
- The token is required on update and prohibited on create. `InventoryService` and order logic are unchanged. No migration.
- **Form:** `edit.tsx` keeps the token from first load, so a rejected form stays rejected until it is really reloaded.

**Files modified:** `app/Support/ProductStockToken.php` (new), `app/Exceptions/StaleProductStockException.php` (new), `app/Http/Controllers/Admin/ProductController.php`, `app/Http/Requests/Admin/ProductRequest.php`, `resources/js/pages/admin/products/edit.tsx`, `tests/Feature/Admin/ProductStockConflictTest.php` (new), `tests/Feature/Admin/ProductStockConflictRaceTest.php` (new), and 4 existing product test files, which now send a token.

**Verification evidence:**
- SQLite: `ProductStockConflictTest` 12/12. Sweep of every product, variant, media and stock test file (48 files, excluding MySQL-only race files): 655/655.
- MySQL 8.0.46 (`shoppydrive_test_loyalty`): `ProductStockConflictRaceTest` 3/3, using real separate processes. `ProductStockConflictTest`, `ProductSaveAtomicityTest` and `ProductVariantManagementTest`: 41/41.
- Race design: one process sells 30 units through `InventoryService` while another repeatedly opens the form and saves back the stock it saw. Final stock must be exactly 70 of 100. A third test blocks a save behind an uncommitted sale, and a final valid save proves the save path works.
- With verification removed, all 3 races fail: flat product 93 instead of 70 (23 sales lost), variant product 77 instead of 70 (7 lost).
- `tsc`, ESLint, Prettier, `npm run build` and Pint passed.
- Not run: the full suite, and a real browser walkthrough of the refusal message.

**Limitations:**
- **Stock only:** two admins changing other fields, with stock unchanged, is still last-write-wins.
- **Busy products:** the form refuses after any sale, so an admin editing a fast-selling product must reload, and typed edits are lost on reload.
- **Forms open during deploy:** they have no token, so the first save fails validation until reloaded. Rotating `APP_KEY` has the same effect.
- **Same values:** stock that changes and returns to the same value passes, because nothing would be overwritten.
- **Deadlocks:** a save locks the product, then its variants by ID. An order locking several variants of that product in another order could deadlock with it. InnoDB would abort one transaction, and neither retries, so the admin or customer would see an error with nothing partly saved. Not observed in these runs.
- **Admin stock edits still write no `inventory_logs` entry:** existing behaviour, unchanged.

**MEDIA-VIDEO-TYPE-001 notes**

**This entry covers the code fix only. It does not cover, and does not imply any decision about, historical data repair — that remains unresolved and unapproved, as its own separate scope (see below).**

- **Root cause (confirmed 2026-11-01 by reading code and git history, no database accessed):** `ProductController::storeUploads()` hardcoded `'type' => 'image'` for any media item that is a retained path string rather than a fresh `UploadedFile` — the frontend (`edit.tsx`) only ever sends an existing media item back as its bare `path`, with no type, so every kept item silently lost its real type on every save. This has existed since video support for product media was first added (commit `894e6a6`, 2026-05-06) — it is not a recent regression, and MED-07's transactional refactor (which this entry does not reopen) carried it forward unchanged.
- **Scope confirmed narrow:** only `Admin\ProductController`/`ProductMedia` — no other media-bearing model (`HeroSlide`, `PromotionalBanner`, `Blog`) stores a type column or supports video at all, so this exact defect class can't occur elsewhere. Only `update()` is affected; `store()` can never receive a retained path (a new product has nothing to keep).
- **Customer-facing impact confirmed real, not theoretical:** `resources/js/pages/catalog/ProductDetail.tsx` renders purely off the stored `type` field (`item.type === 'video' ? <video> : <img>`), never sniffing the file extension — a flipped video silently fails to display rather than playing.
- **Fix:** `update()` now looks up the product's current `product_media` types keyed by path (`$product->media()->pluck('type', 'path')->all()`) before calling `saveAtomically()`, which threads it through to `storeUploads()`. A retained path keeps its real stored type; an unrecognized path or a `null` slot still falls back to `'image'`, exactly as before. Fresh `UploadedFile` uploads are completely unaffected — their type is still detected from the file's own MIME type. `store()` passes nothing new and defaults to an empty mapping, so its behavior is unchanged (it can't receive a retained path anyway).
- **Not changed:** frontend payload shape, `ProductRequest` validation, `media_tags` handling, `syncMedia()`'s own logic, or anything about `store()`'s behavior.

**Files modified:** `app/Http/Controllers/Admin/ProductController.php` (`update()`, `saveAtomically()`, `storeUploads()`), `tests/Feature/Admin/ProductSaveAtomicityTest.php` (2 new tests).

**Verification evidence:**
- `ProductSaveAtomicityTest`: 13/13 passed, 78 assertions, on in-memory SQLite (11 pre-existing + 2 new: a retained video stays `type=video`; a retained image stays `type=image`). The pre-existing "a video upload keeps its video type" test (fresh upload on `store()`) still passes unchanged.
- Directly relevant product-save sweep also run: `ProductCryBabyTest`, `ProductVariantManagementTest`, `ImageUploadSecurityTest`, `VariantAndAttributeUniquenessTest`, `ProductDescriptionSanitizationTest`, `ProductStockConflictTest` — 79 passed, 8 skipped (MySQL-only), 0 failed.
- Pint passed (auto-fixed unrelated spacing/phpdoc-alignment cosmetics on `ProductController.php`, nothing behavioral — re-ran `ProductSaveAtomicityTest` after, still 13/13).
- Not run: the full suite, MySQL, `tsc`/ESLint/Prettier/build — none are implicated by a pure backend type-lookup fix with no frontend or driver-specific behavior.

**Historical data — explicitly unresolved, not approved, separate scope:**
- Code-inference only (no database queried): any product with a video that was edited-and-saved via the admin panel at any point since 2026-05-06 is a plausible candidate for already having `type='image'` incorrectly stored, in whatever database that editing happened against.
- No repair script, migration, or data modification was written or proposed as part of this entry. Fixing already-mistyped rows requires its own separate review and separate approval, as the original finding itself already said ("Historical rows already changed would need a separate review") — this entry does not change that.

## MED-18A notes

**This is a distinct finding from MED-18, not a rewrite of it.** MED-18 is about the literal, unverified `SESSION_SECURE_COOKIE` value in production's `.env`. MED-18A is about something the original audit never covered: whether plain HTTP is reachable at all. Both stay separate and both stay accurately described — see MED-18's own row, unchanged in substance.

**Original live evidence (gathered in a prior read-only turn, re-stated here for the record, cookie values always redacted):**
- `https://shoppydrive.com/` returns the session cookie with `secure; httponly; samesite=lax`.
- `http://shoppydrive.com/` returns `200 OK` with the full real page (not a redirect), and the *same* session cookie set on that response carries no `secure` flag — confirmed by direct side-by-side comparison, not inferred.
- No `Strict-Transport-Security` header on either response.
- `RedirectToCanonicalDomain` (the only host-redirect middleware that existed before this fix) only rewrites `www.` → bare domain and explicitly preserves whatever scheme the request arrived on — by design, not an oversight; it was never meant to touch scheme.
- No `TrustProxies`/`TrustHosts` middleware registered anywhere in `bootstrap/app.php`.

**Root cause:** nothing in this application, `public/.htaccess` (the only `.htaccess` in the repo, confirmed — the stock Laravel rewrite rules plus caching/compression headers, no HTTPS-forcing rule), or the Hostinger/CDN layer in front of it (confirmed only indirectly, via response headers naming `hcdn`/`hostinger` — no server/CDN config is in this repository to inspect directly) ever redirects a plain-HTTP request to HTTPS. The gap is a genuine absence, not a misconfigured existing mechanism.

**Enforcement-layer decision: Laravel application middleware, not `.htaccess`.** Reasoning:
- `public/.htaccess` *is* repository-managed and deployed (not excluded by `deploy.yml`'s rsync `--exclude` list) — an Apache-level fix was considered, but rejected for this change because it would require assuming how Hostinger's `hcdn` edge communicates scheme to the origin Apache process, which cannot be confirmed from this repository alone (no access to that CDN's internal configuration).
- The chosen signal instead is `$request->isSecure()` — the exact same signal Laravel's own Secure-cookie auto-detection already uses, which the live evidence above *directly* proved already resolves correctly in production today (the cookie's `secure` flag correctly differed between the https:// and http:// requests). Reusing a signal already proven correct, instead of introducing a new assumption about proxy headers, is why this is considered safe without any `trustProxies()` configuration.
- `X-Forwarded-Proto` is deliberately never read or trusted — confirmed by a dedicated test that a spoofed value cannot bypass enforcement.

**Fix:** new `App\Http\Middleware\RedirectToHttps`, registered in `bootstrap/app.php`'s `web` group immediately after `RedirectToCanonicalDomain`. Redirects (308, method/body-preserving, matching `RedirectToCanonicalDomain`'s own precedent) any non-secure request to `https://` + the **trusted `config('app.url')` host** (never the request's own `Host` header, closing the open-redirect/Host-spoofing risk explicitly) + the original path and query string. Scoped to `config('app.env') === 'production'` only — local development (no TLS certificate) and the test suite (`APP_ENV=testing`) are structurally never affected, confirmed by a dedicated test for both. If `config('app.url')` can't be parsed to a host, the request passes through unmodified rather than guessing.

**Redirect-loop safety:** a request that's already secure short-circuits immediately (`$request->isSecure()` true → pass through). A request that gets redirected to `https://...` would, on the evidence above, be correctly detected as secure on its next hit to this same application — so it should not loop. This reasoning rests on the live evidence already gathered, not a fresh round-trip through the redirect itself (that would require a real browser/follow-redirects check against production, which is deployment-time verification, not something this local change can prove in advance).

**HSTS: deliberately not added, left for a separate future decision.** HSTS is browser-cached and effectively irreversible for the duration of its `max-age` once a browser has seen it — adding it is only safe once the redirect behavior itself has been proven correct in real production traffic (no loops, no subdomain left broken) for a meaningful period. This fix intentionally stops at the redirect; HSTS should be revisited as its own explicitly-approved step after this one is deployed and observed working.

**Files modified:** `app/Http/Middleware/RedirectToHttps.php` (new), `bootstrap/app.php` (registration only — no other middleware touched, no session/header config touched).

**Verification evidence:**
- `tests/Feature/RedirectToHttpsTest.php`: 7 passed, 11 assertions — plain HTTP→HTTPS with path/query preserved; POST preserved via 308; an already-HTTPS request isn't redirected again; `local` and `testing` environments are both left alone; a spoofed `X-Forwarded-Proto: https` header cannot bypass enforcement; a spoofed `Host: evil.example.com` header cannot redirect anywhere but the trusted configured domain.
- `tests/Feature/RedirectToCanonicalDomainTest.php` (adjacent, unchanged): 4/4 — confirms no interaction regression between the two prepended redirect middlewares.
- `HandleInertiaRequestsSsrExclusionTest` + the BlockedIp test file (middleware-pipeline-adjacent sanity check, since `BlockBlockedIp` now runs immediately after this new middleware): 7/7.
- Pint: passed, no changes needed. MySQL, the full suite, and any build/lint step were deliberately not run — this is pure PHP middleware logic with no driver-specific or frontend-facing behavior.

**Remaining infrastructure requirements, NOT satisfied by this change, NOT deployed:**
- This is a local code change only. It has no effect until deployed, and `MED-18`'s own literal `SESSION_SECURE_COOKIE` value in production `.env` is still unverified — this fix doesn't require that value to be correct (it forces HTTPS before the cookie question even matters for a plain-HTTP visitor), but it also doesn't answer it.
- Whoever deploys this should confirm, after deploying, that a real browser hitting `http://shoppydrive.com/...` actually lands on the equivalent `https://` URL with no loop — the reasoning above is evidence-based, not a production dry-run of the fix itself.
- HSTS remains a distinct, deliberately deferred future decision (see above) — not blocked on anything technical, just deliberately sequenced after this fix is proven live.

## MED-20 notes

**Tier 1 (implemented): converted rows.** A `PendingCheckout` with `order_number` set is pure leftover bookkeeping — the real `Order` already exists independently, and the only remaining purpose of the row (letting a late browser callback on `payment.callback` redirect to the existing order idempotently) is satisfied the moment that callback actually runs and deletes it itself. If the browser never revisits that URL (e.g. the webhook created the order but the customer closed the tab), the row would otherwise sit forever. `pending-checkouts:prune` deletes these once they're 24h past `expires_at`, in bounded 200-row chunks, only after confirming the `Order` it names still exists.

**Tier 2 (deferred, not fixed): unconverted rows.** A `PendingCheckout` with `order_number IS NULL` past `expires_at` is ambiguous between two completely different situations, and **nothing on the row itself distinguishes them:**
- A truly abandoned checkout — the customer never paid. Safe to delete, no money involved.
- A ZiniPay payment that genuinely succeeded but order creation then failed (`PaymentController::callback()`'s `InsufficientStockException`/`CouponUnavailableException` handling *deliberately* does not delete this row — the code comment says so explicitly: "Payment already succeeded on ZiniPay's side but stock can't cover it — keep the PendingCheckout (don't delete) so an admin can resolve/refund manually instead of silently losing the paid-for order"). There is no `payment_confirmed_at` column, no status flag, nothing queryable — the only other trace is a single `Log::error` line from whenever it happened.

A blind time-based delete on this tier would destroy the one safety net this codebase already built for a customer who paid and got nothing. Correctly separating the two cases would need either a new schema field recording "payment confirmed" (a migration, explicitly out of scope by your instruction) or some other positive signal that doesn't currently exist. **`pending-checkouts:prune` never deletes, updates, or otherwise touches any row with `order_number IS NULL`, at any age.** It reports a single aggregate count of how many such rows exist in its own console output (`$this->line(...)`, visible in the scheduler's own log capture, not written via `Log::` at any level) — deliberately not logged per-row, since most of these are ordinary abandoned carts with nothing wrong, and logging each one individually would just be noise obscuring the rare real case. Resolving Tier 2 for real needs a product/business decision this tracker doesn't make unilaterally, not a code change.

## LOW-10 notes

**Status: investigated only. `NOT_STARTED` is unchanged — nothing below implies approval to implement.**

**Original finding:** no explicit DB statement/lock timeout for `migrate --force` on shared hosting — a specific instance of the broader, separately-tracked deployment-recovery problem, flagged because the already-applied `points_balance` unsigned→signed ALTER (`database/migrations/2026_10_30_214612_make_users_points_balance_signed.php`) is exactly the kind of operation that can run long on a large table.

**Fresh verification (2026-11-01, read-only):**
- `.github/workflows/deploy.yml:256`, inside the post-deploy SSH script (running under `set -e` since line 211): `php artisan migrate --force`, with no timeout wrapper, no MySQL session-variable statement, no PDO `init_command`, and no PHP execution-time override anywhere in the codebase. Confirmed unchanged.
- `set -e` already aborts the remaining cache-rebuild steps if migration *fails outright* — the actual gap is narrower: a migration that *hangs* waiting on a lock, which `set -e` cannot help with, since nothing ever returns.
- **One precision correction to the audit's own framing:** its phrase "the already-accepted... risk" points at `docs/MASTER_FIXING_TRACKER.md` §G's `DEPLOY-SAFETY-001` finding C2 ("a failed migration leaves the server broken with no automatic recovery"). Re-reading that section directly, C2 is not formally closed or accepted as settled — it is only indirectly mitigated so far (Tier 1: manual-approval gate + post-deploy health check, committed `599b483`), with the real fix (Tier 2: atomic release/rollback) still explicitly awaiting separate approval and an unconfirmed Hostinger feasibility check. LOW-10 narrows one small, self-contained piece of a still partly-open problem — it does not rest on a settled decision.

**Proposed approach (not implemented, not approved):** wrap just that one line with the shell `timeout` command (e.g. `timeout <N> php artisan migrate --force`) in `.github/workflows/deploy.yml` — no database privileges, no application code, no migration needed; scoped to only the deploy step the finding names, deliberately not a MySQL session-variable change (which would affect every DB connection, not just this one-off step).

**Why this stays `NOT_STARTED`, deferred rather than implemented (user decision, 2026-11-01):**
- The correct timeout budget depends on this app's realistic largest-migration duration, which hasn't been established — picking a number without that risks killing a legitimate slow migration.
- A shell `timeout` only terminates the *client* process; it does not guarantee the database itself performs safe DDL cancellation or rollback — it converts a silent hang into a fast, visible failure, but does not by itself solve safe recovery from a killed-mid-DDL state.
- `.github/workflows/deploy.yml` carries its own standing, separately-flagged sensitivity (CLAUDE.md: never modify it without explicit, separate approval) independent of this finding's small size.
- Whether GNU `timeout` is even available on Hostinger's shared-hosting SSH shell is unverified and was explicitly not checked this session (no server access used).
- By explicit decision, this stays scoped separately from `DEPLOY-SAFETY-001` Tier 2 rather than being folded into it, even though they're related.

**Re-open/revisit when:** a timeout value and Hostinger shell compatibility can be confirmed, and you want to authorize a `deploy.yml` change specifically for this.

## MED-19 notes

**Original finding:** SMS payment amount matching uses a flat ±1 Taka tolerance regardless of order size — a ~20% margin on a very low-value order. The audit classified this `CONFIRMED` as written, impact `THEORETICAL`, and its own remediation line hedged: "scale tolerance to a percentage with a small floor," while the recommended-action table's complexity rating (`Small`) assumed that formula decision was already made.

**Fresh verification (2026-11-01):** both call sites re-read in full and confirmed identical — `app/Http/Controllers/MobilePaymentController.php:143` and `app/Http/Controllers/LandingPageController.php:389`, both `if (abs($sms->parsed_amount - $expectedAmount) > 1) { throw new \RuntimeException('amount_mismatch:'.$sms->parsed_amount); }`, each preceded by an `amount_unavailable` fail-closed guard for an unparseable SMS amount (unrelated to this finding, already sound). No `Setting`, config key, or admin-tunable value exists for this tolerance anywhere — it is a hardcoded literal `1` in both places. `tests/Feature/MobilePaymentMatchingTest.php` has a rejection-case test at each call site (`'an incorrect amount is rejected...'` and its landing-page counterpart), but both use a gross `$amount - 50` mismatch, not a boundary value — the mechanism's existence is tested, the specific ±1 value itself is not pinned by any test.

**Why this stays `ACCEPTED_RISK` rather than `FIX`:**
- Maximum direct amount-matching exposure is capped at ৳1 per order, regardless of order size — a bounded, small, per-order cost, not an unbounded or scaling one.
- The audit itself classifies actual-exploitation impact as `THEORETICAL`.
- The flat tolerance is retained deliberately, to accommodate minor SMS-forwarding/parsing variations (provider template drift, rounding, forwarder reformatting) — it is existing intentional slack, not an oversight.
- A percentage-based tolerance requires an explicit business rule (the percentage and the floor value) that this tracker does not make unilaterally; picked carelessly, it could unintentionally **widen** the acceptance range on larger orders rather than tighten it, which would make the gap worse, not better.
- No behavior change or test-only work is approved at this time — this decision only accepts the current state, it does not even close the test-boundary gap.

**Distinction preserved:** this is about the tolerance *value*, not about the matching mechanism's correctness — the fail-closed `amount_unavailable` guard and the general mismatch-rejection path are confirmed sound and unaffected by this decision.

## LOW-01 notes

**Original finding:** `Order::$fillable` includes all financial fields plus idempotency markers (`order_number`, `guest_token`); safe only because every call site is disciplined, not because the model itself constrains it — a latent defense-in-depth gap, not an active vulnerability. The audit's own recommended-action table lists this under "Optional — no immediate business need."

**Fresh verification (2026-11-01), not just repeating the old tracker note:**
- Every one of the 7 real `Order::create()` call sites was re-read in full: `CheckoutController.php:282`, `Admin/OrderController.php:164`, `Admin/IncompleteOrderController.php:173`, `LandingPageController.php:213` and `:396`, `MobilePaymentController.php:150`, `OrderService.php:54`. All 7 pass explicit, hand-built array literals. None spread `$request->all()`, `$request->validated()`, or any other raw caller input into `Order::create()`.
- `order_number` is always either `OrderNumberGenerator::generate()` called directly, or a variable (`$orderNumber`, `$data['order_number']`, `$cached['order_number']`) that traces back to that same generator via `PendingCheckout.checkout_data` — never a client-supplied value at any point in the chain.
- CHECKOUT-COD-002's actual idempotency lock key (`order_token`, used in `Cache::lock("checkout-order-lock:{$orderToken}")`) is a pure cache key and was confirmed to never be an `orders` column and never appear in `$fillable` — that mechanism sits entirely outside this risk.
- The existing `ip_address`/`user_agent`/`traffic_source`/`traffic_referrer` exclusion pattern at `Order.php`'s `booted()` hook (excluded from `$fillable`, set internally from the request) does not transfer cleanly to `order_number`/`guest_token`: those fields are legitimately generated by the *calling* code, not derivable by the model itself, so narrowing `$fillable` would require a `forceFill()`/direct-assignment change at all 7 call sites simultaneously, not a one-line model edit.

**Why this stays `ACCEPTED_RISK` rather than `FIX`:** `app/Providers`/`bootstrap` were checked — `Model::preventSilentlyDiscardingAttributes()` is not enabled app-wide. That means removing `order_number` from `$fillable` without updating all 7 call sites in the same change would silently drop the field on every `Order::create()` call; since `orders.order_number` is `unique()` and NOT NULL, every single order-creation path would then fail at the database layer with a not-null violation the very next time it ran. The fix is real but touches 7 files in lockstep (matching the tracker's own "Complexity: Small to Medium, many create calls" note) to close a risk that today has zero live exploitation path — every call site was just freshly confirmed disciplined. That asymmetry (tiny latent risk vs. a real risk of breaking order creation app-wide if any one of the 7 sites is missed) is why the smaller, safer action is to accept the risk now rather than touch all 7 controllers for a defense-in-depth-only, optional finding.

**Distinction preserved:** this is a latent defense-in-depth gap (the model doesn't constrain itself), not an active vulnerability (no reachable path today lets a caller actually set `order_number`/`guest_token`). Re-open this finding if a future call site stops building an explicit array literal for `Order::create()`.

## LOW-02 notes

**Original finding:** `ProductVariant.attributes` JSON duplicates `color_value_id`/`size_value_id` as a second source of truth for display labels; can drift only if an `AttributeValue` is renamed after a variant was last saved, and self-heals on the variant's next save. The audit's own recommended-action table lists this under "Optional — no immediate business need," the same tier as LOW-01.

**Fresh verification (2026-11-01):**
- The one write site is `Admin\ProductController::syncVariants()` (`app/Http/Controllers/Admin/ProductController.php:373-402`): on every product save, `attributes = ['Color' => AttributeValue::find($colorId)?->value, 'Size' => AttributeValue::find($sizeId)?->value]` is freshly looked up and written — confirming the snapshot does refresh on save, exactly as the tracker's prior note claimed.
- **The drift is reachable through ordinary admin action, not just a hypothetical:** `Admin\AttributeValueController::update()` lets an admin rename any `AttributeValue.value` (e.g. "Red" → "Crimson") with no guard and no cascade to variants referencing it by FK. Every `ProductVariant` pointing at that value keeps its old snapshotted label until *that specific product* is individually re-saved — a value shared across many products does not propagate anywhere until each product is re-saved one at a time.
- **The stale value is visible on customer-facing views, not just admin screens:** the public product page (`app/Http/Controllers/ProductController.php:61,68`) eager-loads `variants` and serializes the raw `attributes` column straight into page props; this route is not cached by `LayoutCache`/`CatalogFilterCache`/response-cache, so whatever is currently in the DB — stale snapshot included — is what the customer sees, until the product is next saved.
- **Impact confirmed cosmetic/display-only:** no price, stock, SKU, financial, security, or order-integrity effect was found anywhere in the read sites traced (`ProductDetail.tsx`, `Cart.tsx`, `CartDrawer.tsx`, `create-order-modal.tsx`, `convert.tsx`, and the backend controllers that pass the snapshot through). Only the displayed color/size label text can be stale.

**Distinction preserved — two different snapshots, only one in scope here:**
- `ProductVariant.attributes` — a **live catalog display snapshot** that can go stale relative to the current `AttributeValue` it points to. This is the subject of LOW-02.
- `OrderItem.variant_attributes` — an **intentional historical purchase-time snapshot**, written once at order creation from whatever `ProductVariant.attributes` held at that moment, so an order's record always reflects what the customer actually saw when they bought it. This must remain unchanged — freezing the catalog state at purchase time is correct behavior, not a bug, and is explicitly out of scope for this finding.

**Why this stays `ACCEPTED_RISK` rather than `FIX`:** the audit itself classifies this as optional with no immediate business need. A real fix (deriving labels from the `colorValue`/`sizeValue` relations at read time instead of the stored snapshot) would need coordinated changes across every backend read path (`ProductController`, `Admin\ProductController`, `LandingPageController`, `Admin/IncompleteOrderController`, `IncompleteOrderController`, `CheckoutController`, `Admin/OrderController`) plus eager-loading those relations wherever `variants` is loaded — an incomplete rollout risks either N+1 queries or a silently missing label, which is strictly worse than today's cosmetic staleness. That cost and regression surface is disproportionate to a display-only defect that already self-heals on the product's next ordinary save.

## LOW-09 notes

**Original finding:** `OrderNumberGenerator`'s uniqueness check-then-act loop has a classic race, but is fully backstopped by a genuine DB unique constraint — a collision would cleanly roll back the transaction, not corrupt data. The audit estimated "practically unreachable (62⁶ combinations/day)."

**Fresh verification (2026-11-01):**
- `app/Support/OrderNumberGenerator.php` re-read in full: `do { $number = 'ORD-'.date('Ymd').'-'.strtoupper(Str::random(6)); } while (Order::withTrashed()->where('order_number', $number)->exists());` — confirmed a plain check-then-act loop with no locking, and confirmed it checks `withTrashed()`, so a soft-deleted order's number is correctly treated as still taken.
- `database/migrations/2026_04_28_074220_create_orders_table.php:16`: `$table->string('order_number')->unique();` — confirmed the backstop is a genuine DB-level unique index, not just an application-level check. A race that slips past the `exists()` check would fail at `Order::create()` with a real `UniqueConstraintViolationException`, rolling back cleanly, never writing a duplicate row.
- **One correction to the audit's own arithmetic, found while re-verifying (doesn't change the conclusion):** `strtoupper()` is applied to the *entire* generated string, including the random suffix from `Str::random(6)`, which draws from Laravel's default 62-character alphabet (`a-z`, `A-Z`, `0-9`). Upper-casing collapses letter case, so the actual collision space per day is **36⁶ (≈2.18 billion)**, not **62⁶ (≈56.8 billion)** as the audit states. Even at the smaller figure, the birthday-paradox collision probability at any realistic daily order volume for this store remains negligible (on the order of 0.01-0.1% even at several thousand orders/day), so the audit's "practically unreachable" conclusion still holds — only its stated combinatorics were off.

**Why this stays `ACCEPTED_RISK`:** the audit itself didn't place this on its remediation roadmap, recommending acceptance outright. The DB-level unique constraint means the worst case is a cleanly-rolled-back failed insert, not data corruption or a silently duplicated order number — and that backstop, not the loop's own race-freedom, is what actually matters here. No behavior change or test-only work was requested or performed.

## Remaining priority order

The report's roadmap, adjusted where the reconciliation changed the facts.

1. MED-06 is deferred by the user.
2. MED-18: needs the user to check production. MED-18A (separate finding): completed locally, not deployed — see MED-18A notes.
3. MED-20: Tier 1 completed, Tier 2 deferred — housekeeping. MED-19 accepted by the user as `ACCEPTED_RISK` — see MED-19 notes.
4. Medium-term: MED-17. LOW-08, LOW-03, MED-09, MED-10, MED-14 and MED-11 (courier-dispatch extraction only) completed. The remainder of MED-11's original scope (refunds/cancellation, destructive deletion, CRUD, address correction) was deliberately not attempted and remains open if revisited.
5. Optional LOW findings. LOW-01, LOW-02 and LOW-09 accepted by the user as `ACCEPTED_RISK` — see LOW-01, LOW-02 and LOW-09 notes. LOW-05 completed (test-only). LOW-10 remains `NOT_STARTED`.

## Corrections to the audit report

- **HIGH-03:** `app-header.tsx` is not 100% of traffic. The storefront uses `Header.tsx`, which already linked to the working page.
- **MED-01:** the entered price is stored on `order_items`, and the order's `created` entry names the admin. The missing control is the catalog comparison.
- **MED-05:** return approve and reject are logged.
- **MED-08:** the recommended unique index cannot prevent the common duplicate because of NULLs. The report also missed the bundle page-load and stale-checkout paths.
- **MED-20:** no longer tied to CRIT-01.
- **LOW-07:** the finding's premise — "`verify-email.tsx` uses a plain light theme... inconsistent with the rest of the dark-themed auth flow" — does not hold against the actual code, checked 2026-11-01 against every auth page's real outer container class. **No dark authentication flow exists for `verify-email.tsx` to be inconsistent with:**
  - `verify-email.tsx`, `two-factor-challenge.tsx`, `reset-password.tsx` and `confirm-password.tsx` all share the identical outer container (`flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12`) and the same light `bg-gray-50`-page/white-card pattern throughout. `verify-email.tsx` is not the odd one out — it is pixel-identical in theme to three sibling pages.
  - `login.tsx` and `register.tsx` use a different *layout*, not a dark page: a split screen with a dark red (`bg-[#c8102e]`) brand-accent panel on the left (desktop only, hidden on mobile) and the same light `bg-gray-50`/white form panel on the right as the four pages above.
  - `admin-login.tsx` (`bg-gray-950`, genuinely dark) belongs to the admin section's own, deliberately separate design system (`CLAUDE.md`: "Admin pages are visually separate from storefront pages") — it is not part of the customer auth flow `verify-email.tsx` belongs to.
  - **Changing `verify-email.tsx` alone to a dark theme would introduce an inconsistency rather than resolve one** — it would break its match with `two-factor-challenge.tsx`, `reset-password.tsx` and `confirm-password.tsx` to chase a "dark auth flow" that doesn't exist, and would do so by giving one single-action auth page its own one-off design treatment shared by no sibling. No code or styling was changed. Status: `NOT_APPLICABLE`.

## Informational items from the report

Not defects. Recorded so they aren't re-raised: no error-tracking service; `CLAUDE.md` scheduler section omits `app:drain-queue`; `SecuritySettingsController` docblock understates enforcement; whole-order refund shares `orders.update`; no admin password-reset flow by design; ADMIN-2FA-001 permanently declined.

## Newly discovered production defects

These were found during remediation and are not original audit findings.

| ID | Severity | Issue | Status | Files modified | Verification evidence | Completion date | Remaining work |
|---|---|---|---|---|---|---|---|
| REF-CAP-TZ-001 | High (financial, production) | Referral reward cap skipped rewards granted in the first 6 hours of a period | COMPLETED (LOCAL IMPLEMENTATION). Deployment: NOT DEPLOYED | `app/Services/ReferralService.php`, `tests/Feature/ReferralProgramTest.php` | See notes below | 2026-11-01 (local) | See deployment checklist |

**REF-CAP-TZ-001 notes**
- **Root cause:** `periodStart()` returned a Dhaka-time date, the app stores UTC, and Laravel formats a date for SQL without converting its timezone. The period start was therefore 6 hours later than intended, for the whole period. Rewards granted from 00:00 to 06:00 Dhaka on the first day never counted towards the cap, and the daily `rewards:grant-due` run at 01:00 Dhaka falls in that window on the first day of every period. Monthly and weekly caps share the method.
- **Fix:** `periodStart()` converts the result with `setTimezone(config('app.timezone'))`. Nothing else in `ReferralService` changed. No migration.
- **Evidence:** the existing cap test and the 2 new first-hours cases failed before the fix and pass after it. Two new cases confirm a reward granted just before a period starts still does not count towards the new one, for both monthly and weekly. `ReferralProgramTest`: 27/27. Referral sweep (6 files, 121 tests): 111 passed, 10 skipped (MySQL-only). The 5 period tests also pass on MySQL 8.0.46.
- **Past data:** rewards already granted in production during those windows are unchanged. Whether any referrer exceeded a cap was not checked.

## Deployment checklist

Nothing here has been pushed or deployed. Nothing is approved for deployment. Commit, push and deploy each need separate user approval.

- [x] Run the full test suite: done 2026-11-01, see Full verification. Re-run after any further change.
- [ ] Run the local migration for CRIT-01 on the dev database.
- [ ] Confirm production MySQL or MariaDB version and compatibility.
- [ ] Review the CRIT-01 production migration and existing `pending_checkouts` records with the preflight query above.
- [ ] Check existing production `sms_patterns` against `SmsRegex::problem()`, and fix any that fail before relying on automatic matching.
- [ ] Grant the new `failed_jobs` section to the staff who should see failed jobs.
- [ ] Confirm the three security headers on production, with one value each, for HTML and JSON responses.
- [ ] **Blocker:** the MED-06 migration is NOT SAFE TO DEPLOY. Run the deferred read-only preflight and resolve anything it finds, or keep the file out of what is pushed to `main`.
- [ ] Review the final migration and deployment procedure.
- [ ] Perform a real browser walkthrough of checkout and wishlist, including the guest merge, plus product cards, quick-add and the admin create-order form.
- [ ] Obtain user approval to commit, push and deploy, each separately.

## Later work

- Check whether any production referrers exceeded their reward caps before REF-CAP-TZ-001 was fixed.
- Optional safe media cleanup, with reference checks across order snapshots and other products.

## Reconciliation with existing work

Audit IDs cannot be mapped to these until the report is available. Rows may turn out to be already fixed or accepted.

**Completed and deployed (`MASTER_FIXING_TRACKER.md`):** PROMO-LOY-001, PAY-RETURN-001, PAY-RETURN-001-BATCH-2, CHECKOUT-COD-002, SHIP-001, SHIP-DUP-001, DEP-SEC-COMPOSER-001 (Symfony patch), XSS sinks (5 of 5 live).

**Accepted risks, preserve them:**
- ADMIN-2FA-001: declined by the user, final. Do not propose it again.
- No database backup before deploy (DEPLOY-SAFETY-001 C1): accepted only while production holds no important business data.
- `StrictHostKeyChecking=no` (M1): accepted again after the pinning revert `bdb444e`.
- 17 moderate npm advisories: deliberately left, because the only fixes are regressive `--force` versions.
- `Page.content` not purified: no write path exists today (XSS-SINK-GAP-001).

**Open or deferred:** DEPLOY-SAFETY-001 Tier 2, `nunomaduro/pao` rename, E2E-COVERAGE-001, CI-MYSQL-001, TEST-FLAKE-001, OPS-001.

## Informational observations

Non-actionable items from the audit go here once the report is supplied.

## Change log

- 2026-11-01: MED-18A implemented and verified on `integration/shoppydrive-remediation-2026-11-01`. New `App\Http\Middleware\RedirectToHttps`, registered in `bootstrap/app.php`'s `web` group right after `RedirectToCanonicalDomain`, redirects any plain-HTTP request to the equivalent `https://` URL (308, path/query preserved) in production only, using only the trusted `config('app.url')` host — never the request's own `Host` header. Distinct finding from MED-18, surfaced by live read-only verification, not the original audit; MED-18's own row is unchanged. `RedirectToHttpsTest.php` (new): 7 passed, 11 assertions. `RedirectToCanonicalDomainTest.php` (adjacent, unchanged): 4/4. `HandleInertiaRequestsSsrExclusionTest` + the Blocked-IP test file: 7/7. Pint passed. MySQL/full suite/build deliberately not run — pure PHP middleware logic. **Local implementation only, NOT DEPLOYED.** HSTS deliberately deferred as its own future decision. See MED-18A notes and the MED-18A row above.
- 2026-11-01: MEDIA-VIDEO-TYPE-001 code fix implemented and verified on `integration/shoppydrive-remediation-2026-11-01`. `ProductController::update()`/`saveAtomically()`/`storeUploads()` now look up a retained media path's real stored type instead of defaulting it to `'image'`, closing a defect present since video support for product media was first added (2026-05-06), confirmed by git history. Two new tests added to `ProductSaveAtomicityTest.php` (13/13 passed, 78 assertions); six directly-relevant product-save test files also swept (79 passed, 8 skipped MySQL-only, 0 failed). **Historical data repair is explicitly separate, unresolved, and not approved** — no database was accessed, queried, or modified, and no repair script or migration was written. MED-07's `COMPLETED` status is unchanged and was not reopened. See MEDIA-VIDEO-TYPE-001 notes.
- 2026-11-01: LOW-10 investigated (read-only) on `integration/shoppydrive-remediation-2026-11-01`. Confirmed `.github/workflows/deploy.yml:256`'s `php artisan migrate --force` still has no lock/statement timeout. A shell-`timeout`-wrapper approach was proposed but explicitly **not implemented** — the user deferred it: the correct timeout value and Hostinger shell compatibility are unconfirmed, a shell timeout doesn't guarantee safe DDL-side rollback, and `deploy.yml` changes carry their own separate approval requirement. Status remains `NOT_STARTED`. Kept deliberately separate from `DEPLOY-SAFETY-001` Tier 2. No files changed. See LOW-10 notes.
- 2026-11-01: LOW-05 implemented and verified on `integration/shoppydrive-remediation-2026-11-01`. New `tests/Feature/Admin/AdminRoutePermissionWiringTest.php` (test-only, no application code/routes/AdminSections changed) walks the real route table via `Route::getRoutes()` and asserts every non-exempt `admin.*` route carries exactly one `admin.can:<section>,<ability>` middleware entry naming a real section/ability — closing the gap that `AdminPermissionsTest` only proved the gating mechanism correct via 4 representative sections, never that every route is actually wired to it. A 20-route exception list (pre-auth routes, the dashboard/redirect, the shared settings/content-image endpoints, admin-account management, and every `admin.mother`-gated destructive action) was individually verified against its route definition before being added. 2/2 passed, 917 assertions, 315ms, SQLite-only. No wiring defect found. See the LOW-05 row for detail.
- 2026-11-01: LOW-09 investigated (read-only) on `integration/shoppydrive-remediation-2026-11-01` and accepted by the user as `ACCEPTED_RISK`, following the audit's own recommendation. `OrderNumberGenerator`'s check-then-act loop re-confirmed unchanged, backstopped by a genuine DB-level unique constraint on `orders.order_number`. One correction to the audit's own combinatorics found and recorded (36⁶ effective daily combinations, not 62⁶, due to `strtoupper()` collapsing letter case) — doesn't change the "practically unreachable" conclusion. No application code, tests, or migrations changed. See LOW-09 notes.
- 2026-11-01: MED-19 investigated (read-only) on `integration/shoppydrive-remediation-2026-11-01` and accepted by the user as `ACCEPTED_RISK`. Both call sites (`MobilePaymentController.php`, `LandingPageController.php`) re-confirmed to still use an identical, unchanged flat ±1 Taka tolerance; exposure capped at ৳1/order; audit's own impact classification was `THEORETICAL`. No application code, tests, or migrations changed. See MED-19 notes.
- 2026-11-01: MED-14 implemented and verified on `integration/shoppydrive-remediation-2026-11-01`. New `tests/Feature/SearchCaseFoldingMysqlTest.php` (test-only, no application code changed) proves MySQL's `utf8mb4_unicode_ci` case-folds accented Latin letters in `LIKE` queries where SQLite's ASCII-only folding cannot, isolated to the accented letter alone and verified via a direct query, the customer-facing search-suggestions endpoint, and two admin filters (Brand, Customer). Bengali intentionally excluded — no case distinction. SQLite: 2/2 passed, 3 assertions. MySQL (disposable `shoppydrive_test_med14`, user-authorized, created for this work): 5/5 passed, 59 assertions, 80.2s — session charset (`utf8mb4`) and column collation (`utf8mb4_unicode_ci`) independently verified before interpreting results. `shoppydrive_test_loyalty` was not touched.
- 2026-11-01: LOW-02 investigated (read-only) on `integration/shoppydrive-remediation-2026-11-01` and accepted by the user as `ACCEPTED_RISK`. Confirmed `ProductVariant.attributes` can go stale after an ordinary `AttributeValue` rename, visible on customer-facing views until the product is next saved; impact is cosmetic/display-only. `OrderItem.variant_attributes`'s intentional historical snapshot is explicitly preserved and out of scope. No application code, tests, or migrations changed. See LOW-02 notes.
- 2026-11-01: LOW-01 investigated (read-only) on `integration/shoppydrive-remediation-2026-11-01` and accepted by the user as `ACCEPTED_RISK`. All 7 `Order::create()` call sites freshly re-verified disciplined; no unsafe request spreading or uncontrolled `order_number` assignment found. No application code, tests, or migrations changed. See LOW-01 notes.
- 2026-11-01: MED-11 (courier-dispatch extraction only) implemented and verified locally on `integration/shoppydrive-remediation-2026-11-01`. New `OrderCourierController`; 128/128 SQLite tests passed. The real-MySQL race suite initially failed on a stale test-harness reference (fixed, one line); the fix was not re-verified against MySQL — see notes. The rest of MED-11's original scope remains undone.
- 2026-11-01: MED-10 implemented and verified locally on `integration/shoppydrive-remediation-2026-11-01`. New shared `ShippingCostCalculator`; `CheckoutController` and `LandingPageController` now call it directly, `ManualOrderPricing::shippingCost()` delegates to it. All pre-existing shipping tests pass unchanged.
- 2026-11-01: MED-09 implemented and verified locally on `integration/shoppydrive-remediation-2026-11-01`. `CartManager::revalidatePrices()`'s strict float price comparison now compares in integer poisha, matching `ManualOrderPricing::differs()`'s existing convention. No broad float-cast retrofit performed, by design.
- 2026-11-01: MED-20 Tier 1 implemented and verified locally on `integration/shoppydrive-remediation-2026-11-01`: `pending-checkouts:prune` deletes converted, 24h+ expired `PendingCheckout` rows only. Tier 2 (unconverted, expired rows) explicitly deferred — see MED-20 notes.
- 2026-11-01: MED-17 implemented and verified locally on `integration/shoppydrive-remediation-2026-11-01`. Removed the confirmed-dead `ZiniPayService::createInvoice()` method after an exhaustive reference search found no callers anywhere, static or dynamic.
- 2026-11-01: LOW-06 implemented and verified locally on `integration/shoppydrive-remediation-2026-11-01`. Added `SitemapGenerationTest`; no bug found, no command change.
- 2026-11-01: LOW-07 investigated on `integration/shoppydrive-remediation-2026-11-01` and reclassified `NOT_APPLICABLE` — the audit's "inconsistent with the dark auth flow" premise does not match the actual code (`verify-email.tsx` matches three sibling pages exactly; no dark customer-auth flow exists). No code changed. See Corrections to the audit report.
- 2026-11-01: LOW-04 implemented and verified locally on `integration/shoppydrive-remediation-2026-11-01`. Removed the three unused `ShippingZone`/`ShippingMethod`/`TaxRate` TypeScript interfaces.
- 2026-11-01: LOW-03 implemented and verified locally on `integration/shoppydrive-remediation-2026-11-01`. Dashboard low-stock flagging now checks each variant against its own threshold instead of a summed total against one threshold.
- 2026-11-01: LOW-08 implemented and verified locally on `integration/shoppydrive-remediation-2026-11-01`. Dashboard revenue (today/yesterday/7-day chart) now filters to paid, non-cancelled orders.
- 2026-11-01: SEARCH-AUTOCOMPLETE-FT-001 fix written, MySQL verification unfinished. Checkpoint branch `backup/shoppydrive-remediation-checkpoint-2026-11-01` created.
- 2026-11-01: Full verification run. SEARCH-AUTOCOMPLETE-FT-001 found.
- 2026-11-01: MED-13 completed locally. WISHLIST-ADDALL-VARIANT-001 and WISHLIST-ADDALL-DELETED-001 fixed locally. LP-INVOICE-STATUS-001 recorded as unresolved.
- 2026-11-01: MED-12 completed locally. MED-13 remaining tests added, exposing WISHLIST-ADDALL-DELETED-001 and WISHLIST-ADDALL-VARIANT-001.
- 2026-11-01: MED-05 remaining sub-items implemented and verified locally.
- 2026-11-01: MED-06 production preflight deferred by the user. Status BLOCKED (DEFERRED BY USER), migration flagged NOT SAFE TO DEPLOY.
- 2026-11-01: MED-06 verified on disposable MySQL.
- 2026-11-01: MED-06 migration and handling written, verified on in-memory SQLite only. Not complete.
- 2026-11-01: MED-15 implemented in `deploy.yml` locally, with user approval. Not committed or deployed.
- 2026-11-01: MED-15 reassessed against the real deployment architecture.
- 2026-11-01: MED-16 implemented and verified locally.
- 2026-11-01: MED-03 implemented and verified locally.
- 2026-11-01: INV-STALE-STOCK-001 implemented and verified locally.
- 2026-11-01: MED-04 implemented and verified locally. User decided to keep old media files. INV-STALE-STOCK-001 and MEDIA-VIDEO-TYPE-001 recorded as unresolved.
- 2026-11-01: MED-07 implemented and verified locally.
- 2026-11-01: MED-08 implemented and verified locally.
- 2026-11-01: MED-02 implemented and verified locally.
- 2026-11-01: MED-01 implemented and verified locally.
- 2026-11-01: Audit report added and read in full. All MED and LOW findings reconciled against code. HIGH-02 accepted by the user.
- 2026-11-01: HIGH-02 implemented and verified locally. All four Critical and High findings are locally complete. Count-only guest wishlist approved.
- 2026-11-01: HIGH-03, its guest /wishlist finding and REF-CAP-TZ-001 implemented and verified locally. HIGH-01 accepted by the user.
- 2026-11-01: HIGH-01 implemented and verified locally. 
- 2026-11-01: CRIT-01 accepted by the user as COMPLETED (LOCAL IMPLEMENTATION).
- 2026-11-01: CRIT-01 implemented and verified locally. Not committed, pushed or deployed.
- 2026-11-01: Tracker created. CRIT-01 confirmed by code reading. Other findings awaited the audit report. No application code changed.
