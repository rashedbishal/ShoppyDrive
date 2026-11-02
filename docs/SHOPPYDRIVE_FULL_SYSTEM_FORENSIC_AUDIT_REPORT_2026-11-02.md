# ShoppyDrive — Full System Forensic Audit Report (2026-11-02)

**Audit type:** Read-only forensic audit, with no remediation. **Audited commit:** `0ef9d9a` on `main`, identical to `origin/main`. **Performed:** 2026-11-03, using the requested report date. **Baseline documents:** `docs/SHAPPYDRIVE_FORENSIC_AUDIT_REPORT.txt` (the prior audit) and `docs/SHAPPYDRIVE_AUDIT_REMEDIATION_TRACKER.md` (the tracker). The `SHAPPY` spelling is the real filename of both baseline files on disk. Only this new report uses the corrected `SHOPPYDRIVE` spelling.

New finding IDs use the prefix **`FSA-`**. Existing IDs (`CRIT-01`, `HIGH-0x`, `MED-xx`, `LOW-xx`, `LP-INVOICE-STATUS-001`, `SEARCH-AUTOCOMPLETE-FT-001`, …) are preserved and referenced, never renumbered.

---

## 1. Executive summary

The remediation recorded in the tracker is real and largely holds. The specific defects the previous audit named (loyalty double-spend, cart duplicate rows, admin audit gaps, stale stock display, and others) are fixed in the code now on `main`. This audit found that **the most serious remaining risks sit in places the previous audit never examined.** Most are at trust boundaries the earlier passes declared clean ("no IDOR", "no client-submitted price trusted anywhere").

**Two CRITICAL issues:**

1. **FSA-01: cross-product variant price manipulation (confirmed at runtime).** `POST /checkout` and `POST /cart` accept any `variant_id` alongside any `product_id`, without checking that the variant belongs to the product. If product A has a product-wide flash sale, a crafted request pairing A with an expensive variant of product B is charged A's flash price. Product B's variant stock is decremented, and the order line carries B's SKU, so the warehouse ships B. In the probe, a ৳50,000 variant was ordered for ৳100.
2. **FSA-02: SMS payment auto-verification trusts any SMS the forwarder relays.** The sender is stored but never checked. Anyone who sends a correctly formatted fake "you received Tk X, TrxID Y" SMS to the merchant's bKash/Nagad phone (whose number checkout displays) can then submit `Y` and receive a `PAID` order. Exploitability depends on whether the forwarder app filters by sender, which is not verifiable from code.

**Seven HIGH issues:**
- FSA-03: anonymous order confirmation page exposing PII and the guest token.
- FSA-04: wholesale `cost_price` exposed on every storefront product surface.
- FSA-05: draft and inactive products can be viewed and ordered.
- FSA-06: a paid order can be cancelled by an admin and then can never be refunded.
- FSA-07: customers can pay by mobile banking and receive no order.
- FSA-08: landing pages for variant products are dead funnels that can still collect payment.
- `SEARCH-AUTOCOMPLETE-FT-001` remains unverified on MySQL despite now being on `main`.

**Overall health:** the core ledger, refund, stock-locking and permission machinery is well engineered. The weaknesses are in **input-binding validation on the customer side and in the asynchronous mobile-banking payment model**. Both directly touch money. Fix FSA-01 and FSA-02 before anything else.

---

## 2. Overall system health assessment

| Dimension | Assessment |
|---|---|
| Authorization (admin) | Strong. Per-section gating, mother-only destructive actions, route-wiring test (LOW-05). |
| Authorization (customer/public) | **Weak at three points:** the order confirmation route (FSA-03), product visibility (FSA-05), and variant/product binding (FSA-01). |
| Financial integrity | Ledger, refund and loyalty math are strong. **The mobile-banking async model has unrecoverable failure paths** (FSA-07, FSA-06). Payment proof is spoofable (FSA-02). |
| Inventory | Row-locked and correct for valid input. Exploitable through invalid product/variant pairing (FSA-01). |
| Data confidentiality | Cost prices, order PII and guest tokens leak (FSA-03, FSA-04). |
| Reliability/ops | Reasonable for shared hosting. Newsletter sending is fragile (FSA-13). The tracker no longer matches what is on `main` (FSA-16). |
| Tests | Broad (1,586 tests at the last recorded run). **No test covers any FSA finding**, and each one passes the current suite. |

---

## 3. Scope, methodology and repository safety

**Method:** source reading of routes, controllers, services, models, requests and middleware. All findings were traced end to end. Three CRITICAL/HIGH hypotheses were **verified at runtime** with a temporary Pest probe. The probe lived in the session scratchpad, outside the repository, and ran only against the in-memory SQLite test database defined in `phpunit.xml`: `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`, confirmed before running.

- Command: `vendor/bin/pest <scratchpad>/AuditProbeTest.php` → 3 passed, 25 assertions.

**Read-only dev DB checks:**
- `php artisan tinker` key-only serialization checks on **unsaved** model instances. No writes, and no values printed.
- `php artisan catalog:check-variant-attribute-duplicates`, which is read-only.
- `composer audit`.

The dev `ShoppyDrive` database holds **0 products and 0 orders**, so no data-integrity sampling was possible there.

**Not done:** no production access, no MySQL test runs, no browser or Core Web Vitals measurement, no load testing, and no changes to source, migrations, workflows, `.env`, branches, tags or stashes.

**Repository state vs. the audit prompt's stated preconditions.** These are recorded here and were not acted on:

| Prompt says | Actual at audit time |
|---|---|
| Five existing stashes | **One** stash, `stash@{0}`. Left untouched. |
| Tag `archive/shoppydrive-remediation-checkpoint-2026-11-01` | **No tags exist** locally. |
| Tracker and `vite.config.ts` carry uncommitted user changes | Working tree **clean**. Both files were checksummed before and after, see §23. |
| Other branches | Only `main` locally. Remote has only `main`. Earlier in this session, at the user's request, the backup branch was deleted locally and `main` was fast-forwarded to `origin/main`. The remote backup branch was removed by someone else. Earlier in this session, at the user's request, `composer install`, `npm ci`, `npm run build` and `php artisan migrate` ran against the **local dev DB**. That applied `2026_10_31_193742` (CRIT-01 `points_reserved`) and `2026_10_31_223219` (MED-06 unique indexes, after the duplicate preflight came back clean). |

---

## 4. Repository and architecture inventory

- **Stack:** Laravel 13 / PHP 8.3, Inertia v3, React 19, Tailwind v4. MySQL in dev and production, SQLite in-memory in tests. File cache and sessions, a DB queue drained by the scheduled `app:drain-queue`.
- **Size:** 284 PHP files in `app/` (26.6k lines), 132 migrations, 95 Feature/Unit test entries, 478 TS/TSX files, 281 routes.
- **Changes since the previous audit:** `git diff f36ee18..0ef9d9a` changes 128 files (+11,107/−682). This is the remediation batch (`18125d4`), the MED-06 migration (`3254bac`), HTTPS enforcement (`eb3d687`), MEDIA-VIDEO-TYPE fix, the variant duplicate preflight (`91f616c`) and UI commits.

| Module | Entry points | Key files | External | Notes |
|---|---|---|---|---|
| Catalog | `product.show`, `products.index`, `category.show`, `search`, `api/search/suggestions` | `ProductController`, `ProductListingController`, `SearchController`, `ProductSearchQuery` | — | FSA-04, FSA-05, SEARCH-AUTOCOMPLETE-FT-001 |
| Cart | `cart.store`, `cart.sync`, `checkout.store` (items sync) | `CartManager`, `AddToCartRequest`, `SyncCartRequest`, `PlaceOrderRequest` | — | FSA-01 |
| Checkout COD | `checkout.store` | `CheckoutController`, `InventoryService`, `CouponService`, `LoyaltyPointsService`, `ReferralService` | — | Idempotent (CHECKOUT-COD-002) |
| Mobile banking | `payment.mobile.*`, `lp.payment.*`, `api/sms/receive` | `MobilePaymentController`, `LandingPageController`, `SmsTestController`, `SmsParserService` | SMS forwarder | FSA-02, FSA-07 |
| Landing pages | `lp.*` | `LandingPageController` | — | FSA-08, LP-INVOICE-STATUS-001 |
| Orders/admin | `admin.orders.*` | `Admin\OrderController`, `OrderCourierController`, `RefundService` | Pathao, Steadfast | FSA-06, FSA-11 |
| Auth | Fortify, `social.*`, `admin.login` | `SocialAuthController`, `AdminAuthController` | Google, Facebook | FSA-09 |
| Reviews | `product.reviews.store`, `admin.reviews.*` | `ReviewController` | — | FSA-10 |
| Newsletter | `newsletter.*`, `admin.newsletter.send` | `NewsletterController` (×2) | SMTP | FSA-13, FSA-14 |
| Couriers | webhooks, `pathao:sync-statuses` | `CourierOrderStatusMapper`, `CourierSubmissionService` | Pathao, Steadfast | MED-02 fixed |
| Loyalty/referral | checkout, `rewards:grant-due`, daily login | `LoyaltyPointsService`, `ReferralService` | — | CRIT-01 fixed |

**Partially wired, dead or duplicated:**
- The admin review moderation queue is effectively dead because reviews are auto-approved (FSA-10).
- `/compare` gets no products (tracker Observation, unchanged).
- ZiniPay return routes exist only for historical rows (MED-17).
- `StockReservation`, `ShippingZone`, `ShippingMethod`, `ShippingRate` and `TaxRate` are unwired, as documented.
- Landing pages for variant products cannot sell (FSA-08).

---

## 5. Security findings

| ID | Sev | Title |
|---|---|---|
| FSA-01 | CRITICAL | Cross-product variant pairing lets a customer buy any variant at another product's flash-sale price |
| FSA-02 | CRITICAL | SMS payment verification accepts any relayed SMS: sender never authenticated |
| FSA-03 | HIGH | Unauthenticated order confirmation page exposes customer PII and `guest_token` |
| FSA-04 | HIGH | Wholesale `cost_price` exposed to every visitor |
| FSA-05 | HIGH | Inactive/unpublished products viewable by slug and orderable |
| FSA-09 | MEDIUM | Social login auto-links to an existing account by email even when that account's email was never verified (pre-account-takeover) |
| FSA-14 | LOW | Newsletter unsubscribe/status unauthenticated, so subscription can be enumerated and anyone can be unsubscribed |
| FSA-17 | LOW | Encrypted 2FA secret and recovery codes serialized to the browser on every page |
| FSA-19 | LOW | Unset SMS forwarder token makes `/api/sms/receive` throw a TypeError (500) instead of a clean 401 |
| FSA-24 | INFO | Every `Setting` row, including courier/gateway secrets, cached in plaintext in the file cache forever |

Full detail for each is in §17. **Positive re-confirmations:**
- Admin authorization holds.
- SQL is bound or whitelisted everywhere inspected.
- Customer address, return and invoice ownership checks are present.
- `composer audit` reports no vulnerabilities (only the abandoned `nunomaduro/pao`, already deferred).
- Shared Inertia `settings` props are allow-listed, so no secret reaches the page props.

---

## 6. Database findings

- **MED-06 (previously known):** the migration is now on `main`, which auto-deploys. The tracker's blocker ("NOT SAFE TO DEPLOY until preflight") was resolved by making the migration skip an index when duplicates exist (`3254bac`), not by running the production preflight. The dev-DB preflight is clean (0 duplicates, run this session). **Production index presence is unverified.** Use `SHOW INDEX FROM product_variants` / `attribute_values` on production to confirm.
- **FSA-01 data-integrity aspect:** `order_items` can record `product_id = A` with `variant_id` belonging to product B. No foreign key or check constraint ties `order_items.variant_id` or `cart_items.variant_id` to the same product. Any existing production rows with mismatched pairs would show up with `SELECT oi.id FROM order_items oi JOIN product_variants v ON v.id = oi.variant_id WHERE v.product_id <> oi.product_id;`. This is read-only and **should be run on production by the owner.**
- **FSA-06:** cancelled orders with `payment_status = paid` are an inconsistent state the schema permits and the application can create.
- **FSA-12:** `order_items.bundle_id` is never populated for mobile-banking orders.
- Re-confirmed sound: the `order_refunds` ledger, the RESTRICT/SET NULL design (DB-001), the unique `orders.order_number`, the unique `(provider, parsed_transaction_id)` on `received_sms`, and `courier_delivery_evidence` being append-only.

---

## 7. Business logic findings

| ID | Sev | Expected rule | Actual | Impact |
|---|---|---|---|---|
| FSA-01 | CRIT | Price, stock and SKU must all describe the same product | Price comes from `product_id`'s flash sale. Stock and SKU come from the unrelated `variant_id`. | Arbitrary-discount purchase of any variant |
| FSA-02 | CRIT | Only a genuine provider SMS proves payment | Any SMS on the merchant phone matching a pattern counts | Goods shipped against no payment |
| FSA-05 | HIGH | Hidden products cannot be bought | Only deleted products are filtered (`CartManager::validItems`) | Sale of draft, unpriced or withdrawn items |
| FSA-06 | HIGH | Cancelling a paid order must lead to a refund | Cancel allowed, then refund refused for cancelled orders | Customer money untracked, so refunds happen off-ledger or not at all |
| FSA-07 | HIGH | A customer who pays gets an order, or a tracked refund | Stock, coupon cap and expiry are re-checked only after payment, so any failure leaves the SMS unmatched with no workflow | Paid without order |
| FSA-08 | HIGH | Landing pages sell their product | Variant products always fail (`variant_id` hard-coded `null`) | Dead ad funnel. For mobile banking, money is taken first. |
| FSA-10 | MED | Reviews moderated or limited to purchasers | Auto-approved, any account including unverified ones | Fake reviews go live instantly. Moderation UI is dead. |
| FSA-11 | MED | A shipped parcel cannot be silently cancelled | Admin cancel allowed for `shipped` once the courier submission is resolved | Stock restored while goods are in transit. A late "delivered" is ignored (terminal), so COD cash is collected against a cancelled order. |
| (known) | MED | Customer cancel blocked after courier booking | Still allowed and unlocked (`routes/account.php:113-131`) | Tracker Observation. Needs a business decision. |
| (known) | MED | Guest landing-page invoice follows the same rules as the account invoice | LP-INVOICE-STATUS-001 still unresolved | — |

---

## 8. Performance findings

All of these are estimates from code. **Nothing was measured.**

- **PERF-1 (LOW):** `ProductController::show` loads `variants` and `media` without `variants.media`. `ProductVariant::getImageAttribute()` reads `media`, so this is likely one query per variant (tracker Observation, unchanged).
- **PERF-2 (LOW):** `HandleInertiaRequests` runs `CartManager::serializeForRequest()` on every request. That calls `BundleService::reconcile()`/`autoAssemble()`, which can write, and serializes full product models including `cost_price` (FSA-04). Cost grows with cart size on every navigation.
- **PERF-3 (MEDIUM, ops):** newsletter sending is a synchronous request with one message and *N* BCC recipients (FSA-13). With a large list it can hit PHP time limits.
- **PERF-4 (INFO):** the rating breakdown is computed in PHP from at most 50 reviews (FSA-18), which is wrong rather than slow.
- **Core Web Vitals:** not measured. No browser access.

---

## 9. Crash points and failure paths

| Where | Trigger | Result |
|---|---|---|
| `SmsTestController.php:48` | `services.sms_forwarder.token` unset | `hash_equals(null, …)` throws a TypeError, returning 500 (FSA-19) |
| `MobilePaymentController::submit` | Stock gone, coupon capped or session expired *after* payment | Error shown, transaction rolled back, money held with no order (FSA-07) |
| `LandingPageController::submitPayment` | Variant product | Always throws `InsufficientStockException` after payment (FSA-08) |
| `MobilePaymentController::show/submit` | Guest starts payment, then logs in | `abort_if(auth()->id() !== $pending->user_id, 403)`. The guest's own pending checkout becomes inaccessible (FSA-22). |
| `Admin\NewsletterController::send` | SMTP recipient cap or timeout | Whole send fails, possibly partially sent. Temp attachments orphaned (FSA-13). |
| `/api/search/suggestions` on MySQL | 3+ character query | Was a 500 (SEARCH-AUTOCOMPLETE-FT-001). Fix shipped, **never verified on MySQL**. |

---

## 10. Dead zones and incomplete features

- **Review moderation:** `Admin\ReviewController::approve()` and `ReviewApprovedMail` are effectively unreachable for new reviews, because `ReviewController.php:44` sets `is_approved => true` (FSA-10).
- **Landing pages for variant products:** creatable (`Admin\LandingPageController.php:44` accepts any product) but unsellable (FSA-08).
- **`payment_method=zinipay`:** still accepted by `PlaceOrderRequest`, then silently handled as mobile banking (FSA-23).
- **`/compare`:** renders without data (known).
- Unwired scaffolding as documented in CLAUDE.md. Not re-flagged.

---

## 11. API and integration findings

- **`/api/sms/receive`:** bearer-token authenticated. The *content* is trusted without sender authentication (FSA-02). Duplicate deliveries are handled idempotently.
- **Steadfast webhook:** HMAC verified and forward-only (MED-02 fixed). Logs full payloads, including PII, at info level (known Observation).
- **ZiniPay webhook:** unsigned, but re-verifies server to server (MED-17, accepted design).
- **Pathao, Steadfast, ZiniPay HTTP:** all clients set explicit timeouts.
- **Local success vs. external failure:** mobile-banking payment is the clearest case. The provider shows money received while the app holds no order (FSA-07).

---

## 12. Frontend reliability findings

- **Checkout and add-to-cart trust the client to pair product and variant correctly.** Only `SyncCartRequest` validates the pairing (FSA-01).
- **Landing page UI has no variant selector,** while the backend requires one for variant products (FSA-08).
- **Product page rating bars are wrong once there are more than 50 reviews** (FSA-18).
- Tracker Observations still open and unverified in a browser: the wishlist remove button keeps a stale item, and `/compare` is empty.
- No browser walkthrough was possible. The tracker's deployment-checklist item "real browser walkthrough" remains open.

---

## 13. Deployment and operational findings

- **FSA-16 (MEDIUM, process):** the tracker says "NOT DEPLOYED" / "Nothing here has been pushed" for CRIT-01, the HIGH and MED items and REF-CAP-TZ-001. Their code, including two migrations, is now on `origin/main`, and pushing `main` auto-deploys and runs `migrate --force`. Whether production has actually deployed `0ef9d9a` is unverified. The tracker is now an unreliable source of deployment truth. The pre-deploy checklist items were never ticked: CRIT-01 `pending_checkouts` preflight, `sms_patterns` vs `SmsRegex::problem()`, granting the `failed_jobs` section, header confirmation, and the browser walkthrough.
- Deploy remains in place over rsync, non-atomic, with no pre-migration backup (accepted C1) and no lock timeout (LOW-10, deferred).
- The scheduler runs `pathao:sync-statuses`, `rewards:grant-due`, `cart:remind-abandoned`, `pending-checkouts:prune` and `app:drain-queue`, all `withoutOverlapping`.

---

## 14. Cross-module consistency findings

- **Price source vs. stock source vs. SKU:** three modules (CartManager, InventoryService, order-item snapshot) each resolve "the item" independently. Nothing asserts they describe the same product (FSA-01).
- **Order status vs. payment status:** Admin cancel (`OrderController::cancel`) and `RefundService::assertRefundable` disagree on whether a paid order can leave the paid state through cancellation (FSA-06). The dashboard's `collectedRevenueQuery` (LOW-08 fix) then silently drops these orders from revenue.
- **Product visibility:** `LandingPageController` filters `active()->published()`. `ProductController::show`, `CartManager` and `CheckoutController` do not (FSA-05).
- **Phone validation:** main checkout enforces `^01[3-9][0-9]{8}$`. Landing pages accept any 20-character string (FSA-21), and coupon guest-limit checks, order tracking and abandoned-cart matching all key on phone.
- **Order access rule:** `OrderTrackingController` requires phone or email. `order.confirmation` requires nothing (FSA-03).
- **Bundle attribution:** COD and the legacy ZiniPay path carry `bundle_id`. The mobile-banking path drops it (FSA-12).

---

## 15. Adversarial scenario analysis

| # | Scenario | Actual behaviour | Inconsistent? | Evidence / fix |
|---|---|---|---|---|
| 1 | Double-click checkout | Same `order_token`, cache lock, replayed result | No | `CheckoutController.php:206-235` |
| 2 | Same payment SMS forwarded 5× | Unique `(provider, txn)`; duplicate insert caught | No | `SmsTestController.php:63-90` |
| 2b | **Forged payment SMS** | Accepted as payment | **Yes, CRITICAL** | FSA-02 |
| 3 | Two customers, last unit | `lockForUpdate` on variant/product, so one fails | No | `InventoryService.php:53-87` |
| 4 | Browser closed mid-checkout | COD: order exists or not (atomic). Mobile: PendingCheckout expires at 2h, points released by expiry. | No | — |
| 5 | Payment succeeds, server crashes before confirmation | Customer re-submits TrxID within 2h and it works. After 2h, "expired". | **Yes (FSA-07)** | Needs admin reconciliation of unmatched SMS |
| 6 | Courier sends an old status | Steadfast forward-only. Pathao unchanged by design. | Partially | MED-02 notes |
| 7 | Admin edits stock during checkout | Stock token rejects stale form (INV-STALE-STOCK-001) | No | `ProductStockToken` |
| 8 | Coupon expires during submit | Expiry checked at preview only. `consume()` re-checks caps, not dates. | Minor (by design) | `CouponService.php` docblock |
| 9 | Points reserved, payment expires | Reservation is derived from unexpired rows, so it auto-releases | No | CRIT-01 |
| 10 | Queue job runs twice | Mail idempotency per path. Loyalty/referral idempotent per order. | No | — |
| 11 | Courier API down | Timeouts set. `CourierSubmissionService` tracks unresolved state. | No | — |
| 12 | DB drops mid-transaction | All order paths are single transactions, so they roll back | No | — |
| 13 | Payment-return page refreshed repeatedly | `order_number` set means a redirect to confirmation | No | `MobilePaymentController.php:53-55` |
| 14 | Cancellation races payment confirmation | Customer cancel only for `pending` COD. Mobile orders are created `processing`. **Admin cancel of a paid order then blocks refund.** | **Yes** | FSA-06 |
| 15 | Admin deletes media used by historical orders | Snapshot keeps the path. File deletion policy is "keep old files" (MED-07). | No | — |
| 16 | **Crafted product/variant pair** | Accepted. Price from A, goods from B. | **Yes, CRITICAL** | FSA-01 |
| 17 | **Anyone opens `/order/ORD-…/confirmation`** | Full order JSON, PII and `guest_token` | **Yes** | FSA-03 |

---

## 16. Test coverage gaps

| Module | Critical behavior | Existing coverage | Missing scenarios | Risk |
|---|---|---|---|---|
| Cart/checkout | Variant belongs to product | `SyncCartRequest` only (indirect) | `checkout.store` and `cart.store` with a foreign variant | **Critical** |
| Catalog | Hidden products not viewable or orderable | None found | inactive / `published_at` null / future on `product.show` and checkout | High |
| Serialization | No `cost_price` or `guest_token` in public props | None | Assert absent on `product.show`, listings, cart, confirmation | High |
| Order confirmation | Ownership | Redirect target asserted only | Anonymous and other-user access | High |
| SMS payments | Sender authentication | Pattern/amount/duplicate tests (MED-04) | SMS from a non-provider sender must not verify | Critical |
| Admin cancel | Paid orders | `OrderCancellationAtomicityTest` | Cancel of a paid order must be refused or routed to refund | High |
| Mobile banking | Post-payment failure | Insufficient stock and coupon errors asserted as errors | Recovery path, unmatched-SMS reconciliation | High |
| Landing pages | Variant products | COD duplicate tests | Variant-product LP order | High |
| Reviews | Moderation and purchase gate | Bonus tests | Auto-approve policy, unverified account | Medium |
| Social login | Unverified existing account | Google `email_verified=false` case | Existing **local** account with `email_verified_at = null` | Medium |
| Search | MySQL suggestions | `SearchSuggestionsTest` MySQL cases **never completed** | Run on a disposable MySQL DB | High |

---

## 17. Complete finding register (new findings)

### FSA-01: Cross-product variant pairing: buy any variant at another product's flash-sale price
- **Severity:** CRITICAL. **Category:** business logic / price manipulation / IDOR-like binding flaw. **Confidence: Confirmed (runtime probe).** **Status:** newly discovered.
- **Files:**
  - `app/Http/Requests/PlaceOrderRequest.php:49-50`: only `exists:product_variants,id`.
  - `app/Http/Requests/AddToCartRequest.php:19-20`: same.
  - `app/Http/Controllers/CheckoutController.php:152-158`: syncs submitted items straight into the cart.
  - `app/Support/CartManager.php:425-429`: `FlashSaleItem::findActiveFor($productId, $variantId)`.
  - `app/Models/FlashSaleItem.php:63-73`: a product-wide row (`variant_id IS NULL`) matches *any* variant id.
  - `app/Services/InventoryService.php:53-62`: locks and decrements the variant by id alone.
  - `CheckoutController.php:305-323`: SKU and attributes taken from the variant.
- **Contrast:** `SyncCartRequest.php:23-41` *does* validate ownership, and so does `Admin/CreateOrderRequest.php:66`. The check simply was never added to the two other entry points.
- **Reproduction:**
  1. Product A has an active product-wide flash item at ৳100.
  2. B is a ৳50,000 product with a variant priced ৳50,000.
  3. `POST /checkout` with `items=[{product_id:A, variant_id:V_B, quantity:1}]` and `payment_method=cod`.
- **Observed in probe:** order `subtotal 100`, line `product_id=A`, `variant_id=V_B`, `sku=EXP-V1`, `price=100`. V_B stock went from 5 to 4.
- **Preconditions:** any active product-wide flash sale, which is common.
  - Without a flash sale, the price falls to the variant's own price, so there is no discount. The order line is still mislabelled with A's name and B's SKU and stock.
  - Guests can do this too when guest checkout is on.
  - The request also consumes A's flash-sale cap.
- **Impact:** direct, repeatable loss up to the full price of any variant. Inventory and fulfilment mismatches.
- **Remediation:**
  - Add the same ownership closure to `PlaceOrderRequest` and `AddToCartRequest`.
  - Add a defensive check in `CartManager::resolvePrice()` and `InventoryService::decrementForOrderItem()` that `variant.product_id === productId`, failing closed.
  - Optionally add a composite foreign key or trigger.
- **Regression tests:** foreign-variant rejection on `checkout.store` and `cart.store` (logged-in and guest). A service-level assertion in `InventoryService`.
- **Production check (read-only):** the `order_items` join query in §6.

### FSA-02: SMS payment verification accepts any relayed SMS (sender never authenticated)
- **Severity:** CRITICAL. **Category:** payment security. **Confidence: Confirmed in code. Exploitability needs runtime verification** of the forwarder app's own sender filtering. **Status:** newly discovered. MED-04 covered regex safety, not authenticity.
- **Files:**
  - `app/Http/Controllers/SmsTestController.php:48-72`: authenticates the forwarder device by bearer token, then stores `from` as `sender` without checking it.
  - `app/Services/SmsParserService.php` `parse()`: provider chosen by `stripos($content, identifier)`, no sender check.
  - `MobilePaymentController.php:116-145` and `LandingPageController.php:~380-390`: match on transaction id, provider and amount ±1 only.
- **Reproduction:**
  1. Read the merchant's bKash/Nagad number from the checkout payment page (`MobilePaymentController::show` returns `bkashNumber`/`nagadNumber`).
  2. From any phone, send it an SMS copying the provider's "You have received Tk 1,250.00 from 01XXXXXXXXX. … TrxID ABC12345" format, using the amount shown on the payment page.
  3. If the forwarder relays it, `received_sms` gets a matching row.
  4. Submit `ABC12345`. The order is created `PAID`/`processing`.
- **Impact:** goods released against no payment. It scales with knowledge of one SMS template.
- **Remediation:**
  - Allowlist provider sender IDs (for example `bKash`, `NAGAD`) in an `SmsPattern.sender_regex` or a Setting, and reject or ignore everything else at receive time.
  - Show the sender in the admin matched-payment view.
  - Consider requiring admin confirmation above an amount threshold.
  - Verify the forwarder app's filter configuration.
- **Tests:** an SMS with a matching body from a personal-number sender must not create a matchable row.

### FSA-03: Unauthenticated order confirmation exposes PII and `guest_token`
- **Severity:** HIGH. **Category:** broken access control / data exposure. **Confidence: Confirmed (runtime probe).** **Status:** newly discovered. The previous audit stated "no IDOR".
- **File:** `routes/commerce.php:45-49`. No middleware, no ownership check, no throttle. It serializes `Order` with `items.product`, and `Order` has no `$hidden`.
- **Exposed:** shipping and billing address (name, phone, email, full address), totals, coupon, points, `guest_token`, tracking/consignment ids, and `product.cost_price` via FSA-04.
- **Reproduction:** anonymous `GET /order/{order_number}/confirmation`. The probe asserted `order.shipping_address.phone` and `order.guest_token` were present.
- **Notes:**
  - Order numbers (`ORD-YYYYMMDD-XXXXXX`, 36⁶/day) are not designed as secrets. They appear in emails, SMS, courier labels, screenshots and support chats.
  - `OrderTrackingController` deliberately requires phone or email in addition. This route defeats that control.
  - `guest_token` is the bearer secret for `lp.invoice` and `lp.confirmation`.
- **Remediation:**
  - Require ownership: the logged-in owner, or a short-lived signed URL / session flag set at order creation.
  - Add `$hidden = ['guest_token', …]` on `Order`.
  - Serialize an explicit allow-listed array.
  - Throttle the route.
- **Tests:** anonymous and other-user requests are rejected. The owner sees no `guest_token`.

### FSA-04: Wholesale `cost_price` exposed on every storefront surface
- **Severity:** HIGH (business confidentiality). **Confidence: Confirmed (runtime probe).** **Status:** newly discovered.
- **Files:** `app/Models/Product.php` and `app/Models/ProductVariant.php` have no `$hidden`. They are serialized by `ProductController.php:66-68` (`$product->toArray()`, `variants`, `relatedProducts`), `CartManager::serialize()` (on every page via shared `cart`), `CheckoutController::resolveBuyNowItem()`, listing, home and search controllers (no column selects), and `order.confirmation`.
- **Impact:** every competitor and customer can read margins for every product and variant.
- **Remediation:** add `protected $hidden = ['cost_price']` to both models, and check that admin screens read it explicitly (`makeVisible`). Add a test asserting it is absent from `product.show` props.

### FSA-05: Inactive/unpublished products viewable by slug and orderable
- **Severity:** HIGH. **Confidence: Confirmed (runtime probe).** **Status:** newly discovered.
- **Files:**
  - `ProductController::show` (`routes/catalog.php:14`): plain `{product:slug}` binding, no `active()`/`published()` check.
  - `CartManager::validItems()` (`CartManager.php:234-237`) drops only *deleted* products, despite its docblock saying "deleted/deactivated".
  - `PlaceOrderRequest` and `AddToCartRequest` use `exists:products,id`.
- **Impact:**
  - Draft products, scheduled launches, and products withdrawn for pricing or legal reasons can be browsed by guessed or leaked slug, and bought.
  - Scheduled `published_at` is not enforced at the detail page.
- **Remediation:** a 404 for non-visible products on `product.show` (unless an admin preview). Filter `active()->published()` in `validItems()` and in request validation.

### FSA-06: A paid order can be admin-cancelled and then can never be refunded
- **Severity:** HIGH (financial integrity). **Confidence: Confirmed (code).** **Status:** newly discovered.
- **Files:**
  - `app/Http/Controllers/Admin/OrderController.php:406` (`UNCANCELLABLE_STATUSES` = cancelled/refunded/delivered) and `:408-445`: no `payment_status` check.
  - `app/Services/RefundService.php:142-143`: "A cancelled order cannot be refunded."
- **Scenario:**
  1. A mobile-banking order is created `processing`/`paid`.
  2. An admin cancels it. Stock, coupon and points are reversed, and `payment_status` stays `paid`.
  3. The order cannot now be refunded through the ledger.
  4. `DashboardController::collectedRevenueQuery()` excludes cancelled orders, so the money vanishes from reporting too.
- **Remediation:** refuse cancellation when `payment_status = paid` and direct the admin to the refund flow, or make cancel-of-paid create the refund ledger entry atomically. Business decision required.
- **Tests:** cancel of a paid order is refused, or produces an `order_refunds` row.

### FSA-07: Mobile-banking customers can pay and receive no order
- **Severity:** HIGH. **Confidence: Confirmed (code).** **Status:** newly discovered. It is related to MED-20 Tier 2, which was deferred, but distinct from it.
- **Files:**
  - `CheckoutController.php:364-394` and `LandingPageController.php:251-292`: the stage does not hold stock, does not reserve the coupon, and has a hard 2-hour expiry.
  - `MobilePaymentController.php:80-105, 187-204, 247-289`: stock, coupon cap and expiry are checked only at submit, after the customer has sent the money.
- **Failure modes:** last unit sold to someone else in between; coupon `max_uses` reached; customer submits the TrxID after 2 hours; landing page variant product (FSA-08). Each case rolls back, and the customer sees an error.
  - The `received_sms` row stays `is_matched = false`.
  - No admin queue links it to the failed `PendingCheckout`.
  - No refund ledger entry exists (no order).
- **Remediation:**
  - Reserve stock and coupon at staging, or convert a post-payment failure into an order flagged "needs fulfilment review" rather than rolling back.
  - Add an admin "paid but unmatched" reconciliation view.
  - Extend the window for already-paid submissions (SMS `received_at` before `expires_at`).
- **Tests:** stock exhausted between staging and submit leaves a recoverable record, not nothing.

### FSA-08: Landing pages for variant products are dead funnels that can still take payment
- **Severity:** HIGH. **Confidence: Confirmed (code).** **Status:** newly discovered.
- **Files:**
  - `Admin/LandingPageController.php:44,78` (any `product_id`).
  - `LandingPageController.php:168` (`resolvePrice($product->id, null)`), with `variant_id => null` in `$itemData`.
  - `InventoryService.php:76-78` throws for a variant product with no variant.
  - `resources/js/pages/landing/` has no variant selector.
- **Impact:** every COD order fails with "0 available". Mobile banking stages, the customer pays, and then submit fails (FSA-07).
- **Remediation:** block landing pages for variant products at admin save, or add variant selection to the landing-page form and backend.

### FSA-09: Social login pre-account-takeover via unverified local account
- **Severity:** MEDIUM. **Confidence: Confirmed (code).** **Status:** newly discovered.
- **File:** `app/Http/Controllers/Auth/SocialAuthController.php:93-104, 129-135`. It links to any existing `User` with the same email. The only refusal is Google `email_verified === false`. It never checks the local `email_verified_at`, and never resets the password or sessions the attacker created.
- **Scenario:**
  1. The attacker registers `victim@gmail.com` with a password and leaves it unverified.
  2. The victim later uses "Sign in with Google" and lands in the attacker-created account.
  3. The attacker keeps password access to the victim's future orders, addresses and points.
- **Remediation:**
  - When linking to an account whose `email_verified_at` is null, mark it verified, rotate the password (`has_password=false`) and invalidate other sessions.
  - Alternatively, refuse and require a password login.

### FSA-10: Reviews are auto-approved and open to any account
- **Severity:** MEDIUM. **Confidence: Confirmed (code).** **Status:** newly discovered.
- **File:** `app/Http/Controllers/ReviewController.php:40-44`: `is_approved => true`. The purchase check is used only for the bonus. The route has `auth` but no `verified` (`routes/catalog.php:15-17`).
- **Impact:** throwaway accounts post public 1★/5★ reviews instantly. The admin moderation queue and `ReviewApprovedMail` are dead for new reviews.
- **Remediation:** a business decision. Default to pending, require `verified`, or require a delivered purchase.

### FSA-11: Admin can cancel a shipped order
- **Severity:** MEDIUM. **Confidence: Confirmed (code).** **Status:** newly discovered. It is related to the known customer-cancel Observation.
- **File:** `Admin/OrderController.php:406,419-427`. `SHIPPED` is cancellable once the courier submission is resolved.
- **Impact:**
  - Stock is restored while the parcel is in transit, which overstates available stock.
  - Courier "delivered" afterwards is ignored because `CANCELLED` is terminal, so the courier collects COD for a cancelled order.
- **Remediation:** block cancel for `shipped`, or require courier cancellation or return confirmation first.

### FSA-12: Mobile-banking order items drop `bundle_id`
- **Severity:** LOW. **Confidence: Confirmed (code).**
- **File:** `MobilePaymentController.php:189-199`. `bundle_id` is not passed, although `CheckoutController.php:457` puts it in the snapshot and the COD path (`:310`) and `OrderService.php:97` persist it.
- **Impact:** bundle attribution and reporting are lost for mobile-banking orders.
- **Fix:** add `'bundle_id' => $item['bundle_id'] ?? null`.

### FSA-13: Newsletter sending is one synchronous all-BCC message; attachments never cleaned up
- **Severity:** MEDIUM (ops). **Confidence: Confirmed (code).**
- **File:** `app/Http/Controllers/Admin/NewsletterController.php:69-115`.
- **Problems:**
  - It uses `->send()`, not queued.
  - Every subscriber goes into a single message's BCC, so shared-hosting SMTP recipient caps or timeouts fail the whole send.
  - Uploaded attachments stored under `storage/app/newsletter-attachments/tmp` are never deleted.
- **Fix:** queue per-recipient or chunked sends, and delete the temp files after sending.

### FSA-14: Newsletter unsubscribe and status are unauthenticated
- **Severity:** LOW. **Confidence: Confirmed (code).**
- **File:** `app/Http/Controllers/NewsletterController.php:43-66`. Any email can be unsubscribed, or checked for subscription. The per-subscriber `token` exists but is unused.
- **Fix:** use token links for unsubscribe, and remove or limit `status`.

### FSA-15 (previously known, unresolved): Customer cancel unlocked and allowed after courier booking
- **File:** `routes/account.php:113-131`. Recorded in the tracker's Observations. Re-confirmed unchanged. MEDIUM.

### FSA-16: Tracker deployment status no longer reflects `main`
- **Severity:** MEDIUM (process). **Confidence: Confirmed** (git versus tracker text).
- **Evidence:** CRIT-01, HIGH-01–03, MED-01–17, REF-CAP-TZ-001 and the MED-06 migration are all in `origin/main` (`18125d4`, `3254bac`). The tracker still says "NOT DEPLOYED", "Nothing here has been pushed", and that the MED-06 migration is a deploy blocker.
- **Action:** the owner should confirm production's deployed commit and `migrate:status`, then reconcile the tracker. **Not edited**: the file is protected.

### FSA-17: Encrypted 2FA secret and recovery codes serialized on every page
- **Severity:** LOW. **Confidence: Confirmed (unsaved-model check).**
- **File:** `app/Models/User.php:46-50`. `$hidden` lacks `two_factor_secret` and `two_factor_recovery_codes`. `HandleInertiaRequests::share()` sends `auth.user` on every page. The values are ciphertext (`encrypt()`), so impact is defence-in-depth only.
- **Fix:** add both columns to `$hidden`.

### FSA-18: Product rating breakdown computed from 50 reviews only
- **Severity:** LOW. **File:** `ProductController.php:22,52-54`. The bars are wrong once there are more than 50 approved reviews. Use a grouped count query.

### FSA-19: Missing SMS forwarder token causes 500
- **Severity:** LOW. **File:** `SmsTestController.php:48`. `hash_equals(config(...))` with null throws a TypeError. It fails closed, but noisily. Guard `is_string`.

### FSA-20: Product media uploads have no size limit at validation
- **Severity:** LOW. **File:** `app/Http/Requests/Admin/ProductRequest.php:63-77`. There is a MIME allow-list but no `max`. Only PHP's `upload_max_filesize` bounds it, which risks disk and memory. Add `max:`.

### FSA-21: Landing-page phone validation weaker than main checkout
- **Severity:** LOW. **File:** `LandingPageController.php:104` (`max:20`) versus `PlaceOrderRequest` regex. This affects courier booking, tracking and abandoned-cart matching.

### FSA-22: Guest loses access to their own pending payment after logging in
- **Severity:** LOW. **File:** `MobilePaymentController.php:51,85`. A `user_id` mismatch returns 403.

### FSA-23: `payment_method=zinipay` still accepted and silently treated as mobile banking
- **Severity:** LOW. **File:** `PlaceOrderRequest.php:42` and `CheckoutController.php:262-364`. Remove `zinipay` from the allowed list.

### FSA-24: All settings, including secrets, cached in plaintext in the file cache forever
- **Severity:** INFO. **File:** `app/Support/LayoutCache.php:55-60`. This caches `Setting::all()`, which includes courier, gateway and webhook secrets, under `storage/framework/cache`. It is not exposed over HTTP. Consider caching only the public keys.

### Previously known items re-checked

| ID | Current state |
|---|---|
| CRIT-01 | Fixed in code (`points_reserved`, `availablePoints()`). On `main`. Production unverified. |
| HIGH-01/02/03, MED-01–05, 07–10, 12–17, LOW-03–06, 08 | Code present on `main` as the tracker describes. Not re-litigated. |
| MED-06 | Migration on `main`, skip-on-duplicate design. Dev preflight clean. **Production index presence unverified.** |
| MED-11 | Partial (courier extraction only), as recorded. |
| MED-18 | Still BLOCKED (production `.env`). |
| MED-19, LOW-01, LOW-02, LOW-09 | Accepted risk, unchanged. |
| MED-20 | Tier 1 only. Tier 2 deferred. Interacts with FSA-07. |
| LOW-10 | Not started (deferred). |
| LP-INVOICE-STATUS-001 | **Unresolved**, unchanged. |
| SEARCH-AUTOCOMPLETE-FT-001 | Fix now on `main` (`ProductSearchQuery.php:60-80`). **MySQL verification never recorded as completed.** |
| ADMIN-2FA-001, C1, M1, 17 npm moderates, XSS-SINK-GAP-001 | Accepted and preserved. Not reported as findings. |

---

## 18. Previously known vs. newly discovered

- **Newly discovered (23):** FSA-01–14, FSA-16–24 (FSA-15 is a known item restated). By severity: 2 CRITICAL, 6 HIGH, 5 MEDIUM, 9 LOW, 1 INFO.
- **Previously known, still unresolved:** LP-INVOICE-STATUS-001, the customer cancel after booking (FSA-15), SEARCH-AUTOCOMPLETE-FT-001 (unverified), MED-06 production verification, MED-18, MED-20 Tier 2, LOW-10, and the tracker Observations (wishlist remove refresh, `/compare`, variant image N+1, Steadfast payload logging, incomplete-order conversion ignoring flash sales).

## 19. Accepted risks and unresolved issues

These are preserved without change: ADMIN-2FA-001, no pre-deploy DB backup (C1), `StrictHostKeyChecking=no` (M1), 17 moderate npm advisories, `Page.content` unpurified with no write path, MED-19, LOW-01, LOW-02, LOW-09. **FSA-02's exploitability rests on an external configuration**, the forwarder app, which nobody has documented as an accepted risk.

## 20. Runtime verification requirements

| Item | What is needed | Who |
|---|---|---|
| FSA-02 | Does the forwarder app relay SMS from non-provider senders? Send a test SMS from a personal phone to the merchant number, then check Admin › Received SMS. **Do not submit it against an order.** | Owner |
| FSA-01 | Read-only `order_items`/`product_variants` mismatch query (§6) on production | Owner |
| FSA-06 | `SELECT id FROM orders WHERE status='cancelled' AND payment_status='paid'` on production | Owner |
| FSA-07 | `received_sms` rows with `is_matched=0` and a parsed amount, cross-checked against expired `pending_checkouts` | Owner |
| FSA-16 / MED-06 / CRIT-01 | Production deployed commit, `migrate:status`, `SHOW INDEX` on `product_variants` and `attribute_values` | Owner |
| SEARCH-AUTOCOMPLETE-FT-001 | Run `SearchSuggestionsTest` on a disposable `shoppydrive_test_*` MySQL DB, or one read-only request to production `/api/search/suggestions?q=abc` | Owner/next session |
| MED-18 | Production `SESSION_SECURE_COOKIE` | Owner |
| Frontend | Browser walkthrough (tracker checklist) | Owner/next session |

## 21. Prioritized remediation roadmap

None of this is implemented. Each item needs separate approval.

- **Immediate containment:**
  - **FSA-01:** add the variant-ownership rule to `PlaceOrderRequest` and `AddToCartRequest`. Small and isolated.
  - **FSA-02:** verify the forwarder filter now. Until a sender allowlist ships, consider disabling automatic SMS matching (manual confirmation).
  - **FSA-03:** add ownership or a signature to the confirmation route, and `$hidden` on `guest_token`.
- **Critical security fixes:** FSA-04 (`$hidden cost_price`), FSA-05 (visibility enforcement), FSA-09.
- **Financial and database integrity:** FSA-06 (cancel-of-paid rule), FSA-07 (post-payment failure handling and reconciliation view), production mismatch queries (§20).
- **Checkout and payment reliability:** FSA-08, FSA-22, FSA-23, LP-INVOICE-STATUS-001, MED-20 Tier 2.
- **Inventory and order consistency:** FSA-11, FSA-15, FSA-12.
- **Performance:** variant media eager-load, rating aggregation (FSA-18), shared-cart serialization weight.
- **Frontend and UX reliability:** landing-page variant selector or admin block, wishlist remove refresh, `/compare`.
- **Architecture and maintainability:** a single "orderable line" resolver used by cart, checkout, the landing page and InventoryService. Explicit API-shaped serialization instead of `toArray()` of models. Reconcile the tracker (FSA-16).
- **Long-term scalability:** queued newsletter (FSA-13), error tracking, CI on MySQL (CI-MYSQL-001).

## 22. Recommended implementation order

1. FSA-01, with tests.
2. FSA-02 (verify, then allowlist the sender).
3. FSA-03 together with FSA-04 (both serialization/exposure; one PR each).
4. FSA-05.
5. FSA-06 (needs a business decision on cancel vs refund).
6. FSA-07 together with FSA-08.
7. FSA-09, FSA-10, FSA-11.
8. LOW items.

## 23. Audit limitations and integrity check

- No production, browser or MySQL test access. The dev DB holds no catalog or order data, so all data-integrity statements are schema- and code-level.
- Admin modules not individually re-read this pass: blog, pages, hero, homepage, coupons CRUD, attributes, brands, categories. They are covered by the previous audit and the LOW-05 wiring test.
- Performance claims are code-based estimates only.
- **Protected files:** MD5 checksums of `docs/SHAPPYDRIVE_AUDIT_REMEDIATION_TRACKER.md` (`211df7a2…`), `vite.config.ts` (`e7f83584…`) and `docs/SHAPPYDRIVE_FORENSIC_AUDIT_REPORT.txt` (`e86f0045…`) were recorded before the audit and re-checked after writing this report. The only repository change is this new file. Stash, branches and tags were not touched.
