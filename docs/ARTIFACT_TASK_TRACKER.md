# Artifact task tracker

Source: https://claude.ai/artifact/DMM83dRHtY9RkYAWCrEdhj

**Done: 12 / 99** · Live, ready to retest: 11 · Fixed, not live yet: 5 · Failed: 0 · Skipped: 1

`[ ]` not done · `[x]` done · `[>]` live, ready to retest · `[~]` fixed, not live yet · `[!]` failed · `[-]` skipped

## Backend

- [ ] BE-009 — "Today" figures and invoices use UTC, not Dhaka time
- [ ] BE-001 — Malformed input causes server errors
- [~] BE-002 — A deleted product in a guest cart gives a bare 404 at checkout — tester's "need clear message" was right (real deletes are force-deletes, not soft); fixed for guests and, more severely, for logged-in customers whose item was silently cascade-deleted from their cart; fix pushed 2026-11-09 (commit `f37c051`), deploy run outcome not yet confirmed
- [ ] BE-003 — DatabaseSeeder wipes the catalog and has no production guard
- [ ] BE-007 — Search overrides other filters on admin lists
- [ ] BE-011 — Pathao sync reports success when every poll failed
- [ ] BE-004 — Pathao sync can stop for up to 24 hours after a killed run
- [x] BE-008 — Hover prefetches count as page views — tester's claim of a direct controller increment didn't match the code; re-confirmed directly on the actual product route (not just home), added that test coverage, no code change needed
- [ ] BE-010 — Forgot-password sends mail inside the request with no error handling
- [ ] BE-012 — Failed newsletter chunk cannot be retried from admin

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
- [~] BIZ-036 — Duplicate reviews and repeat review bonus — tester failed it ("a user can give at most one review on a product"); now one review per customer per product, permanently, even after an admin deletes it, and the form no longer reappears while a review waits for approval

## Database

- [ ] DB-001 — Brand delete can hard-delete soft-deleted products through the cascade
- [ ] DB-002 — No retention or pruning for growing tables, including personal data
- [~] DB-004 — Expired rate-limit rows pile up in the cache table — already fixed nightly (tester couldn't see this); split into its own hourly command for faster cleanup, fix pushed 2026-11-09 (commit `81259df`), deploy run outcome not yet confirmed

## Frontend

- [ ] FE-005 — A server error empties a logged-in customer's cart
- [ ] FE-006 — Product pages have no search or share tags in the HTML
- [ ] FE-001 — Customers cannot page through order history
- [~] FE-002 — Cart drawer and checkout estimates disagree with the server — tester failed it (checkout showed "Enter address" instead of "Free" above the threshold when no address picked yet); fix pushed 2026-11-09 (commit `9dd80c8`), deploy run outcome not yet confirmed
- [ ] FE-007 — Session timeout on landing-page checkout loses the form and may 405
- [ ] FE-009 — No noindex or canonical; meta helper defects
- [ ] FE-010 — No React error boundary
- [ ] FE-011 — Failed cart sync is silent and the item later vanishes
- [ ] FE-012 — Phone rules differ, and Bangla digits are dropped at checkout
- [ ] FE-003 — Review "Helpful" buttons do nothing
- [ ] FE-004 — Lost-parcel action has no screen
- [ ] FE-013 — Cookie banner offers choices that control nothing
- [ ] FE-014 — Inconsistent money format and small accessibility gaps

## Infrastructure

- [ ] INFRA-001 — Deploy has no maintenance mode and is not atomic
- [ ] INFRA-004 — Workflow hardening gaps
- [ ] INFRA-005 — Deploy env check is thin and .env.example is incomplete
- [ ] INFRA-007 — Leftover packages and public assets

## Operations

- [ ] OPS-002 — If cron or the queue stops, nobody is told and customer mail stops
- [ ] OPS-003 — A short mail outage fails queued mail, and recovery is one click per row
- [~] OPS-005 — A dead forwarder phone disables mobile payment with a misleading message — owner's design (heartbeat ping + gate mobile banking out entirely while down) built: new `/api/sms/heartbeat` endpoint, Android app updated, checkout/landing page hide bKash/Nagad while down; fix pushed 2026-11-09 (commit `22edbb7`), deploy run outcome not yet confirmed. Android app reinstalled by owner but it's configured for shoppydrive.com, so the heartbeat endpoint 404s until this deploy lands
- [ ] OPS-006 — Abandoned-cart mail has no opt-out or cap and reaches deleted accounts
- [ ] OPS-001 — sitemap.xml is missing on the live site
- [ ] OPS-007 — Logging defaults to one unrotated file at debug level

## Performance

- [x] PERF-001 — Product listing runs a query for every variant — tester's note was cut off ("need"); re-confirmed the `variants.media` eager-load fix is intact and the test suite still passes, no code change needed
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
