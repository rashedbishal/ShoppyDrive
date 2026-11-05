# Business Logic and Payments Report

Audited commit `f49bed3`, 2026-11-05. Audit only: nothing was fixed, committed or deployed. Full entries: `COMPLETE_FINDINGS_REGISTER.md`.

**Golden rule result:** no client-sent price, total, discount or shipping fee is trusted (C01). No Critical arises from that rule. The one exception to client trust is district and city, which pick the delivery rate (BIZ-021). The money problems are the forged SMS (SEC-001) and display mismatches (BIZ-001, BIZ-003, BIZ-016).

## Status of earlier findings

| Earlier ID | Status from this audit |
|---|---|
| FSA-02 | Not fixed: mechanism exists and is off by default (SEC-001) |
| FSA-03 | Fixed for confirmation, sibling open (SEC-006) |
| FSA-06 | Partial (BIZ-005) |
| FSA-07 | Partial, with a regression (BIZ-006, BIZ-010) |
| FSA-08 | Partial: admin save blocked, adding variants later is not (BIZ-006) |
| FSA-10 | Fixed in code |
| FSA-11, FSA-15 | Partial (BIZ-007) |
| LP-INVOICE-STATUS-001 | Fixed in code |

| ID | Severity | Priority | Title |
|---|---|---|---|
| BIZ-001 | HIGH | P0 | COD can charge more than the price the customer saw |
| BIZ-002 | HIGH | P0 | A cart containing a complete bundle can never be checked out from the website |
| BIZ-003 | HIGH | P0 | Guests see a coupon discount at checkout but are charged full price |
| BIZ-004 | HIGH | P0 | Converting an incomplete order ignores stock, and cancelling it creates stock |
| BIZ-005 | HIGH | P0 | Paid orders can still be cancelled and then cannot be refunded |
| BIZ-006 | HIGH | P0 | Customers can pay for out-of-stock items and receive no order |
| BIZ-007 | MEDIUM | P1 | Admin can cancel orders already with Steadfast; Steadfast orders never reach Shipped |
| BIZ-008 | MEDIUM | P1 | Refunded orders stay "paid" and keep counting as revenue |
| BIZ-009 | MEDIUM | P2 | Partial delivery is booked as fully delivered and paid, with no way out |
| BIZ-010 | MEDIUM | P1 | Loyalty points can be spent twice through a late mobile payment |
| BIZ-011 | MEDIUM | P1 | No bot or quantity protection on COD orders |
| BIZ-012 | MEDIUM | P2 | A whole-order refund restocks delivered goods automatically |
| BIZ-013 | MEDIUM | P1 | Coupon and duplicate-order rejections are invisible at checkout |
| BIZ-016 | MEDIUM | P1 | Landing page shows different price and delivery than it charges |
| BIZ-027 | MEDIUM | P2 | Steadfast status relies on unverified webhook assumptions with no fallback |
| BIZ-029 | MEDIUM | P1 | Cancelling the Reject prompt still rejects the return and emails the customer |
| BIZ-030 | MEDIUM | P1 | COD confirmation says "Total Paid" |
| BIZ-031 | MEDIUM | P1 | Checkout shows payment logos the shop does not accept |
| BIZ-032 | MEDIUM | P1 | Live terms and FAQ contradict how the shop works |
| BIZ-033 | MEDIUM | P1 | Home page ratings count unmoderated reviews |
| BIZ-034 | MEDIUM | P1 | No return, refund or shipping policy pages; seller identity missing |
| BIZ-014 | LOW | P2 | A retried checkout puts purchased items back in the cart |
| BIZ-015 | LOW | P2 | Per-customer coupon limits do not hold across guest and account |
| BIZ-017 | LOW | P2 | Incomplete-order conversion ignores flash price and variant ownership |
| BIZ-018 | LOW | P3 | Referral discount preview ignores Buy Now |
| BIZ-019 | LOW | P3 | Paying a mobile checkout empties items added later |
| BIZ-020 | LOW | P3 | Admin price fields have no sanity limits |
| BIZ-021 | LOW | P3 | District and city are free text and set the delivery fee |
| BIZ-022 | LOW | P3 | Opposite-order stock locks use a single COD attempt |
| BIZ-023 | LOW | P3 | Duplicate-order protection has no database backstop |
| BIZ-024 | LOW | P2 | Referral discount can be farmed with new emails |
| BIZ-025 | LOW | P2 | Transaction IDs are not tied to the checkout time or payer |
| BIZ-026 | LOW | P2 | Legacy ZiniPay path is still reachable and has no amount check |
| BIZ-028 | LOW | P2 | Pathao silently falls back to sandbox |
| BIZ-035 | LOW | P3 | Bundle prices can miss the advertised total by a few poisha |
| BIZ-036 | LOW | P3 | Duplicate reviews and repeat review bonus |
| BIZ-037 | LOW | P3 | Free delivery ৳999 offer is hardcoded in the footer and login page |

## Details

### BIZ-001: COD can charge more than the price the customer saw

Example: a product is ৳100 in the cart, the admin changes it to ৳150, and the page still shows ৳100. The customer places a COD order and is charged ৳150 with no warning.

- **Severity / confidence:** HIGH / High
- **Files:** resources/js/pages/checkout/Checkout.tsx:670-674; app/Http/Controllers/CheckoutController.php:157-163,188; app/Support/CartManager.php:325,335,477-496; tests/Feature/FlashSalePricingTest.php:148-150
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A02 FAILS: order created.
- **Recommendation (not applied):** Send the displayed line prices or total and compare before syncing.

### BIZ-002: A cart containing a complete bundle can never be checked out from the website

Every Place Order click shows "Some prices have changed" and no order is made. This affects COD and mobile banking, guests and members.

- **Severity / confidence:** HIGH / High. Whether production has active bundles is unknown
- **Files:** resources/js/pages/checkout/Checkout.tsx:670-674; app/Support/CartManager.php:327-345,480; app/Services/BundleService.php; app/Http/Controllers/CheckoutController.php:188-190; tests/Feature/BundleAutoAssembleTest.php:176
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A03 FAILS: 0 orders after 3 tries.
- **Recommendation (not applied):** Send bundle_id and bundle_unit_quantity, and teach the checkout preview about free-delivery bundles.

### BIZ-003: Guests see a coupon discount at checkout but are charged full price

Example: a guest applies SAVE100 on the Cart page on a ৳1,000 cart. Checkout shows Discount −৳100 and Total ৳900, but the order is sent without any coupon code, so the server charges ৳1,000. Logged-in customers: the coupon silently disappears. Applying it again at checkout subtracts it twice on screen.

- **Severity / confidence:** HIGH / High. Display proven in a browser. The charge comes from code reading: the browser order could not be submitted through the District dropdown by my script
- **Files:** resources/js/pages/cart/Cart.tsx:70; resources/js/pages/checkout/Checkout.tsx:354,448,667,727-728; resources/js/stores/cartStore.ts:393-395; resources/js/app.tsx:270-305
- **Evidence:** docs/forensic-audit/evidence/phase2/guest_coupon_browser.txt shows Total ৳900 at checkout. Checkout.tsx:667 sends only appliedCoupon, which starts null.
- **Recommendation (not applied):** Use one coupon state, seed checkout from it and submit it, and remove the extra subtraction.

### BIZ-004: Converting an incomplete order ignores stock, and cancelling it creates stock

Example: stock is 5. An admin converts an incomplete order for 10: the order is created and stock stays 5. For a 2-unit order, cancelling then raises stock from 5 to 7.

- **Severity / confidence:** HIGH / High
- **Files:** app/Http/Controllers/Admin/IncompleteOrderController.php:172-194; app/Models/Order.php:264-267; app/Services/InventoryService.php:133-186; app/Http/Requests/ConvertIncompleteOrderRequest.php
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A04a and A04b FAIL.
- **Recommendation (not applied):** Decrement inside the transaction, catch insufficient stock, lock the row, validate the variant owner.

### BIZ-005: Paid orders can still be cancelled and then cannot be refunded

FSA-06 fixed only admin cancel. A customer can cancel a paid pending order, for example a manual mobile-banking order. A courier return report also cancels a paid order. In both cases the order is cancelled but still paid, and the refund screen refuses because "A cancelled order cannot be refunded".

- **Severity / confidence:** HIGH / High
- **Files:** routes/account.php:124-158; app/Services/CourierOrderStatusMapper.php:39-48,66,199-213; app/Services/RefundService.php:142-144; app/Http/Controllers/Admin/OrderController.php:170-171
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A05 FAILS.
- **Recommendation (not applied):** Refuse customer cancel of paid orders and allow a ledger refund for cancelled and paid orders.

### BIZ-006: Customers can pay for out-of-stock items and receive no order

Example: stock is 0, the customer picks bKash and sees the payment page, pays, submits the TrxID, and gets "insufficient stock". Money is taken, no order exists. Landing pages also lack the late-submission grace and the variant guard, and only an unmatched-SMS count in the sidebar signals the problem.

- **Severity / confidence:** HIGH / High
- **Files:** app/Http/Controllers/CheckoutController.php:394-407,458-527; app/Http/Controllers/MobilePaymentController.php:218,287-293; app/Http/Controllers/LandingPageController.php:293-314,372,392,460; app/Http/Controllers/Admin/ReceivedSmsController.php:46-48
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A10 FAILS: pending checkout created. Landing page grace gap from code reading.
- **Recommendation (not applied):** Check stock before showing payment instructions, share the grace logic, add a pending-checkout to SMS reconciliation screen.

### BIZ-007: Admin can cancel orders already with Steadfast; Steadfast orders never reach Shipped

Example: a COD order is sent to Steadfast and the admin cancels it, restoring stock. The courier delivers and collects cash, but the order stays cancelled because it is terminal. Shipped emails can never fire for Steadfast.

- **Severity / confidence:** MEDIUM / High
- **Files:** app/Http/Controllers/Admin/OrderController.php:486-509; app/Services/CourierOrderStatusMapper.php:57-67,201; app/Services/CourierSubmissionService.php:215-217; app/Models/Order.php:271
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A06 FAILS.
- **Recommendation (not applied):** Add the consignment checks to admin cancel and record a real shipped state.

### BIZ-008: Refunded orders stay "paid" and keep counting as revenue

Example: a ৳1,060 paid order with ৳60 shipping is fully refunded. The refund cap excludes shipping, so the total refunded never reaches collected. Payment stays paid and the dashboard still counts ৳1,060. The dashboard comment claims the opposite. Partial refunds are not subtracted either.

- **Severity / confidence:** MEDIUM / High
- **Files:** app/Services/RefundService.php:173; app/Services/RefundCalculator.php:137; app/Http/Controllers/Admin/DashboardController.php:125-136; tests/Feature/OrderRefundTest.php:173
- **Evidence:** Code reading plus the existing test that asserts PAID stays.
- **Recommendation (not applied):** Exclude refunded orders and subtract ledger refunds.

### BIZ-009: Partial delivery is booked as fully delivered and paid, with no way out

A courier partial delivery sets the order delivered and paid for the full total. Undelivered stock is not restored and refunds are refused permanently.

- **Severity / confidence:** MEDIUM / High (code reading)
- **Files:** app/Services/CourierOrderStatusMapper.php:37,64,209-212; app/Services/RefundCalculator.php:51; app/Services/OrderDeliveryVerificationService.php:72-74; app/Services/CourierEvidenceChain.php:75-77
- **Evidence:** Code reading.
- **Recommendation (not applied):** Add a partial state with collected amount and a mother-admin resolution.

### BIZ-010: Loyalty points can be spent twice through a late mobile payment

Example: with 100 points, start a mobile checkout using them and pay. Wait for the 2-hour hold to end, spend the same points on a COD order, then submit the first TrxID. The grace accepts it. Balance becomes −100 and both orders got the discount.

- **Severity / confidence:** MEDIUM / High
- **Files:** app/Http/Controllers/MobilePaymentController.php:101,121-133,207-208; app/Models/PendingCheckout.php:56; app/Services/LoyaltyPointsService.php:134
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A17 FAILS: balance −100.
- **Recommendation (not applied):** Keep the hold alive when proof exists, or route these to admin review.

### BIZ-011: No bot or quantity protection on COD orders

A guest can order all stock of a product with any valid-format phone, showing it out of stock until staff cancel. No captcha or phone check exists, and no quantity maximum on the main checkout (landing pages cap at 100). Limits are 10 per 10 minutes per IP and 5 per hour per phone.

- **Severity / confidence:** MEDIUM / High
- **Files:** app/Http/Requests/PlaceOrderRequest.php:58; app/Http/Requests/SyncCartRequest.php:45; app/Http/Requests/AddToCartRequest.php:29; app/Http/Controllers/LandingPageController.php:123; app/Providers/AppServiceProvider.php:192-207
- **Evidence:** grep for captcha, turnstile and honeypot in app, resources/js, routes, config and composer.json: no results. docs/forensic-audit/tests/BusinessLogicAuditTest.php A16 FAILS on MariaDB: quantity 3000000000 gives a 500. It passes on SQLite, which hides this.
- **Recommendation (not applied):** Per-order limit, per-product pending-COD cap, bot challenge, phone OTP above a threshold.

### BIZ-012: A whole-order refund restocks delivered goods automatically

Refunding a delivered order returns all units to stock whether or not the goods came back.

- **Severity / confidence:** MEDIUM / High on behaviour. Business intent needs confirming
- **Files:** app/Models/Order.php:264-267; app/Services/InventoryService.php:133-186
- **Evidence:** Code reading.
- **Recommendation (not applied):** Ask "goods returned?" on whole-order refunds of delivered orders.

### BIZ-013: Coupon and duplicate-order rejections are invisible at checkout

If a coupon expires before Place Order, the server rejects it but the page shows nothing: the button resets and the discount still shows.

- **Severity / confidence:** MEDIUM / High (code reading)
- **Files:** app/Http/Controllers/CheckoutController.php:200,238,374; resources/js/pages/checkout/Checkout.tsx:677-680
- **Evidence:** grep of Checkout.tsx finds no render of these keys. Browser confirmation planned for Phase 14.
- **Recommendation (not applied):** Render these errors and clear the coupon on rejection.

### BIZ-016: Landing page shows different price and delivery than it charges

Example: during a flash sale the page shows the regular price but charges the sale price. A product with free delivery from 3 units says "free" for 1 unit but charges shipping. Delivery text is hardcoded as ৳60 and ৳120 regardless of admin rates.

- **Severity / confidence:** MEDIUM / High (code reading)
- **Files:** resources/js/pages/landing/LandingPage.tsx:123-139,334-335,389-391,949-951; app/Http/Controllers/LandingPageController.php:207-213,566
- **Evidence:** Code reading. Browser confirmation planned.
- **Recommendation (not applied):** Send the resolved price and shipping rules from the server.

### BIZ-027: Steadfast status relies on unverified webhook assumptions with no fallback

The code admits the signature format and consignment field are unverified. There is no scheduled poll, so a mismatch means COD orders never become delivered without staff action.

- **Severity / confidence:** MEDIUM / Low. Assumptions unverified
- **Files:** app/Http/Controllers/SteadfastWebhookController.php:65,122-130; app/Services/CourierOrderStatusMapper.php:52-53; routes/console.php
- **Evidence:** BLOCKED: real courier calls are forbidden by the directive.
- **Recommendation (not applied):** Verify with one real webhook and add a scheduled poll.

### BIZ-029: Cancelling the Reject prompt still rejects the return and emails the customer

An admin clicks Reject on a return, then presses Cancel to back out. The return is rejected anyway and the customer is emailed, with no undo.

- **Severity / confidence:** MEDIUM / High (code re-read)
- **Files:** resources/js/pages/admin/returns/index.tsx:72-78; app/Http/Controllers/Admin/ReturnController.php:131-145
- **Evidence:** Code re-read by lead auditor. Browser check planned.
- **Recommendation (not applied):** Return early on null.

### BIZ-030: COD confirmation says "Total Paid"

Every order, including unpaid COD, shows "Total Paid" with nothing saying to pay the courier. Customers may refuse payment at the door.

- **Severity / confidence:** MEDIUM / High (code re-read)
- **Files:** resources/js/pages/order/OrderConfirmation.tsx:154
- **Evidence:** Code re-read.
- **Recommendation (not applied):** Label by payment status and state the amount due.

### BIZ-031: Checkout shows payment logos the shop does not accept

SSLCommerz, Visa and Mastercard are shown on checkout pages under a placeholder comment, but only COD and mobile banking are accepted.

- **Severity / confidence:** MEDIUM / High (code re-read)
- **Files:** resources/js/components/layout/CheckoutLayout.tsx:24-40; app/Http/Requests/PlaceOrderRequest.php
- **Evidence:** Code re-read.
- **Recommendation (not applied):** Show only enabled methods.

### BIZ-032: Live terms and FAQ contradict how the shop works

Live terms say payment must be completed before dispatch, though COD is the default. Live FAQ says online payment is coming soon (bKash and Nagad work), offers cancellation within 1 hour (code has no such rule), and promises email or SMS confirmation (no customer SMS sending exists). Seed text also promises an immediate full refund, while shipping is never refundable.

- **Severity / confidence:** MEDIUM / High. Phrases confirmed on the live pages. Legal effect is for the owner or a lawyer
- **Files:** database/seeders/PageSeeder.php:109,140,143,146,151,168,176,179; resources/js/components/layout/Footer.tsx:30,267
- **Evidence:** Live GETs on 2026-11-05 contain the phrases.
- **Recommendation (not applied):** Rewrite the pages to match actual behaviour.

### BIZ-033: Home page ratings count unmoderated reviews

One unapproved 1-star review changes the rating on the home page immediately. Rejected reviews keep counting too. Other pages filter correctly, so the same product can show two ratings.

- **Severity / confidence:** MEDIUM / High
- **Files:** app/Http/Controllers/HomeController.php:68-69,81-82,98-99
- **Evidence:** docs/forensic-audit/tests/PerformanceAuditTest.php P02.
- **Recommendation (not applied):** Filter on is_approved.

### BIZ-034: No return, refund or shipping policy pages; seller identity missing

Live /return-policy, /refund-policy and /shipping-policy return 404. Only privacy, terms and FAQ exist. No legal entity, trade licence or full address in code. No policy acknowledgement at checkout. Privacy text omits stored IP, browser and saved abandoned-checkout phone numbers. Owner or lawyer to compare with the Digital Commerce Operation Guidelines 2021. This is not legal advice.

- **Severity / confidence:** MEDIUM / High for what exists. Applicability needs a lawyer
- **Files:** database/seeders/PageSeeder.php:25-31,71; resources/js/components/layout/navigation.ts:38-39; app/Models/Order.php:215-216; resources/js/pages/checkout/Checkout.tsx:542-557
- **Evidence:** Live GETs on 2026-11-05.
- **Recommendation (not applied):** Add the pages and disclosures after legal review.

### BIZ-014: A retried checkout puts purchased items back in the cart

No duplicate order is made, but after a network retry the customer sees the bought items in the cart again. With a limit-1 coupon the retry returns a hidden error instead.

- **Severity / confidence:** LOW / High
- **Files:** app/Http/Controllers/CheckoutController.php:157-163,188-201,214
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A12 FAILS.
- **Recommendation (not applied):** Check the replay key first, as LandingPageController already does.

### BIZ-015: Per-customer coupon limits do not hold across guest and account

A limit-1 coupon used while logged in can be used again as a guest with the same phone. Any other valid-format phone works too. Only max_uses holds.

- **Severity / confidence:** LOW / High
- **Files:** app/Models/Coupon.php:185,192,215-223; app/Services/CouponService.php:66,73-80
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A13 FAILS: used_count 2.
- **Recommendation (not applied):** Count by normalized phone too and decide whether limited coupons allow guests.

### BIZ-017: Incomplete-order conversion ignores flash price and variant ownership

Conversion uses the regular price during a flash sale, can pair one product with another product's variant, and the screen hides shipping before creating the order.

- **Severity / confidence:** LOW / High (code reading)
- **Files:** app/Http/Controllers/Admin/IncompleteOrderController.php:38,144,169-170; app/Http/Requests/ConvertIncompleteOrderRequest.php; resources/js/pages/admin/orders/incomplete/convert.tsx:237-240,716-721
- **Evidence:** Code reading.
- **Recommendation (not applied):** Reuse ManualOrderPricing and add the rule.

### BIZ-018: Referral discount preview ignores Buy Now

With Buy Now and an empty cart the preview shows no discount, yet the server applies and uses up the one-time benefit.

- **Severity / confidence:** LOW / High (code reading)
- **Files:** app/Http/Controllers/CheckoutController.php:86,98,447-451; resources/js/pages/checkout/Checkout.tsx:717-722
- **Evidence:** Code reading.
- **Recommendation (not applied):** Compute the preview from cart plus Buy Now.

### BIZ-019: Paying a mobile checkout empties items added later

Items added after staging are deleted when the earlier payment completes.

- **Severity / confidence:** LOW / High (code reading)
- **Files:** app/Http/Controllers/MobilePaymentController.php:266-272; app/Services/OrderService.php:148-153
- **Evidence:** Code reading.
- **Recommendation (not applied):** Remove only staged lines.

### BIZ-020: Admin price fields have no sanity limits

Price 0, flash price above regular price, bundle price 0 and percent coupons above 100 are accepted. A ৳0 total on mobile banking cannot be matched by any SMS.

- **Severity / confidence:** LOW / High (code reading)
- **Files:** app/Http/Requests/Admin/ProductRequest.php:58-59,73-74; FlashSaleRequest.php:33; ProductBundleRequest.php:26; CouponRequest.php:29; app/Support/CartManager.php:452-454
- **Evidence:** Code reading. Percent is safely capped at app/Models/Coupon.php:147-149.
- **Recommendation (not applied):** Add gt:0, lt price and max:100.

### BIZ-021: District and city are free text and set the delivery fee

A crafted request can claim an inside-Dhaka area for an address elsewhere and pay the lower rate. Limited to the rate difference.

- **Severity / confidence:** LOW / High (code reading)
- **Files:** app/Http/Requests/PlaceOrderRequest.php:35-36; app/Http/Controllers/LandingPageController.php:120-121; app/Models/Setting.php:84
- **Evidence:** Code reading.
- **Recommendation (not applied):** Validate against the server lists.

### BIZ-022: Opposite-order stock locks use a single COD attempt

Two carts locking the same products in opposite order can deadlock. One customer gets a 500. It rolls back cleanly and a retry works.

- **Severity / confidence:** LOW / Medium. Deadlock not reproduced
- **Files:** app/Http/Controllers/CheckoutController.php:271,312-314,360-377; app/Services/InventoryService.php:54,73
- **Evidence:** Not yet run.
- **Recommendation (not applied):** Sort lines and pass attempts.

### BIZ-023: Duplicate-order protection has no database backstop

Protection relies on cache. Deploy clears the cache, so a retry across a deploy could make a second order on landing pages.

- **Severity / confidence:** LOW / Medium
- **Files:** app/Http/Controllers/CheckoutController.php:212-218,379,412; app/Http/Controllers/LandingPageController.php:136-140,283
- **Evidence:** Code reading. File lock itself is atomic on a local filesystem. Hostinger filesystem not verified.
- **Recommendation (not applied):** Store a unique token.

### BIZ-024: Referral discount can be farmed with new emails

Each new email gets the referred discount for the same phone and address. Referrer rewards need a delivered order and are capped.

- **Severity / confidence:** LOW / High (code reading)
- **Files:** app/Services/ReferralService.php:41-50,90-97,231-235,310; app/Actions/Fortify/CreateNewUser.php:51
- **Evidence:** Code reading.
- **Recommendation (not applied):** Key eligibility to a normalized phone.

### BIZ-025: Transaction IDs are not tied to the checkout time or payer

Someone who learns an unmatched TrxID, for example from a shared screenshot, can claim it for a same-amount order. Errors also reveal unmatched IDs and amounts. Brute force is impractical.

- **Severity / confidence:** LOW / High (code reading)
- **Files:** app/Http/Controllers/MobilePaymentController.php:146-175,298-316
- **Evidence:** Code reading.
- **Recommendation (not applied):** Require arrival in the checkout window and use generic errors.

### BIZ-026: Legacy ZiniPay path is still reachable and has no amount check

Routes accept any pending token including mobile ones, never compare amount, and delete the pending row on failed status. The page checkout/PaymentPending does not exist. PlaceOrderRequest no longer offers zinipay, so reach is limited.

- **Severity / confidence:** LOW / High for the code. Gateway behaviour and production key unverified
- **Files:** app/Http/Controllers/ZiniPayWebhookController.php:46-91; app/Http/Controllers/PaymentController.php:30,54-99; app/Services/OrderService.php:32-121; resources/js/pages/checkout/ (no PaymentPending)
- **Evidence:** Code reading; directory listing shows only Checkout.tsx and MobilePayment.tsx.
- **Recommendation (not applied):** Retire the routes.

### BIZ-028: Pathao silently falls back to sandbox

If the environment setting is empty or left at default, consignments go to the sandbox with sandbox credentials and are recorded as sent, so no pickup happens.

- **Severity / confidence:** LOW / Medium
- **Files:** app/Services/PathaoCourierService.php:313-318; config/pathao.php:8; resources/js/pages/admin/settings/courier.tsx:26-27
- **Evidence:** Code reading. Live value BLOCKED.
- **Recommendation (not applied):** Warn or fail closed when APP_ENV is production.

### BIZ-035: Bundle prices can miss the advertised total by a few poisha

A ৳1000 bundle of three equal items gives 333.33 × 3 = 999.99.

- **Severity / confidence:** LOW / Medium (not executed)
- **Files:** app/Services/BundleService.php:63
- **Evidence:** Code reading by the module reviewer; spot-checked, not run. Blocked in practice by BIZ-002.
- **Recommendation (not applied):** Allocate the remainder.

### BIZ-036: Duplicate reviews and repeat review bonus

Two simultaneous submits can make two reviews. After an admin deletes an approved review, a new one can earn the bonus again.

- **Severity / confidence:** LOW / High for the missing constraint. Race not reproduced
- **Files:** app/Http/Controllers/ReviewController.php:29-37; database/migrations/2026_04_28_074828_create_reviews_table.php:27-30; app/Services/LoyaltyPointsService.php:466,482
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Recommendation (not applied):** Unique index and count trashed rows.

### BIZ-037: Free delivery ৳999 offer is hardcoded in the footer and login page

If the admin changes the threshold, the site still advertises ৳999.

- **Severity / confidence:** LOW / High (code reading). Live setting unknown
- **Files:** resources/js/components/layout/Footer.tsx:27; resources/js/pages/auth/login.tsx:20; database/seeders/PageSeeder.php:154
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Recommendation (not applied):** Render from the shared prop.

