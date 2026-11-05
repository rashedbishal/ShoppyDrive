# Functional QA Report

Audited commit `f49bed3`, 2026-11-05. Audit only: nothing was fixed, committed or deployed. Full entries: `COMPLETE_FINDINGS_REGISTER.md`.

## Results

Matrix totals: 29 PASS, 37 FAIL, 4 BLOCKED, 9 NOT TESTED.

Audit tests live in `tests/`. Each states the correct behaviour, so FAIL confirms a finding. Final run on in-memory SQLite: 46 tests, 19 passed, 27 failed (`evidence/phase11/audit_suite_final_sqlite.txt`). Run them with:

```
vendor/bin/pest --configuration=docs/forensic-audit/tests/phpunit.audit.xml --test-directory=docs/forensic-audit/tests --compact
```

Safety: tests used in-memory SQLite, or disposable `shoppydrive_test_audit*` databases on a local MariaDB. Mail was `array`, queue `sync`. Browser checks used a separate SQLite copy. No live site writes, and no real payment, SMS, mail or courier calls.

## Matrix

| Test ID | Module | Scenario | Preconditions | Steps | Expected | Actual | Result | Evidence | Finding |
|---|---|---|---|---|---|---|---|---|---|
| A01 | Payments | Forged SMS | Pattern with no sender rule | Send matching SMS, submit TrxID | No paid order | 1 paid order | FAIL | tests/BusinessLogicAuditTest.php | SEC-001 |
| A02 | Checkout | Price raised after add | Cart at 100, price now 150 | Submit UI payload | Bounce to review | Order created at 150 | FAIL | tests/BusinessLogicAuditTest.php | BIZ-001 |
| A03 | Bundles | Bundle checkout | Active bundle A+B | Submit UI payload 3 times | 1 order | 0 orders | FAIL | tests/BusinessLogicAuditTest.php | BIZ-002 |
| B01 | Coupons | Guest Cart page coupon | Guest, valid coupon | Apply on /cart, open /checkout, submit | Shown equals charged | Shows 900. Order not submitted by script; code sends no coupon | FAIL | evidence/phase2/guest_coupon_browser.txt | BIZ-003 |
| A04a | Admin orders | Convert above stock | Stock 5 | Convert 10 | Refused | Order created | FAIL | tests/BusinessLogicAuditTest.php | BIZ-004 |
| A04b | Inventory | Convert then cancel | Stock 5 | Convert 2, cancel | 3 then 5 | Stayed 5 after convert | FAIL | tests/BusinessLogicAuditTest.php | BIZ-004 |
| A05 | Orders | Customer cancels paid order | Paid pending order | PATCH cancel | Refused | Cancelled | FAIL | tests/BusinessLogicAuditTest.php | BIZ-005 |
| A06 | Couriers | Admin cancels with consignment | Steadfast consignment set | PATCH cancel | Refused | Cancelled | FAIL | tests/BusinessLogicAuditTest.php | BIZ-007 |
| A07 | Admin | Secrets to limited admin | about_page view only | Open page | No secrets | Secrets present | FAIL | tests/BusinessLogicAuditTest.php | SEC-002 |
| A08 | Tracking | Whole order exposed | Order with IP | Track with phone | No IP or notes | Present | FAIL | tests/BusinessLogicAuditTest.php | SEC-006 |
| A09 | Coupons | Coupon data exposed | Valid code | POST validate | No counts | used_count present | FAIL | tests/BusinessLogicAuditTest.php | SEC-012 |
| A10 | Payments | Pay for out of stock | Stock 0 | Mobile checkout | Refused | Pending created | FAIL | tests/BusinessLogicAuditTest.php | BIZ-006 |
| A11 | Checkout | Deleted product in guest cart | Soft-deleted product | Submit | Clear message | 404 | FAIL | tests/BusinessLogicAuditTest.php | BE-002 |
| A12 | Checkout | Retried token | Order placed | Resubmit same token | 1 order, empty cart | 1 order, cart refilled | FAIL | tests/BusinessLogicAuditTest.php | BIZ-014 |
| A13 | Coupons | Per-user limit across guest | Limit 1 | Use logged in, then as guest | used_count 1 | 2 | FAIL | tests/BusinessLogicAuditTest.php | BIZ-015 |
| A14 | Tracking | Array phone | Order exists | phone[]=x | Below 500 | 500 | FAIL | tests/BusinessLogicAuditTest.php | BE-001 |
| A15 | Landing | Normal token on LP URL | Pending checkout | GET LP payment | Below 500 | 500 | FAIL | tests/BusinessLogicAuditTest.php | BE-001 |
| A16 | Checkout | Huge quantity | MariaDB | Quantity 3,000,000,000 | Below 500 | 500 on MariaDB, passes on SQLite | FAIL | evidence/phase2/audit_tests_mariadb_subset.txt | BIZ-011 |
| A17 | Loyalty | Points spent twice | 100 points, late paid checkout | COD with points, then late submit | Balance not negative | -100 | FAIL | tests/BusinessLogicAuditTest.php | BIZ-010 |
| A18 | Admin | Show route | Admin | GET /admin/products/1 | Below 500 | 500 | FAIL | tests/BusinessLogicAuditTest.php | BE-001 |
| S01 | Auth | Code on changed email | Unverified, code sent | Change email, submit code | Not verified | Verified | FAIL | tests/AccessControlAuditTest.php | SEC-003 |
| S02 | Customers | Tokens to admin | Social account | Open customer page | No tokens | Present | FAIL | tests/AccessControlAuditTest.php | SEC-004 |
| S03 | Customers | Tokens to customer | Social account | Open connections | No tokens | Present | FAIL | tests/AccessControlAuditTest.php | SEC-004 |
| S04 | Admin | Forwarder token to viewer | View only | Open page | No full token | fullToken present | FAIL | tests/AccessControlAuditTest.php | SEC-007 |
| S05 | Auth | Reset link host | Host evil.example | Forgot password | Canonical host | evil.example in app; live CDN drops unknown hosts | FAIL | tests/AccessControlAuditTest.php | SEC-011 |
| S06 | Admin | Logout as guest | Not logged in | POST /admin/logout | Below 500 | 500 | FAIL | tests/AccessControlAuditTest.php | BE-001 |
| S07 | Auth | Array login email | - | POST login | Below 500 | 500 | FAIL | tests/AccessControlAuditTest.php | BE-001 |
| P01 | Performance | Listing queries | 12 products, 6 variants | GET /products | Under 40 | 101 | FAIL | tests/PerformanceAuditTest.php | PERF-001 |
| P02 | Reviews | Unapproved rating on home | Pending 1-star | GET / | Not counted | Counted | FAIL | tests/PerformanceAuditTest.php | BIZ-033 |
| B02 | Cart | Server error wipes cart | Logged in with item, APP_DEBUG off | Click a page returning 500 | Cart kept | items:[] sent, cart empty | FAIL | evidence/phase2/cart_loss_on_500_browser.txt | FE-005 |
| B03 | Privacy | Back after logout | Logged in with order | Log out, press Back | Data gone | Order shown | FAIL | evidence/phase2/back_after_logout_browser.txt | SEC-021 |
| C01 | Checkout | Client price tampering | Test data | See test | Server prices used | As expected | PASS | tests/AttackControlsAuditTest.php | - |
| C02 | Checkout | Zero and negative quantity (2 cases) | Test data | See test | Rejected | As expected | PASS | tests/AttackControlsAuditTest.php | - |
| C03 | Catalog | Buy hidden product | Test data | See test | No order | As expected | PASS | tests/AttackControlsAuditTest.php | - |
| C04 | Orders | IDOR on order, invoice, cancel | Test data | See test | 403 | As expected | PASS | tests/AttackControlsAuditTest.php | - |
| C05 | Orders | Stranger on confirmation | Test data | See test | Refused | As expected | PASS | tests/AttackControlsAuditTest.php | - |
| C06 | Tracking | Wrong phone | Test data | See test | No data | As expected | PASS | tests/AttackControlsAuditTest.php | - |
| C07 | Payments | Another customer pending checkout | Test data | See test | Refused | As expected | PASS | tests/AttackControlsAuditTest.php | - |
| C08 | Webhooks | SMS wrong or missing token | Test data | See test | 401 | As expected | PASS | tests/AttackControlsAuditTest.php | - |
| C09 | Webhooks | SMS no token configured | Test data | See test | 401 | As expected | PASS | tests/AttackControlsAuditTest.php | - |
| C10 | Webhooks | Steadfast bad signature, with positive control | Test data | See test | 401, no change | As expected | PASS | tests/AttackControlsAuditTest.php | - |
| C11 | Payments | One TrxID, two orders | Test data | See test | 1 paid | As expected | PASS | tests/AttackControlsAuditTest.php | - |
| C12 | Checkout | Double submit | Test data | See test | 1 order, 1 decrement | As expected | PASS | tests/AttackControlsAuditTest.php | - |
| C13 | Admin | Customer on admin; limited admin | Test data | See test | Refused | As expected | PASS | tests/AttackControlsAuditTest.php | - |
| C14 | Accounts | Mass assignment | Test data | See test | No privilege | As expected | PASS | tests/AttackControlsAuditTest.php | - |
| C15 | Checkout | Another customer address | Test data | See test | Not used | As expected | PASS | tests/AttackControlsAuditTest.php | - |
| C16 | Catalog | Script in description | Test data | See test | Purified | As expected | PASS | tests/AttackControlsAuditTest.php | - |
| C17 | Coupons | Coupon above subtotal | Test data | See test | Total not negative | As expected | PASS | tests/AttackControlsAuditTest.php | - |
| R01 | Concurrency | Product stock race | MariaDB 11.8.3 READ-COMMITTED, snapshot isolation on | Run existing tests | No double effect | 3/3 | PASS | evidence/phase11/mariadb_race_*.txt | - |
| R02 | Concurrency | Cart race | MariaDB 11.8.3 READ-COMMITTED, snapshot isolation on | Run existing tests | No double effect | 4/4 | PASS | evidence/phase11/mariadb_race_*.txt | - |
| R03 | Concurrency | Coupon race (port-only copy) | MariaDB 11.8.3 READ-COMMITTED, snapshot isolation on | Run existing tests | No double effect | 1/1 | PASS | evidence/phase11/mariadb_race_*.txt | - |
| R04 | Concurrency | Courier send race (port-only copy) | MariaDB 11.8.3 READ-COMMITTED, snapshot isolation on | Run existing tests | No double effect | 15/15 | PASS | evidence/phase11/mariadb_race_*.txt | - |
| R05 | Concurrency | Loyalty race (port-only copy) | MariaDB 11.8.3 READ-COMMITTED, snapshot isolation on | Run existing tests | No double effect | 12/12 | PASS | evidence/phase11/mariadb_race_*.txt | - |
| R06 | Concurrency | Return and refund race | Same | Run existing tests | No over-refund | Locked read correct; 1/2, wrong isolation assumption in test | FAIL | evidence/phase11/mariadb_race_copies_run.txt | REL-002 |
| R07 | Concurrency | Uniqueness on variants | Same | Run existing tests | Unique enforced | 9 passed, 1 skipped | PASS | evidence/phase11/mariadb_race_run.txt | - |
| L01 | Live | Exposed files | Public site | GET paths | Blocked | Blocked; /build/manifest.json public | PASS | evidence/phase2/live_paths.txt | - |
| L02 | Live | Unknown Host header | Public site | GET with fake Host | Refused | Connection reset | PASS | session 1, 2 requests | SEC-011 |
| L03 | Live | Security headers | Public site | HEAD / | HSTS, CSP | Absent | FAIL | evidence/phase2/live_headers.txt | SEC-013 |
| L04 | Live | Product page tags | Public site | GET product | Title, og, h1 | Generic title, none | FAIL | session 1 | FE-006 |
| L05 | Live | sitemap.xml | Public site | GET | 200 | 404 | FAIL | evidence/phase2/live_paths.txt | OPS-001 |
| L06 | Live | Legal text | Public site | GET /terms, /faq | Matches behaviour | Contradictions | FAIL | session 1 | BIZ-032 |
| L07 | Live | Return and shipping policy pages | Public site | GET | Exists | 404 | FAIL | session 1 | BIZ-034 |
| Q01 | Quality | Project test suite | SQLite | Full suite | Green | 2,026 tests, 1,963 passed, 63 skipped | PASS | Run before the audit at this commit | - |
| Q02 | Quality | Lint and types | Local | ESLint, tsc, Pint | Clean | 0 errors, 7 warnings; tsc 0; Pint passed | PASS | Same run | - |
| Q03 | Build | Production build | Scratch folder | vite build | Succeeds | 11 MB, no maps | PASS | evidence/phase11 | - |
| Q04 | Build | Config, route, event, view cache | Scratch paths, production env | Run caches | Succeed | All files written; project caches unchanged | PASS | session 1 | - |
| N01 | Payments | Real bKash and Nagad payment | - | - | - | Directive forbids real payments | NOT TESTED | - | - |
| N02 | Payments | Real SMS through the forwarder phone | - | - | - | Directive forbids; phone unavailable | NOT TESTED | - | SEC-001 |
| N03 | Couriers | Real Pathao and Steadfast calls | - | - | - | Directive forbids | NOT TESTED | - | BIZ-027 |
| N04 | Email | Real delivery, mail cap | - | - | - | Directive forbids | NOT TESTED | - | OPS-004 |
| N05 | Auth | Real Google and Facebook login | - | - | - | Real provider accounts | NOT TESTED | - | FE-008 |
| N06 | Performance | Load test | - | - | - | Directive forbids | NOT TESTED | - | - |
| N07 | Operations | Backups and restore | - | - | - | Owner declined | BLOCKED | - | OPS-008 |
| N08 | Operations | Cron, queue, opcache | - | - | - | Owner declined | BLOCKED | - | OPS-002 |
| N09 | Security | Real client IP behind CDN | - | - | - | Owner declined | BLOCKED | - | SEC-019 |
| N10 | Payments | Live sender rules and token | - | - | - | Owner declined | BLOCKED | - | SEC-001, SEC-020 |
| N11 | UX | Mobile keyboard, real devices, screen readers | - | - | - | Not available | NOT TESTED | - | - |
| N12 | Returns | Reject prompt Cancel in a browser | - | - | - | Dialogs block the browser tool; code re-read | NOT TESTED | - | BIZ-029 |
| N13 | Landing | Session timeout, 405 | - | - | - | Code only | NOT TESTED | - | FE-007 |

## Other findings from this phase

| ID | Severity | Priority | Title |
|---|---|---|---|
| BE-009 | MEDIUM | P1 | "Today" figures and invoices use UTC, not Dhaka time |
| REL-001 | MEDIUM | P1 | Tests pass payloads the real website never sends |
| BE-001 | LOW | P2 | Malformed input causes server errors |
| BE-002 | LOW | P2 | A deleted product in a guest cart gives a bare 404 at checkout |
| BE-003 | LOW | P2 | DatabaseSeeder wipes the catalog and has no production guard |
| BE-004 | LOW | P3 | Pathao sync can stop for up to 24 hours after a killed run |
| BE-006 | LOW | P3 | Currency symbol setting ignored on home and wishlist |
| BE-007 | LOW | P2 | Search overrides other filters on admin lists |
| BE-008 | LOW | P3 | Hover prefetches count as page views |
| BE-010 | LOW | P3 | Forgot-password sends mail inside the request with no error handling |
| BE-011 | LOW | P2 | Pathao sync reports success when every poll failed |
| BE-012 | LOW | P3 | Failed newsletter chunk cannot be retried from admin |
| REL-003 | LOW | P3 | Four race tests silently skip on anything but port 3306, and show as passed |
| REL-004 | LOW | P3 | Tests leave real files in storage |
| BE-005 | INFO | P3 | Dead code and unused schema |
| REL-002 | INFO | P3 | Race test assumes REPEATABLE READ, but production is READ-COMMITTED |

### BE-009: "Today" figures and invoices use UTC, not Dhaka time

An order at 03:00 Dhaka on 5 Nov is invoiced as 4 Nov. Today's revenue, orders and visitors reset at 06:00 Dhaka.

- **Severity / confidence:** MEDIUM / High (code re-read)
- **Files:** config/app.php:68; app/Http/Controllers/Admin/DashboardController.php:26-33,46-50; app/Http/Middleware/TrackDailyVisit.php:62; resources/views/invoices/order.blade.php:268
- **Evidence:** Code re-read.
- **Recommendation (not applied):** Use Asia/Dhaka boundaries for display.

### REL-001: Tests pass payloads the real website never sends

BIZ-001 and BIZ-002 survived a 2,026-test green suite because tests post bundle_id or omit items, unlike Checkout.tsx. There are no browser tests in the repo, and the suite cannot see frontend-backend mismatches such as BIZ-003.

- **Severity / confidence:** MEDIUM / High
- **Files:** tests/Feature/FlashSalePricingTest.php:148-150; tests/Feature/BundleCheckoutTest.php:29; tests/Feature/BundleAutoAssembleTest.php:176
- **Evidence:** A02 and A03 reproduce with the real payload.
- **Recommendation (not applied):** Add checkout tests that post the exact UI payload, plus a small browser smoke test.

### BE-001: Malformed input causes server errors

Five inputs give a 500: phone[]=x on order tracking, a normal token on a landing-page payment URL, POST /admin/logout as a guest, email[]=x on login, and /admin/products/{id}. No data is exposed, but logs fill and limits do not apply.

- **Severity / confidence:** LOW / High
- **Files:** app/Http/Controllers/OrderTrackingController.php:84; app/Http/Controllers/LandingPageController.php:354-356,384-386; app/Http/Controllers/Admin/AdminAuthController.php:50-55; app/Providers/FortifyServiceProvider.php:150; routes/admin.php:88,97,101,105,110,115,120
- **Evidence:** docs/forensic-audit/tests/A14, A15, A18 and AccessControlAuditTest S06, S07 all FAIL with 500.
- **Recommendation (not applied):** Add guards and except(show).

### BE-002: A deleted product in a guest cart gives a bare 404 at checkout

The customer sees a 404 with no explanation until they remove the item by hand.

- **Severity / confidence:** LOW / High
- **Files:** app/Http/Requests/PlaceOrderRequest.php:56; app/Support/CartManager.php:325,462
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A11 FAILS.
- **Recommendation (not applied):** Match live products only and say what was removed.

### BE-003: DatabaseSeeder wipes the catalog and has no production guard

php artisan db:seed --force in production would truncate products and categories with foreign keys off, overwrite settings, and add test@example.com with a known password. Deploy and composer do not run it.

- **Severity / confidence:** LOW / High
- **Files:** database/seeders/DatabaseSeeder.php:24-33,44
- **Evidence:** Code reading; deploy.yml and composer.json have no db:seed.
- **Recommendation (not applied):** Abort in production.

### BE-004: Pathao sync can stop for up to 24 hours after a killed run

A host-killed run leaves the overlap lock for 1440 minutes, and the code comment acknowledges it.

- **Severity / confidence:** LOW / Medium
- **Files:** routes/console.php:17-19; app/Console/Commands/SyncPathaoOrderStatuses.php:43-55
- **Evidence:** Code reading.
- **Recommendation (not applied):** Shorter lock and a time budget.

### BE-006: Currency symbol setting ignored on home and wishlist

Home and wishlist read a config key that does not exist, so they always show ৳.

- **Severity / confidence:** LOW / High (code reading)
- **Files:** app/Http/Controllers/HomeController.php:143; app/Http/Controllers/WishlistController.php:43
- **Evidence:** config/app.php has no currency_symbol.
- **Recommendation (not applied):** Read the Setting.

### BE-007: Search overrides other filters on admin lists

Products search with status inactive returns active items that match. SMS search with matched=no returns matched rows, which matters when hunting unmatched payments.

- **Severity / confidence:** LOW / High (code re-read)
- **Files:** app/Http/Controllers/Admin/ProductController.php:46-51; app/Http/Controllers/Admin/ReceivedSmsController.php:56-59
- **Evidence:** Code re-read.
- **Recommendation (not applied):** Nest the search.

### BE-008: Hover prefetches count as page views

Hovering a card counts as a view and runs the expensive product page.

- **Severity / confidence:** LOW / Medium
- **Files:** app/Http/Middleware/TrackDailyVisit.php:39-41,83; resources/js/components/shared/ProductCard.tsx:323,336,380
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Recommendation (not applied):** Skip prefetch requests.

### BE-010: Forgot-password sends mail inside the request with no error handling

During an SMTP outage this can return a 500 or hang, only for existing accounts, which also supports SEC-015.

- **Severity / confidence:** LOW / Medium
- **Files:** app/Providers/FortifyServiceProvider.php:61; app/Models/User.php:106-110
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Recommendation (not applied):** Queue it and return a generic response.

### BE-011: Pathao sync reports success when every poll failed

A Pathao outage leaves stale statuses silently, and the readiness marker still looks healthy.

- **Severity / confidence:** LOW / High
- **Files:** app/Console/Commands/SyncPathaoOrderStatuses.php:52,71,73; app/Services/CourierVerificationReadinessService.php:376-380; app/Services/PathaoCourierService.php:278-299
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Recommendation (not applied):** Return failure when all fail and stop on auth failure.

### BE-012: Failed newsletter chunk cannot be retried from admin

Up to 50 subscribers miss the send, and the panel says not safe to retry.

- **Severity / confidence:** LOW / High (allow-list grep re-run)
- **Files:** app/Support/FailedJobSummary.php:40-53; app/Http/Controllers/Admin/NewsletterController.php:142-145
- **Evidence:** grep count of SendNewsletter in RETRYABLE_JOBS: 0.
- **Recommendation (not applied):** Add it with a duplicate warning.

### REL-003: Four race tests silently skip on anything but port 3306, and show as passed

On MariaDB port 3399 the suite said "passed" while 30 tests skipped (coupon, courier, loyalty, return), and two of those files took minutes doing so. Copies with only the port changed then ran: Coupon 1/1, Courier 15/15, Loyalty 12/12, Return 1/2 (see REL-002).

- **Severity / confidence:** LOW / High
- **Files:** tests/Feature/CouponConcurrencyRaceTest.php:73-77; tests/Feature/CourierSendConcurrencyRaceTest.php; tests/Feature/LoyaltyPointsConcurrencyRaceTest.php; tests/Feature/ReturnFullRefundConcurrencyRaceTest.php
- **Evidence:** docs/forensic-audit/evidence/phase11/mariadb_race_run.txt and mariadb_race_copies_run.txt.
- **Recommendation (not applied):** Allow a configured port and fail loudly when asked to run.

### REL-004: Tests leave real files in storage

Each full run leaves about 14 files in storage/app/public/products, and only 10 test files use Http::fake with no preventStray.

- **Severity / confidence:** LOW / High
- **Files:** tests/ (102 files use RefreshDatabase); phpunit.xml
- **Evidence:** Recorded in Phase 1 A2 inspection.
- **Recommendation (not applied):** Fake storage and prevent stray requests.

### BE-005: Dead code and unused schema

Unused: ProductQuestion, OrderSequence, Tag, review_votes, notifications, carts.coupon_id, flash_sale_items.stock_limit, ContactFormMail (the contact form emails nobody), LoginRequest, RegisterRequest, ChangePasswordRequest, HeroSlideRequest. Root routes.json is an old route dump that is not served on the live site.

- **Severity / confidence:** INFO / High
- **Files:** app/Http/Controllers/ContactController.php:52; routes.json
- **Evidence:** docs/forensic-audit/evidence/phase2/live_paths.txt.
- **Recommendation (not applied):** Remove in a cleanup batch; decide on contact alerts.

### REL-002: Race test assumes REPEATABLE READ, but production is READ-COMMITTED

On MariaDB with production settings, the refund race test fails only on its assertion that a plain read still sees 0. The safety assertion passes: the locked read sees 600, so a double refund is prevented. Not a product bug.

- **Severity / confidence:** INFO / High
- **Files:** tests/Feature/ReturnFullRefundConcurrencyRaceTest.php:202-240
- **Evidence:** docs/forensic-audit/evidence/phase11/mariadb_race_copies_run.txt.
- **Recommendation (not applied):** Make the assertion isolation-aware.

