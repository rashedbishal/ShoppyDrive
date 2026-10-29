# ShoppyDrive Remediation Backlog

**Purpose:** single source of truth for remaining audit remediation work, so a future Claude session (or human) can resume without relying on conversation memory. Read this file, then `docs/SESSION_HANDOVER.md`, before taking any action.

**Last updated:** 2026-10-29, immediately after a full read-only remediation status audit. This file was created from that audit's findings — it has not yet been re-verified by a later session.

**Relationship to `docs/SESSION_HANDOVER.md`:** that file is a narrower, session-scoped handover (git state at a specific pause point, with a "how to resume" prompt). This file is the durable, task-by-task backlog across the *whole* audit. Keep both, but treat this file as authoritative for "what remediation work remains" — update `SESSION_HANDOVER.md` for session-specific pause state, and update *this* file whenever a task's status changes. Do not create a third tracker.

---

## 0. Documentation gap — read this before trusting any finding ID below

**The original "ShoppyDrive Exhaustive E-Commerce System Audit" document could not be located** — not in this repository, not in project memory, not anywhere inspected during the most recent audit. No Claude session in this engagement has seen that document's full original text.

Finding IDs with a known, working description (confirmed by direct code/git inspection across this engagement): `ARCH-001`, `DB-001`, `PROMO-LOY-001`, `PAY-RETURN-001`, `OPS-001`, `CART-01`, `SEC-01`, `LOYALTY-01`.

**Finding IDs named by the user but with NO recoverable original description — do not guess at these:**
- `SHIP-001`
- `REL-001`
- `ORD-001`
- `ORD-002`
- `DEP-SEC-003`

These are tracked below as `SOURCE-RECOVERY-NEEDED` tasks. If the original audit document is ever found (ask the user where it might be — a different session's export, a pasted message elsewhere, a ticketing system), re-derive their real descriptions from it rather than inventing plausible-sounding ones. Anywhere this file speculates about a possible match between one of these IDs and a confirmed code finding, it is explicitly labeled **SPECULATIVE** and must not be treated as confirmed.

Separately: the stored-XSS remediation work is documented and confirmed implemented for **5** sink pairs (Blog, Product, Setting, LandingPage, Newsletter). The user has referred to "six identified sinks" at least once; no 6th sink has ever been identified in any session's evidence. This discrepancy is unresolved — see `XSS-SINK-GAP-001` below.

---

## 1. Current repository / commit status

**⚠️ Re-verify this entire section at the start of any future session — do not trust it blindly if meaningful time has passed.**

- Branch: `main`. HEAD at last audit: `db80bdcb3e50db46dd4c27e8dbe4ea0ee2e35eb1` (`style: fix Prettier formatting in delete-customer-dialog`).
- `origin/main` was confirmed in sync with local `HEAD` at `db80bdc...` (0 ahead, 0 behind) as of the last audit.
- Reconciliation merge commit `69f6db814946a283da33c7970932d5db48fccfb3` is confirmed present in history, with parents `c7f9127c9881bfc2618dec2c05a3658c9127637f` (4 local PROMO-LOY-001/PAY-RETURN-001 commits) and `666758d6deccdef867d6e2b8039c3e4434998f2a` (13 commits that were independently pushed to `origin/main` by another session, including an independent fix for the same loyalty/referral atomicity problem, SEC-01, CART-01, and the XSS sanitization work).
- **Two intentionally preserved, unstaged, uncommitted local changes — never stage, commit, or discard either without separate explicit approval:**
  1. `vite.config.ts` — active `server: {...}` block with `host: '192.168.0.111'`.
  2. `resources/js/pages/auth/reset-password.tsx` — `route('password.store')` → `route('password.update')` (ARCH-001).
- **Four stashes exist and must remain untouched** (do not pop, drop, or apply any of them without separate explicit approval):
  - `stash@{0}`: backup of the two preserved changes above, taken immediately before the reconciliation merge (recoverable reference copy — the live working-tree edits are the authoritative source, not this stash).
  - `stash@{1}`, `stash@{2}`, `stash@{3}`: older, pre-existing, unrelated eslint/vite stashes from before this audit engagement began.
- No migration has been run against any persistent database by any session in this engagement. No production access has been used. **Production deployment status for every finding in this file is UNVERIFIED** — a push to `origin/main` auto-triggers `.github/workflows/deploy.yml`, which (if its `checks` job passes) deploys and runs `php artisan migrate --force` against production with no manual gate, but no session has confirmed the actual production outcome of any specific deploy.

---

## 2. How to resume (read this before doing anything else)

1. Read this file in full, then `docs/SESSION_HANDOVER.md`.
2. Re-run read-only git checks: `git status --short`, `git log -1 --oneline`, `git fetch origin main` then compare `origin/main` to local `HEAD`, `git stash list`. Do not trust §1 above without re-confirming it.
3. Confirm the two preserved files (`vite.config.ts`, `resources/js/pages/auth/reset-password.tsx`) are still present as unstaged changes with the exact content described in §1. Do not modify, stage, or commit them.
4. Confirm all 4 stashes are still present. Do not pop, drop, or apply any of them.
5. **Work on exactly one explicitly user-approved task from this backlog at a time.** Do not infer broader permission from a "resume" instruction — each task below still needs its own go-ahead, per its own "Next safe action."
6. When a task's status changes, update its entry in this file (status, evidence, next action) rather than leaving the file stale. Do not create a duplicate or competing tracker.

---

## 3. Completed work (implemented, committed, merged, pushed)

### ARCH-001 — password-reset route fix
- **Status:** `DONE` (code-correct), but **intentionally never committed**.
- **Evidence:** `resources/js/pages/auth/reset-password.tsx:75`, `route('password.update')`.
- **Commit:** none — lives only as an unstaged working-tree edit, by repeated explicit user instruction throughout this engagement.
- **Tests:** no dedicated test; covered incidentally by any password-reset-flow test that exercises this route.
- **Push/Deploy:** not pushed (not committed), not deployed.
- **Next safe action:** none unless the user explicitly asks to commit it.

### DB-001 — anonymize customers instead of hard-deleting
- **Status:** `DONE` in code, committed, merged, pushed. Deployment **unverified**.
- **Evidence/commit:** `dd63873` — confirmed ancestor of current `HEAD`.
- **Tests:** not independently re-run in the most recent audit session; was green at implementation time.
- **Next safe action:** none pending; re-verify production anonymization behavior only if/when Hostinger access is authorized.

### PROMO-LOY-001 — atomic/idempotent loyalty + referral operations
- **Status:** `DONE` in code (Batch covers all originally-scoped methods), committed, merged, pushed. MySQL concurrency **unverified** (see §6).
- **Evidence:** `app/Services/LoyaltyPointsService.php`, `app/Services/ReferralService.php` — all 7 methods transaction-wrapped with retry (`, 3`), correct lock ordering, idempotency re-checks on locked rows.
- **Commits:** `710f88d` (local), reconciled with an independent fix for the same problem from commit `0aa4d11` (origin) during merge `69f6db8`.
- **Tests:** `tests/Feature/LoyaltyPointsTest.php`, `tests/Feature/ReferralProgramTest.php` — passed in targeted and full-suite runs post-merge.
- **Next safe action:** none pending implementation; see `MYSQL-CONCURRENCY-001` for the remaining verification gap.

### PAY-RETURN-001 (Batch 1) — return approval restocks inventory; full-return triggers coupon release, referral revoke, redeemed-points refund
- **Status:** `DONE` for Batch 1 scope, committed, merged, pushed. Batch 2 deferred (see §4). MySQL concurrency **unverified**.
- **Evidence:** `app/Http/Controllers/Admin/ReturnController.php`, `app/Services/InventoryService.php`, `app/Models/ReturnRequest.php`, migration `database/migrations/2026_10_29_173000_add_restocked_quantity_to_order_items_table.php`.
- **Commit:** `c7f9127`.
- **Tests:** `tests/Feature/ReturnRequestTest.php` (37 tests), plus the scheduled-reward-reduction formula tests in `LoyaltyPointsTest.php` — all passed post-merge.
- **Next safe action:** none pending for Batch 1; see `PAY-RETURN-001-BATCH-2` in §4.

### SEC-01 (origin's) — block auto-linking an existing account on an explicitly-unverified Google email
- **Status:** `DONE`, committed, merged, pushed. Deployment unverified.
- **Evidence:** `app/Http/Controllers/Auth/SocialAuthController.php:113` — checks `$socialUser->getRaw()['email_verified'] ?? null) === false`.
- **Commit:** `0aa4d11` (origin), preserved through manual merge-conflict resolution in `69f6db8`.
- **Tests:** `tests/Feature/Auth/SocialLoginTest.php` — passed.
- **Next safe action:** none pending.

### LOYALTY-01 (origin's) — independent atomicity fix, including a `status === DELIVERED` re-check on locked order rows
- **Status:** `DONE`, committed, merged, pushed. This specific re-check is a genuine improvement over PROMO-LOY-001's original scope and was deliberately *kept* (not discarded) during merge conflict resolution.
- **Evidence:** `app/Services/LoyaltyPointsService.php:351`, `app/Services/ReferralService.php:244`.
- **Commit:** `0aa4d11` (origin).
- **Tests:** covered by the same PROMO-LOY-001 test suites above.
- **Next safe action:** none pending.

### CART-01 (origin's; ID confirmed via in-code comment, not SPECULATIVE) — landing-page COD/mobile-banking duplicate-order idempotency
- **Status:** `DONE`, committed, merged, pushed. Deployment unverified.
- **Evidence:** `app/Http/Controllers/LandingPageController.php:91-131` — client-generated UUID `order_token`, `Cache::lock()`, cached-result replay on retry.
- **Commit:** `0aa4d11` (origin).
- **Tests:** `tests/Feature/LandingPageCodDuplicateOrderTest.php` — 9/9 passed.
- **Next safe action:** none pending for the landing-page path; see `CHECKOUT-COD-002` below for the still-incomplete main-checkout equivalent.

### XSS sanitization — 5 of the "6" claimed sinks
- **Status:** `DONE` for the 5 confirmed sink pairs. The claimed 6th sink is unresolved — see `XSS-SINK-GAP-001` in §4.
- **Evidence (direct grep, confirmed wired, not just installed):** `app/Models/Blog.php:46`, `app/Models/Product.php:80`, `app/Models/Setting.php:54` (key-gated), `app/Models/LandingPage.php:41`, `app/Http/Controllers/Admin/NewsletterController.php:85` — all call `Purifier::clean()`.
- **Commit:** `0aa4d11` (origin). Dependency (`mews/purifier`) added earlier in commit `38e51bf`.
- **Tests:** `tests/Feature/Admin/BlogTest.php`, `ProductDescriptionSanitizationTest.php`, `AboutPageSettingsTest.php`, `LandingPageCustomContentValidationTest.php`, `NewsletterSendTest.php`, `ContentSanitizationDryRunCommandTest.php` — all passed.
- **Next safe action:** none pending for the 5 confirmed sinks.

### Courier `ConnectionException` handling — admin-triggered manual Pathao/Steadfast actions
- **Status:** `DONE` for the two confirmed call sites. Not exhaustively re-audited across every courier-service method.
- **Evidence:** `app/Http/Controllers/Admin/OrderController.php:344,392`.
- **Commit:** `0aa4d11` (origin). (The scheduled-sync-command equivalent was already fixed earlier, in batch-3 commit `d5b4383`, predating this file.)
- **Tests:** `tests/Feature/Admin/PathaoCourierOrderTest.php`, `SteadfastCourierOrderTest.php` — passed.
- **Next safe action:** `VERIFICATION_REQUIRED` only if a full audit of every `PathaoCourierService`/`SteadfastCourierService` method is wanted — not currently scheduled.

---

## 4. Open implementation tasks (prioritized by impact and dependencies)

### SHIP-DUP-001 — duplicated and *drifted* shipping-cost calculation
- **Status:** `OPEN`.
- **Description:** `CheckoutController::calculateShippingCost()` (`app/Http/Controllers/CheckoutController.php:403-437`) and `Admin\IncompleteOrderController::calculateShippingCost()` (`app/Http/Controllers/Admin/IncompleteOrderController.php:205-214`) are two independent, private implementations of the same concept. Direct diff confirms the admin version is **missing the entire per-product/bundle free-delivery check** the customer-facing version has — a confirmed, live billing-correctness bug, not a theoretical risk.
- **Priority:** **Highest of the open items** — directly affects what customers get charged when an admin converts an abandoned checkout into a real order.
- **Dependencies:** none; purely a code fix (extract one shared method/service and use it from both places).
- **Evidence:** file/line references above, confirmed by direct read this session.
- **Acceptance criteria:** one shared shipping-cost calculation, used by both controllers; a regression test proving a cart that gets free delivery at normal checkout also gets free delivery when converted from an incomplete order.
- **Next safe action:** `VERIFICATION_REQUIRED` only in the sense of confirming no *other* call site also duplicates this logic (`LandingPageController` appears to call the shared `Setting::shippingRateFor()` directly rather than duplicating the higher-level method — re-confirm before fixing) — otherwise ready for an implementation task once approved. **This is a possible, speculative match for `SHIP-001` — not confirmed, since `SHIP-001`'s original text is unavailable.**

### ADMIN-2FA-001 — admin accounts have no two-factor authentication
- **Status:** `OPEN`.
- **Description:** `app/Models/Admin.php` has no `TwoFactorAuthenticatable` trait and no `two_factor_*` columns anywhere. `config/fortify.php` enables 2FA, but only for the Fortify-registered customer (`web`) guard — `AdminAuthController` is hand-rolled and bypasses Fortify entirely. The highest-privilege accounts in the system have zero second factor.
- **Priority:** High — standing security gap on the most sensitive auth surface.
- **Dependencies:** needs a business decision on scope/approach (TOTP via a hand-rolled flow mirroring Fortify's, or some other mechanism) before implementation.
- **Evidence:** confirmed by direct grep this session (`grep -n "TwoFactorAuthenticatable\|two_factor" app/Models/Admin.php` → no matches).
- **Acceptance criteria:** TBD, pending the scope decision above.
- **Next safe action:** `BLOCKED` on a business decision — do not implement until the user chooses an approach.

### PAY-RETURN-001-BATCH-2 — proportional redeemed-points reversal
- **Status:** `PARTIALLY_COMPLETE` (Batch 1 done, Batch 2 not started).
- **Description:** when a partial return is refunded, redeemed loyalty points used on that order are not proportionally reversed. This was explicitly deferred out of Batch 1's scope.
- **Priority:** Medium — a real gap, but narrower in blast radius than Batch 1's inventory/coupon/referral fixes.
- **Dependencies:** needs a business decision on the exact proportionality formula (mirroring the scheduled-reward-reduction formula already implemented for *earned* points) before implementation.
- **Evidence:** `docs/SESSION_HANDOVER.md` and this engagement's own PAY-RETURN-001 planning record this deferral explicitly.
- **Acceptance criteria:** TBD, pending the formula decision.
- **Next safe action:** `BLOCKED` on a business decision.

### CHECKOUT-COD-002 — main checkout COD path still lacks a real idempotency token
- **Status:** `PARTIALLY_COMPLETE` (landing-page equivalent is `DONE`, see `CART-01`; main checkout is not).
- **Description:** `CheckoutController::store()` relies only on incidental protection (the cart being emptied by order creation causes a retried submission to hit a 422 "cart is empty") — confirmed by direct inspection, no `order_token`/`Cache::lock()` mechanism exists here, unlike the landing-page path.
- **Priority:** Medium-High — same risk class as the now-fixed `CART-01`, on a higher-traffic path.
- **Dependencies:** none technical; could reuse the same `order_token`/`Cache::lock()` pattern already proven in `LandingPageController::placeOrder()`.
- **Evidence:** `app/Http/Controllers/CheckoutController.php:124-154`.
- **Acceptance criteria:** a duplicate/retried `checkout.store()` submission for the same logical attempt cannot create two orders, verified by a test mirroring `LandingPageCodDuplicateOrderTest.php`.
- **Next safe action:** ready for an implementation task once approved — no blocking decision identified.

### XSS-SINK-GAP-001 — possible 6th stored-XSS sink unaccounted for
- **Status:** `VERIFICATION_REQUIRED`.
- **Description:** the user has referred to "six identified sinks" for stored XSS; only 5 sink pairs have ever been documented or confirmed implemented across this engagement (see §3). No session has identified a 6th.
- **Priority:** Medium — could be a miscount, or could be a real, unaddressed sink.
- **Dependencies:** needs either the original audit document (see §0) or a fresh read-only sweep for any other raw/unescaped output or unsanitized rich-text input not yet covered.
- **Evidence:** `docs/SESSION_HANDOVER.md` §5 and origin commit `0aa4d11`'s own message both independently list exactly 5 sinks.
- **Acceptance criteria:** either confirm no 6th sink exists (document the sweep), or identify and remediate it with the same `Purifier::clean()` pattern.
- **Next safe action:** a read-only sweep (e.g. `grep -rn "{!!" resources/views/`, review of all rich-text-editor-backed fields) could be run on request before any code change.

---

## 5. External verification tasks

### OPS-001 — Hostinger scheduler cron and queue-worker execution
- **Status:** `VERIFICATION_REQUIRED`.
- **Description:** whether `php artisan schedule:run` is actually cron-triggered on Hostinger, and whether the database queue is actually draining in production. A prior session's user message reported queue draining as working, but that was stated as **user-confirmed, not independently verified by Claude**, and the scheduler cron specifically was flagged as a *separate*, still-unconfirmed item at that time.
- **Priority:** High — several other "scheduled" fixes (loyalty/referral grants, abandoned-cart reminders) silently depend on this actually running.
- **Dependencies:** requires hPanel or SSH access to Hostinger, which no session in this engagement has used.
- **Evidence:** `docs/SESSION_HANDOVER.md` §6 historical note; no newer evidence exists.
- **Acceptance criteria:** direct confirmation of a cron entry calling `schedule:run` every minute, and direct confirmation (via logs or DB inspection) that queued jobs are processed in a reasonable time window.
- **Next safe action:** ask the user to check hPanel directly, or provide read-only access for a future session to verify.

---

## 6. MySQL concurrency and CI verification tasks

### MYSQL-CONCURRENCY-001 — PROMO-LOY-001 and PAY-RETURN-001 real InnoDB locking/deadlock behavior
- **Status:** `VERIFICATION_REQUIRED`.
- **Description:** three race-test files exist, each gated behind an explicit allow-listed disposable-database env var, and have never once run against real MySQL in this engagement: `tests/Feature/CouponConcurrencyRaceTest.php` (`COUPON_RACE_TEST_DATABASE`), `tests/Feature/LoyaltyPointsConcurrencyRaceTest.php` (`LOYALTY_RACE_TEST_DATABASE`), `tests/Feature/ReturnFullRefundConcurrencyRaceTest.php` (`RETURN_RACE_TEST_DATABASE`).
- **Priority:** Medium — the application-level logic is reasoned-through and tested against SQLite, but the specific InnoDB locking/snapshot-visibility properties these fixes depend on have never been empirically proven.
- **Dependencies:** explicit authorization to provision a disposable MySQL database matching the pattern `^shoppydrive_test_[a-z0-9_]+$` on `127.0.0.1`/`localhost`:`3306`.
- **Evidence:** all three test files; every test run in this engagement reports them as skipped, never passed.
- **Acceptance criteria:** all three files run (not skipped) and pass against a real MySQL connection.
- **Next safe action:** ask the user whether to authorize provisioning such a database; do not provision one without that explicit authorization.

### CI-MYSQL-001 — no MySQL service configured in any CI workflow
- **Status:** `OPEN`.
- **Description:** `tests.yml`, `lint.yml`, and `deploy.yml` all only install the `pdo_mysql` PHP *extension* — none runs an actual MySQL *service container*. The three race tests above will skip in CI forever under the current configuration, not just locally.
- **Priority:** Medium — same underlying gap as `MYSQL-CONCURRENCY-001`, but specifically about making verification *repeatable* rather than a one-off.
- **Dependencies:** a decision on whether to add a MySQL service to one of the existing workflows (likely `tests.yml`, since `deploy.yml`'s `checks` job is the actual deploy gate and shouldn't grow slower/flakier).
- **Evidence:** direct grep of all three `.github/workflows/*.yml` files this session — only extension installs found, no `services:` block.
- **Acceptance criteria:** a CI job runs the three race tests against a real MySQL service and they pass, on every push/PR.
- **Next safe action:** ready for a scoped proposal once approved — no technical blocker identified beyond the decision itself.

---

## 7. Dependency vulnerability triage

### DEP-SEC-COMPOSER-001 — symfony/mime advisory
- **Status:** `OPEN`.
- **Description:** `composer audit` reports one medium-severity advisory: `symfony/mime`, CVE-2026-45070 (email header injection via non-token characters in MIME parameter names). Also one abandoned package, `nunomaduro/pao` (suggested replacement: `laravel/pao`).
- **Priority:** Medium.
- **Dependencies:** CLAUDE.md's standing "no dependency changes without approval" rule.
- **Evidence:** `composer audit` output, captured during the most recent status audit.
- **Acceptance criteria:** either the advisory is resolved via an approved dependency update, or it's explicitly assessed as not exploitable in this app's usage and consciously accepted.
- **Next safe action:** ask the user whether to approve a `composer update symfony/mime` (or equivalent) once a safe target version is confirmed.

### DEP-SEC-NPM-001 — 14 unresolved npm advisories (2 critical, 8 high, 3 moderate, 1 low)
- **Status:** `OPEN`.
- **Description:** `npm audit` reports 14 vulnerabilities, including critical-severity findings in `shell-quote` (via `concurrently`) and high-severity findings in `source-map-js`, `vite`, and `zod`. Most are dev-tooling-only exposure (Vite dev server, `concurrently`), not necessarily production-runtime risk, but this has not been triaged item-by-item.
- **Priority:** Medium — severity labels look alarming, but actual production exposure is unconfirmed either way.
- **Dependencies:** same approval rule as above; `npm audit fix` may pull in breaking changes for some of these (Vite major version bumps historically have).
- **Evidence:** `npm audit` output, captured during the most recent status audit.
- **Acceptance criteria:** each of the 14 findings is explicitly triaged as (a) fixed, or (b) consciously accepted with a documented reason (e.g. "dev-only, never shipped to production").
- **Next safe action:** ask the user whether to review the full `npm audit` output together before deciding on `npm audit fix` vs. `--force` vs. selective upgrades.

---

## 8. Browser/E2E coverage

### E2E-COVERAGE-001 — no browser/E2E test suite exists
- **Status:** `OPEN`.
- **Description:** confirmed via `find tests -iname "*Browser*" -o -iname "*e2e*"` returning nothing. All existing coverage is Pest feature/unit tests against the backend; no real-browser or Inertia-page-rendering verification exists (Pest 4 does support browser testing per this project's `pest-testing` skill, but it is unused).
- **Priority:** Low-Medium — not a known active defect, but a real coverage gap for frontend regressions (hydration mismatches, JS errors, visual breakage) that backend-only tests cannot catch.
- **Dependencies:** scope decision (which flows deserve E2E coverage first — checkout, admin order management, auth are the obvious candidates).
- **Evidence:** confirmed absence this session.
- **Acceptance criteria:** at minimum, smoke-test coverage for the checkout and admin-login flows using Pest's browser-testing support.
- **Next safe action:** ask the user whether/when to scope a first browser-testing pass.

---

## 9. Backup, rollback, observability, and deployment-safety work

### DEPLOY-SAFETY-001 — no backup or rollback step in the deploy pipeline
- **Status:** `OPEN`.
- **Description:** `.github/workflows/deploy.yml` has no backup step and no rollback step of any kind (confirmed via direct grep — zero matches for "backup"/"rollback"). It runs `php artisan migrate --force` directly against production with the `checks` job (Pint + full Pest suite) as the *only* gate — no manual approval, no pre-migration backup, no automated way to revert a bad deploy.
- **Priority:** High — this is a structural production-safety gap, not a one-off bug; any bad migration or bad deploy has no automated recovery path.
- **Dependencies:** needs a decision on backup mechanism given Hostinger shared hosting constraints (no Redis, no persistent worker process, per existing CLAUDE.md architecture notes) — likely a pre-migration DB dump step, or a documented manual rollback procedure at minimum.
- **Evidence:** direct inspection of `.github/workflows/deploy.yml` this session.
- **Acceptance criteria:** TBD, pending the mechanism decision — at minimum, a documented manual rollback runbook should exist even before any automated backup step is built.
- **Next safe action:** ask the user whether this is in scope for near-term work, given it's an infrastructure/process change rather than an application bug.

---

## 10. TEST-FLAKE-001 — intermittent `BuyNowCheckoutTest.php` full-suite failure

- **Status:** `VERIFICATION_REQUIRED` (root cause **unconfirmed** — do not treat as resolved or as root-caused).
- **Description:** when the full test suite runs, `tests/Feature/BuyNowCheckoutTest.php` intermittently fails with "No query results for model Order" — a *different* specific test within the file fails on different runs. The file always passes when run standalone (confirmed 3 separate times).
- **Leading hypothesis (source-evidenced, NOT reproduced):** `checkout.store()` is rate-limited by `app/Providers/AppServiceProvider.php:164-178`'s `place-order` limiter, which includes a sub-limit keyed by the literal phone-number digits in the request (`'phone:'.$phone`, cap of 5 per 60 minutes by default in `app/Support/SecurityRateLimits.php:33-36`). The test environment sets `CACHE_LIMITER_STORE=array` (`phpunit.xml:26`), which persists for the whole PHP process and is never reset by `RefreshDatabase`. 21 test files share the literal placeholder phone number `'01700000000'`, so the counter could in principle accumulate across the whole suite and throttle an unrelated, much-later test.
- **What was tried and failed to reproduce it:** three separate targeted test-combination runs (up to 212 tests, including every file sharing that phone number) immediately followed by `BuyNowCheckoutTest.php` — all passed cleanly, no reproduction.
- **Priority:** Low-Medium — appears to be a test-suite nuisance, not a production-code defect; does not block any of the other work in this file.
- **Dependencies:** confirming it would need a small, temporary diagnostic code edit (e.g. a debug log in the `place-order` rate-limiter closure), which was explicitly out of scope for the investigation performed so far.
- **Evidence:** full-suite run logs from the most recent audit session; direct source inspection of the files cited above.
- **Acceptance criteria:** either the rate-limiter hypothesis is confirmed (and then fixed, e.g. by giving each test file a unique phone number, or scoping the test environment's cache differently) or ruled out with a different confirmed root cause and fix.
- **Next safe action:** ask the user whether to authorize a small temporary diagnostic edit to confirm or rule out the hypothesis.

---

## 11. Business decisions and external approvals still needed (index)

- `ADMIN-2FA-001` — scope/approach for admin 2FA.
- `PAY-RETURN-001-BATCH-2` — proportional redeemed-points reversal formula.
- `MYSQL-CONCURRENCY-001` — authorization to provision a disposable MySQL test database.
- `CI-MYSQL-001` — whether to add a MySQL service container to CI.
- `DEP-SEC-COMPOSER-001` / `DEP-SEC-NPM-001` — approval for specific dependency updates.
- `E2E-COVERAGE-001` — scope for a first browser-testing pass.
- `DEPLOY-SAFETY-001` — backup/rollback mechanism given Hostinger constraints.
- `TEST-FLAKE-001` — authorization for a temporary diagnostic edit.
- `OPS-001` — Hostinger hPanel/SSH access to verify scheduler cron.
- `SHIP-001`, `REL-001`, `ORD-001`, `ORD-002`, `DEP-SEC-003` — source recovery of the original audit document, or explicit instruction to proceed without it.

---

## 12. Change log for this file

- 2026-10-29 — file created from the most recent read-only remediation status audit. No prior version existed.
