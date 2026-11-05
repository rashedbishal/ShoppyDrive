# Complete Findings Register

Audited commit: `f49bed3`. Updated 2026-11-05.

Every Critical and High was re-read in the code by the lead auditor. Lower entries come from code reading by module reviewers and were spot-checked; the Evidence and Confidence fields say which were run. "FAILS" on an audit test means the test states the correct behaviour and the app does not meet it, which confirms the finding. Earlier IDs (FSA, MED, LOW) are cross-referenced, not renumbered. Accepted risks are not re-raised: ADMIN-2FA-001, DEPLOY-SAFETY-001 C1, M1, 17 moderate npm advisories, XSS-SINK-GAP-001, MED-19, LOW-01, LOW-02, LOW-09.

## Summary

| ID | Severity | Priority | Category | Title |
|---|---|---|---|---|
| SEC-001 | CRITICAL | P0 | Confirmed Vulnerability | A forged text message can mark an order as paid |
| SEC-002 | HIGH | P1 | Confirmed Vulnerability | Any admin with one settings page can read every stored secret |
| SEC-003 | HIGH | P1 | Confirmed Vulnerability | A verification code sent to one email can verify a different email |
| SEC-019 | HIGH if the app sees the CDN IP, otherwise INFO | P1 | Untested/Unverified | Whether the app sees real customer IPs behind the CDN is unknown |
| BIZ-001 | HIGH | P0 | Confirmed Functional Bug | COD can charge more than the price the customer saw |
| BIZ-002 | HIGH | P0 | Confirmed Functional Bug | A cart containing a complete bundle can never be checked out from the website |
| BIZ-003 | HIGH | P0 | Confirmed Functional Bug | Guests see a coupon discount at checkout but are charged full price |
| BIZ-004 | HIGH | P0 | Confirmed Functional Bug | Converting an incomplete order ignores stock, and cancelling it creates stock |
| BIZ-005 | HIGH | P0 | Confirmed Functional Bug | Paid orders can still be cancelled and then cannot be refunded |
| BIZ-006 | HIGH | P0 | Confirmed Functional Bug | Customers can pay for out-of-stock items and receive no order |
| OPS-008 | HIGH until verified | P0 | Untested/Unverified | Backups, cron and server settings could not be verified |
| SEC-004 | MEDIUM | P1 | Confirmed Vulnerability | Customers' Google/Facebook tokens are sent to browsers |
| SEC-005 | MEDIUM | P1 | Missing Security Control | Changing an admin password does not log out other sessions |
| SEC-006 | MEDIUM | P1 | Confirmed Vulnerability | Order tracking returns the whole order record |
| SEC-007 | MEDIUM | P1 | Confirmed Vulnerability | View-only SMS forwarder permission reveals the full token |
| SEC-008 | MEDIUM | P2 | Potential Risk | A social link planted on an unverified account can survive the real owner's recovery |
| SEC-010 | MEDIUM | P1 | Missing Security Control | Weaker password policy and no per-account login limit for admins |
| SEC-017 | MEDIUM | P1 | Potential Risk | composer audit lists new Symfony advisories |
| BIZ-007 | MEDIUM | P1 | Confirmed Functional Bug | Admin can cancel orders already with Steadfast; Steadfast orders never reach Shipped |
| BIZ-008 | MEDIUM | P1 | Confirmed Functional Bug | Refunded orders stay "paid" and keep counting as revenue |
| BIZ-009 | MEDIUM | P2 | Confirmed Functional Bug | Partial delivery is booked as fully delivered and paid, with no way out |
| BIZ-010 | MEDIUM | P1 | Confirmed Functional Bug | Loyalty points can be spent twice through a late mobile payment |
| BIZ-011 | MEDIUM | P1 | Missing Security Control | No bot or quantity protection on COD orders |
| BIZ-012 | MEDIUM | P2 | Potential Risk | A whole-order refund restocks delivered goods automatically |
| BIZ-013 | MEDIUM | P1 | Confirmed Functional Bug | Coupon and duplicate-order rejections are invisible at checkout |
| BIZ-016 | MEDIUM | P1 | Confirmed Functional Bug | Landing page shows different price and delivery than it charges |
| BIZ-027 | MEDIUM | P2 | Potential Risk | Steadfast status relies on unverified webhook assumptions with no fallback |
| FE-001 | MEDIUM | P2 | Confirmed Functional Bug | Customers cannot page through order history |
| REL-001 | MEDIUM | P1 | Confirmed Functional Bug | Tests pass payloads the real website never sends |
| SEC-020 | MEDIUM (HIGH if still current) | P0 | Potential Risk | A forwarder token is committed in the repository |
| SEC-021 | MEDIUM | P1 | Confirmed Vulnerability | Back button after logout shows account data |
| BIZ-029 | MEDIUM | P1 | Confirmed Functional Bug | Cancelling the Reject prompt still rejects the return and emails the customer |
| BIZ-030 | MEDIUM | P1 | Confirmed Functional Bug | COD confirmation says "Total Paid" |
| BIZ-031 | MEDIUM | P1 | Confirmed Functional Bug | Checkout shows payment logos the shop does not accept |
| BIZ-032 | MEDIUM | P1 | Confirmed Functional Bug | Live terms and FAQ contradict how the shop works |
| BIZ-033 | MEDIUM | P1 | Confirmed Functional Bug | Home page ratings count unmoderated reviews |
| BIZ-034 | MEDIUM | P1 | Informational | No return, refund or shipping policy pages; seller identity missing |
| DB-002 | MEDIUM | P2 | Missing Security Control | No retention or pruning for growing tables, including personal data |
| PERF-001 | MEDIUM | P1 | Confirmed Performance Issue | Product listing runs a query for every variant |
| PERF-003 | MEDIUM | P2 | Confirmed Performance Issue | Icon registry and admin layout load on the customer home page |
| PERF-004 | MEDIUM | P2 | Confirmed Performance Issue | Uploaded images are served at original size |
| BE-009 | MEDIUM | P1 | Confirmed Functional Bug | "Today" figures and invoices use UTC, not Dhaka time |
| FE-005 | MEDIUM | P1 | Confirmed Functional Bug | A server error empties a logged-in customer's cart |
| FE-006 | MEDIUM | P1 | Confirmed Functional Bug | Product pages have no search or share tags in the HTML |
| FE-007 | MEDIUM | P2 | Confirmed Functional Bug | Session timeout on landing-page checkout loses the form and may 405 |
| FE-008 | MEDIUM | P2 | Confirmed Functional Bug | Google or Facebook login discards the guest cart |
| INFRA-001 | MEDIUM | P1 | Potential Risk | Deploy has no maintenance mode and is not atomic |
| INFRA-002 | MEDIUM | P1 | Potential Risk | A broken release may not be fixable by pushing, and there is no rollback |
| INFRA-003 | MEDIUM | P1 | Missing Security Control | Migrations are never run on MariaDB before production |
| OPS-002 | MEDIUM | P1 | Missing Security Control | If cron or the queue stops, nobody is told and customer mail stops |
| OPS-003 | MEDIUM | P1 | Potential Risk | A short mail outage fails queued mail, and recovery is one click per row |
| OPS-004 | MEDIUM | P2 | Potential Risk | Newsletter has no send-rate limit and shares the order mail account and queue |
| OPS-005 | MEDIUM | P1 | Missing Security Control | A dead forwarder phone disables mobile payment with a misleading message |
| OPS-006 | MEDIUM | P1 | Confirmed Functional Bug | Abandoned-cart mail has no opt-out or cap and reaches deleted accounts |
| SEC-009 | LOW | P2 | Missing Security Control | Customer email can be changed without the password and without notifying the old address |
| SEC-011 | LOW | P2 | Potential Risk | Password reset link is built from the request Host |
| SEC-012 | LOW | P2 | Confirmed Vulnerability | Coupon check reveals coupon details and whether a phone has used it |
| SEC-013 | LOW | P2 | Missing Security Control | No HSTS or CSP on the live site; API answers over plain HTTP |
| SEC-014 | LOW | P2 | Potential Risk | Customer 2FA can be enrolled but is never asked for at login |
| SEC-015 | LOW | P3 | Confirmed Vulnerability | Register, login and forgot-password reveal whether an email exists |
| SEC-018 | LOW | P3 | Missing Security Control | Some admin uploads have no size cap |
| BIZ-014 | LOW | P2 | Confirmed Functional Bug | A retried checkout puts purchased items back in the cart |
| BIZ-015 | LOW | P2 | Missing Security Control | Per-customer coupon limits do not hold across guest and account |
| BIZ-017 | LOW | P2 | Confirmed Functional Bug | Incomplete-order conversion ignores flash price and variant ownership |
| BIZ-018 | LOW | P3 | Confirmed Functional Bug | Referral discount preview ignores Buy Now |
| BIZ-019 | LOW | P3 | Confirmed Functional Bug | Paying a mobile checkout empties items added later |
| BIZ-020 | LOW | P3 | Missing Security Control | Admin price fields have no sanity limits |
| BIZ-021 | LOW | P3 | Missing Security Control | District and city are free text and set the delivery fee |
| BIZ-022 | LOW | P3 | Potential Risk | Opposite-order stock locks use a single COD attempt |
| BIZ-023 | LOW | P3 | Potential Risk | Duplicate-order protection has no database backstop |
| BIZ-024 | LOW | P2 | Missing Security Control | Referral discount can be farmed with new emails |
| BIZ-025 | LOW | P2 | Missing Security Control | Transaction IDs are not tied to the checkout time or payer |
| BIZ-026 | LOW | P2 | Potential Risk | Legacy ZiniPay path is still reachable and has no amount check |
| BIZ-028 | LOW | P2 | Potential Risk | Pathao silently falls back to sandbox |
| BE-001 | LOW | P2 | Confirmed Functional Bug | Malformed input causes server errors |
| BE-002 | LOW | P2 | Confirmed Functional Bug | A deleted product in a guest cart gives a bare 404 at checkout |
| BE-003 | LOW | P2 | Potential Risk | DatabaseSeeder wipes the catalog and has no production guard |
| BE-004 | LOW | P3 | Potential Risk | Pathao sync can stop for up to 24 hours after a killed run |
| BE-006 | LOW | P3 | Confirmed Functional Bug | Currency symbol setting ignored on home and wishlist |
| FE-002 | LOW | P2 | Confirmed Functional Bug | Cart drawer and checkout estimates disagree with the server |
| FE-003 | LOW | P3 | Confirmed Functional Bug | Review "Helpful" buttons do nothing |
| FE-004 | LOW | P3 | Informational | Lost-parcel action has no screen |
| OPS-001 | LOW | P2 | Confirmed Functional Bug | sitemap.xml is missing on the live site |
| SEC-022 | LOW | P1 | Potential Risk | Internal audit documents and agent config are deployed to the server |
| SEC-023 | LOW | P2 | Potential Risk | Personal data can reach logs |
| SEC-024 | LOW | P3 | Potential Risk | Token regeneration rewrites .env from a web request |
| BIZ-035 | LOW | P3 | Potential Risk | Bundle prices can miss the advertised total by a few poisha |
| BIZ-036 | LOW | P3 | Potential Risk | Duplicate reviews and repeat review bonus |
| BIZ-037 | LOW | P3 | Confirmed Functional Bug | Free delivery ৳999 offer is hardcoded in the footer and login page |
| DB-001 | LOW | P2 | Potential Risk | Brand delete can hard-delete soft-deleted products through the cascade |
| DB-004 | LOW | P3 | Potential Risk | Expired rate-limit rows pile up in the cache table |
| PERF-002 | LOW | P2 | Confirmed Performance Issue | Product cards send the whole product record |
| PERF-005 | LOW | P3 | Confirmed Performance Issue | Admin sidebar count scans the orders table |
| PERF-006 | LOW | P2 | Potential Risk | Admin SMS poll hits the app every 5 seconds per tab |
| PERF-007 | LOW | P3 | Confirmed Performance Issue | Search scans the table despite the FULLTEXT index |
| PERF-008 | LOW | P3 | Potential Risk | Orphaned search cache files until the next deploy |
| PERF-009 | LOW | P3 | Potential Risk | Unbounded lists in admin compose screens and Pathao sync |
| BE-007 | LOW | P2 | Confirmed Functional Bug | Search overrides other filters on admin lists |
| BE-008 | LOW | P3 | Confirmed Functional Bug | Hover prefetches count as page views |
| BE-010 | LOW | P3 | Potential Risk | Forgot-password sends mail inside the request with no error handling |
| BE-011 | LOW | P2 | Potential Risk | Pathao sync reports success when every poll failed |
| BE-012 | LOW | P3 | Confirmed Functional Bug | Failed newsletter chunk cannot be retried from admin |
| FE-009 | LOW | P2 | Confirmed Functional Bug | No noindex or canonical; meta helper defects |
| FE-010 | LOW | P2 | Potential Risk | No React error boundary |
| FE-011 | LOW | P2 | Confirmed Functional Bug | Failed cart sync is silent and the item later vanishes |
| FE-012 | LOW | P2 | Confirmed Functional Bug | Phone rules differ, and Bangla digits are dropped at checkout |
| FE-013 | LOW | P3 | Informational | Cookie banner offers choices that control nothing |
| FE-014 | LOW | P3 | Confirmed Functional Bug | Inconsistent money format and small accessibility gaps |
| INFRA-004 | LOW | P2 | Missing Security Control | Workflow hardening gaps |
| INFRA-005 | LOW | P2 | Potential Risk | Deploy env check is thin and .env.example is incomplete |
| INFRA-006 | LOW | P2 | Potential Risk | rsync --delete removes server-created files |
| OPS-007 | LOW | P2 | Potential Risk | Logging defaults to one unrotated file at debug level |
| REL-003 | LOW | P3 | Confirmed Functional Bug | Four race tests silently skip on anything but port 3306, and show as passed |
| REL-004 | LOW | P3 | Confirmed Functional Bug | Tests leave real files in storage |
| SEC-016 | INFO | P3 | Informational | Every page publishes all admin route names to guests |
| BE-005 | INFO | P3 | Informational | Dead code and unused schema |
| DB-003 | INFO | P3 | Informational | Some uniqueness relies on application code only |
| DB-005 | INFO | P3 | Informational | Search index and enums depend on the driver being named mysql |
| INFRA-007 | INFO | P3 | Informational | Leftover packages and public assets |
| REL-002 | INFO | P3 | Informational | Race test assumes REPEATABLE READ, but production is READ-COMMITTED |

## Details

### SEC-001: A forged text message can mark an order as paid

- **Module:** Payments, mobile banking
- **Category:** Confirmed Vulnerability
- **Severity:** CRITICAL
- **Confidence:** High for the code. Production pattern values and the forwarder phone filter could not be checked
- **CVSS 4.0:** `CVSS:4.0/AV:N/AC:L/AT:P/PR:N/UI:N/VC:N/VI:H/VA:N/SC:N/SI:N/SA:N`
- **Earlier ID:** FSA-02
- **Description:** Payment is accepted because an SMS body looks like a bKash/Nagad receipt. Unless a sender rule is set on the pattern, any sender is accepted. Example: a buyer texts the shop number "You have received Tk 500.00 ... TrxID FAKE123" from their own phone, enters FAKE123, and the order becomes paid with nothing to collect on delivery.
- **Root cause:** Sender allowlist is opt-in. An empty sender_regex returns true. Even when set, it only checks a sender value relayed by the phone.
- **Files:** app/Services/SmsParserService.php:87-88; app/Http/Controllers/SmsTestController.php:71-72; app/Http/Controllers/MobilePaymentController.php:146-184; app/Http/Controllers/LandingPageController.php:404-430; database/seeders/SmsPatternSeeder.php (no sender_regex)
- **Reproduction:** Start a mobile-banking checkout, send the forwarder a matching SMS from any number, submit the TrxID.
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A01 FAILS: 1 paid order created from a forged message. Tracker row FSA-02 records the allowlist as open.
- **Business impact:** Goods ship with no money received. Courier COD amount becomes 0.
- **Security impact:** Payment integrity bypass.
- **Likelihood:** High if the forwarder phone does not filter senders. Unknown otherwise.
- **Recommended remediation (not applied):** Make an empty sender rule fail closed for active patterns, set the real bKash/Nagad sender IDs, and hold SMS-only payments for reconciliation against the provider statement.
- **Verification:** A01 passes; a genuine SMS from the allowed sender still matches.
- **Deployment implication:** Needs the real sender IDs entered in production before it is switched on, or genuine payments stop matching.
- **Priority:** P0

### SEC-002: Any admin with one settings page can read every stored secret

- **Module:** Admin settings
- **Category:** Confirmed Vulnerability
- **Severity:** HIGH
- **Confidence:** High
- **CVSS 4.0:** `CVSS:4.0/AV:N/AC:L/AT:N/PR:L/UI:N/VC:H/VI:H/VA:N/SC:H/SI:H/SA:N`
- **Description:** All six settings pages send the whole settings table to the browser. Example: a content editor allowed only the About page receives the Pathao password, Steadfast keys, webhook secret and OAuth client secrets in the page data.
- **Root cause:** Permission is checked per page, but the data is not limited per page. Values are also stored unencrypted.
- **Files:** app/Http/Controllers/Admin/SettingController.php:71,78,85,92,99,106,319-321; routes/admin.php:184-190; app/Models/Setting.php:45-47
- **Reproduction:** Grant an admin only about_page view, open /admin/settings/about-page, read props.settings.
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A07 FAILS: secret values present in the prop.
- **Business impact:** A staff account can take over courier accounts. With the webhook secret it can forge "delivered" events, which mark orders paid.
- **Security impact:** Bypasses the permission matrix; secrets leave the server.
- **Likelihood:** Medium. Needs a staff login.
- **Recommended remediation (not applied):** Send each page only its own keys. Return secrets as a write-only "is set" flag. Encrypt secret values at rest.
- **Verification:** A07 passes; each settings page still saves correctly.
- **Deployment implication:** Rotate courier and OAuth secrets afterwards if any limited admin has existed.
- **Priority:** P1

### SEC-003: A verification code sent to one email can verify a different email

- **Module:** Customer auth
- **Category:** Confirmed Vulnerability
- **Severity:** HIGH
- **Confidence:** High for the first step (tested). The Google-login continuation is from code reading
- **CVSS 4.0:** `CVSS:4.0/AV:N/AC:L/AT:P/PR:N/UI:A/VC:H/VI:L/VA:N/SC:N/SI:N/SA:N`
- **Description:** Changing the email does not cancel the pending code. Example: an attacker registers with their own email, gets a code, changes the account email to victim@gmail.com, and enters the code. The account now counts as verified for the victim's address. When the victim later uses "Continue with Google", they land in the attacker's account, where the attacker still holds the password and can read whatever the victim enters.
- **Root cause:** The code is stored per user, not per address, and the profile update does not clear it. Verified accounts skip the unverified-account claim step.
- **Files:** app/Http/Controllers/Settings/ProfileController.php:33-36; app/Http/Controllers/Auth/EmailVerificationCodeController.php:29-38; routes/settings.php:12; app/Http/Controllers/Auth/SocialAuthController.php:113,131,160
- **Reproduction:** Register, change email through PATCH settings/profile, submit the code from the first email.
- **Evidence:** docs/forensic-audit/tests/AccessControlAuditTest.php S01 FAILS: account verified for an address that never received a code.
- **Business impact:** Customer data and loyalty points exposed to an attacker; loss of trust.
- **Security impact:** Pre-hijack of a real customer identity; the verified flag no longer proves ownership.
- **Likelihood:** Medium. Needs the victim to use social login later.
- **Recommended remediation (not applied):** Clear the code on any email change and bind the code to the address it was sent to.
- **Verification:** S01 passes.
- **Deployment implication:** No data migration. Accounts already affected cannot be identified from the code alone.
- **Priority:** P1

### SEC-019: Whether the app sees real customer IPs behind the CDN is unknown

- **Module:** Rate limiting, blocking
- **Category:** Untested/Unverified
- **Severity:** HIGH if the app sees the CDN IP, otherwise INFO
- **Confidence:** Could not verify
- **Description:** The live site sits behind Hostinger CDN (server: hcdn) and no trusted proxy is configured. If the app sees the CDN address, all guests share rate-limit buckets and blocking one customer blocks many. If the origin takes the IP from a client header, limits can be bypassed.
- **Root cause:** No trustProxies; real REMOTE_ADDR on the host is unknown.
- **Files:** bootstrap/app.php; app/Http/Middleware/BlockBlockedIp.php:36; app/Models/Order.php:216; app/Support/SecurityRateLimits.php:138
- **Reproduction:** BLOCKED: needs production data. The owner declined sharing production settings.
- **Evidence:** docs/forensic-audit/evidence/phase2/live_headers.txt.
- **Business impact:** Could block real customers or leave limits ineffective.
- **Security impact:** All IP-based controls depend on it.
- **Likelihood:** Unknown
- **Recommended remediation (not applied):** The owner can compare IPs on recent orders in Admin against known customer locations. Then configure the exact trusted proxy range if needed.
- **Verification:** IPs on new orders differ per customer.
- **Deployment implication:** Release condition.
- **Priority:** P1

### BIZ-001: COD can charge more than the price the customer saw

- **Module:** Checkout
- **Category:** Confirmed Functional Bug
- **Severity:** HIGH
- **Confidence:** High
- **Description:** Example: a product is ৳100 in the cart, the admin changes it to ৳150, and the page still shows ৳100. The customer places a COD order and is charged ৳150 with no warning.
- **Root cause:** Checkout.tsx always sends items. The server syncs them first, which rewrites the stored price to the current one, then compares the price with itself. The guard only works for a payload the UI never sends.
- **Files:** resources/js/pages/checkout/Checkout.tsx:670-674; app/Http/Controllers/CheckoutController.php:157-163,188; app/Support/CartManager.php:325,335,477-496; tests/Feature/FlashSalePricingTest.php:148-150
- **Reproduction:** Add to cart at 100, raise the price to 150, submit the UI payload.
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A02 FAILS: order created.
- **Business impact:** Refusals on delivery, disputes, consumer trust. Also applies when a flash sale ends.
- **Security impact:** None.
- **Likelihood:** High during price changes and flash sales
- **Recommended remediation (not applied):** Send the displayed line prices or total and compare before syncing.
- **Verification:** A02 passes.
- **Deployment implication:** Frontend and backend must ship together.
- **Priority:** P0

### BIZ-002: A cart containing a complete bundle can never be checked out from the website

- **Module:** Bundles, checkout
- **Category:** Confirmed Functional Bug
- **Severity:** HIGH
- **Confidence:** High. Whether production has active bundles is unknown
- **Description:** Every Place Order click shows "Some prices have changed" and no order is made. This affects COD and mobile banking, guests and members.
- **Root cause:** Checkout.tsx omits bundle_id. Sync strips the bundle, the server re-assembles it and reports a change, and the retry repeats this.
- **Files:** resources/js/pages/checkout/Checkout.tsx:670-674; app/Support/CartManager.php:327-345,480; app/Services/BundleService.php; app/Http/Controllers/CheckoutController.php:188-190; tests/Feature/BundleAutoAssembleTest.php:176
- **Reproduction:** Create bundle A+B, submit the UI payload three times.
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A03 FAILS: 0 orders after 3 tries.
- **Business impact:** Bundle sales are lost.
- **Security impact:** None.
- **Likelihood:** Certain if any bundle is active
- **Recommended remediation (not applied):** Send bundle_id and bundle_unit_quantity, and teach the checkout preview about free-delivery bundles.
- **Verification:** A03 passes; fix existing tests to retry with the same payload.
- **Deployment implication:** Frontend change.
- **Priority:** P0

### BIZ-003: Guests see a coupon discount at checkout but are charged full price

- **Module:** Coupons, checkout
- **Category:** Confirmed Functional Bug
- **Severity:** HIGH
- **Confidence:** High. Display proven in a browser. The charge comes from code reading: the browser order could not be submitted through the District dropdown by my script
- **Description:** Example: a guest applies SAVE100 on the Cart page on a ৳1,000 cart. Checkout shows Discount −৳100 and Total ৳900, but the order is sent without any coupon code, so the server charges ৳1,000. Logged-in customers: the coupon silently disappears. Applying it again at checkout subtracts it twice on screen.
- **Root cause:** Two unconnected coupon states: the cart store and local checkout state.
- **Files:** resources/js/pages/cart/Cart.tsx:70; resources/js/pages/checkout/Checkout.tsx:354,448,667,727-728; resources/js/stores/cartStore.ts:393-395; resources/js/app.tsx:270-305
- **Reproduction:** As a guest: apply on /cart, open /checkout, read the total, submit.
- **Evidence:** docs/forensic-audit/evidence/phase2/guest_coupon_browser.txt shows Total ৳900 at checkout. Checkout.tsx:667 sends only appliedCoupon, which starts null.
- **Business impact:** Customer charged more than shown; COD refusals. Coupons feature was just extended to guests, so exposure grows.
- **Security impact:** None.
- **Likelihood:** High for any guest using the Cart page coupon box
- **Recommended remediation (not applied):** Use one coupon state, seed checkout from it and submit it, and remove the extra subtraction.
- **Verification:** Browser walk-through plus a test.
- **Deployment implication:** Frontend change. I told the owner earlier in this session that guests can use coupons; that holds only for the Checkout page box until this is fixed.
- **Priority:** P0

### BIZ-004: Converting an incomplete order ignores stock, and cancelling it creates stock

- **Module:** Admin orders, inventory
- **Category:** Confirmed Functional Bug
- **Severity:** HIGH
- **Confidence:** High
- **Description:** Example: stock is 5. An admin converts an incomplete order for 10: the order is created and stock stays 5. For a 2-unit order, cancelling then raises stock from 5 to 7.
- **Root cause:** This is the one order path that never calls InventoryService, while cancellation always restores.
- **Files:** app/Http/Controllers/Admin/IncompleteOrderController.php:172-194; app/Models/Order.php:264-267; app/Services/InventoryService.php:133-186; app/Http/Requests/ConvertIncompleteOrderRequest.php
- **Reproduction:** Convert then cancel.
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A04a and A04b FAIL.
- **Business impact:** Overselling and inflated stock counts, no inventory log.
- **Security impact:** None.
- **Likelihood:** High wherever staff use conversion
- **Recommended remediation (not applied):** Decrement inside the transaction, catch insufficient stock, lock the row, validate the variant owner.
- **Verification:** A04a and A04b pass.
- **Deployment implication:** Stock on past converted orders may already be wrong; owner should review.
- **Priority:** P0

### BIZ-005: Paid orders can still be cancelled and then cannot be refunded

- **Module:** Orders, refunds
- **Category:** Confirmed Functional Bug
- **Severity:** HIGH
- **Confidence:** High
- **Earlier ID:** FSA-06 residual
- **Description:** FSA-06 fixed only admin cancel. A customer can cancel a paid pending order, for example a manual mobile-banking order. A courier return report also cancels a paid order. In both cases the order is cancelled but still paid, and the refund screen refuses because "A cancelled order cannot be refunded".
- **Root cause:** The customer cancel route and courier mapper do not check payment_status.
- **Files:** routes/account.php:124-158; app/Services/CourierOrderStatusMapper.php:39-48,66,199-213; app/Services/RefundService.php:142-144; app/Http/Controllers/Admin/OrderController.php:170-171
- **Reproduction:** Create a paid pending order, PATCH account.orders.cancel.
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A05 FAILS.
- **Business impact:** Customer money held with no recorded way to return it.
- **Security impact:** None.
- **Likelihood:** Medium
- **Recommended remediation (not applied):** Refuse customer cancel of paid orders and allow a ledger refund for cancelled and paid orders.
- **Verification:** A05 passes; add a courier-cancel case.
- **Deployment implication:** Business rule needs owner approval.
- **Priority:** P0

### BIZ-006: Customers can pay for out-of-stock items and receive no order

- **Module:** Mobile banking, landing pages
- **Category:** Confirmed Functional Bug
- **Severity:** HIGH
- **Confidence:** High
- **Earlier ID:** FSA-07, FSA-08 residual
- **Description:** Example: stock is 0, the customer picks bKash and sees the payment page, pays, submits the TrxID, and gets "insufficient stock". Money is taken, no order exists. Landing pages also lack the late-submission grace and the variant guard, and only an unmatched-SMS count in the sidebar signals the problem.
- **Root cause:** No stock check before payment instructions. FSA-07 added recovery aids for the cart flow only.
- **Files:** app/Http/Controllers/CheckoutController.php:394-407,458-527; app/Http/Controllers/MobilePaymentController.php:218,287-293; app/Http/Controllers/LandingPageController.php:293-314,372,392,460; app/Http/Controllers/Admin/ReceivedSmsController.php:46-48
- **Reproduction:** Stock 0, mobile checkout.
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A10 FAILS: pending checkout created. Landing page grace gap from code reading.
- **Business impact:** Manual refunds and angry customers.
- **Security impact:** None.
- **Likelihood:** Medium, higher during sales
- **Recommended remediation (not applied):** Check stock before showing payment instructions, share the grace logic, add a pending-checkout to SMS reconciliation screen.
- **Verification:** A10 passes; add landing-page cases.
- **Deployment implication:** None.
- **Priority:** P0

### OPS-008: Backups, cron and server settings could not be verified

- **Module:** Operations
- **Category:** Untested/Unverified
- **Severity:** HIGH until verified
- **Confidence:** Could not verify. Owner declined to share production settings
- **Description:** The repo has no backup code and BackupFailedMail is a stub. Pre-deploy backup was accepted as a risk (C1), and is not re-raised, but docs/MASTER_FIXING_TRACKER.md records that acceptance as conditional on production holding no important business data, and says to revisit once real data accumulates. The site is now live with orders. Also unverified: restore test, cron entry, opcache, mail caps, disk and inode quota.
- **Root cause:** Not visible from the repo.
- **Files:** docs/MASTER_FIXING_TRACKER.md; app/Mail/BackupFailedMail.php
- **Reproduction:** BLOCKED.
- **Evidence:** None available.
- **Business impact:** A failed migration with no tested restore could lose orders.
- **Security impact:** None.
- **Likelihood:** Unknown
- **Recommended remediation (not applied):** Owner confirms a recent off-site backup and one restore test.
- **Verification:** Owner evidence.
- **Deployment implication:** Release condition.
- **Priority:** P0

### SEC-004: Customers' Google/Facebook tokens are sent to browsers

- **Module:** Customers, social login
- **Category:** Confirmed Vulnerability
- **Severity:** MEDIUM
- **Confidence:** High
- **CVSS 4.0:** `CVSS:4.0/AV:N/AC:L/AT:N/PR:L/UI:N/VC:L/VI:N/VA:N/SC:L/SI:N/SA:N`
- **Description:** The social account model has no hidden fields, so live access and refresh tokens appear in the admin customer page data and in the customer's own connections page data. Nothing in the app ever reads them back.
- **Root cause:** No $hidden or encrypted cast on the model.
- **Files:** app/Models/SocialAccount.php:15-29; app/Http/Controllers/Admin/CustomerController.php:37; app/Http/Controllers/Settings/ConnectionsController.php:24; app/Http/Middleware/HandleInertiaRequests.php:120
- **Reproduction:** Open /admin/customers/{id} for a social-login customer and read props.customer.social_accounts.
- **Evidence:** docs/forensic-audit/tests/AccessControlAuditTest.php S02 and S03 FAIL.
- **Business impact:** Privacy exposure of customers to staff.
- **Security impact:** Provider tokens readable by staff and stored in plaintext in backups.
- **Likelihood:** Medium
- **Recommended remediation (not applied):** Stop storing the tokens, or hide and encrypt them.
- **Verification:** S02 and S03 pass; social login still works.
- **Deployment implication:** Consider clearing stored tokens with an approved migration.
- **Priority:** P1

### SEC-005: Changing an admin password does not log out other sessions

- **Module:** Admin auth
- **Category:** Missing Security Control
- **Severity:** MEDIUM
- **Confidence:** High (code reading, not run)
- **Description:** If an admin password is stolen and then changed, the thief's open session keeps working, including with remember-me.
- **Root cause:** No session check tied to the admin guard, and the remember token is not rotated on change.
- **Files:** routes/admin.php:83; app/Http/Controllers/Admin/AdminController.php:86; app/Http/Controllers/Admin/AdminAuthController.php:32
- **Reproduction:** Log in on two browsers, change the password on one, keep using the other.
- **Evidence:** Code reading only.
- **Business impact:** A compromised admin cannot be evicted by a password change.
- **Security impact:** Higher impact because admin 2FA was declined (ADMIN-2FA-001, accepted, not re-raised).
- **Likelihood:** Low to medium
- **Recommended remediation (not applied):** Compare a password hash per admin session and rotate remember_token on change.
- **Verification:** Add a test with two sessions.
- **Deployment implication:** None.
- **Priority:** P1

### SEC-006: Order tracking returns the whole order record

- **Module:** Order tracking
- **Category:** Confirmed Vulnerability
- **Severity:** MEDIUM
- **Confidence:** High
- **CVSS 4.0:** `CVSS:4.0/AV:N/AC:L/AT:P/PR:N/UI:N/VC:L/VI:N/VA:N/SC:N/SI:N/SA:N`
- **Earlier ID:** FSA-03 sibling
- **Description:** Anyone with the order number and phone (both on the parcel label) receives the buyer's IP address, browser details, email, full address, staff notes and internal history notes, which can name staff.
- **Root cause:** The raw model is passed to the page. Only guest_token is hidden. The confirmation page was fixed with an allow-list (FSA-03); this page was not.
- **Files:** app/Http/Controllers/OrderTrackingController.php:33-52; app/Models/Order.php:52; app/Http/Controllers/LandingPageController.php:520-528
- **Reproduction:** GET /order/track?order_number=...&phone=... and read props.order.
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A08 FAILS.
- **Business impact:** Customer privacy and internal notes exposed to couriers or anyone holding a label.
- **Security impact:** PII disclosure.
- **Likelihood:** Medium
- **Recommended remediation (not applied):** Use an allow-listed payload like OrderConfirmationAccess::payload and customer-safe history text.
- **Verification:** A08 passes; tracking page still renders.
- **Deployment implication:** None.
- **Priority:** P1

### SEC-007: View-only SMS forwarder permission reveals the full token

- **Module:** Admin settings
- **Category:** Confirmed Vulnerability
- **Severity:** MEDIUM
- **Confidence:** High
- **CVSS 4.0:** `CVSS:4.0/AV:N/AC:L/AT:N/PR:H/UI:N/VC:L/VI:H/VA:N/SC:N/SI:N/SA:N`
- **Description:** An admin who may only view this page receives the full forwarder token, which allows posting SMS to the payment matcher, so it combines with SEC-001.
- **Root cause:** The controller sends fullToken regardless of update permission.
- **Files:** app/Http/Controllers/Admin/SmsForwarderSettingsController.php:25; routes/admin.php:198
- **Reproduction:** Open the page with sms_forwarder view only.
- **Evidence:** docs/forensic-audit/tests/AccessControlAuditTest.php S04 FAILS.
- **Business impact:** Payment fraud path for a low-privilege staff account.
- **Security impact:** Credential disclosure.
- **Likelihood:** Low to medium
- **Recommended remediation (not applied):** Send the masked token; reveal only on regenerate or with update permission.
- **Verification:** S04 passes.
- **Deployment implication:** None.
- **Priority:** P1

### SEC-008: A social link planted on an unverified account can survive the real owner's recovery

- **Module:** Customer auth
- **Category:** Potential Risk
- **Severity:** MEDIUM
- **Confidence:** Low. Depends on whether a provider returns an unconfirmed email
- **Description:** An unverified account can be created with a social link. The real owner resets the password and verifies, but the link remains and can still log in.
- **Root cause:** Links are removed only by claimUnverifiedAccount and anonymize.
- **Files:** app/Http/Controllers/Auth/SocialAuthController.php:85,122,131,211; app/Actions/Fortify/CreateNewUser.php:84
- **Reproduction:** Requires a provider returning an unconfirmed email claim. Not reproduced.
- **Evidence:** Code reading only. Accepted residual in the tracker covers pre-fix links; this is a post-fix path.
- **Business impact:** Possible account access.
- **Security impact:** Account takeover if provider behaviour allows it.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Delete non-vouching links when an unverified account is first proven by code or reset.
- **Verification:** Add a test once the rule is decided.
- **Deployment implication:** Owner decision.
- **Priority:** P2

### SEC-010: Weaker password policy and no per-account login limit for admins

- **Module:** Admin auth
- **Category:** Missing Security Control
- **Severity:** MEDIUM
- **Confidence:** High
- **Description:** Admins may use 8-character passwords, while customers in production need stronger ones. Login limits key on email+IP and IP, so a slow attack from many IPs on one admin is not limited. Failed admin logins are not logged. Self-edit needs no current password.
- **Root cause:** Admin login is hand-rolled, outside the Fortify policy.
- **Files:** app/Http/Controllers/Admin/AdminController.php:45,76; app/Console/Commands/CreateMotherAdminCommand.php:57; app/Providers/AppServiceProvider.php:135-141; app/Http/Controllers/Admin/AdminAuthController.php:32-34
- **Reproduction:** Code reading.
- **Evidence:** Not run.
- **Business impact:** Admin takeover allows full store control.
- **Security impact:** Matters more because admin 2FA was declined (accepted, not re-raised).
- **Likelihood:** Low to medium
- **Recommended remediation (not applied):** Apply the customer password policy, add an email-only limit, log failures.
- **Verification:** Add tests.
- **Deployment implication:** Existing admins keep their passwords until changed.
- **Priority:** P1

### SEC-017: composer audit lists new Symfony advisories

- **Module:** Dependencies
- **Category:** Potential Risk
- **Severity:** MEDIUM
- **Confidence:** High for presence. Exploitability in this app not assessed per advisory yet
- **Description:** Advisories dated 2026-11-04 affect symfony/http-foundation, http-kernel, routing, serializer and yaml at 7.4.21. Several need components this app may not use, such as access_control rules or trusted proxy shortcuts. nunomaduro/pao is also abandoned.
- **Root cause:** Dependencies are behind the newly published fixes.
- **Files:** composer.lock
- **Reproduction:** composer audit
- **Evidence:** docs/forensic-audit/evidence/phase2/composer_audit_summary.txt. Production npm audit: 0 vulnerabilities.
- **Business impact:** Depends on the advisory.
- **Security impact:** Host and URL related advisories overlap SEC-011.
- **Likelihood:** Unknown
- **Recommended remediation (not applied):** Update Symfony patch versions in a separate approved change and run the full suite.
- **Verification:** composer audit clean.
- **Deployment implication:** Dependency change needs owner approval and a full test run.
- **Priority:** P1

### BIZ-007: Admin can cancel orders already with Steadfast; Steadfast orders never reach Shipped

- **Module:** Couriers, orders
- **Category:** Confirmed Functional Bug
- **Severity:** MEDIUM
- **Confidence:** High
- **Earlier ID:** FSA-11, FSA-15 residual
- **Description:** Example: a COD order is sent to Steadfast and the admin cancels it, restoring stock. The courier delivers and collects cash, but the order stays cancelled because it is terminal. Shipped emails can never fire for Steadfast.
- **Root cause:** Admin cancel checks status, not consignment columns. The Steadfast map has no SHIPPED value. shipped_at is never written.
- **Files:** app/Http/Controllers/Admin/OrderController.php:486-509; app/Services/CourierOrderStatusMapper.php:57-67,201; app/Services/CourierSubmissionService.php:215-217; app/Models/Order.php:271
- **Reproduction:** Set a consignment on a pending order, PATCH admin.orders.cancel.
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A06 FAILS.
- **Business impact:** Cash collected on cancelled orders and phantom stock.
- **Security impact:** None.
- **Likelihood:** Medium
- **Recommended remediation (not applied):** Add the consignment checks to admin cancel and record a real shipped state.
- **Verification:** A06 passes.
- **Deployment implication:** Tracker notes the Pathao side of this gap as well.
- **Priority:** P1

### BIZ-008: Refunded orders stay "paid" and keep counting as revenue

- **Module:** Refunds, dashboard
- **Category:** Confirmed Functional Bug
- **Severity:** MEDIUM
- **Confidence:** High
- **Earlier ID:** LOW-08
- **Description:** Example: a ৳1,060 paid order with ৳60 shipping is fully refunded. The refund cap excludes shipping, so the total refunded never reaches collected. Payment stays paid and the dashboard still counts ৳1,060. The dashboard comment claims the opposite. Partial refunds are not subtracted either.
- **Root cause:** paymentRefunded compares against collected including shipping; revenue filters only on PAID and not cancelled.
- **Files:** app/Services/RefundService.php:173; app/Services/RefundCalculator.php:137; app/Http/Controllers/Admin/DashboardController.php:125-136; tests/Feature/OrderRefundTest.php:173
- **Reproduction:** Refund a paid order with shipping above 0.
- **Evidence:** Code reading plus the existing test that asserts PAID stays.
- **Business impact:** Overstated revenue.
- **Security impact:** None.
- **Likelihood:** Certain on refunds
- **Recommended remediation (not applied):** Exclude refunded orders and subtract ledger refunds.
- **Verification:** Add a dashboard test.
- **Deployment implication:** Changes reported revenue figures.
- **Priority:** P1

### BIZ-009: Partial delivery is booked as fully delivered and paid, with no way out

- **Module:** Couriers, refunds
- **Category:** Confirmed Functional Bug
- **Severity:** MEDIUM
- **Confidence:** High (code reading)
- **Description:** A courier partial delivery sets the order delivered and paid for the full total. Undelivered stock is not restored and refunds are refused permanently.
- **Root cause:** No partial state or collected-amount field.
- **Files:** app/Services/CourierOrderStatusMapper.php:37,64,209-212; app/Services/RefundCalculator.php:51; app/Services/OrderDeliveryVerificationService.php:72-74; app/Services/CourierEvidenceChain.php:75-77
- **Reproduction:** Courier reports partial_delivery on a COD order.
- **Evidence:** Code reading.
- **Business impact:** Revenue overstated and stuck orders. Fails closed, so no money leaves wrongly.
- **Security impact:** None.
- **Likelihood:** Low to medium
- **Recommended remediation (not applied):** Add a partial state with collected amount and a mother-admin resolution.
- **Verification:** Add tests.
- **Deployment implication:** Business rule needed.
- **Priority:** P2

### BIZ-010: Loyalty points can be spent twice through a late mobile payment

- **Module:** Loyalty, mobile banking
- **Category:** Confirmed Functional Bug
- **Severity:** MEDIUM
- **Confidence:** High
- **Earlier ID:** FSA-07 regression
- **Description:** Example: with 100 points, start a mobile checkout using them and pay. Wait for the 2-hour hold to end, spend the same points on a COD order, then submit the first TrxID. The grace accepts it. Balance becomes −100 and both orders got the discount.
- **Root cause:** The FSA-07 grace accepts a checkout whose points hold has lapsed; the debit is unguarded.
- **Files:** app/Http/Controllers/MobilePaymentController.php:101,121-133,207-208; app/Models/PendingCheckout.php:56; app/Services/LoyaltyPointsService.php:134
- **Reproduction:** See test.
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A17 FAILS: balance −100.
- **Business impact:** Discount given twice. Requires a real payment, so the loss is bounded.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Keep the hold alive when proof exists, or route these to admin review.
- **Verification:** A17 passes.
- **Deployment implication:** None.
- **Priority:** P1

### BIZ-011: No bot or quantity protection on COD orders

- **Module:** Checkout, abuse
- **Category:** Missing Security Control
- **Severity:** MEDIUM
- **Confidence:** High
- **Description:** A guest can order all stock of a product with any valid-format phone, showing it out of stock until staff cancel. No captcha or phone check exists, and no quantity maximum on the main checkout (landing pages cap at 100). Limits are 10 per 10 minutes per IP and 5 per hour per phone.
- **Root cause:** No business quantity cap and no bot control.
- **Files:** app/Http/Requests/PlaceOrderRequest.php:58; app/Http/Requests/SyncCartRequest.php:45; app/Http/Requests/AddToCartRequest.php:29; app/Http/Controllers/LandingPageController.php:123; app/Providers/AppServiceProvider.php:192-207
- **Reproduction:** POST a guest order with quantity equal to stock.
- **Evidence:** grep for captcha, turnstile and honeypot in app, resources/js, routes, config and composer.json: no results. docs/forensic-audit/tests/BusinessLogicAuditTest.php A16 FAILS on MariaDB: quantity 3000000000 gives a 500. It passes on SQLite, which hides this.
- **Business impact:** Stock denial during sales, staff load, mail to arbitrary addresses.
- **Security impact:** Abuse.
- **Likelihood:** Medium
- **Recommended remediation (not applied):** Per-order limit, per-product pending-COD cap, bot challenge, phone OTP above a threshold.
- **Verification:** Add tests.
- **Deployment implication:** Business choice.
- **Priority:** P1

### BIZ-012: A whole-order refund restocks delivered goods automatically

- **Module:** Refunds, inventory
- **Category:** Potential Risk
- **Severity:** MEDIUM
- **Confidence:** High on behaviour. Business intent needs confirming
- **Description:** Refunding a delivered order returns all units to stock whether or not the goods came back.
- **Root cause:** Restore runs on any move to REFUNDED; only lost_confirmed_at skips it.
- **Files:** app/Models/Order.php:264-267; app/Services/InventoryService.php:133-186
- **Reproduction:** Refund a delivered verified order.
- **Evidence:** Code reading.
- **Business impact:** Phantom stock and overselling.
- **Security impact:** None.
- **Likelihood:** Medium
- **Recommended remediation (not applied):** Ask "goods returned?" on whole-order refunds of delivered orders.
- **Verification:** Add a test.
- **Deployment implication:** Owner decision.
- **Priority:** P2

### BIZ-013: Coupon and duplicate-order rejections are invisible at checkout

- **Module:** Checkout UI
- **Category:** Confirmed Functional Bug
- **Severity:** MEDIUM
- **Confidence:** High (code reading)
- **Description:** If a coupon expires before Place Order, the server rejects it but the page shows nothing: the button resets and the discount still shows.
- **Root cause:** Errors for coupon_code, order_token and items.N.* are not rendered.
- **Files:** app/Http/Controllers/CheckoutController.php:200,238,374; resources/js/pages/checkout/Checkout.tsx:677-680
- **Reproduction:** Deactivate a coupon after applying, then place the order.
- **Evidence:** grep of Checkout.tsx finds no render of these keys. Browser confirmation planned for Phase 14.
- **Business impact:** Lost sales and confusion.
- **Security impact:** None.
- **Likelihood:** Medium
- **Recommended remediation (not applied):** Render these errors and clear the coupon on rejection.
- **Verification:** Browser test.
- **Deployment implication:** None.
- **Priority:** P1

### BIZ-016: Landing page shows different price and delivery than it charges

- **Module:** Landing pages
- **Category:** Confirmed Functional Bug
- **Severity:** MEDIUM
- **Confidence:** High (code reading)
- **Description:** Example: during a flash sale the page shows the regular price but charges the sale price. A product with free delivery from 3 units says "free" for 1 unit but charges shipping. Delivery text is hardcoded as ৳60 and ৳120 regardless of admin rates.
- **Root cause:** Separate client calculation and raw price sent to the page.
- **Files:** resources/js/pages/landing/LandingPage.tsx:123-139,334-335,389-391,949-951; app/Http/Controllers/LandingPageController.php:207-213,566
- **Reproduction:** Run a flash sale on a landing page product.
- **Evidence:** Code reading. Browser confirmation planned.
- **Business impact:** COD customers can be charged more than shown.
- **Security impact:** None.
- **Likelihood:** Medium
- **Recommended remediation (not applied):** Send the resolved price and shipping rules from the server.
- **Verification:** Add a test.
- **Deployment implication:** None.
- **Priority:** P1

### BIZ-027: Steadfast status relies on unverified webhook assumptions with no fallback

- **Module:** Couriers
- **Category:** Potential Risk
- **Severity:** MEDIUM
- **Confidence:** Low. Assumptions unverified
- **Description:** The code admits the signature format and consignment field are unverified. There is no scheduled poll, so a mismatch means COD orders never become delivered without staff action.
- **Root cause:** Webhook only.
- **Files:** app/Http/Controllers/SteadfastWebhookController.php:65,122-130; app/Services/CourierOrderStatusMapper.php:52-53; routes/console.php
- **Reproduction:** Needs one real signed webhook.
- **Evidence:** BLOCKED: real courier calls are forbidden by the directive.
- **Business impact:** Revenue and status lag.
- **Security impact:** None.
- **Likelihood:** Unknown
- **Recommended remediation (not applied):** Verify with one real webhook and add a scheduled poll.
- **Verification:** Live check by owner.
- **Deployment implication:** Release condition.
- **Priority:** P2

### FE-001: Customers cannot page through order history

- **Module:** Account
- **Category:** Confirmed Functional Bug
- **Severity:** MEDIUM
- **Confidence:** High (code reading)
- **Description:** With more than 10 orders, older ones are unreachable from the list. The pagination area is an empty placeholder.
- **Root cause:** Placeholder comment.
- **Files:** routes/account.php:57; resources/js/pages/account/Orders.tsx:288-292
- **Reproduction:** Account with 11 orders.
- **Evidence:** Code reading.
- **Business impact:** Support load.
- **Security impact:** None.
- **Likelihood:** Medium for repeat buyers
- **Recommended remediation (not applied):** Render pagination links.
- **Verification:** Browser check.
- **Deployment implication:** None.
- **Priority:** P2

### REL-001: Tests pass payloads the real website never sends

- **Module:** Test suite
- **Category:** Confirmed Functional Bug
- **Severity:** MEDIUM
- **Confidence:** High
- **Description:** BIZ-001 and BIZ-002 survived a 2,026-test green suite because tests post bundle_id or omit items, unlike Checkout.tsx. There are no browser tests in the repo, and the suite cannot see frontend-backend mismatches such as BIZ-003.
- **Root cause:** No contract or browser coverage for checkout.
- **Files:** tests/Feature/FlashSalePricingTest.php:148-150; tests/Feature/BundleCheckoutTest.php:29; tests/Feature/BundleAutoAssembleTest.php:176
- **Reproduction:** Compare with Checkout.tsx:670-674.
- **Evidence:** A02 and A03 reproduce with the real payload.
- **Business impact:** A green suite overstates safety; deploy is gated only by it.
- **Security impact:** None.
- **Likelihood:** Certain
- **Recommended remediation (not applied):** Add checkout tests that post the exact UI payload, plus a small browser smoke test.
- **Verification:** A-series pass in CI.
- **Deployment implication:** New test dependency needs approval.
- **Priority:** P1

### SEC-020: A forwarder token is committed in the repository

- **Module:** Secrets
- **Category:** Potential Risk
- **Severity:** MEDIUM (HIGH if still current)
- **Confidence:** High that it exists. Unknown whether it is live
- **Description:** A 64-hex SMS forwarder token (redacted ****b7b0) sits in a tracked local agent settings file. gitleaks did not flag it, which corrects my earlier statement that no secrets were in git history. The repo is private and the file returns 403 on the live site, but it is shipped to the server. If it is the current token, anyone with repo access can post fake payment SMS directly, which is SEC-001 without needing a phone.
- **Root cause:** A local permissions file was committed and not excluded from rsync.
- **Files:** .claude/settings.local.json:82; .github/workflows/deploy.yml:193-203
- **Reproduction:** Compare with the current token in Admin › SMS Forwarder. I did not read .env for this: a subagent tried once and was denied, and neither of us retried.
- **Evidence:** gh repo view: PRIVATE. Live GET /.claude/settings.local.json: 403. Value length 64.
- **Business impact:** Payment fraud route if current.
- **Security impact:** Credential exposure to anyone with repo or server file access.
- **Likelihood:** Unknown
- **Recommended remediation (not applied):** Regenerate the token, untrack the file, add it to .gitignore.
- **Verification:** File absent from git ls-files.
- **Deployment implication:** Regenerating cuts the phone off until it is updated.
- **Priority:** P0

### SEC-021: Back button after logout shows account data

- **Module:** Customer privacy
- **Category:** Confirmed Vulnerability
- **Severity:** MEDIUM
- **Confidence:** High. Reproduced in a browser
- **CVSS 4.0:** `CVSS:4.0/AV:P/AC:L/AT:N/PR:N/UI:N/VC:L/VI:N/VA:N/SC:N/SI:N/SA:N`
- **Description:** Inertia keeps page data in browser history. On a shared PC, the next person pressing Back sees the previous customer's orders with no server request.
- **Root cause:** History encryption is off and history is not cleared on logout.
- **Files:** config/inertia.php; resources/js/components/layout/AccountLayout.tsx:137
- **Reproduction:** View orders, log out, press Back.
- **Evidence:** docs/forensic-audit/evidence/phase2/back_after_logout_browser.txt: after logout and Back, path /account/orders still shows the order number, and history.state holds it.
- **Business impact:** Customer data exposure on shared devices, common in cyber cafes.
- **Security impact:** PII disclosure.
- **Likelihood:** Medium
- **Recommended remediation (not applied):** Enable history encryption and clear on logout.
- **Verification:** Browser check.
- **Deployment implication:** None.
- **Priority:** P1

### BIZ-029: Cancelling the Reject prompt still rejects the return and emails the customer

- **Module:** Returns
- **Category:** Confirmed Functional Bug
- **Severity:** MEDIUM
- **Confidence:** High (code re-read)
- **Description:** An admin clicks Reject on a return, then presses Cancel to back out. The return is rejected anyway and the customer is emailed, with no undo.
- **Root cause:** prompt() null is treated as an empty note.
- **Files:** resources/js/pages/admin/returns/index.tsx:72-78; app/Http/Controllers/Admin/ReturnController.php:131-145
- **Reproduction:** Reject, then Cancel.
- **Evidence:** Code re-read by lead auditor. Browser check planned.
- **Business impact:** Valid returns refused by accident.
- **Security impact:** None.
- **Likelihood:** Medium
- **Recommended remediation (not applied):** Return early on null.
- **Verification:** Browser check.
- **Deployment implication:** None.
- **Priority:** P1

### BIZ-030: COD confirmation says "Total Paid"

- **Module:** Order confirmation
- **Category:** Confirmed Functional Bug
- **Severity:** MEDIUM
- **Confidence:** High (code re-read)
- **Description:** Every order, including unpaid COD, shows "Total Paid" with nothing saying to pay the courier. Customers may refuse payment at the door.
- **Root cause:** Hardcoded label.
- **Files:** resources/js/pages/order/OrderConfirmation.tsx:154
- **Reproduction:** Place a COD order.
- **Evidence:** Code re-read.
- **Business impact:** Courier disputes and refusals.
- **Security impact:** None.
- **Likelihood:** High
- **Recommended remediation (not applied):** Label by payment status and state the amount due.
- **Verification:** Browser check.
- **Deployment implication:** None.
- **Priority:** P1

### BIZ-031: Checkout shows payment logos the shop does not accept

- **Module:** Checkout
- **Category:** Confirmed Functional Bug
- **Severity:** MEDIUM
- **Confidence:** High (code re-read)
- **Description:** SSLCommerz, Visa and Mastercard are shown on checkout pages under a placeholder comment, but only COD and mobile banking are accepted.
- **Root cause:** Placeholder left in.
- **Files:** resources/js/components/layout/CheckoutLayout.tsx:24-40; app/Http/Requests/PlaceOrderRequest.php
- **Reproduction:** Open checkout.
- **Evidence:** Code re-read.
- **Business impact:** Misleading at the point of purchase.
- **Security impact:** None.
- **Likelihood:** Certain
- **Recommended remediation (not applied):** Show only enabled methods.
- **Verification:** Browser check.
- **Deployment implication:** None.
- **Priority:** P1

### BIZ-032: Live terms and FAQ contradict how the shop works

- **Module:** Legal content
- **Category:** Confirmed Functional Bug
- **Severity:** MEDIUM
- **Confidence:** High. Phrases confirmed on the live pages. Legal effect is for the owner or a lawyer
- **Description:** Live terms say payment must be completed before dispatch, though COD is the default. Live FAQ says online payment is coming soon (bKash and Nagad work), offers cancellation within 1 hour (code has no such rule), and promises email or SMS confirmation (no customer SMS sending exists). Seed text also promises an immediate full refund, while shipping is never refundable.
- **Root cause:** Seed copy never reconciled with features.
- **Files:** database/seeders/PageSeeder.php:109,140,143,146,151,168,176,179; resources/js/components/layout/Footer.tsx:30,267
- **Reproduction:** GET /terms and /faq.
- **Evidence:** Live GETs on 2026-11-05 contain the phrases.
- **Business impact:** Written promises customers can hold the shop to.
- **Security impact:** None.
- **Likelihood:** Certain
- **Recommended remediation (not applied):** Rewrite the pages to match actual behaviour.
- **Verification:** Re-read live pages.
- **Deployment implication:** No admin editor exists for Page (XSS-SINK-GAP-001), so edits need a migration or seeder.
- **Priority:** P1

### BIZ-033: Home page ratings count unmoderated reviews

- **Module:** Reviews
- **Category:** Confirmed Functional Bug
- **Severity:** MEDIUM
- **Confidence:** High
- **Description:** One unapproved 1-star review changes the rating on the home page immediately. Rejected reviews keep counting too. Other pages filter correctly, so the same product can show two ratings.
- **Root cause:** Home queries omit the is_approved filter.
- **Files:** app/Http/Controllers/HomeController.php:68-69,81-82,98-99
- **Reproduction:** See test.
- **Evidence:** docs/forensic-audit/tests/PerformanceAuditTest.php P02.
- **Business impact:** Moderation bypassed on the most-viewed page.
- **Security impact:** None.
- **Likelihood:** High
- **Recommended remediation (not applied):** Filter on is_approved.
- **Verification:** P02 passes.
- **Deployment implication:** None.
- **Priority:** P1

### BIZ-034: No return, refund or shipping policy pages; seller identity missing

- **Module:** Legal content
- **Category:** Informational
- **Severity:** MEDIUM
- **Confidence:** High for what exists. Applicability needs a lawyer
- **Description:** Live /return-policy, /refund-policy and /shipping-policy return 404. Only privacy, terms and FAQ exist. No legal entity, trade licence or full address in code. No policy acknowledgement at checkout. Privacy text omits stored IP, browser and saved abandoned-checkout phone numbers. Owner or lawyer to compare with the Digital Commerce Operation Guidelines 2021. This is not legal advice.
- **Root cause:** Only three pages seeded.
- **Files:** database/seeders/PageSeeder.php:25-31,71; resources/js/components/layout/navigation.ts:38-39; app/Models/Order.php:215-216; resources/js/pages/checkout/Checkout.tsx:542-557
- **Reproduction:** GET the URLs.
- **Evidence:** Live GETs on 2026-11-05.
- **Business impact:** Customer trust and possible regulatory exposure.
- **Security impact:** Privacy disclosure gap.
- **Likelihood:** Certain
- **Recommended remediation (not applied):** Add the pages and disclosures after legal review.
- **Verification:** Re-read live pages.
- **Deployment implication:** Owner decision.
- **Priority:** P1

### DB-002: No retention or pruning for growing tables, including personal data

- **Module:** Database, privacy
- **Category:** Missing Security Control
- **Severity:** MEDIUM
- **Confidence:** High
- **Earlier ID:** MED-20
- **Description:** incomplete_orders (guest name and phone), received_sms (raw text), activity_log, failed_jobs, job_batches and guest carts grow forever. activity log retention is set to 365 days, but activitylog:clean never runs. If the forwarder phone relays all SMS, unrelated personal messages and OTPs are stored too (filter unverified).
- **Root cause:** No retention policy in code.
- **Files:** routes/console.php; config/activitylog.php:16; app/Console/Commands/ClearResetsCommand.php:20; database/migrations (create_incomplete_orders_table):22
- **Reproduction:** grep for prune commands finds none of these.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Business impact:** Storage growth on shared hosting.
- **Security impact:** Personal data kept indefinitely.
- **Likelihood:** Certain over time
- **Recommended remediation (not applied):** Decide periods, then schedule pruning.
- **Verification:** Add tests.
- **Deployment implication:** Owner decision. Pending-checkout Tier 2 is a recorded deferral (MED-20).
- **Priority:** P2

### PERF-001: Product listing runs a query for every variant

- **Module:** Performance
- **Category:** Confirmed Performance Issue
- **Severity:** MEDIUM
- **Confidence:** High. Measured
- **Description:** 12 products with 6 variants each took 101 queries on /products. A 30-variant product page costs about 90 extra, and each variant adds flash-sale lookups.
- **Root cause:** The appended image accessor lazy-loads media during serialization.
- **Files:** app/Models/ProductVariant.php:37,50-54; app/Http/Controllers/ProductListingController.php:43-49; app/Http/Controllers/HomeController.php:58,131; app/Http/Controllers/ProductController.php:24,90,112
- **Reproduction:** See test.
- **Evidence:** docs/forensic-audit/tests/PerformanceAuditTest.php P01 FAILS: 101 queries.
- **Business impact:** Slow pages on shared hosting, worse in sales.
- **Security impact:** None.
- **Likelihood:** Certain with variant products
- **Recommended remediation (not applied):** Eager-load variants.media and resolve flash prices in a batch.
- **Verification:** P01 passes.
- **Deployment implication:** None.
- **Priority:** P1

### PERF-003: Icon registry and admin layout load on the customer home page

- **Module:** Performance
- **Category:** Confirmed Performance Issue
- **Severity:** MEDIUM
- **Confidence:** High (build manifest walked by module reviewer)
- **Description:** The home page chain is 75 JS chunks, about 367 KB gzip. Admin layouts are in the main chunk. Lucide dynamic icons account for 1,414 of the 1,723 entries in the 653 KB public manifest.
- **Root cause:** Static imports.
- **Files:** resources/js/lib/category-icons.tsx:2; resources/js/pages/Home.tsx:39; resources/js/app.tsx:10,12
- **Reproduction:** Inspect public/build/manifest.json.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run. Live manifest size 653,360 bytes confirmed.
- **Business impact:** Slow first load on phones.
- **Security impact:** None; manifest also names admin pages.
- **Likelihood:** Certain
- **Recommended remediation (not applied):** Lazy-load icons and the admin layout.
- **Verification:** Re-measure.
- **Deployment implication:** None.
- **Priority:** P2

### PERF-004: Uploaded images are served at original size

- **Module:** Performance
- **Category:** Confirmed Performance Issue
- **Severity:** MEDIUM
- **Confidence:** High for the code. Live sizes not measured
- **Description:** A 5 MB photo can show as a small card thumbnail. No resizing library and no srcset.
- **Root cause:** Raw store().
- **Files:** app/Http/Controllers/Admin/ProductController.php; app/Http/Controllers/Admin/BlogController.php:47; app/Http/Requests/Admin/ProductRequest.php:17-18
- **Reproduction:** Upload a large photo.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run. Lazy loading is present.
- **Business impact:** Data cost and slow pages.
- **Security impact:** None.
- **Likelihood:** High
- **Recommended remediation (not applied):** Generate WebP thumbnails.
- **Verification:** Measure image bytes.
- **Deployment implication:** New dependency needs approval.
- **Priority:** P2

### BE-009: "Today" figures and invoices use UTC, not Dhaka time

- **Module:** Dashboard, invoices
- **Category:** Confirmed Functional Bug
- **Severity:** MEDIUM
- **Confidence:** High (code re-read)
- **Description:** An order at 03:00 Dhaka on 5 Nov is invoiced as 4 Nov. Today's revenue, orders and visitors reset at 06:00 Dhaka.
- **Root cause:** app.timezone is UTC and day boundaries are not converted.
- **Files:** config/app.php:68; app/Http/Controllers/Admin/DashboardController.php:26-33,46-50; app/Http/Middleware/TrackDailyVisit.php:62; resources/views/invoices/order.blade.php:268
- **Reproduction:** See example.
- **Evidence:** Code re-read.
- **Business impact:** Wrong daily numbers for the owner; wrong invoice dates.
- **Security impact:** None.
- **Likelihood:** Certain
- **Recommended remediation (not applied):** Use Asia/Dhaka boundaries for display.
- **Verification:** Add tests.
- **Deployment implication:** Keep stored values in UTC.
- **Priority:** P1

### FE-005: A server error empties a logged-in customer's cart

- **Module:** Cart
- **Category:** Confirmed Functional Bug
- **Severity:** MEDIUM
- **Confidence:** High. Reproduced in a browser with production error handling
- **Description:** The 5xx page sends no shared props, so the client treats it as logout, clears the cart, and syncs an empty list to the server.
- **Root cause:** Missing auth is treated as logged out.
- **Files:** app/Providers/AppServiceProvider.php:108-117; resources/js/app.tsx:125,128,267,282; app/Support/CartManager.php:343-345
- **Reproduction:** Log in with an item in the cart, then click a page that returns a 500 with APP_DEBUG off.
- **Evidence:** docs/forensic-audit/evidence/phase2/cart_loss_on_500_browser.txt: POST /cart/sync sent items:[] and cart_items went from 1 to 0. BE-001 inputs and any real server bug can trigger this.
- **Business impact:** Lost carts during any server error.
- **Security impact:** None.
- **Likelihood:** Medium, on every server error
- **Recommended remediation (not applied):** Run applyLogout only when auth is present and null.
- **Verification:** Repeat browser test.
- **Deployment implication:** None.
- **Priority:** P1

### FE-006: Product pages have no search or share tags in the HTML

- **Module:** SEO
- **Category:** Confirmed Functional Bug
- **Severity:** MEDIUM
- **Confidence:** High. Live page checked
- **Description:** A live product page returns title "ShoppyDrive", no h1, an empty app div, and no og tags, canonical or JSON-LD. Facebook previews show no product. SSR is never built by deploy, so the SSR notes in CLAUDE.md do not apply in production.
- **Root cause:** Client-only rendering; deploy runs npm run build only.
- **Files:** .github/workflows/deploy.yml:160; config/inertia.php:19; resources/js/pages/catalog/ProductDetail.tsx:505-513
- **Reproduction:** curl a product page.
- **Evidence:** Live GET 2026-11-05: generic title, 0 meta tags, 0 h1.
- **Business impact:** Weak organic and Facebook traffic, important for a Bangladesh store.
- **Security impact:** None.
- **Likelihood:** Certain
- **Recommended remediation (not applied):** Output tags from Blade for product pages.
- **Verification:** curl shows tags.
- **Deployment implication:** Shared host cannot run the SSR daemon.
- **Priority:** P1

### FE-007: Session timeout on landing-page checkout loses the form and may 405

- **Module:** Landing pages
- **Category:** Confirmed Functional Bug
- **Severity:** MEDIUM
- **Confidence:** Medium. Needs a browser check
- **Description:** After 2 hours the 419 page replaces the form, and its Refresh reloads a POST-only URL. Main checkout also loses the address.
- **Root cause:** Error page replaces the component.
- **Files:** routes/web.php:66; resources/js/pages/errors/ErrorPage.tsx:50-56,219; config/session.php:35
- **Reproduction:** Leave open, submit, click Refresh.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Business impact:** Lost orders from ad traffic.
- **Security impact:** None.
- **Likelihood:** Medium
- **Recommended remediation (not applied):** Refresh the token and resubmit, or keep values.
- **Verification:** Browser check.
- **Deployment implication:** None.
- **Priority:** P2

### FE-008: Google or Facebook login discards the guest cart

- **Module:** Cart
- **Category:** Confirmed Functional Bug
- **Severity:** MEDIUM
- **Confidence:** Medium. Not run, real OAuth is out of scope
- **Description:** Form login merges the guest cart. Social login is a full page load with no merge, so the cart empties at the moment of purchase.
- **Root cause:** First-load path does not merge.
- **Files:** resources/js/app.tsx:157,248,285-295; resources/js/pages/auth/login.tsx:174,205
- **Reproduction:** Guest cart then Google login.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Business impact:** Lost carts.
- **Security impact:** None.
- **Likelihood:** Medium
- **Recommended remediation (not applied):** Merge on first load after social login.
- **Verification:** Browser check with a fake login.
- **Deployment implication:** None.
- **Priority:** P2

### INFRA-001: Deploy has no maintenance mode and is not atomic

- **Module:** Deployment
- **Category:** Potential Risk
- **Severity:** MEDIUM
- **Confidence:** High
- **Description:** rsync writes in place, then migrate runs, so new code runs before its schema. public/storage is removed by --delete until recreated, so images break briefly. Old chunks are deleted and there is no vite:preloadError handler.
- **Root cause:** No release directory.
- **Files:** .github/workflows/deploy.yml:168,191-204,236-237,257
- **Reproduction:** Deploy with a schema change during checkout.
- **Evidence:** grep "artisan down": none. Not exercised.
- **Business impact:** Errors for customers during each deploy.
- **Security impact:** None.
- **Likelihood:** Certain per deploy, brief
- **Recommended remediation (not applied):** Use artisan down or a release symlink, and expand-first migrations.
- **Verification:** Staged deploy.
- **Deployment implication:** Tracker C2, C3 and Tier 2 are open.
- **Priority:** P1

### INFRA-002: A broken release may not be fixable by pushing, and there is no rollback

- **Module:** Deployment
- **Category:** Potential Risk
- **Severity:** MEDIUM
- **Confidence:** High
- **Description:** The pre-deploy check boots the old code. If it crashes on boot, the check exits before rsync, so the fix never lands. There is no workflow_dispatch and no general runbook.
- **Root cause:** The check depends on the release it replaces.
- **Files:** .github/workflows/deploy.yml:3-6,115,172-178,242-250,264-265
- **Reproduction:** A boot-crashing release.
- **Evidence:** Code reading. Not exercised.
- **Business impact:** Longer outage.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Clear stale caches first or allow a bypass, and write a runbook.
- **Verification:** Drill on a staging copy.
- **Deployment implication:** Modifying deploy.yml needs owner approval.
- **Priority:** P1

### INFRA-003: Migrations are never run on MariaDB before production

- **Module:** CI
- **Category:** Missing Security Control
- **Severity:** MEDIUM
- **Confidence:** High
- **Description:** CI uses SQLite only. The first real MariaDB run of each migration is production with --force, and MariaDB DDL does not roll back.
- **Root cause:** No database service in CI.
- **Files:** .github/workflows/deploy.yml:34,69-70,257; .github/workflows/tests.yml:45-48
- **Reproduction:** Inspect the workflows.
- **Evidence:** In this audit, MariaDB 11.8.3 ran the suite subset and race files without schema errors. A16 only failed on MariaDB.
- **Business impact:** Half-migrated production.
- **Security impact:** None.
- **Likelihood:** Low per migration, high impact
- **Recommended remediation (not applied):** Add a gated MariaDB CI job.
- **Verification:** CI green.
- **Deployment implication:** Needs approval.
- **Priority:** P1

### OPS-002: If cron or the queue stops, nobody is told and customer mail stops

- **Module:** Operations
- **Category:** Missing Security Control
- **Severity:** MEDIUM
- **Confidence:** High
- **Description:** A stopped queue creates no failed rows, so the dashboard stays at 0. Order mail and Pathao updates stop while the site looks healthy.
- **Root cause:** No heartbeat.
- **Files:** routes/console.php:19-47; app/Http/Controllers/Admin/DashboardController.php:110-112; app/Console/Commands/DrainQueueCommand.php:81-86,118
- **Reproduction:** Disable cron.
- **Evidence:** Code reading. Cron state BLOCKED.
- **Business impact:** Silent customer mail loss.
- **Security impact:** None.
- **Likelihood:** Medium on shared hosting
- **Recommended remediation (not applied):** Show last-drain time and oldest job age; add a dead-man ping.
- **Verification:** Test by stopping the worker locally.
- **Deployment implication:** Release condition.
- **Priority:** P1

### OPS-003: A short mail outage fails queued mail, and recovery is one click per row

- **Module:** Operations
- **Category:** Potential Risk
- **Severity:** MEDIUM
- **Confidence:** High for the code. Not exercised
- **Description:** Three tries with 30s backoff are used up in a few minutes (estimate), and staff must retry each mail. mail.timeout is null, so a hung SMTP uses up the job time.
- **Root cause:** Short retry window.
- **Files:** app/Console/Commands/DrainQueueCommand.php:42-43,163-164; config/mail.php:48; app/Http/Controllers/Admin/FailedJobController.php:50-62
- **Reproduction:** Refuse SMTP.
- **Evidence:** Code reading.
- **Business impact:** Customers miss confirmations.
- **Security impact:** None.
- **Likelihood:** Medium
- **Recommended remediation (not applied):** Longer backoff, a 15s SMTP timeout, retry-all.
- **Verification:** Local SMTP-down test.
- **Deployment implication:** None.
- **Priority:** P1

### OPS-004: Newsletter has no send-rate limit and shares the order mail account and queue

- **Module:** Operations
- **Category:** Potential Risk
- **Severity:** MEDIUM
- **Confidence:** Medium. Host limits unverified
- **Description:** A large send can use up the host mail cap, then order mail is rejected, and order mail waits behind chunks. No List-Unsubscribe header.
- **Root cause:** No pacing or separate queue.
- **Files:** app/Jobs/SendNewsletterChunk.php:64-66; app/Http/Controllers/Admin/NewsletterController.php:30,136-150; app/Console/Commands/DrainQueueCommand.php:49,61; app/Mail/NewsletterCustomMail.php:24-27
- **Reproduction:** Send to a big list.
- **Evidence:** Code reading.
- **Business impact:** Lost order mail and worse deliverability.
- **Security impact:** None.
- **Likelihood:** Medium
- **Recommended remediation (not applied):** Throttle, separate queue, add the header.
- **Verification:** Add tests.
- **Deployment implication:** Owner to confirm host cap.
- **Priority:** P2

### OPS-005: A dead forwarder phone disables mobile payment with a misleading message

- **Module:** Operations
- **Category:** Missing Security Control
- **Severity:** MEDIUM
- **Confidence:** High
- **Description:** If the phone is off, customers who paid are told their ID was not found, and no one is alerted. The sidebar badge stays at 0 because nothing arrives. Single point of failure.
- **Root cause:** No liveness signal.
- **Files:** app/Http/Controllers/MobilePaymentController.php:133,161,298-300,339-344; app/Http/Controllers/Admin/ReceivedSmsController.php:46-48
- **Reproduction:** Turn the phone off.
- **Evidence:** Code reading. Phone behaviour unverified.
- **Business impact:** Paid customers with no order.
- **Security impact:** None.
- **Likelihood:** Medium
- **Recommended remediation (not applied):** Heartbeat, alert, and a "not received yet" message.
- **Verification:** Add tests.
- **Deployment implication:** Needs an app or phone-side change.
- **Priority:** P1

### OPS-006: Abandoned-cart mail has no opt-out or cap and reaches deleted accounts

- **Module:** Operations, privacy
- **Category:** Confirmed Functional Bug
- **Severity:** MEDIUM
- **Confidence:** High (code reading)
- **Description:** Three checkout sessions make three mails. After a cron outage every old row is mailed at once. A customer who deletes their account is still mailed. There is no unsubscribe link.
- **Root cause:** Dedupe per row, no age limit, no anonymized check.
- **Files:** app/Console/Commands/RemindAbandonedCarts.php:19-23; app/Jobs/AbandonedCartReminderJob.php:59; resources/views/emails/cart/abandoned.blade.php:1-25; app/Models/User.php:188; resources/js/pages/checkout/Checkout.tsx:517-525
- **Reproduction:** See the example.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Business impact:** Spam complaints against the account that sends order mail.
- **Security impact:** Contact after deletion.
- **Likelihood:** Medium
- **Recommended remediation (not applied):** Opt-out, cooldown, 72h ceiling, skip anonymized.
- **Verification:** Add tests.
- **Deployment implication:** None.
- **Priority:** P1

### SEC-009: Customer email can be changed without the password and without notifying the old address

- **Module:** Customer account
- **Category:** Missing Security Control
- **Severity:** LOW
- **Confidence:** High
- **Description:** A hijacked session can change the email and then reset the password, making the takeover permanent.
- **Root cause:** No current_password rule and no notice to the old address.
- **Files:** routes/settings.php:12; app/Http/Controllers/Settings/ProfileController.php:33-36
- **Reproduction:** PATCH settings/profile with a new email.
- **Evidence:** Exercised during S01.
- **Business impact:** Permanent account loss for a customer.
- **Security impact:** Weak account recovery.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Require the current password and notify the old address.
- **Verification:** Add a test.
- **Deployment implication:** None.
- **Priority:** P2

### SEC-011: Password reset link is built from the request Host

- **Module:** Customer auth
- **Category:** Potential Risk
- **Severity:** LOW
- **Confidence:** High for the code. Not reachable from outside in my live checks
- **Description:** The app itself would email a reset link pointing at a host chosen by the requester. On the live site, requests with an unknown Host were dropped by the CDN (connection reset), so this is defence in depth unless the origin can be reached directly.
- **Root cause:** No trustHosts or forced root URL.
- **Files:** bootstrap/app.php (no trustHosts); app/Http/Middleware/RedirectToCanonicalDomain.php:25-31
- **Reproduction:** POST /forgot-password with Host: evil.example.
- **Evidence:** docs/forensic-audit/tests/AccessControlAuditTest.php S05 FAILS in the app. Live: two GETs with unknown Host got connection reset.
- **Business impact:** Reset token could reach an attacker if the origin is exposed.
- **Security impact:** Token leak.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Add trustHosts or force the root URL.
- **Verification:** S05 passes.
- **Deployment implication:** None.
- **Priority:** P2

### SEC-012: Coupon check reveals coupon details and whether a phone has used it

- **Module:** Coupons
- **Category:** Confirmed Vulnerability
- **Severity:** LOW
- **Confidence:** High
- **CVSS 4.0:** `CVSS:4.0/AV:N/AC:L/AT:N/PR:N/UI:N/VC:L/VI:N/VA:N/SC:N/SI:N/SA:N`
- **Description:** Anonymous /coupon/validate returns the whole coupon record (usage count, limits, dates) and different messages for disabled, future and expired codes. With a known limited code, guests can also test whether a phone number has used it.
- **Root cause:** Whole model returned; preview reuses the per-phone check.
- **Files:** routes/commerce.php:70,95-96,111,132-133
- **Reproduction:** POST /coupon/validate.
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A09 FAILS.
- **Business impact:** Reveals campaign details.
- **Security impact:** Minor information leak; phone-usage oracle limited by throttle:coupon-check.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Return only code, type, value, eligible_keys and one generic error.
- **Verification:** A09 passes.
- **Deployment implication:** Check Cart.tsx and Checkout.tsx still have what they need.
- **Priority:** P2

### SEC-013: No HSTS or CSP on the live site; API answers over plain HTTP

- **Module:** Transport
- **Category:** Missing Security Control
- **Severity:** LOW
- **Confidence:** High
- **Earlier ID:** MED-16, MED-18A
- **Description:** Live headers contain no Strict-Transport-Security, Content-Security-Policy or Permissions-Policy. POST http://shoppydrive.com/api/sms/receive answered 401 directly instead of redirecting, so a misconfigured forwarder would send its token unencrypted.
- **Root cause:** SecurityHeaders sends three headers only. RedirectToHttps is on the web group only.
- **Files:** app/Http/Middleware/SecurityHeaders.php:24-28; bootstrap/app.php:50
- **Reproduction:** curl -I https://shoppydrive.com/
- **Evidence:** docs/forensic-audit/evidence/phase2/live_headers.txt; live POST result recorded in session 1.
- **Business impact:** Lower protection against downgrade and script injection.
- **Security impact:** Defence in depth.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Enable HSTS, refuse plain-HTTP API calls, add a CSP in report-only first.
- **Verification:** Re-check headers.
- **Deployment implication:** Tracker MED-16/MED-18A record this as a pending decision, not an accepted risk.
- **Priority:** P2

### SEC-014: Customer 2FA can be enrolled but is never asked for at login

- **Module:** Customer auth
- **Category:** Potential Risk
- **Severity:** LOW
- **Confidence:** High
- **Description:** Settings tells customers 2FA is enabled, but login never challenges them, so they have false assurance.
- **Root cause:** RedirectsIfTwoFactorAuthenticatable was removed on purpose while enrolment stayed.
- **Files:** app/Providers/FortifyServiceProvider.php:200-220; config/fortify.php:151
- **Reproduction:** Enable 2FA, log out, log in: no challenge.
- **Evidence:** Code reading.
- **Business impact:** Misleading security claim.
- **Security impact:** No second factor despite the screen saying so.
- **Likelihood:** Certain for anyone who enrols
- **Recommended remediation (not applied):** Remove enrolment or enforce the challenge.
- **Verification:** Add a test.
- **Deployment implication:** Separate from ADMIN-2FA-001.
- **Priority:** P2

### SEC-015: Register, login and forgot-password reveal whether an email exists

- **Module:** Customer auth
- **Category:** Confirmed Vulnerability
- **Severity:** LOW
- **Confidence:** High
- **CVSS 4.0:** `CVSS:4.0/AV:N/AC:L/AT:N/PR:N/UI:N/VC:L/VI:N/VA:N/SC:N/SI:N/SA:N`
- **Description:** Messages confirm an email is registered and which provider it uses.
- **Root cause:** Provider hints and the default broker message.
- **Files:** app/Actions/Fortify/CreateNewUser.php:84; app/Providers/FortifyServiceProvider.php:83; app/Http/Controllers/Auth/SocialAuthController.php:106
- **Reproduction:** Submit a known email.
- **Evidence:** Code reading.
- **Business impact:** Customer list can be probed slowly.
- **Security impact:** Enumeration, slowed by throttles.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Generic forgot-password response. Provider hints are an owner trade-off.
- **Verification:** Add a test.
- **Deployment implication:** None.
- **Priority:** P3

### SEC-018: Some admin uploads have no size cap

- **Module:** Uploads
- **Category:** Missing Security Control
- **Severity:** LOW
- **Confidence:** High
- **Description:** Category, sub-category, bundle and banner images check type but set no maximum size. Limited only by the PHP upload limit, which could not be checked.
- **Root cause:** RasterImageOrUrl sets no max.
- **Files:** app/Http/Requests/Admin/CategoryRequest.php:70; SubCategoryRequest.php:31; ProductBundleRequest.php:24; app/Http/Controllers/Admin/PromotionalBannerController.php:45,77
- **Reproduction:** Upload a large image.
- **Evidence:** Code reading.
- **Business impact:** Disk use on shared hosting.
- **Security impact:** Admin only.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Add max rules.
- **Verification:** Add a test.
- **Deployment implication:** None.
- **Priority:** P3

### BIZ-014: A retried checkout puts purchased items back in the cart

- **Module:** Checkout
- **Category:** Confirmed Functional Bug
- **Severity:** LOW
- **Confidence:** High
- **Description:** No duplicate order is made, but after a network retry the customer sees the bought items in the cart again. With a limit-1 coupon the retry returns a hidden error instead.
- **Root cause:** Replay check runs after the cart sync.
- **Files:** app/Http/Controllers/CheckoutController.php:157-163,188-201,214
- **Reproduction:** Submit the same order_token twice.
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A12 FAILS.
- **Business impact:** Confusion and possible repeat purchases.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Check the replay key first, as LandingPageController already does.
- **Verification:** A12 passes.
- **Deployment implication:** None.
- **Priority:** P2

### BIZ-015: Per-customer coupon limits do not hold across guest and account

- **Module:** Coupons
- **Category:** Missing Security Control
- **Severity:** LOW
- **Confidence:** High
- **Description:** A limit-1 coupon used while logged in can be used again as a guest with the same phone. Any other valid-format phone works too. Only max_uses holds.
- **Root cause:** Guest limits key on an unverified phone; account and guest uses are not cross-counted.
- **Files:** app/Models/Coupon.php:185,192,215-223; app/Services/CouponService.php:66,73-80
- **Reproduction:** See test.
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A13 FAILS: used_count 2.
- **Business impact:** Extra discounts.
- **Security impact:** None.
- **Likelihood:** Medium for popular coupons
- **Recommended remediation (not applied):** Count by normalized phone too and decide whether limited coupons allow guests.
- **Verification:** A13 passes.
- **Deployment implication:** Business choice. Recent coupon work kept this model.
- **Priority:** P2

### BIZ-017: Incomplete-order conversion ignores flash price and variant ownership

- **Module:** Admin orders
- **Category:** Confirmed Functional Bug
- **Severity:** LOW
- **Confidence:** High (code reading)
- **Description:** Conversion uses the regular price during a flash sale, can pair one product with another product's variant, and the screen hides shipping before creating the order.
- **Root cause:** Separate price logic and missing VariantBelongsToProduct.
- **Files:** app/Http/Controllers/Admin/IncompleteOrderController.php:38,144,169-170; app/Http/Requests/ConvertIncompleteOrderRequest.php; resources/js/pages/admin/orders/incomplete/convert.tsx:237-240,716-721
- **Reproduction:** Convert during a flash sale.
- **Evidence:** Code reading.
- **Business impact:** Billing above the price seen; mismatched lines.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Reuse ManualOrderPricing and add the rule.
- **Verification:** Add a test.
- **Deployment implication:** Fix with BIZ-004.
- **Priority:** P2

### BIZ-018: Referral discount preview ignores Buy Now

- **Module:** Referrals
- **Category:** Confirmed Functional Bug
- **Severity:** LOW
- **Confidence:** High (code reading)
- **Description:** With Buy Now and an empty cart the preview shows no discount, yet the server applies and uses up the one-time benefit.
- **Root cause:** Preview uses the cart subtotal only.
- **Files:** app/Http/Controllers/CheckoutController.php:86,98,447-451; resources/js/pages/checkout/Checkout.tsx:717-722
- **Reproduction:** Eligible referred user, empty cart, Buy Now.
- **Evidence:** Code reading.
- **Business impact:** Benefit consumed unexpectedly; charge is lower than shown.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Compute the preview from cart plus Buy Now.
- **Verification:** Add a test.
- **Deployment implication:** None.
- **Priority:** P3

### BIZ-019: Paying a mobile checkout empties items added later

- **Module:** Cart
- **Category:** Confirmed Functional Bug
- **Severity:** LOW
- **Confidence:** High (code reading)
- **Description:** Items added after staging are deleted when the earlier payment completes.
- **Root cause:** The whole cart is deleted.
- **Files:** app/Http/Controllers/MobilePaymentController.php:266-272; app/Services/OrderService.php:148-153
- **Reproduction:** Stage, add an item, pay.
- **Evidence:** Code reading.
- **Business impact:** Minor annoyance.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Remove only staged lines.
- **Verification:** Add a test.
- **Deployment implication:** None.
- **Priority:** P3

### BIZ-020: Admin price fields have no sanity limits

- **Module:** Admin catalog
- **Category:** Missing Security Control
- **Severity:** LOW
- **Confidence:** High (code reading)
- **Description:** Price 0, flash price above regular price, bundle price 0 and percent coupons above 100 are accepted. A ৳0 total on mobile banking cannot be matched by any SMS.
- **Root cause:** No floors or comparisons.
- **Files:** app/Http/Requests/Admin/ProductRequest.php:58-59,73-74; FlashSaleRequest.php:33; ProductBundleRequest.php:26; CouponRequest.php:29; app/Support/CartManager.php:452-454
- **Reproduction:** Save a flash price above regular.
- **Evidence:** Code reading. Percent is safely capped at app/Models/Coupon.php:147-149.
- **Business impact:** Mistakes reach customers.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Add gt:0, lt price and max:100.
- **Verification:** Add tests.
- **Deployment implication:** None.
- **Priority:** P3

### BIZ-021: District and city are free text and set the delivery fee

- **Module:** Shipping
- **Category:** Missing Security Control
- **Severity:** LOW
- **Confidence:** High (code reading)
- **Description:** A crafted request can claim an inside-Dhaka area for an address elsewhere and pay the lower rate. Limited to the rate difference.
- **Root cause:** No server list check.
- **Files:** app/Http/Requests/PlaceOrderRequest.php:35-36; app/Http/Controllers/LandingPageController.php:120-121; app/Models/Setting.php:84
- **Reproduction:** POST state=Dhaka with a distant address.
- **Evidence:** Code reading.
- **Business impact:** Small loss per order.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Validate against the server lists.
- **Verification:** Add a test.
- **Deployment implication:** None.
- **Priority:** P3

### BIZ-022: Opposite-order stock locks use a single COD attempt

- **Module:** Checkout, concurrency
- **Category:** Potential Risk
- **Severity:** LOW
- **Confidence:** Medium. Deadlock not reproduced
- **Description:** Two carts locking the same products in opposite order can deadlock. One customer gets a 500. It rolls back cleanly and a retry works.
- **Root cause:** Lines are not sorted and no retry count is given.
- **Files:** app/Http/Controllers/CheckoutController.php:271,312-314,360-377; app/Services/InventoryService.php:54,73
- **Reproduction:** Run in Phase 11 on MariaDB.
- **Evidence:** Not yet run.
- **Business impact:** Occasional error in a sale.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Sort lines and pass attempts.
- **Verification:** Race test.
- **Deployment implication:** None.
- **Priority:** P3

### BIZ-023: Duplicate-order protection has no database backstop

- **Module:** Checkout
- **Category:** Potential Risk
- **Severity:** LOW
- **Confidence:** Medium
- **Description:** Protection relies on cache. Deploy clears the cache, so a retry across a deploy could make a second order on landing pages.
- **Root cause:** No unique order_token column.
- **Files:** app/Http/Controllers/CheckoutController.php:212-218,379,412; app/Http/Controllers/LandingPageController.php:136-140,283
- **Reproduction:** Retry across cache:clear.
- **Evidence:** Code reading. File lock itself is atomic on a local filesystem. Hostinger filesystem not verified.
- **Business impact:** Rare duplicate orders.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Store a unique token.
- **Verification:** Add a test.
- **Deployment implication:** Migration needed.
- **Priority:** P3

### BIZ-024: Referral discount can be farmed with new emails

- **Module:** Referrals
- **Category:** Missing Security Control
- **Severity:** LOW
- **Confidence:** High (code reading)
- **Description:** Each new email gets the referred discount for the same phone and address. Referrer rewards need a delivered order and are capped.
- **Root cause:** No phone or address de-duplication; registration bonus before verification.
- **Files:** app/Services/ReferralService.php:41-50,90-97,231-235,310; app/Actions/Fortify/CreateNewUser.php:51
- **Reproduction:** Register several emails with one code.
- **Evidence:** Code reading.
- **Business impact:** Discount leakage.
- **Security impact:** None.
- **Likelihood:** Medium
- **Recommended remediation (not applied):** Key eligibility to a normalized phone.
- **Verification:** Add a test.
- **Deployment implication:** Business choice.
- **Priority:** P2

### BIZ-025: Transaction IDs are not tied to the checkout time or payer

- **Module:** Mobile banking
- **Category:** Missing Security Control
- **Severity:** LOW
- **Confidence:** High (code reading)
- **Description:** Someone who learns an unmatched TrxID, for example from a shared screenshot, can claim it for a same-amount order. Errors also reveal unmatched IDs and amounts. Brute force is impractical.
- **Root cause:** No received_at floor and no payer check.
- **Files:** app/Http/Controllers/MobilePaymentController.php:146-175,298-316
- **Reproduction:** Create a same-amount order and submit the known ID.
- **Evidence:** Code reading.
- **Business impact:** One customer's payment applied to another's order.
- **Security impact:** Weak binding.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Require arrival in the checkout window and use generic errors.
- **Verification:** Add a test.
- **Deployment implication:** Consider with SEC-001.
- **Priority:** P2

### BIZ-026: Legacy ZiniPay path is still reachable and has no amount check

- **Module:** Payments, legacy
- **Category:** Potential Risk
- **Severity:** LOW
- **Confidence:** High for the code. Gateway behaviour and production key unverified
- **Description:** Routes accept any pending token including mobile ones, never compare amount, and delete the pending row on failed status. The page checkout/PaymentPending does not exist. PlaceOrderRequest no longer offers zinipay, so reach is limited.
- **Root cause:** Feature was retired from the entry point only.
- **Files:** app/Http/Controllers/ZiniPayWebhookController.php:46-91; app/Http/Controllers/PaymentController.php:30,54-99; app/Services/OrderService.php:32-121; resources/js/pages/checkout/ (no PaymentPending)
- **Reproduction:** Needs a gateway response for a live token.
- **Evidence:** Code reading; directory listing shows only Checkout.tsx and MobilePayment.tsx.
- **Business impact:** A live pending checkout could be deleted or a wrong amount accepted.
- **Security impact:** Payment integrity if the gateway is reachable.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Retire the routes.
- **Verification:** Route absent.
- **Deployment implication:** None if unused; confirm first.
- **Priority:** P2

### BIZ-028: Pathao silently falls back to sandbox

- **Module:** Couriers
- **Category:** Potential Risk
- **Severity:** LOW
- **Confidence:** Medium
- **Description:** If the environment setting is empty or left at default, consignments go to the sandbox with sandbox credentials and are recorded as sent, so no pickup happens.
- **Root cause:** Default of sandbox.
- **Files:** app/Services/PathaoCourierService.php:313-318; config/pathao.php:8; resources/js/pages/admin/settings/courier.tsx:26-27
- **Reproduction:** Save with the default.
- **Evidence:** Code reading. Live value BLOCKED.
- **Business impact:** Orders not collected.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Warn or fail closed when APP_ENV is production.
- **Verification:** Add a test.
- **Deployment implication:** Owner can confirm the value on Admin › Courier Config.
- **Priority:** P2

### BE-001: Malformed input causes server errors

- **Module:** Backend robustness
- **Category:** Confirmed Functional Bug
- **Severity:** LOW
- **Confidence:** High
- **Description:** Five inputs give a 500: phone[]=x on order tracking, a normal token on a landing-page payment URL, POST /admin/logout as a guest, email[]=x on login, and /admin/products/{id}. No data is exposed, but logs fill and limits do not apply.
- **Root cause:** Missing type guards; Route::resource without except(show).
- **Files:** app/Http/Controllers/OrderTrackingController.php:84; app/Http/Controllers/LandingPageController.php:354-356,384-386; app/Http/Controllers/Admin/AdminAuthController.php:50-55; app/Providers/FortifyServiceProvider.php:150; routes/admin.php:88,97,101,105,110,115,120
- **Reproduction:** See tests.
- **Evidence:** docs/forensic-audit/tests/A14, A15, A18 and AccessControlAuditTest S06, S07 all FAIL with 500.
- **Business impact:** Log noise and hidden real errors.
- **Security impact:** None.
- **Likelihood:** Medium, bots send these
- **Recommended remediation (not applied):** Add guards and except(show).
- **Verification:** Tests pass.
- **Deployment implication:** None.
- **Priority:** P2

### BE-002: A deleted product in a guest cart gives a bare 404 at checkout

- **Module:** Checkout
- **Category:** Confirmed Functional Bug
- **Severity:** LOW
- **Confidence:** High
- **Description:** The customer sees a 404 with no explanation until they remove the item by hand.
- **Root cause:** exists rule includes soft-deleted rows, then findOrFail throws.
- **Files:** app/Http/Requests/PlaceOrderRequest.php:56; app/Support/CartManager.php:325,462
- **Reproduction:** See test.
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A11 FAILS.
- **Business impact:** Lost sale.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Match live products only and say what was removed.
- **Verification:** A11 passes.
- **Deployment implication:** None.
- **Priority:** P2

### BE-003: DatabaseSeeder wipes the catalog and has no production guard

- **Module:** Operations
- **Category:** Potential Risk
- **Severity:** LOW
- **Confidence:** High
- **Description:** php artisan db:seed --force in production would truncate products and categories with foreign keys off, overwrite settings, and add test@example.com with a known password. Deploy and composer do not run it.
- **Root cause:** No environment check.
- **Files:** database/seeders/DatabaseSeeder.php:24-33,44
- **Reproduction:** Manual operator action only. Not run.
- **Evidence:** Code reading; deploy.yml and composer.json have no db:seed.
- **Business impact:** Catalog loss.
- **Security impact:** Known-password account.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Abort in production.
- **Verification:** Add a guard test.
- **Deployment implication:** None.
- **Priority:** P2

### BE-004: Pathao sync can stop for up to 24 hours after a killed run

- **Module:** Scheduler
- **Category:** Potential Risk
- **Severity:** LOW
- **Confidence:** Medium
- **Description:** A host-killed run leaves the overlap lock for 1440 minutes, and the code comment acknowledges it.
- **Root cause:** withoutOverlapping(1440, false) with sequential 30s calls.
- **Files:** routes/console.php:17-19; app/Console/Commands/SyncPathaoOrderStatuses.php:43-55
- **Reproduction:** Kill a run.
- **Evidence:** Code reading.
- **Business impact:** Stale statuses.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Shorter lock and a time budget.
- **Verification:** Add a test.
- **Deployment implication:** Cron state BLOCKED.
- **Priority:** P3

### BE-006: Currency symbol setting ignored on home and wishlist

- **Module:** Settings
- **Category:** Confirmed Functional Bug
- **Severity:** LOW
- **Confidence:** High (code reading)
- **Description:** Home and wishlist read a config key that does not exist, so they always show ৳.
- **Root cause:** Wrong key.
- **Files:** app/Http/Controllers/HomeController.php:143; app/Http/Controllers/WishlistController.php:43
- **Reproduction:** Change the symbol.
- **Evidence:** config/app.php has no currency_symbol.
- **Business impact:** Minor inconsistency.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Read the Setting.
- **Verification:** Add a test.
- **Deployment implication:** None.
- **Priority:** P3

### FE-002: Cart drawer and checkout estimates disagree with the server

- **Module:** Storefront
- **Category:** Confirmed Functional Bug
- **Severity:** LOW
- **Confidence:** High (code reading)
- **Description:** The drawer ignores the free-delivery threshold, bundles and selected-item coupons. Checkout preview has no bundle free-delivery branch. HandleInertiaRequests holds another copy of the shipping rule. Final charges are correct.
- **Root cause:** Repeated logic.
- **Files:** resources/js/components/layout/CartDrawer.tsx:16-28,56-84,92-96; resources/js/pages/checkout/Checkout.tsx:451-487; app/Http/Middleware/HandleInertiaRequests.php:275-326
- **Reproduction:** Cart above the threshold.
- **Evidence:** Code reading.
- **Business impact:** Wrong estimates shown.
- **Security impact:** None.
- **Likelihood:** Medium
- **Recommended remediation (not applied):** One server-computed preview.
- **Verification:** Add a test.
- **Deployment implication:** None.
- **Priority:** P2

### FE-003: Review "Helpful" buttons do nothing

- **Module:** Reviews
- **Category:** Confirmed Functional Bug
- **Severity:** LOW
- **Confidence:** High (code reading)
- **Description:** Clicking has no effect, and the label renders "Helpful ()". No route or model exists.
- **Root cause:** Half-built feature.
- **Files:** resources/js/components/shared/ReviewCard.tsx:95-127; resources/js/pages/catalog/ProductDetail.tsx:1227-1230
- **Reproduction:** Click Helpful.
- **Evidence:** Code reading.
- **Business impact:** Looks broken.
- **Security impact:** None.
- **Likelihood:** Certain
- **Recommended remediation (not applied):** Build or remove.
- **Verification:** Browser check.
- **Deployment implication:** None.
- **Priority:** P3

### FE-004: Lost-parcel action has no screen

- **Module:** Admin orders
- **Category:** Informational
- **Severity:** LOW
- **Confidence:** High
- **Description:** The endpoint exists but nothing calls it; the tracker already records this.
- **Root cause:** No UI built.
- **Files:** routes/admin.php:158; app/Http/Controllers/Admin/OrderController.php:327
- **Reproduction:** Search resources/js.
- **Evidence:** Tracker notes.
- **Business impact:** Staff cannot close a lost parcel.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Add a mother-admin action.
- **Verification:** Browser check.
- **Deployment implication:** None.
- **Priority:** P3

### OPS-001: sitemap.xml is missing on the live site

- **Module:** SEO
- **Category:** Confirmed Functional Bug
- **Severity:** LOW
- **Confidence:** High
- **Description:** GET /sitemap.xml returns 404, and sitemap:generate is not scheduled. robots.txt has no sitemap line.
- **Root cause:** Manual command never run or output not deployed.
- **Files:** app/Console/Commands/GenerateSitemapCommand.php; routes/console.php
- **Reproduction:** GET /sitemap.xml
- **Evidence:** docs/forensic-audit/evidence/phase2/live_paths.txt.
- **Business impact:** Slower search indexing.
- **Security impact:** None.
- **Likelihood:** Certain
- **Recommended remediation (not applied):** Schedule it and reference it in robots.txt.
- **Verification:** Live GET returns 200.
- **Deployment implication:** Rsync must not delete the generated file.
- **Priority:** P2

### SEC-022: Internal audit documents and agent config are deployed to the server

- **Module:** Deployment
- **Category:** Potential Risk
- **Severity:** LOW
- **Confidence:** High that they ship. Live GETs for /docs/ and /CLAUDE.md returned 404
- **Description:** docs/, tests/, .claude and similar folders are not excluded from rsync. They include past audit reports, the tracker, and the SSH host and path in the handoff. Warning: committing docs/forensic-audit would ship this list of unfixed problems on the next deploy.
- **Root cause:** Exclude list covers only a few paths.
- **Files:** .github/workflows/deploy.yml:193-203; docs/SHAPPYDRIVE_PROJECT_HANDOFF_2026-11-03.md:38
- **Reproduction:** Inspect the rsync command.
- **Evidence:** Live: /docs/ 404, /docs/SHAPPYDRIVE_AUDIT_REMEDIATION_TRACKER.md 404, /CLAUDE.md 404.
- **Business impact:** Attack guide on the server if another hole opens.
- **Security impact:** Information exposure.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Exclude them, and update CourierVerificationReadinessService::EXCLUDED_PATHS so fingerprints still match.
- **Verification:** Absent after deploy.
- **Deployment implication:** Do not commit docs/forensic-audit before this is fixed.
- **Priority:** P1

### SEC-023: Personal data can reach logs

- **Module:** Logging
- **Category:** Potential Risk
- **Severity:** LOW
- **Confidence:** Medium
- **Description:** Webhook payloads, courier error bodies and SMS senders are logged. In the mobile payment path catch(RuntimeException) can also catch database errors, whose message includes SQL bindings such as the address.
- **Root cause:** No field allow-list.
- **Files:** app/Http/Controllers/SteadfastWebhookController.php:32,44; app/Services/PathaoCourierService.php:83-86; app/Services/SteadfastCourierService.php:68-71; app/Http/Controllers/SmsTestController.php:110-115; app/Http/Controllers/MobilePaymentController.php:282,319
- **Reproduction:** Any webhook.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Business impact:** Privacy exposure.
- **Security impact:** PII in log files with no retention limit.
- **Likelihood:** Medium
- **Recommended remediation (not applied):** Log allow-listed fields and catch domain exceptions only.
- **Verification:** Inspect log output in a test.
- **Deployment implication:** None.
- **Priority:** P2

### SEC-024: Token regeneration rewrites .env from a web request

- **Module:** Admin settings
- **Category:** Potential Risk
- **Severity:** LOW
- **Confidence:** High for the code. Failure not tested
- **Description:** Regenerating writes .env without a lock, temp file or audit entry and then caches config. A full disk could truncate .env and take the site down.
- **Root cause:** Web-writable .env.
- **Files:** app/Http/Controllers/Admin/SmsForwarderSettingsController.php:29-43,74,86
- **Reproduction:** Regenerate with a full disk.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Business impact:** Possible outage.
- **Security impact:** No audit trail for a payment credential change.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Store it as a hashed Setting or write atomically and audit.
- **Verification:** Add a test.
- **Deployment implication:** None.
- **Priority:** P3

### BIZ-035: Bundle prices can miss the advertised total by a few poisha

- **Module:** Bundles
- **Category:** Potential Risk
- **Severity:** LOW
- **Confidence:** Medium (not executed)
- **Description:** A ৳1000 bundle of three equal items gives 333.33 × 3 = 999.99.
- **Root cause:** Per-line rounding with no remainder.
- **Files:** app/Services/BundleService.php:63
- **Reproduction:** Three equal items.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run. Blocked in practice by BIZ-002.
- **Business impact:** Cosmetic mismatch.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Allocate the remainder.
- **Verification:** Add a test.
- **Deployment implication:** Fix with BIZ-002.
- **Priority:** P3

### BIZ-036: Duplicate reviews and repeat review bonus

- **Module:** Reviews, loyalty
- **Category:** Potential Risk
- **Severity:** LOW
- **Confidence:** High for the missing constraint. Race not reproduced
- **Description:** Two simultaneous submits can make two reviews. After an admin deletes an approved review, a new one can earn the bonus again.
- **Root cause:** Check-then-insert with no unique index; trashed rows ignored.
- **Files:** app/Http/Controllers/ReviewController.php:29-37; database/migrations/2026_04_28_074828_create_reviews_table.php:27-30; app/Services/LoyaltyPointsService.php:466,482
- **Reproduction:** Double submit.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Business impact:** Small loyalty leakage.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Unique index and count trashed rows.
- **Verification:** Add a test.
- **Deployment implication:** Migration needed; check for existing duplicates first.
- **Priority:** P3

### BIZ-037: Free delivery ৳999 offer is hardcoded in the footer and login page

- **Module:** Storefront
- **Category:** Confirmed Functional Bug
- **Severity:** LOW
- **Confidence:** High (code reading). Live setting unknown
- **Description:** If the admin changes the threshold, the site still advertises ৳999.
- **Root cause:** Hardcoded text.
- **Files:** resources/js/components/layout/Footer.tsx:27; resources/js/pages/auth/login.tsx:20; database/seeders/PageSeeder.php:154
- **Reproduction:** Change the setting.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Business impact:** An offer checkout may not honour.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Render from the shared prop.
- **Verification:** Browser check.
- **Deployment implication:** None.
- **Priority:** P3

### DB-001: Brand delete can hard-delete soft-deleted products through the cascade

- **Module:** Database
- **Category:** Potential Risk
- **Severity:** LOW
- **Confidence:** Medium. Needs a production count
- **Description:** The brand check ignores trashed products, and the FK cascades. Reviews, stock history and variants of those products would go. No current screen creates trashed products, but older rows may exist.
- **Root cause:** products.brand_id cascades; products() excludes trashed.
- **Files:** app/Http/Controllers/Admin/BrandController.php:82,100,123; database/migrations (create_products_table):22; docs/forensic-audit/evidence/phase4/schema_checks.txt
- **Reproduction:** BLOCKED: needs a read-only production count.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run. Live delete paths otherwise refuse while products exist.
- **Business impact:** Loss of history in a rare case.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Count withTrashed and change the FK to RESTRICT.
- **Verification:** Add a test.
- **Deployment implication:** Migration needed.
- **Priority:** P2

### DB-004: Expired rate-limit rows pile up in the cache table

- **Module:** Database
- **Category:** Potential Risk
- **Severity:** LOW
- **Confidence:** Medium. Production limiter store unverified
- **Description:** Each new IP or user key leaves rows only removed if read again.
- **Root cause:** No garbage collection on the database store.
- **Files:** config/cache.php:32; .github/workflows/deploy.yml:258
- **Reproduction:** Observe cache table growth.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Business impact:** Slow growth.
- **Security impact:** None.
- **Likelihood:** Medium
- **Recommended remediation (not applied):** Schedule a delete of expired rows.
- **Verification:** Row count stable.
- **Deployment implication:** None.
- **Priority:** P3

### PERF-002: Product cards send the whole product record

- **Module:** Performance
- **Category:** Confirmed Performance Issue
- **Severity:** LOW
- **Confidence:** High for the code. Live split estimated
- **Description:** Live home HTML is 145 KB (21 KB compressed) and a product page 82 KB (18 KB compressed), with first byte around 0.42s, so this is modest today. Cards need about 10 fields but get all columns plus relations. Ziggy and its script add about 52 KB (estimate). cost_price is correctly hidden, so this is size only.
- **Root cause:** toArray() on full models.
- **Files:** app/Http/Controllers/HomeController.php:154,162-163; app/Support/CartManager.php; resources/views/app.blade.php:52
- **Reproduction:** curl the home page.
- **Evidence:** Live sizes measured 2026-11-05.
- **Business impact:** Slower pages on mobile data.
- **Security impact:** None.
- **Likelihood:** Certain
- **Recommended remediation (not applied):** Add a slim card resource and filter Ziggy.
- **Verification:** Re-measure HTML.
- **Deployment implication:** None.
- **Priority:** P2

### PERF-005: Admin sidebar count scans the orders table

- **Module:** Performance
- **Category:** Confirmed Performance Issue
- **Severity:** LOW
- **Confidence:** High. EXPLAIN on MariaDB 11.8.3
- **Description:** is_seen has no index, and the count runs on every admin request. The reviewer also suspected whereDate, but MariaDB 11.8 uses the created_at index for it, so that part was dropped as a false positive.
- **Root cause:** Missing index.
- **Files:** app/Http/Controllers/Admin/DashboardController.php:30-33; app/Http/Middleware/HandleInertiaRequests.php:168-170
- **Reproduction:** EXPLAIN.
- **Evidence:** docs/forensic-audit/evidence/phase4/explain_mariadb.txt: DATE(created_at) type range; is_seen type ALL. Table nearly empty, so plans show shape only.
- **Business impact:** Grows with orders.
- **Security impact:** None.
- **Likelihood:** Certain over time
- **Recommended remediation (not applied):** Add an index on is_seen.
- **Verification:** EXPLAIN on MariaDB.
- **Deployment implication:** Migration needed.
- **Priority:** P3

### PERF-006: Admin SMS poll hits the app every 5 seconds per tab

- **Module:** Performance
- **Category:** Potential Risk
- **Severity:** LOW
- **Confidence:** High for the code. Host limits unverified
- **Description:** Three tabs make 2,160 requests an hour even when hidden.
- **Root cause:** No visibility check.
- **Files:** resources/js/hooks/useSmsPolling.ts:13,104; resources/js/layouts/admin-layout.tsx:592
- **Reproduction:** Open tabs.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Business impact:** Uses shared-host PHP processes that shoppers need.
- **Security impact:** None.
- **Likelihood:** Medium
- **Recommended remediation (not applied):** Pause when hidden and lengthen the interval.
- **Verification:** Observe requests.
- **Deployment implication:** None.
- **Priority:** P2

### PERF-007: Search scans the table despite the FULLTEXT index

- **Module:** Performance
- **Category:** Confirmed Performance Issue
- **Severity:** LOW
- **Confidence:** High for plan shape. EXPLAIN on a nearly empty table
- **Description:** MATCH alone uses the index, but adding OR sku LIKE gives a full scan.
- **Root cause:** Query shape.
- **Files:** app/Support/ProductSearchQuery.php:70,85; app/Http/Controllers/SearchController.php:48-52
- **Reproduction:** EXPLAIN.
- **Evidence:** docs/forensic-audit/evidence/phase4/explain_mariadb.txt: with OR type ALL; without OR type fulltext.
- **Business impact:** Grows with the catalog.
- **Security impact:** None.
- **Likelihood:** Low now
- **Recommended remediation (not applied):** Split the query.
- **Verification:** EXPLAIN.
- **Deployment implication:** None.
- **Priority:** P3

### PERF-008: Orphaned search cache files until the next deploy

- **Module:** Performance
- **Category:** Potential Risk
- **Severity:** LOW
- **Confidence:** Medium
- **Description:** Each distinct matched query leaves a file. The version-stamp flush leaves old files. This narrows the claim made by my own search fix that junk cannot fill the disk.
- **Root cause:** File store deletes only on read.
- **Files:** app/Support/SearchSuggestionCache.php:49,58,65-67
- **Reproduction:** Search many terms.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Business impact:** Inode use on shared hosting. Limits unverified.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Sweep expired files.
- **Verification:** Count files.
- **Deployment implication:** None.
- **Priority:** P3

### PERF-009: Unbounded lists in admin compose screens and Pathao sync

- **Module:** Performance
- **Category:** Potential Risk
- **Severity:** LOW
- **Confidence:** High
- **Description:** Newsletter compose loads all active products with media. Pathao sync loads all in-flight orders and calls one by one with 30s timeouts.
- **Root cause:** No limit.
- **Files:** app/Http/Controllers/Admin/NewsletterController.php:72-75; app/Console/Commands/SyncPathaoOrderStatuses.php:37-44
- **Reproduction:** Large catalog.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Business impact:** Slow or killed runs.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Use search pickers and capped batches.
- **Verification:** Add tests.
- **Deployment implication:** None.
- **Priority:** P3

### BE-007: Search overrides other filters on admin lists

- **Module:** Admin
- **Category:** Confirmed Functional Bug
- **Severity:** LOW
- **Confidence:** High (code re-read)
- **Description:** Products search with status inactive returns active items that match. SMS search with matched=no returns matched rows, which matters when hunting unmatched payments.
- **Root cause:** Ungrouped orWhere.
- **Files:** app/Http/Controllers/Admin/ProductController.php:46-51; app/Http/Controllers/Admin/ReceivedSmsController.php:56-59
- **Reproduction:** Combine filter and search.
- **Evidence:** Code re-read.
- **Business impact:** Wrong lists for staff.
- **Security impact:** None.
- **Likelihood:** Medium
- **Recommended remediation (not applied):** Nest the search.
- **Verification:** Add tests.
- **Deployment implication:** None.
- **Priority:** P2

### BE-008: Hover prefetches count as page views

- **Module:** Analytics
- **Category:** Confirmed Functional Bug
- **Severity:** LOW
- **Confidence:** Medium
- **Description:** Hovering a card counts as a view and runs the expensive product page.
- **Root cause:** Prefetch not skipped.
- **Files:** app/Http/Middleware/TrackDailyVisit.php:39-41,83; resources/js/components/shared/ProductCard.tsx:323,336,380
- **Reproduction:** Hover cards.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Business impact:** Inflated dashboard views.
- **Security impact:** None.
- **Likelihood:** Certain
- **Recommended remediation (not applied):** Skip prefetch requests.
- **Verification:** Add a test.
- **Deployment implication:** None.
- **Priority:** P3

### BE-010: Forgot-password sends mail inside the request with no error handling

- **Module:** Auth
- **Category:** Potential Risk
- **Severity:** LOW
- **Confidence:** Medium
- **Description:** During an SMTP outage this can return a 500 or hang, only for existing accounts, which also supports SEC-015.
- **Root cause:** Framework notification unwrapped.
- **Files:** app/Providers/FortifyServiceProvider.php:61; app/Models/User.php:106-110
- **Reproduction:** SMTP down.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Business impact:** Poor recovery in an outage.
- **Security impact:** Enumeration signal.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Queue it and return a generic response.
- **Verification:** Add a test.
- **Deployment implication:** None.
- **Priority:** P3

### BE-011: Pathao sync reports success when every poll failed

- **Module:** Scheduler
- **Category:** Potential Risk
- **Severity:** LOW
- **Confidence:** High
- **Description:** A Pathao outage leaves stale statuses silently, and the readiness marker still looks healthy.
- **Root cause:** Per-order catch with no counter.
- **Files:** app/Console/Commands/SyncPathaoOrderStatuses.php:52,71,73; app/Services/CourierVerificationReadinessService.php:376-380; app/Services/PathaoCourierService.php:278-299
- **Reproduction:** Wrong credentials.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Business impact:** Stale orders; attestation may mislead.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Return failure when all fail and stop on auth failure.
- **Verification:** Add a test.
- **Deployment implication:** None.
- **Priority:** P2

### BE-012: Failed newsletter chunk cannot be retried from admin

- **Module:** Newsletter
- **Category:** Confirmed Functional Bug
- **Severity:** LOW
- **Confidence:** High (allow-list grep re-run)
- **Description:** Up to 50 subscribers miss the send, and the panel says not safe to retry.
- **Root cause:** Allow-list predates the feature.
- **Files:** app/Support/FailedJobSummary.php:40-53; app/Http/Controllers/Admin/NewsletterController.php:142-145
- **Reproduction:** A chunk exhausts its tries.
- **Evidence:** grep count of SendNewsletter in RETRYABLE_JOBS: 0.
- **Business impact:** Missed mail.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Add it with a duplicate warning.
- **Verification:** Add a test.
- **Deployment implication:** None.
- **Priority:** P3

### FE-009: No noindex or canonical; meta helper defects

- **Module:** SEO
- **Category:** Confirmed Functional Bug
- **Severity:** LOW
- **Confidence:** High
- **Description:** /cart, /checkout, /admin/login and search URLs send no noindex (live headers confirm no X-Robots-Tag). Titles double up, the default og:image file does not exist, and category SEO fields are unused.
- **Root cause:** Helper not used; no header.
- **Files:** resources/js/components/seo/PageMeta.tsx:10,19,22,26-30,36; resources/js/pages/blog/Show.tsx:31,36; resources/js/pages/category/show.tsx:47; public/robots.txt
- **Reproduction:** curl -I the paths.
- **Evidence:** Live HEAD checks 2026-11-05.
- **Business impact:** Thin pages in search; admin login discoverable.
- **Security impact:** Minor.
- **Likelihood:** Certain
- **Recommended remediation (not applied):** Add X-Robots-Tag and fix the helper.
- **Verification:** curl.
- **Deployment implication:** None.
- **Priority:** P2

### FE-010: No React error boundary

- **Module:** Frontend
- **Category:** Potential Risk
- **Severity:** LOW
- **Confidence:** High that it is absent
- **Description:** Any render exception gives a blank white page, including at checkout.
- **Root cause:** None added.
- **Files:** resources/js/app.tsx:243
- **Reproduction:** grep ErrorBoundary: none.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Business impact:** Dead page in an error.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Add a root boundary.
- **Verification:** Force an error in a test.
- **Deployment implication:** None.
- **Priority:** P2

### FE-011: Failed cart sync is silent and the item later vanishes

- **Module:** Cart
- **Category:** Confirmed Functional Bug
- **Severity:** LOW
- **Confidence:** High (code reading)
- **Description:** On flaky mobile data, the customer sees success, the sync fails, and the next page restores the server cart without the item.
- **Root cause:** No catch, and the signature is updated before the request.
- **Files:** resources/js/app.tsx:199-206,227,265,267,302
- **Reproduction:** Add while offline.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Business impact:** Lost items.
- **Security impact:** None.
- **Likelihood:** Medium on mobile
- **Recommended remediation (not applied):** Catch, retry and notify.
- **Verification:** Browser check.
- **Deployment implication:** None.
- **Priority:** P2

### FE-012: Phone rules differ, and Bangla digits are dropped at checkout

- **Module:** Checkout
- **Category:** Confirmed Functional Bug
- **Severity:** LOW
- **Confidence:** High (code reading)
- **Description:** A Bangla keyboard enters nothing, and pasted +8801712345678 becomes 88017123456. Register, profile and address accept free text, so a saved number can fail checkout.
- **Root cause:** No shared normaliser.
- **Files:** resources/js/pages/checkout/Checkout.tsx:424,883; app/Http/Requests/PlaceOrderRequest.php:31; app/Http/Controllers/LandingPageController.php:112,173-186; app/Http/Requests/ProfileUpdateRequest.php:22
- **Reproduction:** Type Bangla digits.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Business impact:** Failed orders.
- **Security impact:** None.
- **Likelihood:** Medium
- **Recommended remediation (not applied):** One shared normaliser.
- **Verification:** Add tests.
- **Deployment implication:** None.
- **Priority:** P2

### FE-013: Cookie banner offers choices that control nothing

- **Module:** Privacy UI
- **Category:** Informational
- **Severity:** LOW
- **Confidence:** High
- **Description:** Analytics functions have no callers and no tracking script exists, so the toggles do nothing. CLAUDE.md describes GA that is not in the code. Tracking cookies are set regardless, and Google Fonts loads before consent. The owner also has no conversion analytics.
- **Root cause:** Unwired feature.
- **Files:** resources/js/lib/analytics.ts; resources/js/components/layout/CookieConsent.tsx:57-76; resources/views/app.blade.php
- **Reproduction:** grep callers.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run. Live cookies confirmed in headers.
- **Business impact:** No analytics.
- **Security impact:** Consent UI implies something untrue.
- **Likelihood:** Certain
- **Recommended remediation (not applied):** Wire it or simplify it.
- **Verification:** Browser check.
- **Deployment implication:** None.
- **Priority:** P3

### FE-014: Inconsistent money format and small accessibility gaps

- **Module:** Frontend
- **Category:** Confirmed Functional Bug
- **Severity:** LOW
- **Confidence:** High
- **Description:** Landing pages show ৳1,200 where the shop shows ৳1,200.00. Landing inputs have no labels. Checkout has nested main elements and two stacked footers.
- **Root cause:** Separate formatting and layout wrap.
- **Files:** resources/js/pages/landing/LandingPage.tsx:220,379,426-520,618,628; resources/js/components/layout/AppLayout.tsx:96; resources/js/components/layout/CheckoutLayout.tsx:12
- **Reproduction:** Open pages.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Business impact:** Polish.
- **Security impact:** None.
- **Likelihood:** Certain
- **Recommended remediation (not applied):** Use formatCurrency, labels, and a checkout layout.
- **Verification:** Browser check.
- **Deployment implication:** None.
- **Priority:** P3

### INFRA-004: Workflow hardening gaps

- **Module:** CI
- **Category:** Missing Security Control
- **Severity:** LOW
- **Confidence:** High
- **Description:** No permissions key, third-party actions pinned by tag including the one holding the deploy key, and secrets interpolated into shell text.
- **Root cause:** Default setup.
- **Files:** .github/workflows/deploy.yml:31,130,164,176-178,204,213
- **Reproduction:** Inspect the workflows.
- **Evidence:** Code reading. Mitigations: ignore-scripts, SSH agent loaded late.
- **Business impact:** Supply-chain exposure.
- **Security impact:** Deploy key at risk if a tag moves.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Pin SHAs, set permissions, use env.
- **Verification:** Lint workflows.
- **Deployment implication:** Needs approval.
- **Priority:** P2

### INFRA-005: Deploy env check is thin and .env.example is incomplete

- **Module:** Deployment
- **Category:** Potential Risk
- **Severity:** LOW
- **Confidence:** High
- **Description:** Only 5 keys are checked. A missing MAIL_MAILER defaults to log, so verification codes go to a log with a green deploy. .env.example lacks SMS_FORWARDER_TOKEN, GOOGLE, FACEBOOK and ZINIPAY names.
- **Root cause:** Check built for DB and key only.
- **Files:** .github/workflows/deploy.yml:224; config/mail.php:17; config/app.php:55
- **Reproduction:** Rebuild .env from the example.
- **Evidence:** Code reading.
- **Business impact:** Silent mail loss.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Extend the check.
- **Verification:** Run the check.
- **Deployment implication:** Needs approval.
- **Priority:** P2

### INFRA-006: rsync --delete removes server-created files

- **Module:** Deployment
- **Category:** Potential Risk
- **Severity:** LOW
- **Confidence:** High
- **Description:** public/sitemap.xml and maintenance mode files are not excluded, so a deploy lifts maintenance mode mid-incident and removes a generated sitemap. This is a second cause for OPS-001.
- **Root cause:** Excludes follow tracked content.
- **Files:** .github/workflows/deploy.yml:192-203; app/Console/Commands/GenerateSitemapCommand.php:38
- **Reproduction:** Generate a sitemap, then deploy.
- **Evidence:** Code reading.
- **Business impact:** Lost sitemap.
- **Security impact:** None.
- **Likelihood:** Certain per deploy
- **Recommended remediation (not applied):** Add excludes or generate post-deploy.
- **Verification:** Check after deploy.
- **Deployment implication:** Needs approval.
- **Priority:** P2

### OPS-007: Logging defaults to one unrotated file at debug level

- **Module:** Operations
- **Category:** Potential Risk
- **Severity:** LOW
- **Confidence:** Could not verify. Production setting BLOCKED
- **Description:** If production kept the defaults, the log grows forever on a fixed quota, threatening sessions and uploads.
- **Root cause:** Framework defaults.
- **Files:** config/logging.php:21,57,61-66; .env.example; .github/workflows/deploy.yml:202
- **Reproduction:** Check LOG_STACK and LOG_LEVEL.
- **Evidence:** Code reading.
- **Business impact:** Disk full.
- **Security impact:** PII retention, see SEC-023.
- **Likelihood:** Unknown
- **Recommended remediation (not applied):** daily and warning.
- **Verification:** Check file size.
- **Deployment implication:** Release condition.
- **Priority:** P2

### REL-003: Four race tests silently skip on anything but port 3306, and show as passed

- **Module:** Test suite
- **Category:** Confirmed Functional Bug
- **Severity:** LOW
- **Confidence:** High
- **Description:** On MariaDB port 3399 the suite said "passed" while 30 tests skipped (coupon, courier, loyalty, return), and two of those files took minutes doing so. Copies with only the port changed then ran: Coupon 1/1, Courier 15/15, Loyalty 12/12, Return 1/2 (see REL-002).
- **Root cause:** Hardcoded port allow-list.
- **Files:** tests/Feature/CouponConcurrencyRaceTest.php:73-77; tests/Feature/CourierSendConcurrencyRaceTest.php; tests/Feature/LoyaltyPointsConcurrencyRaceTest.php; tests/Feature/ReturnFullRefundConcurrencyRaceTest.php
- **Reproduction:** Run with DB_PORT other than 3306.
- **Evidence:** docs/forensic-audit/evidence/phase11/mariadb_race_run.txt and mariadb_race_copies_run.txt.
- **Business impact:** False confidence.
- **Security impact:** None.
- **Likelihood:** Certain
- **Recommended remediation (not applied):** Allow a configured port and fail loudly when asked to run.
- **Verification:** Run on MariaDB.
- **Deployment implication:** None.
- **Priority:** P3

### REL-004: Tests leave real files in storage

- **Module:** Test suite
- **Category:** Confirmed Functional Bug
- **Severity:** LOW
- **Confidence:** High
- **Description:** Each full run leaves about 14 files in storage/app/public/products, and only 10 test files use Http::fake with no preventStray.
- **Root cause:** No Storage::fake in some tests.
- **Files:** tests/ (102 files use RefreshDatabase); phpunit.xml
- **Reproduction:** Run the suite twice and count.
- **Evidence:** Recorded in Phase 1 A2 inspection.
- **Business impact:** Clutter; stray HTTP risk.
- **Security impact:** None.
- **Likelihood:** Certain
- **Recommended remediation (not applied):** Fake storage and prevent stray requests.
- **Verification:** Count files.
- **Deployment implication:** None.
- **Priority:** P3

### SEC-016: Every page publishes all admin route names to guests

- **Module:** Frontend exposure
- **Category:** Informational
- **Severity:** INFO
- **Confidence:** High
- **Description:** The live home page contains 203 admin.* route names. Access is still enforced server-side; this only maps the admin area.
- **Root cause:** @routes with no Ziggy filter.
- **Files:** resources/views/app.blade.php:52 (no config/ziggy.php)
- **Reproduction:** View source on the home page.
- **Evidence:** Live home page: 203 admin route names counted.
- **Business impact:** None directly.
- **Security impact:** Reconnaissance aid.
- **Likelihood:** Certain
- **Recommended remediation (not applied):** Filter admin routes for guests.
- **Verification:** Re-count on the live page.
- **Deployment implication:** Check admin pages still get their routes.
- **Priority:** P3

### BE-005: Dead code and unused schema

- **Module:** Maintenance
- **Category:** Informational
- **Severity:** INFO
- **Confidence:** High
- **Description:** Unused: ProductQuestion, OrderSequence, Tag, review_votes, notifications, carts.coupon_id, flash_sale_items.stock_limit, ContactFormMail (the contact form emails nobody), LoginRequest, RegisterRequest, ChangePasswordRequest, HeroSlideRequest. Root routes.json is an old route dump that is not served on the live site.
- **Root cause:** Leftovers.
- **Files:** app/Http/Controllers/ContactController.php:52; routes.json
- **Reproduction:** Live GET /routes.json returned 404.
- **Evidence:** docs/forensic-audit/evidence/phase2/live_paths.txt.
- **Business impact:** Staff get no alert for contact messages.
- **Security impact:** None.
- **Likelihood:** Certain
- **Recommended remediation (not applied):** Remove in a cleanup batch; decide on contact alerts.
- **Verification:** Suite passes.
- **Deployment implication:** Migrations needed for schema removal.
- **Priority:** P3

### DB-003: Some uniqueness relies on application code only

- **Module:** Database
- **Category:** Informational
- **Severity:** INFO
- **Confidence:** High
- **Description:** coupon_uses per order, cart_items, carts.user_id, flash_sale_items and payments.transaction_id have no unique constraint. Call-site guards and the passing coupon and cart race tests cover the main paths.
- **Root cause:** Design choice.
- **Files:** docs/forensic-audit/evidence/phase4/schema_checks.txt
- **Reproduction:** Inspect indexes.
- **Evidence:** Read-only schema query on dev.
- **Business impact:** Low.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Add constraints in a planned batch after checking data.
- **Verification:** Migration tests.
- **Deployment implication:** Migration needed.
- **Priority:** P3

### DB-005: Search index and enums depend on the driver being named mysql

- **Module:** Database
- **Category:** Informational
- **Severity:** INFO
- **Confidence:** Medium. Production DB_CONNECTION unverified
- **Description:** Seven checks test getDriverName() === "mysql". If production used the mariadb driver name, FULLTEXT and enum changes would be skipped.
- **Root cause:** Driver branching.
- **Files:** app/Support/ProductSearchQuery.php; 7 migrations
- **Reproduction:** Check the DB_CONNECTION value.
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Business impact:** Slower search or schema drift.
- **Security impact:** None.
- **Likelihood:** Low
- **Recommended remediation (not applied):** Treat mariadb the same.
- **Verification:** Run migrations under both names.
- **Deployment implication:** BLOCKED: production setting.
- **Priority:** P3

### INFRA-007: Leftover packages and public assets

- **Module:** Maintenance
- **Category:** Informational
- **Severity:** INFO
- **Confidence:** High
- **Description:** spatie/laravel-responsecache ^0.0.1 is an empty skeleton. public/vendor/moonshine has assets for a removed package. laravel/tinker is in require.
- **Root cause:** Leftovers.
- **Files:** composer.json; public/vendor/moonshine; routes.json
- **Reproduction:** Inspect.
- **Evidence:** Live /vendor/moonshine/ returns 403 as a directory.
- **Business impact:** Clutter.
- **Security impact:** Small.
- **Likelihood:** Certain
- **Recommended remediation (not applied):** Remove them.
- **Verification:** Suite passes.
- **Deployment implication:** Dependency change needs approval.
- **Priority:** P3

### REL-002: Race test assumes REPEATABLE READ, but production is READ-COMMITTED

- **Module:** Test suite
- **Category:** Informational
- **Severity:** INFO
- **Confidence:** High
- **Description:** On MariaDB with production settings, the refund race test fails only on its assertion that a plain read still sees 0. The safety assertion passes: the locked read sees 600, so a double refund is prevented. Not a product bug.
- **Root cause:** Test comment assumes MySQL default isolation.
- **Files:** tests/Feature/ReturnFullRefundConcurrencyRaceTest.php:202-240
- **Reproduction:** Run on MariaDB READ-COMMITTED.
- **Evidence:** docs/forensic-audit/evidence/phase11/mariadb_race_copies_run.txt.
- **Business impact:** None.
- **Security impact:** None.
- **Likelihood:** Certain on production settings
- **Recommended remediation (not applied):** Make the assertion isolation-aware.
- **Verification:** Green on both.
- **Deployment implication:** None.
- **Priority:** P3
