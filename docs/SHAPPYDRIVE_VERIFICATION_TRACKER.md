# ShoppyDrive verification tracker

Source checklist: https://claude.ai/artifact/DMM83dRHtY9RkYAWCrEdhj (loaded 2026-11-09, 99 items).

Claude marks an item here once its result is known. This file records live-site check results only. The status of each finding still lives in `docs/SHAPPYDRIVE_AUDIT_REMEDIATION_TRACKER.md`.

## Progress

| Group | Total | Pass | Fail | Skipped | Not checked |
|---|---|---|---|---|---|
| Backend (BE) | 10 | 0 | 0 | 0 | 10 |
| Business logic (BIZ) | 32 | 7 | 5 | 2 | 18 |
| Database (DB) | 3 | 0 | 1 | 0 | 2 |
| Frontend (FE) | 13 | 0 | 1 | 0 | 12 |
| Infrastructure (INFRA) | 4 | 0 | 0 | 0 | 4 |
| Operations (OPS) | 6 | 0 | 0 | 0 | 6 |
| Performance (PERF) | 7 | 0 | 0 | 0 | 7 |
| Reliability and tests (REL) | 3 | 0 | 0 | 0 | 3 |
| Security (SEC) | 21 | 1 | 0 | 0 | 20 |
| **All** | **99** | **8** | **7** | **2** | **82** |

## How to read this

- `[ ]` not checked
- `[x]` pass: the old bug could not be reproduced
- `[!]` fail: the old bug still happens, and the result line says how
- `[-]` skipped, and the result line says why

The rule for every item: the Problem line describes a bug that used to exist. Try to make it happen. If the site behaves correctly instead, it passes.

Items marked DEVELOPER CHECK ONLY are checked in the code, not on the website.

When marking an item, change its checkbox, fill in its Result line with the date and what was seen, and update the Progress table.

## Backend (BE)

- [ ] **BE-009** · P1 · MEDIUM · "Today" figures and invoices use UTC, not Dhaka time
  - **Problem:** An order at 03:00 Dhaka on 5 Nov is invoiced as 4 Nov. Today's revenue, orders and visitors reset at 06:00 Dhaka.
  - **What to check:** See example.
  - **Result:** Not checked

- [ ] **BE-001** · P2 · LOW · Malformed input causes server errors
  - **Problem:** Five inputs give a 500: phone[]=x on order tracking, a normal token on a landing-page payment URL, POST /admin/logout as a guest, email[]=x on login, and /admin/products/{id}. No data is exposed, but logs fill and limits do not apply.
  - **What to check:** Try these five things and confirm each shows a normal "something went wrong" page, not a blank crash screen: (1) a tracking link with phone[]=x in the URL, (2) a landing-page payment link with a plain made-up token instead of the real one, (3) visiting the admin logout link while logged out, (4) submitting login with email[]=x in the form, (5) visiting /admin/products/999999 (an id that doesn't exist).
  - **Result:** Not checked

- [ ] **BE-002** · P2 · LOW · A deleted product in a guest cart gives a bare 404 at checkout
  - **Problem:** The customer sees a 404 with no explanation until they remove the item by hand.
  - **What to check:** As a guest, add a product to your cart. Have an admin delete that exact product. Go to checkout and submit your order. You should see a clear message naming the unavailable item, not a bare "404 Not Found" page.
  - **Result:** Not checked

- [ ] **BE-003** · P2 · LOW · DatabaseSeeder wipes the catalog and has no production guard
  - **Problem:** php artisan db:seed --force in production would truncate products and categories with foreign keys off, overwrite settings, and add test@example.com with a known password. Deploy and composer do not run it.
  - **What to check:** DEVELOPER CHECK ONLY — do not run this yourself, it would erase real catalog data if the safeguard is missing. Ask Claude to confirm `database/seeders/DatabaseSeeder.php` refuses to run against production rather than running it live.
  - **Result:** Not checked

- [ ] **BE-007** · P2 · LOW · Search overrides other filters on admin lists
  - **Problem:** Products search with status inactive returns active items that match. SMS search with matched=no returns matched rows, which matters when hunting unmatched payments.
  - **What to check:** Combine filter and search.
  - **Result:** Not checked

- [ ] **BE-011** · P2 · LOW · Pathao sync reports success when every poll failed
  - **Problem:** A Pathao outage leaves stale statuses silently, and the readiness marker still looks healthy.
  - **What to check:** Wrong credentials.
  - **Result:** Not checked

- [ ] **BE-004** · P3 · LOW · Pathao sync can stop for up to 24 hours after a killed run
  - **Problem:** A host-killed run leaves the overlap lock for 1440 minutes, and the code comment acknowledges it.
  - **What to check:** Kill a run.
  - **Result:** Not checked

- [ ] **BE-008** · P3 · LOW · Hover prefetches count as page views
  - **Problem:** Hovering a card counts as a view and runs the expensive product page.
  - **What to check:** Hover cards.
  - **Result:** Not checked

- [ ] **BE-010** · P3 · LOW · Forgot-password sends mail inside the request with no error handling
  - **Problem:** During an SMTP outage this can return a 500 or hang, only for existing accounts, which also supports SEC-015.
  - **What to check:** SMTP down.
  - **Result:** Not checked

- [ ] **BE-012** · P3 · LOW · Failed newsletter chunk cannot be retried from admin
  - **Problem:** Up to 50 subscribers miss the send, and the panel says not safe to retry.
  - **What to check:** A chunk exhausts its tries.
  - **Result:** Not checked

## Business logic (BIZ)

- [!] **BIZ-001** · P0 · HIGH · COD can charge more than the price the customer saw
  - **Problem:** Example: a product is ৳100 in the cart, the admin changes it to ৳150, and the page still shows ৳100. The customer places a COD order and is charged ৳150 with no warning.
  - **What to check:** Add to cart at 100, raise the price to 150, submit the UI payload.
  - **Result:** Fail (tester, 2026-11-08). The overcharge itself didn't happen — the price stayed at ৳100 on screen, and clicking Confirm correctly refused with "Some prices in your cart have changed. Please review your updated cart and try again." instead of charging ৳150. But retrying never worked: the price never updated and the same refusal repeated every time, with no way to actually place the order. Fixed, pushed and deployed 2026-11-09 (owner confirmed the deploy succeeded) — the refusal now also corrects the cart and updates what's shown on screen, so a second attempt succeeds at the real price. Awaiting the tester's live retest.

- [!] **BIZ-002** · P0 · HIGH · A cart containing a complete bundle can never be checked out from the website
  - **Problem:** Every Place Order click shows "Some prices have changed" and no order is made. This affects COD and mobile banking, guests and members.
  - **What to check:** Create bundle A+B, submit the UI payload three times.
  - **Result:** Fail (tester, 2026-11-08) — a different scenario: a bundle component's quantity was set higher than the product's actual stock (e.g. recipe needs 10, stock is 5), which the admin shouldn't be able to save. Checkout itself was already fine here (reproduced directly: a clear "Only N unit(s) left in stock" refusal, no order placed — not the generic "Some prices changed"). Fixed, pushed and deployed 2026-11-09 (owner confirmed the deploy succeeded): the admin bundle create/edit form now refuses a component quantity above its actual stock, and shows the live stock next to the quantity field. Awaiting the tester's live retest.

- [ ] **BIZ-003** · P0 · HIGH · Guests see a coupon discount at checkout but are charged full price
  - **Problem:** Example: a guest applies SAVE100 on the Cart page on a ৳1,000 cart. Checkout shows Discount −৳100 and Total ৳900, but the order is sent without any coupon code, so the server charges ৳1,000. Logged-in customers: the coupon silently disappears. Applying it again at checkout subtracts it twice on screen.
  - **What to check:** As a guest: apply on /cart, open /checkout, read the total, submit.
  - **Result:** Not checked

- [ ] **BIZ-004** · P0 · HIGH · Converting an incomplete order ignores stock, and cancelling it creates stock
  - **Problem:** Example: stock is 5. An admin converts an incomplete order for 10: the order is created and stock stays 5. For a 2-unit order, cancelling then raises stock from 5 to 7.
  - **What to check:** Convert then cancel.
  - **Result:** Not checked

- [!] **BIZ-005** · P0 · HIGH · Paid orders can still be cancelled and then cannot be refunded
  - **Problem:** FSA-06 fixed only admin cancel. A customer can cancel a paid pending order, for example a manual mobile-banking order. A courier return report also cancels a paid order. In both cases the order is cancelled but still paid, and the refund screen refuses because "A cancelled order cannot be refunded".
  - **What to check:** Create a paid pending order, PATCH account.orders.cancel.
  - **Result:** Fail (tester skipped, "admin needs to handle this manually" — true of all refunds, not a new finding; reproduced the exact check myself anyway). The specific "A cancelled order cannot be refunded" refusal was already fixed, but a different dead end existed: the refund was then refused for needing courier verification, for an order that never touched a courier at all. Fixed, pushed and deployed 2026-11-09 (owner confirmed the deploy succeeded): cancelled/refunded orders with no courier evidence no longer require a courier-readiness attestation. Awaiting the tester's live retest.

- [ ] **BIZ-006** · P0 · HIGH · Customers can pay for out-of-stock items and receive no order
  - **Problem:** Example: stock is 0, the customer picks bKash and sees the payment page, pays, submits the TrxID, and gets "insufficient stock". Money is taken, no order exists. Landing pages also lack the late-submission grace and the variant guard, and only an unmatched-SMS count in the sidebar signals the problem.
  - **What to check:** Stock 0, mobile checkout.
  - **Result:** Not checked

- [-] **BIZ-007** · P1 · MEDIUM · Admin can cancel orders already with Steadfast; Steadfast orders never reach Shipped
  - **Problem:** Example: a COD order is sent to Steadfast and the admin cancels it, restoring stock. The courier delivers and collects cash, but the order stays cancelled because it is terminal. Shipped emails can never fire for Steadfast.
  - **What to check:** Set a consignment on a pending order, PATCH admin.orders.cancel.
  - **Result:** Skipped, owner-closed (2026-11-09). Tester deferred this to the owner rather than testing. The cancel-side protection was already deployed 2026-11-05 and matches the owner's actual process (cancel/return it in Steadfast's own portal — confirmed from Steadfast's published API reference that no cancel/delete-order endpoint exists at all, so there was nothing more to build). Owner reviewed the remaining "Steadfast orders never reach Shipped" gap and chose to close the whole finding without fixing it.

- [!] **BIZ-008** · P1 · MEDIUM · Refunded orders stay "paid" and keep counting as revenue
  - **Problem:** Example: a ৳1,060 paid order with ৳60 shipping is fully refunded. The refund cap excludes shipping, so the total refunded never reaches collected. Payment stays paid and the dashboard still counts ৳1,060. The dashboard comment claims the opposite. Partial refunds are not subtracted either.
  - **What to check:** Refund a paid order with shipping above 0.
  - **Result:** Fail (tester, 2026-11-08) — couldn't test at all: no working refund/cancel button on a paid order. Traced to a COD order marked paid without a delivered-by-courier timestamp, which silently disabled the refund button with no explanation (not the originally-described shipping-cap bug). Fixed, pushed and deployed 2026-11-09 (owner confirmed the deploy succeeded): refund now works for any order with payment_status = paid, regardless of that missing field. Awaiting the tester's live retest.

- [-] **BIZ-010** · P1 · MEDIUM · Loyalty points can be spent twice through a late mobile payment
  - **Problem:** Example: with 100 points, start a mobile checkout using them and pay. Wait for the 2-hour hold to end, spend the same points on a COD order, then submit the first TrxID. The grace accepts it. Balance becomes −100 and both orders got the discount.
  - **What to check:** Using a test account with loyalty points, start a mobile banking (bKash/Nagad) checkout and redeem points as part of the payment. Let the 2-hour payment window expire without finishing it. Place a separate COD order using those same points. Then go back and complete the first mobile payment with its real transaction ID. Check: did the points get deducted twice (balance going more negative than it should)?
  - **Result:** Skipped (tester, 2026-11-08) — needs live SMS/bKash config, can't test before the site goes live. Not a failure: the balance-never-negative half was already fixed and deployed 2026-11-05, re-verified still green. Owner then chose to also close the remaining "both orders got the discount" gap (previously accepted as a bounded risk) rather than leave it. Fixed, pushed and deployed 2026-11-09 (owner confirmed the deploy succeeded): points now stay held for up to 24 extra hours past the normal 2-hour window whenever real SMS evidence of a pending payment already exists. Awaiting the tester's live retest with real SMS config.

- [x] **BIZ-011** · P1 · MEDIUM · No bot or quantity protection on COD orders
  - **Problem:** A guest can order all stock of a product with any valid-format phone, showing it out of stock until staff cancel. No captcha or phone check exists, and no quantity maximum on the main checkout (landing pages cap at 100). Limits are 10 per 10 minutes per IP and 5 per hour per phone.
  - **What to check:** POST a guest order with quantity equal to stock.
  - **Result:** Pass (2026-11-09). Tester skipped this one ("requires backend api calls"), so I ran the exact check myself: a guest product with 50 in stock, POSTed a guest COD order for all 50. Correctly refused — "Cash on delivery allows at most 10 of '...' per phone number until earlier orders are dispatched..." — no order created, stock untouched. Already deployed (2026-11-06); no code change needed. Owner confirmed the remaining gaps (bot phone-rotation, no OTP, MariaDB untested) stay as previously declined on 2026-11-06, not revisited.

- [ ] **BIZ-013** · P1 · MEDIUM · Coupon and duplicate-order rejections are invisible at checkout
  - **Problem:** If a coupon expires before Place Order, the server rejects it but the page shows nothing: the button resets and the discount still shows.
  - **What to check:** Deactivate a coupon after applying, then place the order.
  - **Result:** Not checked

- [x] **BIZ-029** · P1 · MEDIUM · Cancelling the Reject prompt still rejects the return and emails the customer
  - **Problem:** An admin clicks Reject on a return, then presses Cancel to back out. The return is rejected anyway and the customer is emailed, with no undo.
  - **What to check:** Reject, then Cancel.
  - **Result:** Pass (2026-11-09). Tester couldn't test this — their account had no delivered order, and "Return Items" only shows for a delivered order, by design (not a bug). Re-confirmed the actual fix directly in code: `admin/returns/index.tsx`'s `handleReject()` returns early when `prompt()` returns `null` (Cancel), before any request is sent — unchanged since it was fixed and deployed 2026-11-05 (`5762cbb`, last commit to touch this file). Backend `ReturnRequestTest.php` (37/37) still confirms reject+email behaves correctly when actually invoked. No code change needed.

- [ ] **BIZ-030** · P1 · MEDIUM · COD confirmation says "Total Paid"
  - **Problem:** Every order, including unpaid COD, shows "Total Paid" with nothing saying to pay the courier. Customers may refuse payment at the door.
  - **What to check:** Place a COD order.
  - **Result:** Not checked

- [ ] **BIZ-031** · P1 · MEDIUM · Checkout shows payment logos the shop does not accept
  - **Problem:** SSLCommerz, Visa and Mastercard are shown on checkout pages under a placeholder comment, but only COD and mobile banking are accepted.
  - **What to check:** Open checkout.
  - **Result:** Not checked

- [x] **BIZ-033** · P1 · MEDIUM · Home page ratings count unmoderated reviews
  - **Problem:** One unapproved 1-star review changes the rating on the home page immediately. Rejected reviews keep counting too. Other pages filter correctly, so the same product can show two ratings.
  - **What to check:** Submit a product review as a customer. In Admin, leave it pending (don't approve or reject it yet). Check the home page's rating for that product — it should NOT include your pending review. Then reject the review in Admin and check again — a rejected review also should not count.
  - **Result:** Pass (2026-11-09). First reported Fail (2026-11-08): approving a review broke that product's detail page ("something went wrong") every time. Investigated directly — two realistic reproductions via the real routes (a plain review, and a verified-purchase review that also exercises the review-bonus-points path), both with full exception visibility enabled, found no crash in either case; checked the dev server's browser-console log for the relevant window too, no matching error. Owner/tester confirmed on retest the product page now loads fine after approval — looks like it was transient (stale dev build/HMR state), not a code defect. No code change made, since nothing reproducible was found to fix.

- [x] **BIZ-034** · P1 · MEDIUM · No return, refund or shipping policy pages; seller identity missing
  - **Problem:** Live /return-policy, /refund-policy and /shipping-policy return 404. Only privacy, terms and FAQ exist. No legal entity, trade licence or full address in code. No policy acknowledgement at checkout. Privacy text omits stored IP, browser and saved abandoned-checkout phone numbers. Owner or lawyer to compare with the Digital Commerce Operation Guidelines 2021. This is not legal advice.
  - **What to check:** GET the URLs.
  - **Result:** Pass, with an owner action item (2026-11-09). All three pages return 200 with real policy content, confirming the 2026-11-06 fix is live. Tester correctly flagged that the legal accuracy of the policy text and the privacy disclosures still needs the owner's or a lawyer's review — that was already the explicit, recorded disclaimer on this finding, not something Claude can determine. Verified `legal_business_name`/`trade_licence_number` (Admin › Contact Page) are still genuinely empty, no placeholder data anywhere — the owner still needs to fill in the real business name and trade licence number. No code change.

- [x] **BIZ-012** · P2 · MEDIUM · A whole-order refund restocks delivered goods automatically
  - **Problem:** Refunding a delivered order returns all units to stock whether or not the goods came back.
  - **What to check:** Refund a delivered verified order.
  - **Result:** Pass (2026-11-09). Tester skipped it — a genuinely delivered, courier-verified order is hard to set up manually. Reproduced it directly instead: `RefundRestockConfirmationTest` (6/6, re-run) builds a real delivered + courier-verified order and refunds it through the actual `admin.orders.markRefunded` route — without confirming the goods returned, stock stays untouched and the order history records why; with the "goods came back" checkbox ticked, stock restores normally. Already deployed 2026-11-06. Also re-ran the related return/lost-parcel suites (54 tests) to confirm no regression from today's separate BIZ-005 courier-verification change, which deliberately left shipped/delivered orders untouched. No code change.

- [x] **BIZ-014** · P2 · LOW · A retried checkout puts purchased items back in the cart
  - **Problem:** No duplicate order is made, but after a network retry the customer sees the bought items in the cart again. With a limit-1 coupon the retry returns a hidden error instead.
  - **What to check:** Submit the same order_token twice.
  - **Result:** Pass (2026-11-09). Not a Fail — the tester couldn't manually resubmit the same request (that needs devtools/scripting, not something a manual tester can easily do). Re-ran the existing fix's test (`CheckoutRetryCartTest`, 2/2): a retried submission still redirects to the same order with no cart write, for both COD and mobile banking. No code change.

- [!] **BIZ-015** · P2 · LOW · Per-customer coupon limits do not hold across guest and account
  - **Problem:** A limit-1 coupon used while logged in can be used again as a guest with the same phone. Any other valid-format phone works too. Only max_uses holds.
  - **What to check:** Use a "limit 1 use per customer" coupon while logged into a test account with a phone number. Log out, and try the same coupon as a guest checkout using that same phone number. It should be refused the second time, not accepted again.
  - **Result:** Fail → fixed (2026-11-09). The enforcement itself already worked correctly (tester confirmed: "it works as expected"). The complaint was the message: a guest hit the limit saw a deliberately vague "This coupon isn't available for this order" instead of the specific reason a logged-in customer sees, by design — SEC-012's anti-enumeration protection, so a stranger with a coupon code couldn't probe phone numbers to learn who's used it. Owner chose clearer messaging over that protection. Fixed, pushed and deployed 2026-11-09 (owner confirmed the deploy succeeded): a guest now sees the same specific "already used the maximum number of times" message. Awaiting the tester's live retest.

- [x] **BIZ-017** · P2 · LOW · Incomplete-order conversion ignores flash price and variant ownership
  - **Problem:** Conversion uses the regular price during a flash sale, can pair one product with another product's variant, and the screen hides shipping before creating the order.
  - **What to check:** Convert during a flash sale.
  - **Result:** Pass (2026-11-09). Tester's note described two different complaints (no delivery charge added; no way to add more products) — traced both through the code and the dedicated test suite (`IncompleteOrderConversionPricingTest`, 5/5): district/city are required before submission, the backend charges and the screen previews the real delivery cost, and a "Search products to add…" box already exists on the conversion screen. Couldn't reproduce either complaint; likely the tester didn't scroll to the Products card or didn't fill in district/city before judging the delivery line. No code change.

- [ ] **BIZ-024** · P2 · LOW · Referral discount can be farmed with new emails
  - **Problem:** Each new email gets the referred discount for the same phone and address. Referrer rewards need a delivered order and are capped.
  - **What to check:** Register several emails with one code.
  - **Result:** Not checked

- [ ] **BIZ-025** · P2 · LOW · Transaction IDs are not tied to the checkout time or payer
  - **Problem:** Someone who learns an unmatched TrxID, for example from a shared screenshot, can claim it for a same-amount order. Errors also reveal unmatched IDs and amounts. Brute force is impractical.
  - **What to check:** Create a same-amount order and submit the known ID.
  - **Result:** Not checked

- [ ] **BIZ-026** · P2 · LOW · Legacy ZiniPay path is still reachable and has no amount check
  - **Problem:** Routes accept any pending token including mobile ones, never compare amount, and delete the pending row on failed status. The page checkout/PaymentPending does not exist. PlaceOrderRequest no longer offers zinipay, so reach is limited.
  - **What to check:** Needs a gateway response for a live token.
  - **Result:** Not checked

- [ ] **BIZ-027** · P2 · MEDIUM · Steadfast status relies on unverified webhook assumptions with no fallback
  - **Problem:** The code admits the signature format and consignment field are unverified. There is no scheduled poll, so a mismatch means COD orders never become delivered without staff action.
  - **What to check:** Needs one real signed webhook.
  - **Result:** Not checked

- [ ] **BIZ-028** · P2 · LOW · Pathao silently falls back to sandbox
  - **Problem:** If the environment setting is empty or left at default, consignments go to the sandbox with sandbox credentials and are recorded as sent, so no pickup happens.
  - **What to check:** Save with the default.
  - **Result:** Not checked

- [ ] **BIZ-018** · P3 · LOW · Referral discount preview ignores Buy Now
  - **Problem:** With Buy Now and an empty cart the preview shows no discount, yet the server applies and uses up the one-time benefit.
  - **What to check:** Eligible referred user, empty cart, Buy Now.
  - **Result:** Not checked

- [ ] **BIZ-019** · P3 · LOW · Paying a mobile checkout empties items added later
  - **Problem:** Items added after staging are deleted when the earlier payment completes.
  - **What to check:** Stage, add an item, pay.
  - **Result:** Not checked

- [ ] **BIZ-020** · P3 · LOW · Admin price fields have no sanity limits
  - **Problem:** Price 0, flash price above regular price, bundle price 0 and percent coupons above 100 are accepted. A ৳0 total on mobile banking cannot be matched by any SMS.
  - **What to check:** Save a flash price above regular.
  - **Result:** Not checked

- [ ] **BIZ-021** · P3 · LOW · District and city are free text and set the delivery fee
  - **Problem:** A crafted request can claim an inside-Dhaka area for an address elsewhere and pay the lower rate. Limited to the rate difference.
  - **What to check:** POST state=Dhaka with a distant address.
  - **Result:** Not checked

- [ ] **BIZ-022** · P3 · LOW · Opposite-order stock locks use a single COD attempt
  - **Problem:** Two carts locking the same products in opposite order can deadlock. One customer gets a 500. It rolls back cleanly and a retry works.
  - **What to check:** Run in Phase 11 on MariaDB.
  - **Result:** Not checked

- [ ] **BIZ-023** · P3 · LOW · Duplicate-order protection has no database backstop
  - **Problem:** Protection relies on cache. Deploy clears the cache, so a retry across a deploy could make a second order on landing pages.
  - **What to check:** Retry across cache:clear.
  - **Result:** Not checked

- [ ] **BIZ-036** · P3 · LOW · Duplicate reviews and repeat review bonus
  - **Problem:** Two simultaneous submits can make two reviews. After an admin deletes an approved review, a new one can earn the bonus again.
  - **What to check:** Double submit.
  - **Result:** Not checked

## Database (DB)

- [ ] **DB-001** · P2 · LOW · Brand delete can hard-delete soft-deleted products through the cascade
  - **Problem:** The brand check ignores trashed products, and the FK cascades. Reviews, stock history and variants of those products would go. No current screen creates trashed products, but older rows may exist.
  - **What to check:** In Admin, move a test product to Trash (don't permanently delete it). Then delete that product's Brand. Check Admin's trash/deleted-products view — the trashed product should still be there, not silently gone.
  - **Result:** Not checked

- [ ] **DB-002** · P2 · MEDIUM · No retention or pruning for growing tables, including personal data
  - **Problem:** incomplete_orders (guest name and phone), received_sms (raw text), activity_log, failed_jobs, job_batches and guest carts grow forever. activity log retention is set to 365 days, but activitylog:clean never runs. If the forwarder phone relays all SMS, unrelated personal messages and OTPs are stored too (filter unverified).
  - **What to check:** grep for prune commands finds none of these.
  - **Result:** Not checked

- [!] **DB-004** · P3 · LOW · Expired rate-limit rows pile up in the cache table
  - **Problem:** Each new IP or user key leaves rows only removed if read again.
  - **What to check:** Observe cache table growth.
  - **Result:** Fail (tester, 2026-11-08) — asked for a daily/hourly schedule. That already existed (`data:prune`, nightly, deployed 2026-11-07); the tester had no way to see the scheduler or the table to know. Investigating it found a real improvement though: cache rows expire within minutes, so bundling cleanup into the once-a-day run let the table still grow during the day. Fixed locally 2026-11-09 (not yet pushed/deployed): cache cleanup split into its own hourly command. Needs push + deploy, then confirm the schedule actually fires in production.

## Frontend (FE)

- [ ] **FE-005** · P1 · MEDIUM · A server error empties a logged-in customer's cart
  - **Problem:** The 5xx page sends no shared props, so the client treats it as logout, clears the cart, and syncs an empty list to the server.
  - **What to check:** Log in with an item in the cart, then click a page that returns a 500 with APP_DEBUG off.
  - **Result:** Not checked

- [ ] **FE-006** · P1 · MEDIUM · Product pages have no search or share tags in the HTML
  - **Problem:** A live product page returns title "ShoppyDrive", no h1, an empty app div, and no og tags, canonical or JSON-LD. Facebook previews show no product. SSR is never built by deploy, so the SSR notes in CLAUDE.md do not apply in production.
  - **What to check:** curl a product page.
  - **Result:** Not checked

- [ ] **FE-001** · P2 · MEDIUM · Customers cannot page through order history
  - **Problem:** With more than 10 orders, older ones are unreachable from the list. The pagination area is an empty placeholder.
  - **What to check:** Account with 11 orders.
  - **Result:** Not checked

- [!] **FE-002** · P2 · LOW · Cart drawer and checkout estimates disagree with the server
  - **Problem:** The drawer ignores the free-delivery threshold, bundles and selected-item coupons. Checkout preview has no bundle free-delivery branch. HandleInertiaRequests holds another copy of the shipping rule. Final charges are correct.
  - **What to check:** Cart above the threshold.
  - **Result:** Fail (tester, 2026-11-08) — checkout's shipping line showed "Enter address" instead of "Free" for a cart already past the free-delivery threshold, as long as district/city hadn't been picked yet. `Checkout.tsx` checked for a missing address before checking free delivery, even though free delivery never depends on the address; `CartDrawer.tsx` already had the order right. Fixed locally 2026-11-09 (not yet pushed/deployed): free delivery is now checked first. No frontend test runner in this project and no browser tool available in this session to verify live — checked by `tsc`/ESLint/Prettier and code reasoning only, matching the original fix's own unresolved "not exercised in the browser" gap. Needs push + deploy, then a live retest.

- [ ] **FE-007** · P2 · MEDIUM · Session timeout on landing-page checkout loses the form and may 405
  - **Problem:** After 2 hours the 419 page replaces the form, and its Refresh reloads a POST-only URL. Main checkout also loses the address.
  - **What to check:** Leave open, submit, click Refresh.
  - **Result:** Not checked

- [ ] **FE-009** · P2 · LOW · No noindex or canonical; meta helper defects
  - **Problem:** /cart, /checkout, /admin/login and search URLs send no noindex (live headers confirm no X-Robots-Tag). Titles double up, the default og:image file does not exist, and category SEO fields are unused.
  - **What to check:** curl -I the paths.
  - **Result:** Not checked

- [ ] **FE-010** · P2 · LOW · No React error boundary
  - **Problem:** Any render exception gives a blank white page, including at checkout.
  - **What to check:** grep ErrorBoundary: none.
  - **Result:** Not checked

- [ ] **FE-011** · P2 · LOW · Failed cart sync is silent and the item later vanishes
  - **Problem:** On flaky mobile data, the customer sees success, the sync fails, and the next page restores the server cart without the item.
  - **What to check:** Add while offline.
  - **Result:** Not checked

- [ ] **FE-012** · P2 · LOW · Phone rules differ, and Bangla digits are dropped at checkout
  - **Problem:** A Bangla keyboard enters nothing, and pasted +8801712345678 becomes 88017123456. Register, profile and address accept free text, so a saved number can fail checkout.
  - **What to check:** Type Bangla digits.
  - **Result:** Not checked

- [ ] **FE-003** · P3 · LOW · Review "Helpful" buttons do nothing
  - **Problem:** Clicking has no effect, and the label renders "Helpful ()". No route or model exists.
  - **What to check:** Click Helpful.
  - **Result:** Not checked

- [ ] **FE-004** · P3 · LOW · Lost-parcel action has no screen
  - **Problem:** The endpoint exists but nothing calls it; the tracker already records this.
  - **What to check:** Search resources/js.
  - **Result:** Not checked

- [ ] **FE-013** · P3 · LOW · Cookie banner offers choices that control nothing
  - **Problem:** Analytics functions have no callers and no tracking script exists, so the toggles do nothing. CLAUDE.md describes GA that is not in the code. Tracking cookies are set regardless, and Google Fonts loads before consent. The owner also has no conversion analytics.
  - **What to check:** grep callers.
  - **Result:** Not checked

- [ ] **FE-014** · P3 · LOW · Inconsistent money format and small accessibility gaps
  - **Problem:** Landing pages show ৳1,200 where the shop shows ৳1,200.00. Landing inputs have no labels. Checkout has nested main elements and two stacked footers.
  - **What to check:** Open pages.
  - **Result:** Not checked

## Infrastructure (INFRA)

- [ ] **INFRA-001** · P1 · MEDIUM · Deploy has no maintenance mode and is not atomic
  - **Problem:** rsync writes in place, then migrate runs, so new code runs before its schema. public/storage is removed by --delete until recreated, so images break briefly. Old chunks are deleted and there is no vite:preloadError handler.
  - **What to check:** Deploy with a schema change during checkout.
  - **Result:** Not checked

- [ ] **INFRA-004** · P2 · LOW · Workflow hardening gaps
  - **Problem:** No permissions key, third-party actions pinned by tag including the one holding the deploy key, and secrets interpolated into shell text.
  - **What to check:** DEVELOPER CHECK ONLY — this is about the GitHub Actions workflow files, not something visible on the live site. Ask Claude to review `.github/workflows/*.yml` directly.
  - **Result:** Not checked

- [ ] **INFRA-005** · P2 · LOW · Deploy env check is thin and .env.example is incomplete
  - **Problem:** Only 5 keys are checked. A missing MAIL_MAILER defaults to log, so verification codes go to a log with a green deploy. .env.example lacks SMS_FORWARDER_TOKEN, GOOGLE, FACEBOOK and ZINIPAY names.
  - **What to check:** Rebuild .env from the example.
  - **Result:** Not checked

- [ ] **INFRA-007** · P3 · INFO · Leftover packages and public assets
  - **Problem:** spatie/laravel-responsecache ^0.0.1 is an empty skeleton. public/vendor/moonshine has assets for a removed package. laravel/tinker is in require.
  - **What to check:** Visit https://shoppydrive.com/vendor/moonshine/ in your browser. It should show a 404 or 403 (not found), confirming leftover files from a removed package aren't still being served.
  - **Result:** Not checked

## Operations (OPS)

- [ ] **OPS-002** · P1 · MEDIUM · If cron or the queue stops, nobody is told and customer mail stops
  - **Problem:** A stopped queue creates no failed rows, so the dashboard stays at 0. Order mail and Pathao updates stop while the site looks healthy.
  - **What to check:** Disable cron.
  - **Result:** Not checked

- [ ] **OPS-003** · P1 · MEDIUM · A short mail outage fails queued mail, and recovery is one click per row
  - **Problem:** Three tries with 30s backoff are used up in a few minutes (estimate), and staff must retry each mail. mail.timeout is null, so a hung SMTP uses up the job time.
  - **What to check:** Refuse SMTP.
  - **Result:** Not checked

- [ ] **OPS-005** · P1 · MEDIUM · A dead forwarder phone disables mobile payment with a misleading message
  - **Problem:** If the phone is off, customers who paid are told their ID was not found, and no one is alerted. The sidebar badge stays at 0 because nothing arrives. Single point of failure.
  - **What to check:** Turn the phone off.
  - **Result:** Not checked

- [ ] **OPS-006** · P1 · MEDIUM · Abandoned-cart mail has no opt-out or cap and reaches deleted accounts
  - **Problem:** Three checkout sessions make three mails. After a cron outage every old row is mailed at once. A customer who deletes their account is still mailed. There is no unsubscribe link.
  - **What to check:** See the example.
  - **Result:** Not checked

- [ ] **OPS-001** · P2 · LOW · sitemap.xml is missing on the live site
  - **Problem:** GET /sitemap.xml returns 404, and sitemap:generate is not scheduled. robots.txt has no sitemap line.
  - **What to check:** GET /sitemap.xml
  - **Result:** Not checked

- [ ] **OPS-007** · P2 · LOW · Logging defaults to one unrotated file at debug level
  - **Problem:** If production kept the defaults, the log grows forever on a fixed quota, threatening sessions and uploads.
  - **What to check:** Check LOG_STACK and LOG_LEVEL.
  - **Result:** Not checked

## Performance (PERF)

- [ ] **PERF-001** · P1 · MEDIUM · Product listing runs a query for every variant
  - **Problem:** 12 products with 6 variants each took 101 queries on /products. A 30-variant product page costs about 90 extra, and each variant adds flash-sale lookups.
  - **What to check:** Open the Products page (/products) where some products have many color/size options. It should feel snappy to load, not noticeably slow compared to other pages.
  - **Result:** Not checked

- [ ] **PERF-002** · P2 · LOW · Product cards send the whole product record
  - **Problem:** Live home HTML is 145 KB (21 KB compressed) and a product page 82 KB (18 KB compressed), with first byte around 0.42s, so this is modest today. Cards need about 10 fields but get all columns plus relations. Ziggy and its script add about 52 KB (estimate). cost_price is correctly hidden, so this is size only.
  - **What to check:** curl the home page.
  - **Result:** Not checked

- [ ] **PERF-003** · P2 · MEDIUM · Icon registry and admin layout load on the customer home page
  - **Problem:** The home page chain is 75 JS chunks, about 367 KB gzip. Admin layouts are in the main chunk. Lucide dynamic icons account for 1,414 of the 1,723 entries in the 653 KB public manifest.
  - **What to check:** Open the home page in an incognito/private browser window, open DevTools (F12) → Network tab, and reload. Look through the list of loaded files — you should NOT see anything referencing admin icons or an admin icon registry loading on the public home page.
  - **Result:** Not checked

- [ ] **PERF-006** · P2 · LOW · Admin SMS poll hits the app every 5 seconds per tab
  - **Problem:** Three tabs make 2,160 requests an hour even when hidden.
  - **What to check:** Open tabs.
  - **Result:** Not checked

- [ ] **PERF-005** · P3 · LOW · Admin sidebar count scans the orders table
  - **Problem:** is_seen has no index, and the count runs on every admin request. The reviewer also suspected whereDate, but MariaDB 11.8 uses the created_at index for it, so that part was dropped as a false positive.
  - **What to check:** EXPLAIN.
  - **Result:** Not checked

- [ ] **PERF-007** · P3 · LOW · Search scans the table despite the FULLTEXT index
  - **Problem:** MATCH alone uses the index, but adding OR sku LIKE gives a full scan.
  - **What to check:** EXPLAIN.
  - **Result:** Not checked

- [ ] **PERF-008** · P3 · LOW · Orphaned search cache files until the next deploy
  - **Problem:** Each distinct matched query leaves a file. The version-stamp flush leaves old files. This narrows the claim made by my own search fix that junk cannot fill the disk.
  - **What to check:** Search many terms.
  - **Result:** Not checked

## Reliability and tests (REL)

- [ ] **REL-001** · P1 · MEDIUM · Tests pass payloads the real website never sends
  - **Problem:** BIZ-001 and BIZ-002 survived a 2,026-test green suite because tests post bundle_id or omit items, unlike Checkout.tsx. There are no browser tests in the repo, and the suite cannot see frontend-backend mismatches such as BIZ-003.
  - **What to check:** Compare with Checkout.tsx:670-674.
  - **Result:** Not checked

- [ ] **REL-003** · P3 · LOW · Four race tests silently skip on anything but port 3306, and show as passed
  - **Problem:** On MariaDB port 3399 the suite said "passed" while 30 tests skipped (coupon, courier, loyalty, return), and two of those files took minutes doing so. Copies with only the port changed then ran: Coupon 1/1, Courier 15/15, Loyalty 12/12, Return 1/2 (see REL-002).
  - **What to check:** Run with DB_PORT other than 3306.
  - **Result:** Not checked

- [ ] **REL-004** · P3 · LOW · Tests leave real files in storage
  - **Problem:** Each full run leaves about 14 files in storage/app/public/products, and only 10 test files use Http::fake with no preventStray.
  - **What to check:** Run the suite twice and count.
  - **Result:** Not checked

## Security (SEC)

- [ ] **SEC-002** · P1 · HIGH · Any admin with one settings page can read every stored secret
  - **Problem:** All six settings pages send the whole settings table to the browser. Example: a content editor allowed only the About page receives the Pathao password, Steadfast keys, webhook secret and OAuth client secrets in the page data.
  - **What to check:** Grant an admin only about_page view, open /admin/settings/about-page, read props.settings.
  - **Result:** Not checked

- [ ] **SEC-003** · P1 · HIGH · A verification code sent to one email can verify a different email
  - **Problem:** Changing the email does not cancel the pending code. Example: an attacker registers with their own email, gets a code, changes the account email to victim@gmail.com, and enters the code. The account now counts as verified for the victim's address. When the victim later uses "Continue with Google", they land in the attacker's account, where the attacker still holds the password and can read whatever the victim enters.
  - **What to check:** Register, change email through PATCH settings/profile, submit the code from the first email.
  - **Result:** Not checked

- [ ] **SEC-004** · P1 · MEDIUM · Customers' Google/Facebook tokens are sent to browsers
  - **Problem:** The social account model has no hidden fields, so live access and refresh tokens appear in the admin customer page data and in the customer's own connections page data. Nothing in the app ever reads them back.
  - **What to check:** Open /admin/customers/{id} for a social-login customer and read props.customer.social_accounts.
  - **Result:** Not checked

- [ ] **SEC-005** · P1 · MEDIUM · Changing an admin password does not log out other sessions
  - **Problem:** If an admin password is stolen and then changed, the thief's open session keeps working, including with remember-me.
  - **What to check:** Log in on two browsers, change the password on one, keep using the other.
  - **Result:** Not checked

- [ ] **SEC-006** · P1 · MEDIUM · Order tracking returns the whole order record
  - **Problem:** Anyone with the order number and phone (both on the parcel label) receives the buyer's IP address, browser details, email, full address, staff notes and internal history notes, which can name staff.
  - **What to check:** GET /order/track?order_number=...&phone=... and read props.order.
  - **Result:** Not checked

- [ ] **SEC-007** · P1 · MEDIUM · View-only SMS forwarder permission reveals the full token
  - **Problem:** An admin who may only view this page receives the full forwarder token, which allows posting SMS to the payment matcher, so it combines with SEC-001.
  - **What to check:** Open the page with sms_forwarder view only.
  - **Result:** Not checked

- [ ] **SEC-010** · P1 · MEDIUM · Weaker password policy and no per-account login limit for admins
  - **Problem:** Admins may use 8-character passwords, while customers in production need stronger ones. Login limits key on email+IP and IP, so a slow attack from many IPs on one admin is not limited. Failed admin logins are not logged. Self-edit needs no current password.
  - **What to check:** In Admin, try creating or editing an admin account with a short password (e.g. 6-7 characters). It should be refused with a clear error, the same way customer registration refuses a weak password.
  - **Result:** Not checked

- [ ] **SEC-017** · P1 · MEDIUM · composer audit lists new Symfony advisories
  - **Problem:** Advisories dated 2026-11-04 affect symfony/http-foundation, http-kernel, routing, serializer and yaml at 7.4.21. Several need components this app may not use, such as access_control rules or trusted proxy shortcuts. nunomaduro/pao is also abandoned.
  - **What to check:** composer audit
  - **Result:** Not checked

- [ ] **SEC-021** · P1 · MEDIUM · Back button after logout shows account data
  - **Problem:** Inertia keeps page data in browser history. On a shared PC, the next person pressing Back sees the previous customer's orders with no server request.
  - **What to check:** View orders, log out, press Back.
  - **Result:** Not checked

- [ ] **SEC-022** · P1 · LOW · Internal audit documents and agent config are deployed to the server
  - **Problem:** docs/, tests/, .claude and similar folders are not excluded from rsync. They include past audit reports, the tracker, and the SSH host and path in the handoff. Warning: committing docs/forensic-audit would ship this list of unfixed problems on the next deploy.
  - **What to check:** Visit https://shoppydrive.com/CLAUDE.md and https://shoppydrive.com/docs/ in your browser. Both should show "not found" — confirming internal project documents aren't being served publicly.
  - **Result:** Not checked

- [ ] **SEC-008** · P2 · MEDIUM · A social link planted on an unverified account can survive the real owner's recovery
  - **Problem:** An unverified account can be created with a social link. The real owner resets the password and verifies, but the link remains and can still log in.
  - **What to check:** Requires a provider returning an unconfirmed email claim. Not reproduced.
  - **Result:** Not checked

- [ ] **SEC-009** · P2 · LOW · Customer email can be changed without the password and without notifying the old address
  - **Problem:** A hijacked session can change the email and then reset the password, making the takeover permanent.
  - **What to check:** PATCH settings/profile with a new email.
  - **Result:** Not checked

- [ ] **SEC-011** · P2 · LOW · Password reset link is built from the request Host
  - **Problem:** The app itself would email a reset link pointing at a host chosen by the requester. On the live site, requests with an unknown Host were dropped by the CDN (connection reset), so this is defence in depth unless the origin can be reached directly.
  - **What to check:** POST /forgot-password with Host: evil.example.
  - **Result:** Not checked

- [ ] **SEC-012** · P2 · LOW · Coupon check reveals coupon details and whether a phone has used it
  - **Problem:** Anonymous /coupon/validate returns the whole coupon record (usage count, limits, dates) and different messages for disabled, future and expired codes. With a known limited code, guests can also test whether a phone number has used it.
  - **What to check:** POST /coupon/validate.
  - **Result:** Not checked

- [ ] **SEC-013** · P2 · LOW · No HSTS or CSP on the live site; API answers over plain HTTP
  - **Problem:** Live headers contain no Strict-Transport-Security, Content-Security-Policy or Permissions-Policy. POST http://shoppydrive.com/api/sms/receive answered 401 directly instead of redirecting, so a misconfigured forwarder would send its token unencrypted.
  - **What to check:** curl -I https://shoppydrive.com/
  - **Result:** Not checked

- [ ] **SEC-014** · P2 · LOW · Customer 2FA can be enrolled but is never asked for at login
  - **Problem:** Settings tells customers 2FA is enabled, but login never challenges them, so they have false assurance.
  - **What to check:** Enable 2FA, log out, log in: no challenge.
  - **Result:** Not checked

- [ ] **SEC-023** · P2 · LOW · Personal data can reach logs
  - **Problem:** Webhook payloads, courier error bodies and SMS senders are logged. In the mobile payment path catch(RuntimeException) can also catch database errors, whose message includes SQL bindings such as the address.
  - **What to check:** Any webhook.
  - **Result:** Not checked

- [ ] **SEC-015** · P3 · LOW · Register, login and forgot-password reveal whether an email exists
  - **Problem:** Messages confirm an email is registered and which provider it uses.
  - **What to check:** Submit a known email.
  - **Result:** Not checked

- [ ] **SEC-016** · P3 · INFO · Every page publishes all admin route names to guests
  - **Problem:** The live home page contains 203 admin.* route names. Access is still enforced server-side; this only maps the admin area.
  - **What to check:** View source on the home page.
  - **Result:** Not checked

- [ ] **SEC-018** · P3 · LOW · Some admin uploads have no size cap
  - **Problem:** Category, sub-category, bundle and banner images check type but set no maximum size. Limited only by the PHP upload limit, which could not be checked.
  - **What to check:** Upload a large image.
  - **Result:** Not checked

- [x] **SEC-024** · P3 · LOW · Token regeneration rewrites .env from a web request
  - **Problem:** Regenerating writes .env without a lock, temp file or audit entry and then caches config. A full disk could truncate .env and take the site down.
  - **What to check:** Regenerate with a full disk.
  - **Result:** Pass (2026-11-09). Tester couldn't fill a production disk to test this directly, which is the right call — but it's moot anyway. SEC-020's 2026-11-07 follow-up moved the SMS forwarder token out of `.env` into the database; regenerating it today only updates a `Setting` row. No file write happens at all, so a full disk can't corrupt `.env` through this action. Confirmed by reading `App\Support\SmsForwarderToken` and `SmsForwarderSettingsController::regenerate()` directly.
