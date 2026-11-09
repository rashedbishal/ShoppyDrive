# Artifact task tracker

Source: https://claude.ai/artifact/DMM83dRHtY9RkYAWCrEdhj

**Done: 1 / 99** · Live, ready to retest: 5 · Fixed, not live yet: 4 · Failed: 0 · Skipped: 1

`[ ]` not done · `[x]` done · `[>]` live, ready to retest · `[~]` fixed, not live yet · `[!]` failed · `[-]` skipped

## Backend

- [ ] BE-009 — "Today" figures and invoices use UTC, not Dhaka time
- [ ] BE-001 — Malformed input causes server errors
- [ ] BE-002 — A deleted product in a guest cart gives a bare 404 at checkout
- [ ] BE-003 — DatabaseSeeder wipes the catalog and has no production guard
- [ ] BE-007 — Search overrides other filters on admin lists
- [ ] BE-011 — Pathao sync reports success when every poll failed
- [ ] BE-004 — Pathao sync can stop for up to 24 hours after a killed run
- [ ] BE-008 — Hover prefetches count as page views
- [ ] BE-010 — Forgot-password sends mail inside the request with no error handling
- [ ] BE-012 — Failed newsletter chunk cannot be retried from admin

## Business logic

- [~] BIZ-001 — COD can charge more than the price the customer saw — tester failed it (retry never worked, not an overcharge); fix committed locally 2026-11-09, not yet pushed/deployed
- [~] BIZ-002 — A cart containing a complete bundle can never be checked out from the website — tester failed it (admin could save a bundle needing more stock than exists, not an "every bundle fails" regression); fix committed locally 2026-11-09, not yet pushed/deployed
- [ ] BIZ-003 — Guests see a coupon discount at checkout but are charged full price
- [ ] BIZ-004 — Converting an incomplete order ignores stock, and cancelling it creates stock
- [ ] BIZ-005 — Paid orders can still be cancelled and then cannot be refunded
- [ ] BIZ-006 — Customers can pay for out-of-stock items and receive no order
- [-] BIZ-007 — Admin can cancel orders already with Steadfast; Steadfast orders never reach Shipped — owner-closed 2026-11-09: cancel-side already blocked in code (deployed 2026-11-05), no Steadfast cancel API exists, owner accepted the Shipped-status gap as-is
- [~] BIZ-008 — Refunded orders stay "paid" and keep counting as revenue — tester failed it (no working refund button at all, not the shipping-cap bug); fix committed locally 2026-11-09, not yet pushed/deployed
- [~] BIZ-010 — Loyalty points can be spent twice through a late mobile payment — tester skipped it (needs live SMS config); owner chose to close the previously-accepted residual gap anyway, fix committed locally 2026-11-09, not yet pushed/deployed
- [ ] BIZ-011 — No bot or quantity protection on COD orders
- [ ] BIZ-013 — Coupon and duplicate-order rejections are invisible at checkout
- [ ] BIZ-029 — Cancelling the Reject prompt still rejects the return and emails the customer
- [ ] BIZ-030 — COD confirmation says "Total Paid"
- [ ] BIZ-031 — Checkout shows payment logos the shop does not accept
- [ ] BIZ-033 — Home page ratings count unmoderated reviews
- [ ] BIZ-034 — No return, refund or shipping policy pages; seller identity missing
- [ ] BIZ-012 — A whole-order refund restocks delivered goods automatically
- [ ] BIZ-014 — A retried checkout puts purchased items back in the cart
- [ ] BIZ-015 — Per-customer coupon limits do not hold across guest and account
- [ ] BIZ-017 — Incomplete-order conversion ignores flash price and variant ownership
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
- [ ] BIZ-036 — Duplicate reviews and repeat review bonus

## Database

- [ ] DB-001 — Brand delete can hard-delete soft-deleted products through the cascade
- [ ] DB-002 — No retention or pruning for growing tables, including personal data
- [ ] DB-004 — Expired rate-limit rows pile up in the cache table

## Frontend

- [ ] FE-005 — A server error empties a logged-in customer's cart
- [ ] FE-006 — Product pages have no search or share tags in the HTML
- [ ] FE-001 — Customers cannot page through order history
- [ ] FE-002 — Cart drawer and checkout estimates disagree with the server
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
- [ ] OPS-005 — A dead forwarder phone disables mobile payment with a misleading message
- [ ] OPS-006 — Abandoned-cart mail has no opt-out or cap and reaches deleted accounts
- [ ] OPS-001 — sitemap.xml is missing on the live site
- [ ] OPS-007 — Logging defaults to one unrotated file at debug level

## Performance

- [ ] PERF-001 — Product listing runs a query for every variant
- [ ] PERF-002 — Product cards send the whole product record
- [ ] PERF-003 — Icon registry and admin layout load on the customer home page
- [ ] PERF-006 — Admin SMS poll hits the app every 5 seconds per tab
- [ ] PERF-005 — Admin sidebar count scans the orders table
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
