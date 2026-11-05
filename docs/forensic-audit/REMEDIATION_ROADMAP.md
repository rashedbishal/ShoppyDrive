# Remediation Roadmap

Audited commit `f49bed3`, 2026-11-05. Audit only: nothing was fixed, committed or deployed. Full entries: `COMPLETE_FINDINGS_REGISTER.md`.

Recommendations only. Follow the project rules: one finding at a time, update the tracker, and get approval for each commit, push and deploy. Pushing `main` deploys and migrates production. Nothing here has been applied.

The audit tests in `tests/` are ready to use as regression checks: each fix should turn its FAIL into a PASS.

## Suggested small batches

| Batch | Findings | Depends on | Tests | Regression risk |
|---|---|---|---|---|
| Batch 0: owner actions, no code | OPS-008, SEC-020, SEC-019, SEC-002 secret rotation, SEC-001 sender rules | None | Owner confirms | None. Regenerating the token cuts the phone off until it is updated. |
| Batch 1: payment integrity | SEC-001, SEC-007, BIZ-025 | Batch 0 sender IDs | A01, S04 | High: a wrong sender rule stops genuine payments. Run on staging values first. |
| Batch 2: charged equals shown | BIZ-001, BIZ-003, BIZ-002, BIZ-013, BIZ-030 | None | A02, A03, browser walk-through | High: touches checkout. REL-001 tests first. Bundle fix needs the preview change in FE-002. |
| Batch 3: stock and money stuck | BIZ-004, BIZ-017, BIZ-005, BIZ-006, BIZ-010, BIZ-007 | Owner approves refund rule for cancelled paid orders | A04a, A04b, A05, A06, A10, A17 | High: order lifecycle. Run race tests on MariaDB. |
| Batch 4: access and privacy | SEC-002, SEC-003, SEC-004, SEC-006, SEC-021, SEC-005, SEC-010, FE-005 | None | A07, A08, S01, S02, S03, B02, B03 | Medium: settings pages must still save. |
| Batch 5: safety net | REL-001, INFRA-003, INFRA-001, INFRA-002, SEC-022, OPS-002, OPS-003, OPS-005, OPS-006, SEC-017 | Owner approval for deploy.yml and dependency changes | CI green on MariaDB | Medium: deploy changes affect every release. |
| Batch 6: truthful pages and reach | BIZ-029, BIZ-031, BIZ-032, BIZ-033, BIZ-034, BIZ-016, BE-009, FE-006, PERF-001 | Legal review for pages | P01, P02, curl for tags | Low to medium. |

The remaining P2 and P3 items can follow in small groups by module.

## P0

| ID | Severity | Priority | Title |
|---|---|---|---|
| SEC-001 | CRITICAL | P0 | A forged text message can mark an order as paid |
| BIZ-001 | HIGH | P0 | COD can charge more than the price the customer saw |
| BIZ-002 | HIGH | P0 | A cart containing a complete bundle can never be checked out from the website |
| BIZ-003 | HIGH | P0 | Guests see a coupon discount at checkout but are charged full price |
| BIZ-004 | HIGH | P0 | Converting an incomplete order ignores stock, and cancelling it creates stock |
| BIZ-005 | HIGH | P0 | Paid orders can still be cancelled and then cannot be refunded |
| BIZ-006 | HIGH | P0 | Customers can pay for out-of-stock items and receive no order |
| OPS-008 | HIGH until verified | P0 | Backups, cron and server settings could not be verified |
| SEC-020 | MEDIUM (HIGH if still current) | P0 | A forwarder token is committed in the repository |

## P1

| ID | Severity | Priority | Title |
|---|---|---|---|
| SEC-002 | HIGH | P1 | Any admin with one settings page can read every stored secret |
| SEC-003 | HIGH | P1 | A verification code sent to one email can verify a different email |
| SEC-019 | HIGH if the app sees the CDN IP, otherwise INFO | P1 | Whether the app sees real customer IPs behind the CDN is unknown |
| BE-009 | MEDIUM | P1 | "Today" figures and invoices use UTC, not Dhaka time |
| BIZ-007 | MEDIUM | P1 | Admin can cancel orders already with Steadfast; Steadfast orders never reach Shipped |
| BIZ-008 | MEDIUM | P1 | Refunded orders stay "paid" and keep counting as revenue |
| BIZ-010 | MEDIUM | P1 | Loyalty points can be spent twice through a late mobile payment |
| BIZ-011 | MEDIUM | P1 | No bot or quantity protection on COD orders |
| BIZ-013 | MEDIUM | P1 | Coupon and duplicate-order rejections are invisible at checkout |
| BIZ-016 | MEDIUM | P1 | Landing page shows different price and delivery than it charges |
| BIZ-029 | MEDIUM | P1 | Cancelling the Reject prompt still rejects the return and emails the customer |
| BIZ-030 | MEDIUM | P1 | COD confirmation says "Total Paid" |
| BIZ-031 | MEDIUM | P1 | Checkout shows payment logos the shop does not accept |
| BIZ-032 | MEDIUM | P1 | Live terms and FAQ contradict how the shop works |
| BIZ-033 | MEDIUM | P1 | Home page ratings count unmoderated reviews |
| BIZ-034 | MEDIUM | P1 | No return, refund or shipping policy pages; seller identity missing |
| FE-005 | MEDIUM | P1 | A server error empties a logged-in customer's cart |
| FE-006 | MEDIUM | P1 | Product pages have no search or share tags in the HTML |
| INFRA-001 | MEDIUM | P1 | Deploy has no maintenance mode and is not atomic |
| INFRA-002 | MEDIUM | P1 | A broken release may not be fixable by pushing, and there is no rollback |
| INFRA-003 | MEDIUM | P1 | Migrations are never run on MariaDB before production |
| OPS-002 | MEDIUM | P1 | If cron or the queue stops, nobody is told and customer mail stops |
| OPS-003 | MEDIUM | P1 | A short mail outage fails queued mail, and recovery is one click per row |
| OPS-005 | MEDIUM | P1 | A dead forwarder phone disables mobile payment with a misleading message |
| OPS-006 | MEDIUM | P1 | Abandoned-cart mail has no opt-out or cap and reaches deleted accounts |
| PERF-001 | MEDIUM | P1 | Product listing runs a query for every variant |
| REL-001 | MEDIUM | P1 | Tests pass payloads the real website never sends |
| SEC-004 | MEDIUM | P1 | Customers' Google/Facebook tokens are sent to browsers |
| SEC-005 | MEDIUM | P1 | Changing an admin password does not log out other sessions |
| SEC-006 | MEDIUM | P1 | Order tracking returns the whole order record |
| SEC-007 | MEDIUM | P1 | View-only SMS forwarder permission reveals the full token |
| SEC-010 | MEDIUM | P1 | Weaker password policy and no per-account login limit for admins |
| SEC-017 | MEDIUM | P1 | composer audit lists new Symfony advisories |
| SEC-021 | MEDIUM | P1 | Back button after logout shows account data |
| SEC-022 | LOW | P1 | Internal audit documents and agent config are deployed to the server |

## P2

| ID | Severity | Priority | Title |
|---|---|---|---|
| BIZ-009 | MEDIUM | P2 | Partial delivery is booked as fully delivered and paid, with no way out |
| BIZ-012 | MEDIUM | P2 | A whole-order refund restocks delivered goods automatically |
| BIZ-027 | MEDIUM | P2 | Steadfast status relies on unverified webhook assumptions with no fallback |
| DB-002 | MEDIUM | P2 | No retention or pruning for growing tables, including personal data |
| FE-001 | MEDIUM | P2 | Customers cannot page through order history |
| FE-007 | MEDIUM | P2 | Session timeout on landing-page checkout loses the form and may 405 |
| FE-008 | MEDIUM | P2 | Google or Facebook login discards the guest cart |
| OPS-004 | MEDIUM | P2 | Newsletter has no send-rate limit and shares the order mail account and queue |
| PERF-003 | MEDIUM | P2 | Icon registry and admin layout load on the customer home page |
| PERF-004 | MEDIUM | P2 | Uploaded images are served at original size |
| SEC-008 | MEDIUM | P2 | A social link planted on an unverified account can survive the real owner's recovery |
| BE-001 | LOW | P2 | Malformed input causes server errors |
| BE-002 | LOW | P2 | A deleted product in a guest cart gives a bare 404 at checkout |
| BE-003 | LOW | P2 | DatabaseSeeder wipes the catalog and has no production guard |
| BE-007 | LOW | P2 | Search overrides other filters on admin lists |
| BE-011 | LOW | P2 | Pathao sync reports success when every poll failed |
| BIZ-014 | LOW | P2 | A retried checkout puts purchased items back in the cart |
| BIZ-015 | LOW | P2 | Per-customer coupon limits do not hold across guest and account |
| BIZ-017 | LOW | P2 | Incomplete-order conversion ignores flash price and variant ownership |
| BIZ-024 | LOW | P2 | Referral discount can be farmed with new emails |
| BIZ-025 | LOW | P2 | Transaction IDs are not tied to the checkout time or payer |
| BIZ-026 | LOW | P2 | Legacy ZiniPay path is still reachable and has no amount check |
| BIZ-028 | LOW | P2 | Pathao silently falls back to sandbox |
| DB-001 | LOW | P2 | Brand delete can hard-delete soft-deleted products through the cascade |
| FE-002 | LOW | P2 | Cart drawer and checkout estimates disagree with the server |
| FE-009 | LOW | P2 | No noindex or canonical; meta helper defects |
| FE-010 | LOW | P2 | No React error boundary |
| FE-011 | LOW | P2 | Failed cart sync is silent and the item later vanishes |
| FE-012 | LOW | P2 | Phone rules differ, and Bangla digits are dropped at checkout |
| INFRA-004 | LOW | P2 | Workflow hardening gaps |
| INFRA-005 | LOW | P2 | Deploy env check is thin and .env.example is incomplete |
| INFRA-006 | LOW | P2 | rsync --delete removes server-created files |
| OPS-001 | LOW | P2 | sitemap.xml is missing on the live site |
| OPS-007 | LOW | P2 | Logging defaults to one unrotated file at debug level |
| PERF-002 | LOW | P2 | Product cards send the whole product record |
| PERF-006 | LOW | P2 | Admin SMS poll hits the app every 5 seconds per tab |
| SEC-009 | LOW | P2 | Customer email can be changed without the password and without notifying the old address |
| SEC-011 | LOW | P2 | Password reset link is built from the request Host |
| SEC-012 | LOW | P2 | Coupon check reveals coupon details and whether a phone has used it |
| SEC-013 | LOW | P2 | No HSTS or CSP on the live site; API answers over plain HTTP |
| SEC-014 | LOW | P2 | Customer 2FA can be enrolled but is never asked for at login |
| SEC-023 | LOW | P2 | Personal data can reach logs |

## P3

| ID | Severity | Priority | Title |
|---|---|---|---|
| BE-004 | LOW | P3 | Pathao sync can stop for up to 24 hours after a killed run |
| BE-006 | LOW | P3 | Currency symbol setting ignored on home and wishlist |
| BE-008 | LOW | P3 | Hover prefetches count as page views |
| BE-010 | LOW | P3 | Forgot-password sends mail inside the request with no error handling |
| BE-012 | LOW | P3 | Failed newsletter chunk cannot be retried from admin |
| BIZ-018 | LOW | P3 | Referral discount preview ignores Buy Now |
| BIZ-019 | LOW | P3 | Paying a mobile checkout empties items added later |
| BIZ-020 | LOW | P3 | Admin price fields have no sanity limits |
| BIZ-021 | LOW | P3 | District and city are free text and set the delivery fee |
| BIZ-022 | LOW | P3 | Opposite-order stock locks use a single COD attempt |
| BIZ-023 | LOW | P3 | Duplicate-order protection has no database backstop |
| BIZ-035 | LOW | P3 | Bundle prices can miss the advertised total by a few poisha |
| BIZ-036 | LOW | P3 | Duplicate reviews and repeat review bonus |
| BIZ-037 | LOW | P3 | Free delivery ৳999 offer is hardcoded in the footer and login page |
| DB-004 | LOW | P3 | Expired rate-limit rows pile up in the cache table |
| FE-003 | LOW | P3 | Review "Helpful" buttons do nothing |
| FE-004 | LOW | P3 | Lost-parcel action has no screen |
| FE-013 | LOW | P3 | Cookie banner offers choices that control nothing |
| FE-014 | LOW | P3 | Inconsistent money format and small accessibility gaps |
| PERF-005 | LOW | P3 | Admin sidebar count scans the orders table |
| PERF-007 | LOW | P3 | Search scans the table despite the FULLTEXT index |
| PERF-008 | LOW | P3 | Orphaned search cache files until the next deploy |
| PERF-009 | LOW | P3 | Unbounded lists in admin compose screens and Pathao sync |
| REL-003 | LOW | P3 | Four race tests silently skip on anything but port 3306, and show as passed |
| REL-004 | LOW | P3 | Tests leave real files in storage |
| SEC-015 | LOW | P3 | Register, login and forgot-password reveal whether an email exists |
| SEC-018 | LOW | P3 | Some admin uploads have no size cap |
| SEC-024 | LOW | P3 | Token regeneration rewrites .env from a web request |
| BE-005 | INFO | P3 | Dead code and unused schema |
| DB-003 | INFO | P3 | Some uniqueness relies on application code only |
| DB-005 | INFO | P3 | Search index and enums depend on the driver being named mysql |
| INFRA-007 | INFO | P3 | Leftover packages and public assets |
| REL-002 | INFO | P3 | Race test assumes REPEATABLE READ, but production is READ-COMMITTED |
| SEC-016 | INFO | P3 | Every page publishes all admin route names to guests |
