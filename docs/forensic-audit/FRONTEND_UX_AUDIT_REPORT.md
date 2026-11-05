# Frontend and UX Audit Report

Audited commit `f49bed3`, 2026-11-05. Audit only: nothing was fixed, committed or deployed. Full entries: `COMPLETE_FINDINGS_REGISTER.md`.

Legal items are for the owner or a lawyer to confirm. This is not legal advice.

| ID | Severity | Priority | Title |
|---|---|---|---|
| FE-001 | MEDIUM | P2 | Customers cannot page through order history |
| FE-005 | MEDIUM | P1 | A server error empties a logged-in customer's cart |
| FE-006 | MEDIUM | P1 | Product pages have no search or share tags in the HTML |
| FE-007 | MEDIUM | P2 | Session timeout on landing-page checkout loses the form and may 405 |
| FE-008 | MEDIUM | P2 | Google or Facebook login discards the guest cart |
| FE-002 | LOW | P2 | Cart drawer and checkout estimates disagree with the server |
| FE-003 | LOW | P3 | Review "Helpful" buttons do nothing |
| FE-004 | LOW | P3 | Lost-parcel action has no screen |
| FE-009 | LOW | P2 | No noindex or canonical; meta helper defects |
| FE-010 | LOW | P2 | No React error boundary |
| FE-011 | LOW | P2 | Failed cart sync is silent and the item later vanishes |
| FE-012 | LOW | P2 | Phone rules differ, and Bangla digits are dropped at checkout |
| FE-013 | LOW | P3 | Cookie banner offers choices that control nothing |
| FE-014 | LOW | P3 | Inconsistent money format and small accessibility gaps |
| BIZ-013 | MEDIUM | P1 | Coupon and duplicate-order rejections are invisible at checkout |
| BIZ-016 | MEDIUM | P1 | Landing page shows different price and delivery than it charges |
| BIZ-030 | MEDIUM | P1 | COD confirmation says "Total Paid" |
| BIZ-031 | MEDIUM | P1 | Checkout shows payment logos the shop does not accept |
| BIZ-032 | MEDIUM | P1 | Live terms and FAQ contradict how the shop works |
| BIZ-034 | MEDIUM | P1 | No return, refund or shipping policy pages; seller identity missing |
| BIZ-037 | LOW | P3 | Free delivery ৳999 offer is hardcoded in the footer and login page |

## Checked and clean (code reading)

- Logout clears cart, coupon and wishlist.
- Double-submit protection on checkout and admin money actions.
- Destructive admin actions confirm, apart from BIZ-029.
- Every `route()` name in the frontend exists.
- Drawers use Radix focus traps and the custom Select has keyboard support.
- Removed products return 404.

## Details

### FE-001: Customers cannot page through order history

With more than 10 orders, older ones are unreachable from the list. The pagination area is an empty placeholder.

- **Severity / confidence:** MEDIUM / High (code reading)
- **Files:** routes/account.php:57; resources/js/pages/account/Orders.tsx:288-292
- **Evidence:** Code reading.
- **Recommendation (not applied):** Render pagination links.

### FE-005: A server error empties a logged-in customer's cart

The 5xx page sends no shared props, so the client treats it as logout, clears the cart, and syncs an empty list to the server.

- **Severity / confidence:** MEDIUM / High. Reproduced in a browser with production error handling
- **Files:** app/Providers/AppServiceProvider.php:108-117; resources/js/app.tsx:125,128,267,282; app/Support/CartManager.php:343-345
- **Evidence:** docs/forensic-audit/evidence/phase2/cart_loss_on_500_browser.txt: POST /cart/sync sent items:[] and cart_items went from 1 to 0. BE-001 inputs and any real server bug can trigger this.
- **Recommendation (not applied):** Run applyLogout only when auth is present and null.

### FE-006: Product pages have no search or share tags in the HTML

A live product page returns title "ShoppyDrive", no h1, an empty app div, and no og tags, canonical or JSON-LD. Facebook previews show no product. SSR is never built by deploy, so the SSR notes in CLAUDE.md do not apply in production.

- **Severity / confidence:** MEDIUM / High. Live page checked
- **Files:** .github/workflows/deploy.yml:160; config/inertia.php:19; resources/js/pages/catalog/ProductDetail.tsx:505-513
- **Evidence:** Live GET 2026-11-05: generic title, 0 meta tags, 0 h1.
- **Recommendation (not applied):** Output tags from Blade for product pages.

### FE-007: Session timeout on landing-page checkout loses the form and may 405

After 2 hours the 419 page replaces the form, and its Refresh reloads a POST-only URL. Main checkout also loses the address.

- **Severity / confidence:** MEDIUM / Medium. Needs a browser check
- **Files:** routes/web.php:66; resources/js/pages/errors/ErrorPage.tsx:50-56,219; config/session.php:35
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Recommendation (not applied):** Refresh the token and resubmit, or keep values.

### FE-008: Google or Facebook login discards the guest cart

Form login merges the guest cart. Social login is a full page load with no merge, so the cart empties at the moment of purchase.

- **Severity / confidence:** MEDIUM / Medium. Not run, real OAuth is out of scope
- **Files:** resources/js/app.tsx:157,248,285-295; resources/js/pages/auth/login.tsx:174,205
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Recommendation (not applied):** Merge on first load after social login.

### FE-002: Cart drawer and checkout estimates disagree with the server

The drawer ignores the free-delivery threshold, bundles and selected-item coupons. Checkout preview has no bundle free-delivery branch. HandleInertiaRequests holds another copy of the shipping rule. Final charges are correct.

- **Severity / confidence:** LOW / High (code reading)
- **Files:** resources/js/components/layout/CartDrawer.tsx:16-28,56-84,92-96; resources/js/pages/checkout/Checkout.tsx:451-487; app/Http/Middleware/HandleInertiaRequests.php:275-326
- **Evidence:** Code reading.
- **Recommendation (not applied):** One server-computed preview.

### FE-003: Review "Helpful" buttons do nothing

Clicking has no effect, and the label renders "Helpful ()". No route or model exists.

- **Severity / confidence:** LOW / High (code reading)
- **Files:** resources/js/components/shared/ReviewCard.tsx:95-127; resources/js/pages/catalog/ProductDetail.tsx:1227-1230
- **Evidence:** Code reading.
- **Recommendation (not applied):** Build or remove.

### FE-004: Lost-parcel action has no screen

The endpoint exists but nothing calls it; the tracker already records this.

- **Severity / confidence:** LOW / High
- **Files:** routes/admin.php:158; app/Http/Controllers/Admin/OrderController.php:327
- **Evidence:** Tracker notes.
- **Recommendation (not applied):** Add a mother-admin action.

### FE-009: No noindex or canonical; meta helper defects

/cart, /checkout, /admin/login and search URLs send no noindex (live headers confirm no X-Robots-Tag). Titles double up, the default og:image file does not exist, and category SEO fields are unused.

- **Severity / confidence:** LOW / High
- **Files:** resources/js/components/seo/PageMeta.tsx:10,19,22,26-30,36; resources/js/pages/blog/Show.tsx:31,36; resources/js/pages/category/show.tsx:47; public/robots.txt
- **Evidence:** Live HEAD checks 2026-11-05.
- **Recommendation (not applied):** Add X-Robots-Tag and fix the helper.

### FE-010: No React error boundary

Any render exception gives a blank white page, including at checkout.

- **Severity / confidence:** LOW / High that it is absent
- **Files:** resources/js/app.tsx:243
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Recommendation (not applied):** Add a root boundary.

### FE-011: Failed cart sync is silent and the item later vanishes

On flaky mobile data, the customer sees success, the sync fails, and the next page restores the server cart without the item.

- **Severity / confidence:** LOW / High (code reading)
- **Files:** resources/js/app.tsx:199-206,227,265,267,302
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Recommendation (not applied):** Catch, retry and notify.

### FE-012: Phone rules differ, and Bangla digits are dropped at checkout

A Bangla keyboard enters nothing, and pasted +8801712345678 becomes 88017123456. Register, profile and address accept free text, so a saved number can fail checkout.

- **Severity / confidence:** LOW / High (code reading)
- **Files:** resources/js/pages/checkout/Checkout.tsx:424,883; app/Http/Requests/PlaceOrderRequest.php:31; app/Http/Controllers/LandingPageController.php:112,173-186; app/Http/Requests/ProfileUpdateRequest.php:22
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Recommendation (not applied):** One shared normaliser.

### FE-013: Cookie banner offers choices that control nothing

Analytics functions have no callers and no tracking script exists, so the toggles do nothing. CLAUDE.md describes GA that is not in the code. Tracking cookies are set regardless, and Google Fonts loads before consent. The owner also has no conversion analytics.

- **Severity / confidence:** LOW / High
- **Files:** resources/js/lib/analytics.ts; resources/js/components/layout/CookieConsent.tsx:57-76; resources/views/app.blade.php
- **Evidence:** Code reading by the module reviewer; spot-checked, not run. Live cookies confirmed in headers.
- **Recommendation (not applied):** Wire it or simplify it.

### FE-014: Inconsistent money format and small accessibility gaps

Landing pages show ৳1,200 where the shop shows ৳1,200.00. Landing inputs have no labels. Checkout has nested main elements and two stacked footers.

- **Severity / confidence:** LOW / High
- **Files:** resources/js/pages/landing/LandingPage.tsx:220,379,426-520,618,628; resources/js/components/layout/AppLayout.tsx:96; resources/js/components/layout/CheckoutLayout.tsx:12
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Recommendation (not applied):** Use formatCurrency, labels, and a checkout layout.

