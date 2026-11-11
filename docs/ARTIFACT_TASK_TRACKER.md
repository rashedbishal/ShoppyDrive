# Artifact task tracker

Source: https://claude.ai/artifact/DMM83dRHtY9RkYAWCrEdhj

**Done: 11 / 99** · Live, ready to retest: 25 · Fixed, not live yet: 5 · Failed: 0 · Skipped: 1

`[ ]` not done · `[x]` done · `[>]` live, ready to retest · `[~]` fixed, not live yet · `[!]` failed · `[-]` skipped

## Backend

- [>] BE-009 — "Today" figures and invoices use UTC, not Dhaka time — tester marked it Fail (2026-11-11) because `config/app.php` still says `'timezone' => 'UTC'`. That is deliberate and not the bug: timestamps are stored in UTC (changing the config would shift every stored time), and the fix (`dad977d`, deployed 2026-11-05) moved only the places that show a day: the dashboard's today/yesterday boundaries and 7-day chart, the visitor counter's day, and the invoice "Date Issued" are all computed in Asia/Dhaka. Checked with the tester's own example: an order at 03:00 Dhaka on 5 Nov (21:00 UTC on 4 Nov) is invoiced 05 Nov 2026, counts as today's order/revenue on the dashboard, and a 03:30 Dhaka visit is counted on the Dhaka day. New invoice-date test added (fails when the conversion is removed, so it really guards this). No application code change; ready to retest: place an order between 00:00 and 06:00 Dhaka time and check the invoice date and the dashboard Today figures
- [ ] BE-001 — Malformed input causes server errors
- [~] BE-002 — A deleted product in a guest cart gives a bare 404 at checkout — tester's "need clear message" was right (real deletes are force-deletes, not soft); fixed for guests and, more severely, for logged-in customers whose item was silently cascade-deleted from their cart; fix pushed 2026-11-09 (commit `f37c051`), deploy run outcome not yet confirmed
- [>] BE-003 — DatabaseSeeder wipes the catalog and has no production guard — tester marked it Fail (2026-11-11) from code inspection ("no `app()->isProduction()` guard"); that is not what the code says. The guard exists (`app()->environment('production')` throws before anything is touched; shipped `e1ebd4b`, deployed 2026-11-07), just not under that exact method name. Proved with a real run: on a scratch SQLite database holding a marker category, `APP_ENV=production php artisan db:seed --force` stopped with "Refusing to seed demo data in production…", the category survived and no test@example.com user was created. Normal local seeding still works (11 categories, 36 products, test user). Nothing in `composer.json`, the workflows or the app runs `db:seed`, and no other seeder truncates or sets a password. No code change; ready to retest (read the file, do not run db:seed on the live site)
- [ ] BE-007 — Search overrides other filters on admin lists
- [ ] BE-011 — Pathao sync reports success when every poll failed
- [ ] BE-004 — Pathao sync can stop for up to 24 hours after a killed run
- [x] BE-008 — Hover prefetches count as page views — tester's claim of a direct controller increment didn't match the code; re-confirmed directly on the actual product route (not just home), added that test coverage, no code change needed
- [ ] BE-010 — Forgot-password sends mail inside the request with no error handling
- [>] BE-012 — Failed newsletter chunk cannot be retried from admin — tester marked it Fail (2026-11-11) describing a per-recipient loop and a blocked retry; not reproducible on current `main` (shipped in `cef9550`, deploy run `42323203844`): each chunk is ONE BCC message, so it goes out whole or not at all (no per-recipient state to lose), a failed chunk never cancels the others (`allowFailures()`), and Failed Jobs offers Retry with a "some may get it twice" warning. New end-to-end test proves it: a chunk fails 3 tries, the other chunk is delivered, admin retry re-delivers the failed one. No code change; ready to retest on the live site

## Business logic

- [>] BIZ-001 — COD can charge more than the price the customer saw — tester failed it (retry never worked, not an overcharge); fix pushed and deployed 2026-11-09 (owner confirmed deploy succeeded), awaiting tester retest
- [>] BIZ-002 — A cart containing a complete bundle can never be checked out from the website — tester failed it (admin could save a bundle needing more stock than exists, not an "every bundle fails" regression); fix pushed and deployed 2026-11-09 (owner confirmed deploy succeeded), awaiting tester retest
- [ ] BIZ-003 — Guests see a coupon discount at checkout but are charged full price
- [ ] BIZ-004 — Converting an incomplete order ignores stock, and cancelling it creates stock
- [>] BIZ-005 — Paid orders can still be cancelled and then cannot be refunded — tester skipped it; reproduced directly and found a real dead end (courier-readiness blocked a non-courier refund), fix pushed and deployed 2026-11-09 (owner confirmed deploy succeeded), awaiting tester retest
- [ ] BIZ-006 — Customers can pay for out-of-stock items and receive no order
- [-] BIZ-007 — Admin can cancel orders already with Steadfast; Steadfast orders never reach Shipped — owner-closed 2026-11-09: cancel-side already blocked in code (deployed 2026-11-05), no Steadfast cancel API exists, owner accepted the Shipped-status gap as-is
- [>] BIZ-008 — Refunded orders stay "paid" and keep counting as revenue — tester failed it (no working refund button at all, not the shipping-cap bug); fix pushed and deployed 2026-11-09 (owner confirmed deploy succeeded), awaiting tester retest
- [>] BIZ-010 — Loyalty points can be spent twice through a late mobile payment — tester skipped it (needs live SMS config); owner chose to close the previously-accepted residual gap anyway, fix pushed and deployed 2026-11-09 (owner confirmed deploy succeeded), awaiting tester retest
- [x] BIZ-011 — No bot or quantity protection on COD orders — tester skipped it; directly reproduced and confirmed working 2026-11-09, no code change needed, bot/OTP gaps stay owner-declined
- [ ] BIZ-013 — Coupon and duplicate-order rejections are invisible at checkout
- [x] BIZ-029 — Cancelling the Reject prompt still rejects the return and emails the customer — tester skipped it (no delivered order to test with); re-confirmed in code 2026-11-09, fix unchanged since deploy, no code change needed
- [ ] BIZ-030 — COD confirmation says "Total Paid"
- [ ] BIZ-031 — Checkout shows payment logos the shop does not accept
- [x] BIZ-033 — Home page ratings count unmoderated reviews — tester first failed it (product page broke after approving a review), couldn't reproduce directly, owner confirmed fixed on retest 2026-11-09 (likely transient, no code change found/needed)
- [x] BIZ-034 — No return, refund or shipping policy pages; seller identity missing — tester confirmed pages return 200, flagged legal content still needs owner/lawyer review (already the recorded disclaimer); legal_business_name/trade_licence_number confirmed still empty, owner's to fill in
- [x] BIZ-012 — A whole-order refund restocks delivered goods automatically — tester skipped it (hard to set up manually); reproduced directly via real route, already deployed and working correctly, no code change needed
- [x] BIZ-014 — A retried checkout puts purchased items back in the cart — tester skipped it (couldn't manually resubmit); re-confirmed via test 2026-11-09, no code change needed
- [>] BIZ-015 — Per-customer coupon limits do not hold across guest and account — enforcement worked, tester flagged a vague guest error message; owner chose clarity over the SEC-012 anti-enumeration tradeoff, fix pushed and deployed 2026-11-09 (owner confirmed deploy succeeded), awaiting tester retest
- [x] BIZ-017 — Incomplete-order conversion ignores flash price and variant ownership — tester's two new complaints (no delivery charge, no add-product option) could not be reproduced, both already implemented and tested; no code change needed
- [ ] BIZ-024 — Referral discount can be farmed with new emails
- [ ] BIZ-025 — Transaction IDs are not tied to the checkout time or payer
- [ ] BIZ-026 — Legacy ZiniPay path is still reachable and has no amount check
- [ ] BIZ-027 — Steadfast status relies on unverified webhook assumptions with no fallback
- [ ] BIZ-028 — Pathao silently falls back to sandbox
- [ ] BIZ-018 — Referral discount preview ignores Buy Now
- [ ] BIZ-019 — Paying a mobile checkout empties items added later
- [ ] BIZ-020 — Admin price fields have no sanity limits
- [ ] BIZ-021 — District and city are free text and set the delivery fee
- [ ] BIZ-022 — Opposite-order stock locks use a single COD attempt
- [ ] BIZ-023 — Duplicate-order protection has no database backstop
- [>] BIZ-036 — Duplicate reviews and repeat review bonus — tester failed it ("a user can give at most one review on a product"); now one review per customer per product, permanently, even after an admin deletes it, and the form no longer reappears while a review waits for approval; live 2026-11-10 (deploy run 42719140927), awaiting tester retest

## Database

- [ ] DB-001 — Brand delete can hard-delete soft-deleted products through the cascade
- [ ] DB-002 — No retention or pruning for growing tables, including personal data
- [~] DB-004 — Expired rate-limit rows pile up in the cache table — already fixed nightly (tester couldn't see this); split into its own hourly command for faster cleanup, fix pushed 2026-11-09 (commit `81259df`), deploy run outcome not yet confirmed

## Frontend

- [~] FE-005 — A server error empties a logged-in customer's cart — tester skipped it (2026-11-11, couldn't test: needs a real 500 with APP_DEBUG off). Re-checked the code: the in-site-navigation case was already fixed (`6dbf2ba`, deployed 2026-11-05), but the same "missing auth = logout" mistake was still on the full-page-load path (typing the URL, refresh, plain link landing on a 500 page), which wiped the local cart, wishlist and login marker. Fixed locally in `resources/js/app.tsx` (not pushed yet): a page with no shared props now leaves all of that untouched. To retest after deploy: log in, add an item, open a page that errors (500, APP_DEBUG off) by typing its URL, then go home — the cart must still be there
- [ ] FE-006 — Product pages have no search or share tags in the HTML
- [ ] FE-001 — Customers cannot page through order history
- [>] FE-002 — Cart drawer and checkout estimates disagree with the server — tester skipped it (2026-11-11): "possibly no free delivery threshold implemented". It is implemented, but it is a setting, not a built-in number: Admin › Homepage › announcement bar › "Free Delivery Minimum Amount (৳)" (0 = off), so with nothing set there is no threshold to test. Re-checked all three audit points against the current code: the drawer (`CartDrawer.tsx`) and checkout (`Checkout.tsx`) both use the shared `isDeliveryFree()` (threshold, per-product free delivery with minimum quantity, free-delivery bundles) and the drawer's coupon preview counts only eligible items; `HandleInertiaRequests` now only shares the inputs (threshold, rates) from the same settings the server uses, not its own rule. 11 browser-side scenarios run in Node agree with the server calculator's own test scenarios. No code change beyond the 2026-11-09 fix (`9dd80c8`); to retest: set the free-delivery minimum (e.g. ৳1000), put more than that in the cart — drawer and checkout must both say Free before any address is chosen, and below it show the rate
- [>] FE-007 — Session timeout on landing-page checkout loses the form and may 405 — tester skipped it (2026-11-11): had to wait 2 hours for a session to expire, and asked that the user get a friendly message on a 419. Already built (`218a8e8`, deployed 2026-11-06) and now proven live without waiting: on a local copy with APP_DEBUG off, a form POST with no valid session/CSRF token (exactly what an expired session sends) to the landing-page order route returns a redirect back to the same page (so the form stays filled) carrying the message "Your session expired for security. Your details are still here — please press the button again."; a plain non-Inertia post gets the themed "Session expired — refresh the page and try that again" page. No code change. Quick retest on the live site without waiting 2 hours: open a landing page, then in the browser's dev tools delete the site's cookies (session + XSRF-TOKEN), fill the form and submit — the message appears, the form stays filled, and pressing the button again places the order
- [ ] FE-009 — No noindex or canonical; meta helper defects
- [>] FE-010 — No React error boundary — tester skipped it (2026-11-11): "couldn't find any react error", asked how exceptions are handled because it could break checkout. The boundary exists (`components/error-boundary.tsx`, wraps the whole app in `app.tsx`; shipped `4ee2bd5`, deployed 2026-11-06), so a page that crashes while rendering no longer shows a blank white page. The tracker said it had never been exercised in a browser; it is now: a home page forced to throw in real Chrome showed "Something went wrong on this page. Your cart is safe." with "Reload page" and "Go to home page" buttons (admin pages say "Nothing was saved or changed" and link to the dashboard), the crash was written to the server log as "Browser page crash" with the message, page path (no query string) and component stack, and "Go to home page" recovered to a normal page. No code change. A render crash is rare and cannot be triggered from the website, so this is a developer check; code that fails outside rendering (button handlers, requests) is handled by the existing form-error and toast code, not by this boundary
- [ ] FE-011 — Failed cart sync is silent and the item later vanishes
- [ ] FE-012 — Phone rules differ, and Bangla digits are dropped at checkout
- [ ] FE-003 — Review "Helpful" buttons do nothing
- [>] FE-004 — Lost-parcel action has no screen — tester marked it Fail (2026-11-11) after searching `resources/js` for the route. The screen exists (`components/admin/LostParcelDialog.tsx`, used in `pages/admin/orders/show.tsx`; shipped `7083f65`, deployed 2026-11-07); it calls the route by name (`route('admin.orders.confirmLostParcel', …)`), which a plain text search for the URL misses. It is shown only to the main (mother) admin, inside the amber "Courier report needs investigation" banner, which appears only for a non-final order whose courier status is not recognised. Verified in real Chrome with exactly that setup (scratch database, main admin login): banner shows, the "Confirm parcel lost" button opens a dialog titled "Confirm parcel lost" with a labelled note box, a labelled password box, Cancel and Confirm lost. No code change. To retest on the live site: you need a main-admin login and an order whose Steadfast/Pathao status is an unrecognised one; the server also requires a verified not-delivered courier outcome first
- [>] FE-013 — Cookie banner offers choices that control nothing — tester marked it Fail (2026-11-11): "toggles do not control anything — cookies are set unconditionally". The toggles no longer exist: `f4800f6` (deployed 2026-11-07) replaced them with an honest notice. Checked again today in real Chrome on the current build: the notice reads "We use cookies to keep your cart and login working, count visits, and note how you found us. We also use tracking tools to measure and improve our ads.", has 0 toggles/checkboxes, shows only "Got it" and "Privacy policy", "Got it" hides it and stores the choice, and it does not return on the next visit. Cookies are still set on first load (session, XSRF, `sd_traffic_source`, `sd_traffic_referrer`, `sd_last_seen`) by the owner's 2026-11-08 decision ("fire for everyone, no consent gate"), and the notice says so; Google Fonts still loads from Google (known, recorded). No code change; if the tester's browser still shows switches it is an old cached copy — hard-refresh and retest
- [ ] FE-014 — Inconsistent money format and small accessibility gaps

## Infrastructure

- [ ] INFRA-001 — Deploy has no maintenance mode and is not atomic
- [ ] INFRA-004 — Workflow hardening gaps
- [ ] INFRA-005 — Deploy env check is thin and .env.example is incomplete
- [ ] INFRA-007 — Leftover packages and public assets

## Operations

- [~] OPS-002 — If cron or the queue stops, nobody is told and customer mail stops — tester marked it Fail (2026-11-11): the dashboard banner (deployed 2026-11-05) only helps someone already looking; they expected an active alert. Real gap, owner chose the design: new Admin › Security card (mother-only) takes a heartbeat-monitor ping URL (Healthchecks.io, UptimeRobot, etc.); `app:drain-queue` pings it every minute after a clean run, so the monitor alerts when cron or the queue stops. Also built (owner request): a visit-triggered email to the main admin when cron has been silent 10+ minutes (once per outage, plus a recovered email) and an Admin › System Monitor page; fixed locally, not committed yet. To test after deploy: paste a ping URL, confirm the monitor shows pings, then disable cron and confirm the monitor alerts
- [>] OPS-003 — A short mail outage fails queued mail, and recovery is one click per row — tester marked it Fail (2026-11-11) with the audit's original numbers (3 tries / 30s backoff, mail.timeout null, no bulk retry); not reproducible on current `main`: re-ran "refuse SMTP" on a scratch SQLite DB (MAIL_PORT=1) through `app:drain-queue` and the job was rescheduled 300s out (attempts=1, not failed), `mail.mailers.smtp.timeout` reads 15, and Admin › Failed jobs has Retry All. All three sub-fixes shipped in `b1ad61a` (deploy run `42046689296`, 2026-11-05). Likely tested an older deploy or a `composer dev` worker. No code change; ready to retest on the live site
- [~] OPS-005 — A dead forwarder phone disables mobile payment with a misleading message — owner's design (heartbeat ping + gate mobile banking out entirely while down) built: new `/api/sms/heartbeat` endpoint, Android app updated, checkout/landing page hide bKash/Nagad while down; fix pushed 2026-11-09 (commit `22edbb7`), deploy run outcome not yet confirmed. Android app reinstalled by owner but it's configured for shoppydrive.com, so the heartbeat endpoint 404s until this deploy lands
- [>] OPS-006 — Abandoned-cart mail has no opt-out or cap and reaches deleted accounts — tester couldn't test it live (2026-11-10) and listed 7 points; 3, 4, 5 were fixed 2026-11-05, but the audit's "cooldown" was never built, so 1, 2, 6 were real: 3 abandoned attempts still meant 3 mails. Fixed 2026-11-11: max one reminder per customer per 7 days (owner decision), newest cart mailed; unverified emails never mailed; new Settings › Profile toggle turns reminders off or back on (owner decision). Deployed 2026-11-11 with its migration (run `42895410141`), ready to retest
- [>] OPS-001 — sitemap.xml is missing on the live site — tester skipped it 2026-11-10: /sitemap.xml no longer 404s and robots.txt has the line, unsure about the scheduled sitemap:generate; rechecked: scheduled daily 04:00 Dhaka, and the live file's Last-Modified is 09 Nov 22:00:04 UTC (= 04:00:04 Dhaka), so the nightly job is running in production. No code change
- [>] OPS-007 — Logging defaults to one unrotated file at debug level — tester skipped it 2026-11-10 asking for a code-level recheck; rechecked 2026-11-11: production fix (daily files, info level, 30 days, ignores .env) live since `896aede` and intact, no bypasses; only `.env.example` still read single/debug (local defaults), now commented to say production ignores them. Old pre-fix `laravel.log` on the server still needs the owner to clear it

## Performance

- [>] PERF-001 — Product listing runs a query for every variant — tester re-failed it 2026-11-10 ("really does run multiple queries for a single product"); right: the listing was fixed but the product page still ran 2 flash-sale queries per variant (30 variants on sale = 91 queries). Fixed 2026-11-11 with one batched lookup (also landing page, AI assistant); deployed 2026-11-11 (run `42895410141`), ready to retest
- [x] PERF-002 — Product cards send the whole product record — tester's note was cut off ("need"); re-confirmed via live production curl (78.5 KB/17.1 KB gz, matches recorded post-fix size), no code change needed
- [ ] PERF-003 — Icon registry and admin layout load on the customer home page
- [ ] PERF-006 — Admin SMS poll hits the app every 5 seconds per tab
- [x] PERF-005 — Admin sidebar count scans the orders table — tester restated the original finding (couldn't run EXPLAIN); re-confirmed directly 2026-11-09, index is present and used, no code change needed
- [ ] PERF-007 — Search scans the table despite the FULLTEXT index
- [ ] PERF-008 — Orphaned search cache files until the next deploy

## Reliability and tests

- [ ] REL-001 — Tests pass payloads the real website never sends
- [ ] REL-003 — Four race tests silently skip on anything but port 3306, and show as passed
- [ ] REL-004 — Tests leave real files in storage

## Security

- [ ] SEC-002 — Any admin with one settings page can read every stored secret
- [>] SEC-003 — A verification code sent to one email can verify a different email — tester failed it; fix live 2026-11-09, ready to retest
- [ ] SEC-004 — Customers' Google/Facebook tokens are sent to browsers
- [ ] SEC-005 — Changing an admin password does not log out other sessions
- [ ] SEC-006 — Order tracking returns the whole order record
- [ ] SEC-007 — View-only SMS forwarder permission reveals the full token
- [ ] SEC-010 — Weaker password policy and no per-account login limit for admins
- [ ] SEC-017 — composer audit lists new Symfony advisories
- [ ] SEC-021 — Back button after logout shows account data
- [ ] SEC-022 — Internal audit documents and agent config are deployed to the server
- [ ] SEC-008 — A social link planted on an unverified account can survive the real owner's recovery
- [ ] SEC-009 — Customer email can be changed without the password and without notifying the old address
- [ ] SEC-011 — Password reset link is built from the request Host
- [ ] SEC-012 — Coupon check reveals coupon details and whether a phone has used it
- [>] SEC-013 — No HSTS or CSP on the live site; API answers over plain HTTP — tester failed it (CSP report-only, unsafe-inline); fix live 2026-11-09, ready to retest
- [>] SEC-014 — Customer 2FA can be enrolled but is never asked for at login — feature fully removed by owner decision, live 2026-11-09, ready to retest
- [ ] SEC-023 — Personal data can reach logs
- [>] SEC-015 — Register, login and forgot-password reveal whether an email exists — tester failed it; login and register fix live 2026-11-09, forgot-password already generic, ready to retest
- [>] SEC-016 — Every page publishes all admin route names to guests — tester failed it; fix live 2026-11-09, ready to retest
- [ ] SEC-018 — Some admin uploads have no size cap
- [x] SEC-024 — Token regeneration rewrites .env from a web request — superseded by SEC-020's follow-up, no longer touches .env, already verified in production 2026-11-07
