# ShoppyDrive Master Fixing Tracker

**This file is the canonical source of truth for all ShoppyDrive remediation status.** A new Claude CLI session should read this file first, before `docs/REMEDIATION_BACKLOG.md` or `docs/SESSION_HANDOVER.md` (both still exist and hold valuable historical detail, but this file supersedes them for "what is the current status of task X").

**Last updated:** 2026-10-31. Originally written immediately after implementing and locally validating `SHIP-DUP-001` (uncommitted); §A was then independently re-verified against live `git ls-remote` output (not cached refs) in a follow-up correction pass the same day; `SHIP-DUP-001` was then committed and pushed (`2092756`); `DEPLOY-SAFETY-001` Tier 1 was committed and pushed (`599b483`); a pre-existing flaky test (`PathaoCourierOrderTest`) surfaced by CI was diagnosed and fixed (`e5e9a68`); SSH known-host pinning was then explicitly reverted by user decision after it correctly failed closed with no `SSH_KNOWN_HOSTS` secret configured (`bdb444e`) — see §C/§G. `DEP-SEC-COMPOSER-001`'s Symfony patch (`symfony/mime`+`symfony/mailer` → `7.4.22`) was then implemented, validated, committed, and pushed (`c37c59f`) — deployment user-confirmed; `DEP-SEC-NPM-001`'s safe fixes (29→17 vulnerabilities) were then implemented, validated, committed, pushed, and deployed (`86759a1`) — deployment user-confirmed — see §H/§I. Most recently, `CHECKOUT-COD-002` (main-checkout COD/mobile-banking idempotency) was implemented, locally validated, committed (`cfafd2d`), pushed to `origin/main` (confirmed via live `git ls-remote`), and deployed — deployment user-confirmed via the GitHub Actions run — see §K. After that, `PAY-RETURN-001-BATCH-2` (proportional redeemed-points reversal on partial returns) — previously implemented and tested but uncommitted — was independently re-verified against the live repository (diff re-read, 42/42 `OrderRefundTest` + the full 203-test regression sweep re-run, Pint re-checked), committed locally as `0a02320` (exactly the 4 approved files, documentation kept separate as instructed), pushed to `origin/main` as a clean fast-forward (confirmed via live `git ls-remote`), and deployed — deployment user-confirmed — see §N. This is the current state of every task in this file as of this update; nothing else changed.

---

## A. Project and Git Context

- **Repository:** `git@github.com:rashedbishal/ShoppyDrive.git`, working directory `/media/rashed/HDD Drive 1/ShoppyDrive`.
- **Verified current branch:** `main`.
- **Verified current HEAD, re-verified via live `git ls-remote origin refs/heads/main` (not a cached ref) immediately after pushing:** `cfafd2d34a0b35a07ed4917bb583d4944d87719a` ("fix: prevent duplicate orders in main checkout (CHECKOUT-COD-002)") — **confirmed in sync with `origin/main`**, both at this exact SHA, immediately after a clean fast-forward push (`86759a1..cfafd2d`).
- **Commit history, oldest to newest:** `2092756` (SHIP-DUP-001) → `599b483` (DEPLOY-SAFETY-001 Tier 1) → `e5e9a68` (fixed a pre-existing flaky test, `PathaoCourierOrderTest`, surfaced by CI) → `bdb444e` (reverted SSH known-host pinning by explicit user decision after it failed closed on the first real deploy attempt) → `c37c59f` (DEP-SEC-COMPOSER-001 Symfony patch, deployment user-confirmed) → `86759a1` (DEP-SEC-NPM-001 safe npm fixes, deployment user-confirmed) → `cfafd2d` (CHECKOUT-COD-002, deployment user-confirmed). **All seven are pushed, live on `origin/main` (confirmed via `git ls-remote`), and local `main`/`origin/main` are in exact sync.**
- **Deployment to Hostinger: user-confirmed for `c37c59f`, `86759a1`, and now `cfafd2d`** (CHECKOUT-COD-002 — user reported "Checked the Actions run, deployment succeeded"). This session still has no `gh` CLI or SSH access, so no tool here independently observed any of these GitHub Actions runs or queried Hostinger directly — every one of these statuses rests on the user's explicit statement, not a tool-verified check.
- **Transfer branch `transfer/shoppydrive-pc-migration-20261031` — independently re-verified absent from the live remote**, via `git ls-remote origin refs/heads/main refs/heads/transfer/shoppydrive-pc-migration-20261031` returning only the `main` line. Also absent locally (`git branch -avv` lists only `main`).
- **Local branches that actually exist, re-verified via `git branch -avv`:** only `main`. `dev` and `backup/local-main-677c2aa` are confirmed gone, not merely assumed deleted.
- **Working-tree state (re-verified via `git status --short` after committing `CHECKOUT-COD-002`; supersedes any earlier snapshot in this file):**
  ```
   M docs/SESSION_HANDOVER.md
  ?? docs/MASTER_FIXING_TRACKER.md
  ```
  All 24 `CHECKOUT-COD-002` files (3 implementation + 20 modified tests + 1 new test file) are now committed as `cfafd2d`. Only this tracker and the `SESSION_HANDOVER.md` pointer update remain uncommitted, both documentation-only, exactly as instructed. See §K for the full file list and breakdown.
- **5 pre-existing stashes, re-verified via `git stash list`:** all 5 still present, untouched — **never pop, drop, or apply any of them without separate explicit approval.**
- **Deployment fact to always re-state:** `.github/workflows/deploy.yml` triggers automatically on every push to `main` — if its `checks` job (Pint + full Pest suite) passes, it deploys to Hostinger via rsync **and runs `php artisan migrate --force` against production with no manual gate.** Local commits, a GitHub push, and a production deploy are three separate, independently-approved actions — never assume one implies permission for the next. **Deployment outcome has been user-confirmed three times so far, for `c37c59f`, `86759a1`, and `cfafd2d` — no session tool has independently queried GitHub Actions or Hostinger directly; every "deployed" status in this file rests on that same kind of testimony, not direct observation.**
- **Local branch cleanup (historical action, independently re-confirmed above):** `dev` and `backup/local-main-677c2aa` (both fully-absorbed-into-`main`, zero unique commits) were deleted locally earlier this engagement. The remote transfer branch was also deleted, after independently confirming every commit on it was reachable from the (by-then pushed) `origin/main`. Both facts are re-confirmed live as of this correction, not merely carried forward from memory.

**⚠️ Re-verify all of the above at the start of any future session — branch/HEAD/remote state changes constantly across sessions, and this snapshot will go stale the moment it's acted on.**

---

## B. Current Master Task Tracker

| Task ID | Priority | Current Status | Evidence | Next Step |
|---|---|---|---|---|
| PROMO-LOY-001 | P1 | **COMPLETED** | 116/116 targeted SQLite tests passed this session (`LoyaltyPointsTest`, `ReferralProgramTest`, `CouponValidationTest`, `CouponPreviewTest`, `OrderRefundTest`, `OrderCancellationAtomicityTest`); earlier this session, real-MySQL race validation (`LoyaltyPointsConcurrencyRaceTest`) 8/8 passed | Closed — no further action |
| PAY-RETURN-001 | P1 | **`VERIFIED — APPLIED IN PRODUCTION`** (production migration status; implementation itself was already fully done — see §M) | All previously-outstanding MySQL suites fixed and passed earlier this engagement (see §D); merged to `main` and pushed to `origin/main` at `e609cf1`. This session could not obtain SSH/API access to confirm directly (no credentials anywhere in the environment — see prior attempt, superseded below). **Resolved 2026-10-31: the user personally ran `php artisan migrate:status` in Hostinger's production terminal and reported the output, confirming all 4 PAY-RETURN-001 migrations show `[31] Ran`:** `2026_10_30_214609_create_order_refunds_table`, `2026_10_30_214610_backfill_order_refunds_from_return_requests`, `2026_10_30_214611_create_courier_delivery_evidence_tables`, `2026_10_30_214612_make_users_points_balance_signed`. This is user-reported command output, not a tool independently connecting to production from this session — consistent with how every other production-deployment fact in this file is sourced (§H/§I/§K/§N) — but it is now a direct, first-hand command result rather than an inference from deploy success, which is the strongest evidence this engagement has for any production-state claim. | None pending — closed |
| DB-001 | P0 | **IMPLEMENTED & COMMITTED** | Commit `0e4af329fc248756a5048ce5b29488b93757c2db`, confirmed ancestor of pushed `origin/main` | Production migration and backup readiness |
| SHIP-DUP-001 | High | **COMMITTED, PUSHED & DEPLOYED** | Commit `2092756fa13b11e2d48f728abf4b353a1dc6694a`, confirmed live on `origin/main` via `git ls-remote`; 16/16 new/modified tests passed (46 assertions, 0.876s); 29/29 with adjacent existing tests (94 assertions, 12.07s). Deployment: `git merge-base --is-ancestor 2092756 c37c59f` confirms this commit is an ancestor of `c37c59f`, which the user has separately confirmed deployed — so this code is necessarily also live (verified by ancestry + testimony, not direct independent observation of this specific commit's deploy). | None pending |
| SHIP-001 | P1 | **COMPLETED** (user-confirmed scope, see §M) | User confirmed: "late or out-of-order courier status updates must never demote an already Delivered order." Implementation: `CourierOrderStatusMapper::TERMINAL_STATUSES` (`app/Services/CourierOrderStatusMapper.php:112`) includes `OrderStatus::DELIVERED` alongside `CANCELLED`/`REFUNDED`; `sync()` (lines 125-226) refuses to change `status`/`payment_status` whenever the order is already in a terminal status, writing only the raw tracking field plus an "already delivered/cancelled" history note. Shipped in commit `71c65b5` (deployed, ancestor of `origin/main`). Test evidence (re-run fresh this session, not inferred from old comments): `tests/Feature/Services/CourierOrderStatusMapperTest.php` + `tests/Feature/SteadfastWebhookTest.php` — 31/31 passed, 96 assertions; the 2 tests asserting this exact guard — `'it never regresses a delivered order back to an earlier status'` and `'a late, validly-signed webhook cannot regress a delivered order back to pending through the real controller'` — isolated and re-run: 2/2 passed, 8 assertions. **Not to be confused with `SHIP-DUP-001`** (a separate, already-fixed shipping-cost-calculation bug — see §C). | None pending |
| REL-001 | P1 | **SOURCE-RECOVERY-NEEDED** | No recoverable description anywhere in the repository (code, tests, commits, or docs) | Recover the original description, or explicit instruction to proceed without it |
| ORD-001 / ORD-002 | P1 | **SOURCE-RECOVERY-NEEDED** | Same — no recoverable descriptions anywhere | Same |
| ARCH-003 | P1 | **SOURCE-RECOVERY-NEEDED** | Does not exist anywhere in the repository, not even as a placeholder (unlike REL-001/ORD-001/ORD-002, which are at least named in `docs/REMEDIATION_BACKLOG.md`'s own "unrecoverable" list) | Confirm whether this is a typo for `ARCH-001` (real, already `DONE` and pushed) or a genuinely separate, undocumented item |
| DEP-SEC-003 | P1 | **SOURCE-RECOVERY-NEEDED** | Does not match either real dependency-finding ID in the repo (`DEP-SEC-COMPOSER-001`, `DEP-SEC-NPM-001`) | Clarify whether it maps to one of those two or is a third, distinct finding |
| SEC-XSS-001 | P1 | **RECONCILIATION REQUIRED** | This exact ID doesn't exist in the repo. The real underlying work — 5 stored-XSS sink pairs (Blog, Product, Setting, LandingPage, Newsletter) — is already implemented, tested and shipped (commit `0aa4d11`); re-confirmed wired this session via direct grep. `XSS-SINK-GAP-001` (the 6th-sink question) is now resolved — see below and §M. | Confirm whether `SEC-XSS-001` was meant to track this already-mostly-complete work, or something else entirely |

### Real repository task IDs (tracked separately — do not merge with the user-provided IDs above without evidence)

| Repository Task ID | Current Status | Next Step |
|---|---|---|
| DEP-SEC-COMPOSER-001 | **SYMFONY PATCH COMMITTED, PUSHED & DEPLOYED** (`c37c59fb52ddfe40ff2681728f63b983521636f7`) (see §H) | Pushed to `origin/main` (confirmed via `git ls-remote`); Hostinger deployment **user-confirmed**. `nunomaduro/pao` → `laravel/pao` rename still not done (separately scoped, not yet approved) |
| DEP-SEC-NPM-001 | **PARTIALLY FIXED, COMMITTED, PUSHED & DEPLOYED** (`86759a1149093f476bd6f86853ce36a8c456ac60`) (see §I) | Pushed to `origin/main` (confirmed via `git ls-remote`); Hostinger deployment **user-confirmed** (not independently observed by any tool this session — GitHub Actions/Hostinger remain unreachable from here). 29 → 17 vulnerabilities via safe `npm audit fix`: **0 Critical, 0 High, 0 Low** remaining; **17 Moderate** findings (ESLint ecosystem + `@tailwindcss/typography`) deliberately left unfixed — only resolve via `--force` to clearly regressive versions |
| XSS-SINK-GAP-001 | **RESOLVED — no exploitable 6th sink found** (see §M) | An exhaustive grep of every `dangerouslySetInnerHTML` call site in the frontend (8 total, all 8 traced to source) found exactly one HTML field without `Purifier::clean()`: `Page.content` (`app/Models/Page.php`) — but it has **no admin write path anywhere in the app** (no controller, no route, no admin UI; only `database/seeders/PageSeeder.php` populates it, with hardcoded trusted legal copy). Not currently exploitable. Recommended as an optional defense-in-depth fix, not implemented (not approved, and arguably not urgent since nothing can write attacker-controlled content into it today). |
| SHIP-DUP-001 | **COMMITTED, PUSHED & DEPLOYED** (`2092756fa13b11e2d48f728abf4b353a1dc6694a`) | None pending — see §C |

### Other known open/blocked backlog items (status/description as last recorded in `docs/REMEDIATION_BACKLOG.md` — not reassessed this session; do not invent beyond this)

| Task ID | Last Known Status | Description (as recorded) |
|---|---|---|
| ADMIN-2FA-001 | **`WONT DO — DECLINED BY USER`** (2026-10-31, final) | The user explicitly rejected this feature: "I explicitly do NOT want the Admin Two-Factor Authentication feature. It is completely unnecessary for my business requirements... This is a final decision. Do not implement it, do not propose it again, and do not keep it blocked awaiting approval." **Knowingly accepted security trade-off:** admin accounts (`App\Models\Admin`) have no two-factor authentication of any kind — the highest-privilege accounts in the system rely on password-only auth. This is a deliberate, informed decision, not an oversight. **Not pending implementation or approval of any kind — do not revisit or recommend this feature again unless the user explicitly reopens it.** |
| PAY-RETURN-001-BATCH-2 | **`COMMITTED, PUSHED & DEPLOYED`** (`0a02320`, deployment user-confirmed, see §N) | Business rule approved by the user 2026-10-31 (Option A — proportional reversal, formula: `points_redeemed × cumulative_refunded_product_amount / original_eligible_product_subtotal`, cumulative basis tracking, idempotent). Implemented by mirroring the existing earned-points (`REVOKE_ORDER`) reversal architecture. Full-order refund/cancellation behavior preserved (verified by regression test) with one necessary correctness fix — see §N. | None pending |
| CHECKOUT-COD-002 | **COMMITTED, PUSHED & DEPLOYED** (`cfafd2d34a0b35a07ed4917bb583d4944d87719a`), deployment user-confirmed (see §K) | Main checkout (COD + mobile banking) now has the same `order_token`/`Cache::lock()` idempotency as the landing page's `CART-01` fix. Full suite green (1245/1245, 28 skipped) prior to commit. |
| DEPLOY-SAFETY-001 | `TIER 1 PUSHED (PARTIALLY REVERTED)` (see §G) | Tier 1 committed+pushed as `599b483`. First real deploy attempt: `checks` initially failed on a pre-existing flaky test (`tests/Feature/Admin/PathaoCourierOrderTest.php` — two `OrderStatusHistory` rows created in one request shared an identical second-precision `created_at`, making `->latest()->first()` pick an undefined one of the two; unrelated to DEPLOY-SAFETY-001 itself, just surfaced by it), fixed in `e5e9a68` (test-only, 3 consecutive clean full-suite runs confirmed the fix). Second attempt: `checks` passed, but `rsync` correctly failed closed — `SSH_KNOWN_HOSTS` was never populated, so the new host-key pinning refused the unverified connection exactly as designed. **By explicit user decision, SSH known-host pinning was then reverted** (`bdb444e`): `StrictHostKeyChecking` is back to `=no`, the pinning step removed. M1 (no host-key verification) is now a **knowingly re-accepted risk**. The `production` Environment gate, concurrency group, health check, chmod fix, and `.env` validation are all still in place, unaffected by the revert. Hostinger deployment outcome for any of this: user-reported, not independently tool-verified. | None pending for Tier 1 as currently scoped; Tier 2 remains a separate, not-yet-started follow-up |
| E2E-COVERAGE-001 | `OPEN` | No browser/E2E test suite exists at all |
| CI-MYSQL-001 | `OPEN` | No CI workflow runs an actual MySQL service container — the MySQL race tests always skip in CI |
| TEST-FLAKE-001 | `VERIFICATION_REQUIRED`, root cause unconfirmed | Intermittent `BuyNowCheckoutTest.php` full-suite failure, suspected rate-limiter cross-test contamination, never reproduced |

---

## C. SHIP-DUP-001 Implementation Details

**Status: `COMMITTED, PUSHED & DEPLOYED`.** (Corrected from an earlier stale entry — confirmed pushed via `git ls-remote`, and confirmed deployed by ancestry: this commit is an ancestor of `c37c59f`/`86759a1`, both of which have since been explicitly user-confirmed deployed.)

- **Full commit SHA:** `2092756fa13b11e2d48f728abf4b353a1dc6694a`
- **Commit message:** `fix: correct free-delivery eligibility in shipping calculations (SHIP-DUP-001)`
- **Verified via `git show --stat HEAD`:** exactly the 4 expected files, 225 insertions(+), 6 deletions(-) — no unrelated file included.
- Local `main` is now 1 commit ahead of `origin/main`. Pushing it (and whether that's even desired yet, given a push to `main` auto-deploys to Hostinger and runs `php artisan migrate --force`) is a separate, not-yet-requested approval.

**The confirmed bugs:**
1. `Admin\IncompleteOrderController::calculateShippingCost()` calculated the sitewide free-delivery threshold and the base location rate, but **completely ignored per-product free-delivery eligibility** (`Product::free_delivery`, `free_delivery_min_qty`) — an admin converting an incomplete order into a real order would always charge standard shipping on a cart that would have shipped free at normal customer checkout.
2. `LandingPageController::calculateShipping()` checked `Product::free_delivery` but **never checked the ordered quantity**, so a product configured with a `free_delivery_min_qty` requirement shipped free on a landing page regardless of how many units were actually ordered — the opposite direction of bug 1 (under-charging instead of over-charging).

**The fixes:**
- Admin conversion: `calculateShippingCost()` now receives the already-resolved order items (each carrying `free_delivery`/`free_delivery_min_qty`, captured from the product lookup `placeOrder()` already performs — no new query) and applies the same all-items-must-qualify rule (`->every()`) that `CheckoutController` uses as the reference implementation.
- Landing page: `calculateShipping()` now receives the actual ordered `$quantity` from `completeLandingPageOrder()` and only waives shipping when `free_delivery` is true **and** (`free_delivery_min_qty` is unset or the quantity meets it).
- **Bundle eligibility is deliberately NOT part of the admin-conversion fix** — `ConvertIncompleteOrderRequest` never collects a `bundle_id` anywhere in its validation rules, so an admin-converted order can never structurally represent a bundle. This is documented directly in the method's docblock, not silently omitted. A dedicated regression test (`'a product that would have qualified as part of a bundle is not treated as free delivery when converted individually'`) proves bundle status correctly cannot leak through.
- The sitewide free-delivery threshold and `Setting::shippingRateFor()` (the shared per-location base rate) were **not touched** in either fix — both were already consistent across all three controllers; only the free-delivery *eligibility* logic above them was wrong.
- **No migration was required** — `free_delivery` and `free_delivery_min_qty` already existed on `products`.

**Modified/new files:**
- `app/Http/Controllers/Admin/IncompleteOrderController.php`
- `app/Http/Controllers/LandingPageController.php`
- `tests/Feature/Admin/IncompleteOrderManagementTest.php` (6 new tests added to the existing file)
- `tests/Feature/LandingPageShippingTest.php` (new file, 5 tests)

**Test evidence:**
- New/modified test files alone: **16/16 passed, 46 assertions, 0.876s.**
- New/modified plus directly-adjacent existing tests (`LandingPageCodDuplicateOrderTest`, `LandingPageOrphanedProductTest`): **29/29 passed, 94 assertions, 12.07s.**
- No full suite, MySQL concurrency/race, or stress tests were run for this task (per standing testing policy, §E).

---

## D. PROMO-LOY-001 Evidence

**Status: `COMPLETED`.**

- **This session's targeted SQLite verification (fast, no MySQL):** 116/116 passed across `LoyaltyPointsTest.php` + `ReferralProgramTest.php` (39/206), `CouponValidationTest.php` + `CouponPreviewTest.php` (31/133), `OrderRefundTest.php` + `OrderCancellationAtomicityTest.php` (46/163) — total ~4.6s.
- **Earlier this same session, real MySQL validation** (part of the broader PAY-RETURN-001 consolidation, not re-run specifically for this task): `LoyaltyPointsConcurrencyRaceTest.php` 8/8 passed against the disposable `shoppydrive_test_consolidation` database, after fixing the admin-authentication-fixture gap described in §D of the PAY-RETURN-001 work.
- **Explicitly not re-verified in the PROMO-LOY-001 verification task itself:** the MySQL race suite was **not re-run** at that point in the session — it had already been run and passed earlier in the same session as part of a different, broader task. This distinction matters: don't conflate "verified at some point this session" with "re-verified specifically for this task" when reading this record later.
- **Code inspection confirmed no regression:** all 7 originally-fixed methods across `LoyaltyPointsService.php`/`ReferralService.php` still have their `DB::transaction(..., 3)` wrap, lock-then-recheck pattern intact after the later PAY-RETURN-001 merge; the only changes PAY-RETURN-001 made in this area (`reconcileEarnedPointsAfterRefund()`, a `points_balance` negative-value guard) are additive and don't touch the original 7 methods' atomicity.

---

## E. Testing Policy — Permanent Project Rule

This is a standing policy for every future session, not a one-off instruction:

- **Prefer fast, targeted SQLite-compatible tests.** Default to the smallest relevant file(s), not broad sweeps.
- **Do not run the full test suite unless explicitly approved** for that specific task.
- **Do not run MySQL concurrency/race-condition suites by default.** These require a verified disposable `shoppydrive_test_*` database and are genuinely slow (each file reruns a full `migrate:fresh`, and race tests spawn real subprocesses — individual files have taken 5–22 minutes this session). Only run them when explicitly authorized, and always verify the target database's identity (`SELECT DATABASE()`) immediately before and is never the real `ShoppyDrive` dev database or production.
- **Do not run stress tests by default.**
- **Mark any concurrency test not run as `SKIPPED — NOT VERIFIED`** in reports — never claim concurrency safety from SQLite-only evidence.
- **If a targeted test is unexpectedly slow, stop and report** rather than waiting indefinitely or assuming it's stuck without checking.
- **Never weaken tests or business rules to make a test pass.** Every fix in this engagement that touched a test (the MySQL JSON key-order comparison, the admin-auth fixtures, the cancellation-vs-refund race assertion) was a narrowly-scoped, explicitly-approved correction to match either a confirmed driver difference or an already-approved business rule — never a weakening.
- **Ask before running unusually long validation.**

---

## F. Git and Deployment Safety — Permanent Rules

- **No force push, ever**, without explicit, scoped approval for that specific push.
- **No history rewriting** (`rebase`, `commit --amend` on shared history, etc.).
- **No unapproved branch deletion** — always independently verify every commit on a branch is reachable elsewhere before deleting it, and report explicitly if any commit is not reachable.
- **Preserve all unrelated local changes and stashes** — never pop, drop, apply, stage, commit, or discard a stash or an unstaged change that isn't the specific subject of the current approved task.
- **Never commit `.env`, secrets, database dumps, logs, or build artifacts.**
- **Local merge, GitHub push, and production deployment are three separate approval gates** — approval for one is never approval for the next.
- **A push to `main` can trigger Hostinger deployment and `php artisan migrate --force` against production**, automatically, with no manual gate in `deploy.yml` as currently configured.
- **Never push or deploy without explicit, separate approval for that specific action.**
- **Production backup and migration review are mandatory before any production deployment** — this has never been done in this engagement; every "deployed" claim in this file or elsewhere should be treated with suspicion unless backed by direct evidence.

---

## G. DEPLOY-SAFETY-001 — Read-Only Audit Findings (2026-10-31)

**Status: `AUDITED — NOT FIXED`.** Full read-only audit performed; nothing in the deploy pipeline was changed. This supersedes the one-line description of `DEPLOY-SAFETY-001` in §B's "other known backlog items" table with concrete, line-numbered evidence.

**Architecture:** one workflow (`deploy.yml`), triggers on every push to `main`; `checks` (Pint + full Pest, SQLite) gates `deploy` (`needs: checks`); `deploy` builds assets then `rsync -azP --delete`s the whole repo **in place** into a single mutable directory on Hostinger shared hosting (no releases/symlink pattern — not atomic), then SSHes in to clear caches and run `php artisan migrate --force`. `tests.yml`/`lint.yml` run in parallel on the same push but cannot gate `deploy.yml` — GitHub Actions has no cross-workflow `needs:`.

**Critical findings:**
- **C1 — No backup of any kind before `migrate --force`** (`deploy.yml:177`). No backup script exists anywhere in the repo; the one backup-related artifact, `app/Mail/BackupFailedMail.php`, is a confirmed orphaned stub, never dispatched from anywhere.
- **C2 — A failed migration leaves the server broken with no automatic recovery.** The remote script deletes framework caches (`:170`) *before* running migrations (`:177`) under `set -e` (`:146`); if migration fails partway, the cache-rebuild steps (`:178-182`) never run, the new code is already fully live from the rsync that already completed, and MySQL's lack of transactional DDL means a multi-migration batch can leave the schema itself partially applied.
- **C3 — No rollback mechanism for code or database exists anywhere** (confirmed via repo-wide grep, zero "rollback" matches in `.github/`). The in-place rsync layout means there's no previous release to fall back to even in principle.

**High findings:** H1 — no `environment:` key on the `deploy` job (`:72-182`), so no manual-approval gate is possible regardless of repo settings; H2 — no `concurrency:` key in any workflow, so two rapid pushes could race two parallel deploys/migrations against the same server; H3 — no post-deploy health check, so a broken deploy is only caught by a human noticing.

**Medium findings:** M1 — `StrictHostKeyChecking=no` on both the rsync (`:124`) and SSH (`:143`) connections; M2 — `chmod ... || true` (`:155`) silently swallows permission failures; M3 — `lint.yml` checks never gate `deploy.yml` by architecture (already documented as a deliberate interim tradeoff in `deploy.yml`'s own comment, `:17-23`).

**Low findings:** L1 — the full test suite runs twice per push (`tests.yml` + `deploy.yml`'s own `checks` job), pure CI waste; L2 — `.env` existence is checked (`:149-152`) but never its correctness (required keys present/valid).

**Recommended minimal fixes, in priority order (none implemented — each needs separate approval):** (1) pre-migration backup step, fail closed if it fails; (2) a GitHub Environment with required reviewers on the `deploy` job; (3) a `concurrency:` group to serialize deploys; (4) a post-deploy health check against Laravel's `/up` route; (5) pin a known-hosts entry instead of `StrictHostKeyChecking=no`; (6) make the `chmod` failure visible instead of swallowed.

**Verdict (original, pre-rescope):** current deployment is **not safe** for a migration-bearing push — C1+C2+C3 together mean such a push is a one-way, irreversible production action with no safety net. No backup-and-rollback strategy exists in any form, automated or manual-but-documented, for arbitrary future migrations.

### Revised scope (2026-10-31, same day — explicit business decision)

**C1 (no database backup) is now an explicitly accepted risk, not a blocker** — the user confirmed the production database currently holds no important business data, so implementing a backup system is deliberately deferred. **This acceptance is conditional on that fact remaining true — revisit the moment real business data starts accumulating.** C2 and C3 (no safe migration-failure recovery, no rollback) remain real findings but are addressed indirectly below: the manual-approval gate (H1 fix) means a human reviews every production push before it can run, and the health check (H3 fix) at least surfaces a broken deploy immediately instead of silently.

**Revised plan — design only, not yet implemented, awaiting approval:**
- **Tier 1** (low complexity, recommended first): add `environment: production` to the `deploy` job + a GitHub Environment with required reviewers (closes H1); add a `concurrency:` group on the `deploy` job, `cancel-in-progress: false` (closes H2); add a retried health check against `/up` after deploy (closes H3 — confirmed `/up` exists, `bootstrap/app.php:27`, and is reachable unauthenticated since it sits outside the `web` middleware group); replace `StrictHostKeyChecking=no` with a pinned `known_hosts` secret (closes M1); remove the `|| true` on the `chmod` step so `set -e` catches it (closes M2); replace `.env` existence-only check with a per-key presence check via `grep -q` (never printing values) (closes L2).
- **Tier 2** (medium-high complexity, separate follow-up after Tier 1 is live and proven): release-directory + symlink-swap deployment, enabling atomic cutover and instant automatic code-only rollback on a failed health check. Feasibility depends on confirming in hPanel whether the domain's document root can point at a symlink — not yet confirmed.
- **Known limitation carried forward:** `/up` has no `DiagnosingHealth` listener registered (confirmed via `grep -rl "DiagnosingHealth" app/` → empty), so it verifies the app boots, not that the database is actually reachable/correct.

**Next safe action:** awaiting the user's approval to implement Tier 1. Tier 2 needs its own separate approval and a live Hostinger-side feasibility check before any implementation.

### Tier 1 implementation (2026-10-31, same day) — `COMMITTED LOCALLY` (`599b483734fa59e17977e74a45f302d62c10b4c8`)

All 6 approved Tier 1 items implemented in `.github/workflows/deploy.yml` only (no application code, tests, or migrations touched):
1. `environment: production` added to the `deploy` job (closes H1) — **requires you to manually create a GitHub Environment named exactly `production` with required reviewers; the workflow change alone does nothing without that.**
2. `concurrency: {group: production-deploy, cancel-in-progress: false}` added to the `deploy` job (closes H2).
3. New "Verify deployment health" step: retries `curl` against `https://shoppydrive.com/up` up to 10 times, 5s apart, 10s timeout each, fails the job if all attempts fail (closes H3). Known limitation carried forward as documented above: no `DiagnosingHealth` listener, so this proves the app boots, not that the database is reachable.
4. New "Add pinned SSH known host" step writes a new secret, `SSH_KNOWN_HOSTS`, into `~/.ssh/known_hosts`; both `StrictHostKeyChecking=no` occurrences changed to `=yes` (closes M1) — **requires you to capture the server's host-key fingerprint out of band and add it as a new GitHub secret named `SSH_KNOWN_HOSTS`; this doesn't work until that secret exists.**
5. `chmod -R 775 bootstrap/cache storage || true` → `|| true` removed, so the existing `set -e` now stops the deploy on a genuine permissions failure instead of silently continuing (closes M2).
6. `.env` existence-only check replaced with a per-key `grep -Eq "^$key=.+"` loop over `APP_KEY`, `DB_CONNECTION`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — never prints a matched line/value (closes L2).

**Validation performed (no deployment, no network calls to the real server):** YAML parses correctly; `environment`/`concurrency` keys confirmed present with correct values; the exact text reaching both the local runner's shell and the remote SSH shell was reconstructed with dummy secret values and syntax-checked with `bash -n` on both sides; the `.env` validation loop, the `chmod`/`set -e` interaction, and the health-check retry/failure logic were each functionally tested in isolation (sample `.env` files, a genuinely-failing `chmod` target, and a fake local HTTP server for both the success and exhausted-retries paths) — all behaved exactly as intended, with the `.env` check never printing a value in any test.

**Committed locally as `599b483734fa59e17977e74a45f302d62c10b4c8`, after an extended multi-round diff review (full diff, full file contents, exact change count, EOF-newline correction) — not pushed, not deployed.** The two manual prerequisites (the `production` GitHub Environment with required reviewers, and the `SSH_KNOWN_HOSTS` secret) are **not yet confirmed configured** — until they are, this commit's gates don't yet protect anything at runtime; the environment key with no matching Environment, or the known-hosts step with an empty secret, will simply cause the next real deploy run to fail (safely) at that step.

---

## H. DEP-SEC-COMPOSER-001 — Audit Findings and Symfony Patch Implementation (2026-10-31)

**Status: `SYMFONY PATCH COMMITTED, PUSHED & DEPLOYED`.** Pushed to `origin/main`, confirmed via `git ls-remote`. Hostinger deployment outcome: **user-confirmed** (not independently observed by any tool in this session — GitHub Actions/Hostinger are not reachable from here — but recorded as confirmed per the user's explicit statement, distinct from earlier entries in this file marked "user-reported, not independently verified" before any such confirmation was given). The `nunomaduro/pao` → `laravel/pao` rename below remains unapproved/not implemented — only the Symfony security patch was authorized, implemented, committed, pushed, and deployed.

- **Commit SHA:** `c37c59fb52ddfe40ff2681728f63b983521636f7`
- **Commit message:** `fix: update Symfony MIME and Mailer security patches`
- **Verified via `git show --stat HEAD`:** exactly `composer.lock`, 24 lines changed (12 insertions/12 deletions) — no other file included.

### Implementation (2026-10-31, same day as the audit below)

- **Command run, exactly as scoped:** `composer update symfony/mime symfony/mailer --with-all-dependencies` → "Lock file operations: 0 installs, **2 updates**, 0 removals" (`symfony/mailer` v7.4.19→v7.4.22, `symfony/mime` v7.4.19→v7.4.22). No other package touched — confirmed by `git diff composer.lock` showing only these two package blocks changed (12 insertions/12 deletions total), and `git diff composer.json` being completely empty (no constraint edit was needed — both were already permitted by existing ranges).
- **`composer audit`:** **0 security advisories** (CVE-2026-45070 cleared). The `nunomaduro/pao` abandoned-package notice remains, untouched as instructed.
- **Mail-related tests** (every file using `Mail::fake`/`assertSent`/`assertQueued`: `AbandonedCartReminderTest`, `Admin/NewsletterSendTest`, `Auth/EmailVerificationCodeTest`, `Auth/VerificationNotificationTest`, `LandingPageCodDuplicateOrderTest`, `OrderCancellationAtomicityTest`, `OrderNotificationTest`, `ReturnRequestTest`): **94/94 passed, 355 assertions.**
- **`composer test` (Pint + full Pest suite):** Pint passed; **1236 tests, 1208 passed, 0 failed, 28 skipped** (MySQL-only, correctly skipped) — identical to the pre-change baseline, confirming zero regression.
- **Done since:** pushed to `origin/main` (`git ls-remote` confirms `c37c59f` live); deployment user-confirmed.
- **Not done:** the `nunomaduro/pao` rename (separate scope, not approved).

### Original audit findings (unchanged, for reference)

**`symfony/mime` CVE-2026-45070** (medium severity, email header injection via non-token MIME parameter names). Installed/locked: `v7.4.19` (confirmed via `composer show`/`composer.lock`), inside the affected range `>=7.4.0,<7.4.22`. **Patched version confirmed via `composer outdated`: `v7.4.22`**, released this week — a pure patch bump within the already-in-use 7.4.x line, not a minor/major jump. `symfony/mailer` (also `v7.4.19`, consumes mime) should move to `7.4.22` alongside it — `composer show "symfony/*"` shows every *other* symfony/* package in this lockfile already independently patch-drifted to 7.4.16–7.4.21; mime/mailer are the only two stuck at the oldest patch. Both `laravel/framework`'s constraint (`^7.4.0 || ^8.0.0`) and `symfony/mailer`'s own constraint on mime (`^7.2|^8.0`) already permit `7.4.22` — no composer.json constraint change needed, just a lockfile version bump. PHP 8.3 / Laravel 13 compatibility unaffected (`symfony/mime` requires `php >=8.2`). **Real exploitability in this app is low** — confirmed via `grep -rn "->attach(\|Attachment::fromData\|Attachment::fromPath\|->attachData(" app/` returning nothing: none of the 16 Mailables attach files, so the MIME-parameter-name injection vector has no live attack surface here today. The fix is still correct and effectively free to apply.

**`nunomaduro/pao` (abandoned package).** Confirmed via `composer show nunomaduro/pao`: the installed package's own source repo is already `github.com/laravel/pao.git` — the maintainer renamed the project; `nunomaduro/pao` is just the old alias. **This is an active dev dependency, not dead weight** — `composer why nunomaduro/pao` confirms it's required for development, and it's the exact tool producing the `{"tool":"pest",...}` compact JSON output `php artisan test --compact` has used throughout this entire engagement. `require-dev`-only, never shipped to production (`deploy.yml` uses `--no-dev`). Minimal fix: rename the `composer.json` `require-dev` entry from `nunomaduro/pao` to `laravel/pao` — same codebase, no behavior change.

**Proposed minimal steps (none executed):** (1) `composer update symfony/mime symfony/mailer --with-all-dependencies`, scoped to just those two; (2) rename `nunomaduro/pao` → `laravel/pao` in `composer.json`, then `composer update laravel/pao`; (3) re-run `composer audit` to confirm the advisory clears; (4) review the `composer.lock` diff to confirm only the expected packages moved.

**Targeted test plan (once approved):** existing `Mail::fake()`-based Mailable tests, plus `composer test` (Pint + full Pest) as the standard acceptance gate — no new tests needed, this isn't an application-logic change. No MySQL/concurrency/stress tests relevant.

**Risk assessment:** low. Patch-only bump within an already-satisfied constraint range, plus a same-codebase rename for an abandoned alias. Main residual risk is the generic "any `composer update` could shift an unanticipated transitive dependency" — mitigated by scoping the command to named packages and reviewing the lockfile diff before committing.

**Next safe action:** awaiting the user's approval to implement.

---

## I. DEP-SEC-NPM-001 — Implementation and Validation (2026-10-31)

**Status: `PARTIALLY FIXED, COMMITTED, PUSHED & DEPLOYED`.**

- **Commit SHA:** `86759a1149093f476bd6f86853ce36a8c456ac60`
- **Commit message:** `fix: update vulnerable npm dependencies`
- **Verified via `git show --stat HEAD`:** exactly `package-lock.json`, 588 insertions/344 deletions — no other file included; `package.json` confirmed byte-unchanged (`git diff package.json` empty).
- **Advisory summary after the safe fix: 0 Critical, 0 High, 0 Low, 17 Moderate remaining** — confirmed via a fresh `npm audit` immediately before committing, unchanged from the validation pass. The 17 Moderate findings (ESLint ecosystem + `@tailwindcss/typography`) were deliberately left unfixed (see below for why).
- **Pushed to `origin/main`** as a clean fast-forward (`c37c59f..86759a1`), confirmed via `git ls-remote origin refs/heads/main` returning exactly this SHA.
- **Hostinger deployment: user-confirmed.** No tool in this session queried GitHub Actions or Hostinger directly — this status rests on the user's explicit statement, consistent with how `DEP-SEC-COMPOSER-001`'s deployment was recorded.

**Starting state, re-verified fresh this session (not assumed unchanged):** `npm audit` reported 29 vulnerabilities — 2 critical, 8 high, 18 moderate, 1 low — matching the previously-recorded count exactly.

**Grouped by root package via `npm audit --json`:**
- **Safe (non-`--force`) fix available — 14 packages:** both criticals (`concurrently`, `shell-quote`), all 8 highs (`brace-expansion`, `browserslist`, `js-yaml`, `nanoid`, `postcss`, `source-map-js`, `vite`, `zod`), 3 moderates (`@typescript-eslint/eslint-plugin`, `@typescript-eslint/type-utils`, `baseline-browser-mapping`), the 1 low (`@babel/core`).
- **Only resolves via `--force` to a regressive version — 15 moderate findings**, forming one ESLint-ecosystem cluster (`eslint`, `@eslint/config-array`, `@eslint/eslintrc`, `minimatch`, `@typescript-eslint/parser`, `@typescript-eslint/typescript-estree`, `@typescript-eslint/utils`, `typescript-eslint`, `eslint-config-prettier`, `eslint-plugin-import`, `eslint-plugin-react`, `@eslint-community/eslint-utils`, `@stylistic/eslint-plugin`) plus `@tailwindcss/typography`/`postcss-selector-parser`. Confirmed these are not real fixes: npm's proposed forced resolution for the ESLint cluster is `eslint@4.0.0` (a 2017-era release), and for the Tailwind cluster is `@tailwindcss/typography@0.5.4` — **below** the project's own existing `^0.5.20` floor in `package.json`. Both are npm's resolver picking the oldest version that avoids the vulnerable transitive dependency, not a genuine remediation — exactly the kind of change the task explicitly said to avoid (`--force`, unnecessary major/regressive version changes).

**Implementation:** ran plain `npm audit fix` (no `--force`). Verified `package.json` is completely unchanged (every fix landed as a patch/minor bump already permitted by existing ranges) and individually confirmed each of the 14 packages' before/after versions stayed within the same major version (e.g. `vite` 8.0.10→8.3.5, `zod` 4.3.6→4.6.7, `@typescript-eslint/eslint-plugin`/`type-utils` 8.59.1→8.72.1, `shell-quote` 1.8.3→1.12.0, etc. — no major-version jumps anywhere).

**Result: 29 → 17 vulnerabilities. 0 critical, 0 high, 0 low remaining — only the 17 moderate ESLint/Tailwind-typography findings above, deliberately left unfixed.**

**Validation:** `npm run build` — succeeded cleanly in 9.47s, only pre-existing unrelated warnings (chunk size, one `INEFFECTIVE_DYNAMIC_IMPORT` note). `npm run types:check` (`tsc --noEmit`) — zero output, zero errors. No PHP/Pest tests are relevant to a frontend-dependency-only change, so none were run.

**Remaining 17 moderate findings — recommend as a separately-scoped follow-up, not fixed now.** Two real paths exist, neither attempted here: (a) a deliberate, coordinated major-version upgrade across the whole `eslint`/`typescript-eslint`/`eslint-plugin-react`/`eslint-plugin-import`/`eslint-config-prettier` set together (dev-tooling only — `deploy.yml`'s build step never runs `eslint`, so this carries zero production exposure), or (b) waiting for `@tailwindcss/typography` to publish a patch release that itself bumps its `postcss-selector-parser` dependency to `>=6.1.5`.

**Next safe action:** none pending — committed, pushed, and deployed (user-confirmed). The 17 remaining Moderate advisories and the `nunomaduro/pao` rename (from §H) remain open, separately-scoped follow-ups, not part of this task.

---

## K. CHECKOUT-COD-002 — Implementation Details (2026-10-31)

**Status: `COMMITTED, PUSHED & DEPLOYED`. Deployment user-confirmed** ("Checked the Actions run, deployment succeeded") — this session still has no `gh` CLI or SSH access, so this rests on that statement, not independent tool observation (same pattern as `c37c59f`/`86759a1`, see §H/§I).

- **Full commit SHA:** `cfafd2d34a0b35a07ed4917bb583d4944d87719a`
- **Commit message:** `fix: prevent duplicate orders in main checkout (CHECKOUT-COD-002)`
- **Verified via `git show --stat HEAD` / `git status --short` immediately after committing:** exactly the 24 intended files (3 implementation + 20 modified tests + 1 new test file), 323 insertions(+), 0 deletions(-) — consistent with this being a pure wrap-and-add change (no existing logic lines were altered or removed). `docs/SESSION_HANDOVER.md` and `docs/MASTER_FIXING_TRACKER.md` correctly remained unstaged/untracked, exactly as instructed. `git diff --check` on all 24 files was clean before staging. All 5 pre-existing stashes re-confirmed present and untouched after the commit.
- **Pushed to `origin/main` as a clean fast-forward.** Pre-push verification: local HEAD (`cfafd2d`) was confirmed the *only* unpushed commit (`git log origin/main..HEAD --oneline` showed exactly this one line), and its direct parent (`git rev-parse HEAD^`) was confirmed identical to the live remote HEAD (`git ls-remote origin refs/heads/main` → `86759a1`) via `git merge-base --is-ancestor`, so the push could not be anything but a fast-forward. `git push origin main` reported `86759a1..cfafd2d main -> main`. **Post-push, re-verified via a fresh live `git ls-remote origin refs/heads/main` (not a cached ref): returns `cfafd2d34a0b35a07ed4917bb583d4944d87719a` — remote `main` is confirmed at this exact SHA.** No force-push, rebase, amend, or reset was used. All 5 stashes re-confirmed present after the push; working tree still shows only the two documentation files as dirty/untracked — nothing else was touched by the push itself (a push transfers committed history only, never the working tree).
- **GitHub Actions (`checks` → `deploy` in `deploy.yml`): user-confirmed succeeded** ("Checked the Actions run, deployment succeeded"). This session has no `gh` CLI (`which gh` → not found) and no way to query the Actions API or UI directly, so this is not independently tool-verified — it rests on the user having checked the run themselves, same as `c37c59f`/`86759a1`.
- **Hostinger deployment: user-confirmed**, for the same reason — no SSH/API access from this session to observe it directly. The post-deploy script's `php artisan migrate --force` therefore ran against production as part of this deploy (standard for every push to `main` whose `checks` job passes) — **no migration was run manually from this session**; this is the automated deploy pipeline's own migration run, consistent with the restriction against manual migrations.

**The gap this closes:** `LandingPageController::placeOrder()` already had a proven `order_token` + `Cache::lock()` idempotency pattern (`CART-01`, see `tests/Feature/LandingPageCodDuplicateOrderTest.php`). The main checkout flow (`CheckoutController::store()`) had no equivalent — a double-click, network retry, or back-button resubmit of the same checkout page render could create two separate `Order` rows (COD) or two separate `PendingCheckout` rows (mobile banking), relying only on the cart becoming empty after the first order as incidental, racy protection.

**The fix — reused the exact landing-page pattern, no new architecture:**
- `PlaceOrderRequest` now requires `order_token` (`['required', 'uuid']`) — missing/malformed fails validation outright, same as `CART-01`, never silently skipping the idempotency check.
- `CheckoutController::index()` issues a fresh `orderToken` (`Str::uuid()`) as an Inertia prop on every page render, mirroring `LandingPageController::show()`.
- `Checkout.tsx` takes that prop and submits it back unchanged as `order_token` for every submission of that page render (added to the `useForm` initial data; it flows through `handleSubmit`'s `...form.data` spread with no other change to the submit payload).
- `CheckoutController::store()`: after the existing read-only precompute (cart sync, price revalidation, shipping cost, coupon preview — all unchanged, all still before any lock, exactly mirroring where `LandingPageController::placeOrder()` precomputes before its own lock), it now checks `Cache::get("checkout-order-result:{token}")`, and on a miss acquires `Cache::lock("checkout-order-lock:{token}", 30)` and blocks up to 10s. The actual COD transaction and the mobile-banking `PendingCheckout::create()` (both byte-identical internally — extracted into a new private `completeCheckout()` purely to sit inside the locked callback, no logic changed) run inside that lock. A `LockTimeoutException` returns the same "still being processed, wait a moment" error as the landing page. Only a **successful** outcome is cached (COD: `order_number`; mobile banking: `pending_token`) for 2 hours — a failed attempt (`InsufficientStockException`, `CouponUnavailableException`) is never cached, so the same token can still be legitimately retried after a transient failure, exactly like `CART-01`.
- A cache hit replays the original redirect (`order.confirmation` or `payment.mobile.show`) rather than re-running any order logic.

**Explicitly NOT changed:** the internal COD transaction (user-row locking, discount resolution, `Order::create`, inventory decrement, coupon consumption, referral/points debit, cart deletion, `IncompleteOrder` cleanup) and the mobile-banking `PendingCheckout` construction are both byte-identical to before — only wrapped, not altered. Shipping/coupon/price-revalidation logic above the lock is untouched. No migration was needed — this is entirely `Cache` (file-store in prod, matching `LayoutCache`/rate-limit conventions already in the codebase) plus one new required validation field.

**Modified files:**
- `app/Http/Controllers/CheckoutController.php` — lock/cache wrapper, `orderToken` prop, `completeCheckout()`/`redirectForCachedCheckoutOrder()` extraction.
- `app/Http/Requests/PlaceOrderRequest.php` — `order_token` validation rule.
- `resources/js/pages/checkout/Checkout.tsx` — `orderToken` prop plumbed into the submitted form.
- 23 existing test files — mechanical only: each test/helper that posts to `checkout.store` now includes a generated `order_token` in its payload (via a shared payload-builder default where one existed, or inline otherwise). No test scenario, assertion, or intent changed. The one case requiring care: `InventoryEnforcementTest`'s "two concurrent-style customers for the last unit" test and `BundleAutoAssembleTest`/`BundleCheckoutTest`'s "retry after a price-bounce" tests needed their tokens generated *fresh per call* (inside a closure/array-literal, not hoisted to a shared variable) so they still exercise two genuinely different checkout attempts rather than being deduped by this new cache.

**New test file:** `tests/Feature/CheckoutCodDuplicateOrderTest.php` (9 tests, mirroring `LandingPageCodDuplicateOrderTest.php`'s structure) — covers: same token submitted twice yields exactly one order and both responses redirect to the same confirmation; two different tokens yield two separate legitimate orders; stock/notifications fire exactly once for a repeated token; missing/malformed token fails validation before ever reaching the idempotency check; a held lock causes the second request to time out rather than fall through to order creation (`->group('slow')`, same convention as the landing-page equivalent); a retry after the first request's lock already released and cached replays the original confirmation; mobile-banking duplicate submission reuses the same `PendingCheckout` instead of creating a second one.

**Test evidence:**
- New file alone: 9/9 passed.
- New file + all 23 touched existing files + `CheckoutAccessGuardTest` + `LandingPageCodDuplicateOrderTest` (regression check that the landing-page pattern this was copied from still works unmodified): **278/278 passed, 1204 assertions, ~31.6s.**
- **Full suite:** **1245/1245 passed (28 skipped, same as baseline), 5538 assertions, ~75.6s** — up from the pre-task baseline of 1236 tests (the +9 are the new file); zero regressions anywhere else in the suite.
- `vendor/bin/pint --dirty --format agent`: passed (clean after its own auto-fixes to import ordering/fully-qualified-name style in the touched test files — no manual-logic changes from Pint).
- `npx tsc --noEmit` and `npx eslint resources/js/pages/checkout/Checkout.tsx`: zero errors/warnings on the touched frontend file.
- No MySQL concurrency/stress suite was run (per standing testing policy, §E, and this task's own explicit instruction to prefer targeted SQLite tests).

**Remaining risks / things a future session should know:**
- The lock/cache keys (`checkout-order-lock:`, `checkout-order-result:`) are deliberately namespaced separately from the landing page's (`lp-order-lock:`/`lp-order-result:`) even though both use the same `Cache` store — a collision would require an identical UUID to be issued by both controllers simultaneously, astronomically unlikely, but kept distinct anyway since they're logically different flows.
- `payment_method=zinipay` is accepted by `PlaceOrderRequest`'s validation but has no dedicated branch in `CheckoutController::store()` — it would currently fall into the mobile-banking branch by default. This is a **pre-existing quirk, not introduced or changed by this task**, and the real storefront `Checkout.tsx` never sends that value (its `payment_method` type is `'cod' | 'mobile_banking'` only) — flagging it here only because it was noticed during this task's inspection, not because it was in scope to fix.
- As with the landing-page original, this is single-process-cache-store correctness (file driver in production, per `CLAUDE.md`'s documented cache/session setup) — not evaluated against a multi-node deployment, which this app does not have.

---

## M. SHIP-001 Investigation and XSS-SINK-GAP-001 Resolution (2026-10-31)

**No application code was modified for either item below — read-only investigation only, per this session's explicit scope.**

### SHIP-001 — RESOLVED (user confirmed scope 2026-10-31): the courier delivered-order regression guard (Candidate A below)

**User's exact confirmation:** "late or out-of-order courier status updates must never demote an already Delivered order." This matches Candidate A, not Candidate B (`SHIP-DUP-001`) — see §B for the closed-out status row. The investigation that originally surfaced both candidates is kept below for provenance/audit trail.

- **Candidate A — courier delivered-order regression guard.** `App\Services\CourierOrderStatusMapper::TERMINAL_STATUSES` (`app/Services/CourierOrderStatusMapper.php:112`) includes `OrderStatus::DELIVERED` alongside `CANCELLED`/`REFUNDED`, with a docblock explaining exactly the regression this guards against (a late/corrective/out-of-order courier report must not demote an already-delivered order, which would wrongly re-trigger stock-restore/coupon-release/loyalty-reversal). Two test files reference `SHIP-001` by name directly in code comments describing this exact behavior: `tests/Feature/Services/CourierOrderStatusMapperTest.php:116` and `tests/Feature/SteadfastWebhookTest.php:241`.
  - **Chain of custody, checked via `git log -S`:** the `TERMINAL_STATUSES` guard itself was introduced in commit `71c65b5` ("fix: resolve ecommerce audit issues #1-22" — a large, unlabeled batch covering ~20 unrelated fixes, no per-finding breakdown in the message). The `SHIP-001` comment labels were added later, in `b1c8ced` ("fix: resolve priority ecommerce audit findings batch 1" — again no body, no scope text). Both commits are confirmed deployed (`71c65b5` is an ancestor of `origin/main`).
  - **The label is unverifiable as authoritative.** Neither commit message states what `SHIP-001` was. The `SHIP-001` tag in those test comments was evidently assigned by whichever prior Claude session wrote them, as its own best guess at the finding this covered — not copied from the original (lost) audit document. Treating it as confirmed would be circular: "it's SHIP-001 because an earlier session's comment says so" is not independent evidence.
- **Candidate B — `SHIP-DUP-001`.** Already a separately-tracked ID (shipping-cost calculation duplicated and drifted between `CheckoutController` and `Admin\IncompleteOrderController`), already fixed, committed, pushed and deployed (`2092756`, see §C). `docs/REMEDIATION_BACKLOG.md` itself flags this as a **possible, speculative** match for `SHIP-001` — explicitly not confirmed, since `SHIP-001`'s original text was never recovered.
- **No third candidate found.** A repo-wide grep for `SHIP-001` outside these two contexts returns nothing else.

**Resolution:** the user confirmed Candidate A directly (quoted above) — `SHIP-001` is the courier delivered-order regression guard, not `SHIP-DUP-001`. Closed out in §B as `COMPLETED`, with fresh test evidence (not the old comment labels) as proof: `tests/Feature/Services/CourierOrderStatusMapperTest.php` + `tests/Feature/SteadfastWebhookTest.php` re-run this session, 31/31 passed (96 assertions); the 2 tests specifically asserting this guard isolated and re-run, 2/2 passed (8 assertions). No application code was changed — the guard was already correct and already deployed (`71c65b5`).

### XSS-SINK-GAP-001 — resolved: no exploitable 6th sink exists

**Method:** rather than re-auditing the 5 already-confirmed sinks again, this session did an exhaustive sweep of the *entire* frontend for `dangerouslySetInnerHTML` — the only way raw HTML ever reaches a browser in this app (`grep -rn "dangerouslySetInnerHTML" resources/js/`) — and traced every result back to its PHP data source. This is a complete enumeration, not a sample: there are exactly 8 call sites in the whole codebase.

| Call site | Data source | Sanitized? |
|---|---|---|
| `resources/js/components/two-factor-setup-modal.tsx:81` | Fortify/BaconQrCode-generated SVG (not user input) | N/A — not user-controllable |
| `resources/js/pages/blog/Show.tsx:80` | `Blog.content` | ✅ `Purifier::clean()`, `app/Models/Blog.php:46` |
| `resources/js/pages/static/About.tsx:106` | `Setting['about_mission_body']` | ✅ `Purifier::clean()`, gated by `Setting::RICH_TEXT_KEYS` |
| `resources/js/pages/catalog/ProductDetail.tsx:835` | `Product.description` | ✅ `Purifier::clean()`, `app/Models/Product.php:80` |
| `resources/js/pages/landing/LandingPage.tsx:919` | `Product.description` | ✅ (same as above) |
| `resources/js/pages/landing/LandingPage.tsx:927` | `LandingPage.sections.description.custom_content` | ✅ `Purifier::clean()`, `app/Models/LandingPage.php:41` |
| `resources/js/pages/static/Page.tsx:249` | `Page.content` | ❌ **not sanitized** |
| `resources/js/pages/static/FAQ.tsx:111` | `Page.content` (same model, slug `faq`) | ❌ **not sanitized** (same gap as above) |

**The one real gap: `Page.content`.** `app/Models/Page.php` has no mutator/cast calling `Purifier::clean()`, and both `/page/{slug}` and `/faq` render its `content` column as raw HTML via `dangerouslySetInnerHTML` with no client-side sanitization either (`parseContent()`/`parseHtmlToGroups()` only re-serialize `el.outerHTML`, they don't clean it).

**Why this is not currently exploitable, confirmed three ways:**
1. `php artisan route:list --path=page` lists exactly one route touching this model: `GET page/{page:slug}` (public, read-only). No admin route exists.
2. `grep -rln "Models\\Page" app/` (excluding the sitemap command) returns nothing — no controller anywhere constructs, updates, or saves a `Page` record.
3. The only code that ever writes `Page` rows is `database/seeders/PageSeeder.php`, which contains hardcoded, developer-authored legal copy (Privacy Policy, Terms, FAQ) with no templating or external input of any kind.

There is no admin form, no API endpoint, and no other write path through which anyone — including a malicious or compromised admin — could inject content into this field today. This is a **latent defense-in-depth gap** (the field *shape* matches the other 5 sinks exactly: admin-manageable-in-principle rich content rendered unescaped to every public visitor), not a live vulnerability — the missing piece isn't the sanitizer, it's a write path that doesn't exist.

**Recommended smallest safe fix (not implemented — not approved, and no urgency since nothing can write to this field via the app):** add the same one-line mutator pattern already used by `Blog`/`Product`/`LandingPage` to `Page::content`:
```php
protected function content(): Attribute
{
    return Attribute::make(
        set: fn (?string $value) => $value === null ? null : Purifier::clean($value),
    );
}
```
This would be a no-op on the existing seeded content (already clean HTML, no scripts) and would close the gap permanently if a `Page` admin UI is ever added later — cheap insurance, not an urgent fix.

**Conclusion: the "six sinks" reference was very likely a miscount (or counted `Page.content` despite it never having had a write path) — there are 5 live, exploitable sink pairs, all already fixed, and one inert field that is safe today only because it is unreachable, not because it is sanitized.**

---

## N. PAY-RETURN-001-BATCH-2 — Implementation Details (2026-10-31)

**Status: `COMMITTED, PUSHED & DEPLOYED`. Deployment user-confirmed** ("I confirm that PAY-RETURN-001-BATCH-2 has successfully deployed to production.") — this session still has no `gh` CLI or SSH/API access, so this rests on that statement, not independent tool observation (same pattern as `c37c59f`/`86759a1`/`cfafd2d`, see §H/§I/§K).

- **Full commit SHA:** `0a023207a84878aa41cb472b42240ebd40f54cca`
- **Commit message:** `fix: reverse redeemed loyalty points on partial refunds (PAY-RETURN-001-BATCH-2)`
- **Verified via `git show --stat HEAD`:** exactly the 4 intended files (`app/Enums/PointTransactionType.php`, `app/Services/LoyaltyPointsService.php`, `app/Services/RefundService.php`, `tests/Feature/OrderRefundTest.php`), 216 insertions(+), 8 deletions(-) — no documentation file included.
- **Pushed to `origin/main` as a clean fast-forward.** Pre-push verification: local HEAD (`0a02320`) was confirmed the only unpushed commit (`git log origin/main..HEAD --oneline` showed exactly this one line), and its direct parent (`git rev-parse HEAD^`) was confirmed identical to the then-live remote HEAD (`git ls-remote origin refs/heads/main` → `cfafd2d`). `git push origin main` reported `cfafd2d..0a02320 main -> main`. Post-push, re-verified via a fresh live `git ls-remote` (not a cached ref): returned `0a023207a84878aa41cb472b42240ebd40f54cca` — remote `main` confirmed at this exact SHA. No force-push, rebase, amend, or reset was used.
- **Reconciled again at this update (read-only, no changes made):** local `main` HEAD and live `origin/main` both still exactly `0a023207a84878aa41cb472b42240ebd40f54cca` — no drift since the push. Working tree unchanged: only `docs/SESSION_HANDOVER.md` (modified) and `docs/MASTER_FIXING_TRACKER.md` (untracked) remain, both still deliberately uncommitted. All 5 pre-existing stashes re-confirmed present and untouched.
- **GitHub Actions (`checks` → `deploy` in `deploy.yml`): user-confirmed succeeded.** This session has no `gh` CLI (`which gh` → not found) and no way to query the Actions API or UI directly, so this is not independently tool-verified — it rests on the user's explicit confirmation.
- **Hostinger deployment: user-confirmed**, for the same reason — no SSH/API access from this session to observe it directly. The post-deploy script's `php artisan migrate --force` therefore ran against production as part of this deploy — but this change required **no migration** (the new `PointTransactionType::REFUND_PARTIAL` case is backed by the pre-existing plain `string` column on `referral_point_transactions.type`, confirmed via the table's migration, not a DB-level enum), so this deploy's automatic migration run had nothing new to apply for this task specifically.

**Exact changed files (4 app/test files, no others):**
1. `app/Enums/PointTransactionType.php`
2. `app/Services/LoyaltyPointsService.php`
3. `app/Services/RefundService.php`
4. `tests/Feature/OrderRefundTest.php`

**Business rule (user-approved, Option A):** when a partial return is refunded on an order where loyalty points were redeemed at checkout, restore redeemed points proportionally: `points_redeemed × (cumulative_refunded_product_amount / original_eligible_product_subtotal)`, using cumulative basis tracking so repeated partial returns never over- or under-credit, with integer rounding resolved exactly on the return that completes the refund.

**Implementation — reused the existing earned-points reversal architecture, no new concept introduced:**
- `app/Enums/PointTransactionType.php` — added one new case, `REFUND_PARTIAL`, distinct from the existing `REFUND` (full-order restoration) the same way `REVOKE_ORDER` is distinct from `REVOKE`. The backing column (`referral_point_transactions.type`) is a plain `string`, not a DB enum — **no migration needed or made.**
- `app/Services/LoyaltyPointsService.php` — new public method `reconcileRedeemedPointsAfterRefund(Order $order)`, mirroring `reconcileEarnedPointsAfterRefund()`'s exact shape: lock the order, recompute `target = intdiv(points_redeemed × min(refundedPaisa, productNetPaisa), productNetPaisa)` from the current state of the shared refund ledger (`RefundCalculator::refundedPaisa()`/`productNetPaisa()` — the same cap/basis already used by every other PAY-RETURN-001 calculation), subtract what a prior call already restored (summed fresh from `REFUND_PARTIAL` ledger rows, locked), and apply only the `delta`. Because `points_redeemed` has exactly one fixed value per order (unlike earned points, which can be granted at different times with different bases), there was no legacy/no-basis branching to replicate — simpler than its earned-points mirror.
  - **Rounding correctness:** since `target` is recomputed from scratch each call using `min(refunded, productNet)`, once cumulative refunds reach the product-net cap, `target` is exactly `points_redeemed` (division of a value by itself) — the return that completes the refund always picks up whatever floor-rounding remainder earlier partial returns left behind. Verified by a dedicated test with intentionally-non-dividing numbers (100 points over 3 equal lines: 33, then 66, then — on the step that happens to also fully close the order — the full 100 via the path below).
- `app/Services/RefundService.php`, `recordReturnRefund()` — added one call to the new method, placed alongside the existing `reconcileEarnedPointsAfterRefund()` call, in the exact same `! $fullyReturned` (partial-only) branch. A return that fully closes the order takes the pre-existing path instead (see below), so this new method is never reached in that case.
- **One necessary correctness fix, required by this feature, not unrelated refactoring:** `LoyaltyPointsService::refundRedeemedPointsIfApplicable()` (the pre-existing full-order-refund/cancellation path, triggered via `Order::booted()`) previously restored the *entire* `points_redeemed` unconditionally. A sequence of partial returns that happens to eventually refund every unit (fully closing the order on its last step) would reach this same unconditional full-restoration code — which, without this fix, would re-credit the full amount on top of whatever the partial mechanism already restored, double-crediting the customer. Fixed to restore only `max(0, points_redeemed − already_restored_via_REFUND_PARTIAL)`. **In the common case — no prior partial restoration, i.e. every full-order refund/cancellation this system has ever processed before this feature existed — `already_restored` is always 0, so `remaining` is always the full `points_redeemed`, byte-identical to the previous behavior.** This preserves "full-order cancellation/refund behavior must remain unchanged" for every case that previously existed, while making the new partial-then-eventually-full sequence correct — a case that could not have occurred before this feature, since nothing previously restored redeemed points incrementally.

**Modified files:** `app/Enums/PointTransactionType.php`, `app/Services/LoyaltyPointsService.php`, `app/Services/RefundService.php`, `tests/Feature/OrderRefundTest.php` (6 new tests, no file renamed/moved). No migration.

**New test evidence (`tests/Feature/OrderRefundTest.php`, new "Redeemed points (PAY-RETURN-001-BATCH-2)" section):**
1. A single partial return restores points proportionally to the refunded amount, not the full order.
2. Successive partial returns restore cumulatively without over/under-crediting, and the step that happens to fully close the order correctly hands off to the full-reversal path for exactly the remainder owed (the interaction-fix regression test — asserts the combined `REFUND_PARTIAL` + `REFUND` total equals `points_redeemed` exactly, not more).
3. A non-closing sequence of partial returns (one unit deliberately never returned, so the order never closes) ends exactly on the cumulative-formula target with no remainder lost.
4. A full-order refund with zero prior partial returns still restores all redeemed points through the pre-existing, unchanged `REFUND`-type path (regression guard: Batch 2 did not alter Batch 1's common-case behavior).
5. Reprocessing the same partial-return state (calling the new method twice with no new refund in between) never restores points twice.
6. An order with no redeemed points is a complete no-op.

**Test evidence (exact run, this session):**
- `tests/Feature/OrderRefundTest.php` alone: **42/42 passed, 120 assertions** (36 pre-existing + 6 new).
- Targeted sweep of every file this session found referencing `refundRedeemedPointsIfApplicable`/`points_refunded_at`/`PointTransactionType::REFUND`/`RefundService`/`recordReturnRefund` (regression check for the correctness fix above): `ReferralProgramTest`, `OrderCancellationAtomicityTest`, `LoyaltyPointsTest`, `Admin/PathaoCourierOrderTest`, `Admin/OrderPermanentDeletionTest`, `Admin/SteadfastCourierOrderTest`, `LoyaltyPointsConcurrencyRaceTest` (MySQL-only, skips on SQLite as expected), `ReturnRequestTest`, `Unit/CourierDeliveryEvidenceArchTest`, `ReturnFullRefundConcurrencyRaceTest` (MySQL-only, skips on SQLite as expected) — **combined with `OrderRefundTest.php`: 203 tests, 193 passed, 0 failed, 10 skipped (all MySQL-only), 876 assertions.**
- `vendor/bin/pint --dirty --format agent`: passed, no changes needed beyond what was already written.
- No full suite and no MySQL concurrency suite were run, per this task's own instruction to prefer targeted tests.

**Remaining risks / things a future session should know:**
- `CourierSendConcurrencyRaceTest.php` and other MySQL-only race files reference `RefundService`/redeemed-points flows but were not run against real MySQL this session (consistent with the restriction against lengthy MySQL concurrency suites) — the targeted SQLite evidence above is what's attested; MySQL-level concurrent-partial-return behavior is unverified, same caveat as the rest of PAY-RETURN-001.
- The interaction fix changes `refundRedeemedPointsIfApplicable()`'s behavior *only* in the new scenario this feature makes possible (prior partial restoration before a closing return) — there is no way to exercise that scenario without this feature existing, so there was no pre-existing test coverage to accidentally break; the new regression test (#2 above) is the only coverage for it.

---

## L. Instructions for the Next Claude CLI Session

**ShoppyDrive is in its final stabilization and closure phase (declared 2026-10-31).** Core development and every approved fixing task are complete, **including** `PAY-RETURN-001`'s production migration verification (resolved 2026-10-31 — see §B, user-provided `migrate:status` output confirmed all 4 migrations `Ran`). **Do not start new features, refactoring, or additional audits** — there is no confirmed outstanding engineering work left in this file; only deliberately deferred items remain, and none of them are to be picked up without the user explicitly reopening them.

1. **Read this file first.** Then `docs/REMEDIATION_BACKLOG.md` (deeper historical/task detail) and `docs/SESSION_HANDOVER.md` (narrower session-pause detail — **note: large parts of `SESSION_HANDOVER.md` describe a stale, pre-merge, different-machine state; see the warning at its top**).
2. **Inspect the current Git state yourself** — branch, HEAD, `git status --short`, `git diff --stat`, remote state, `git stash list` (expect exactly 5). Do not trust this file's §A snapshot blindly once any time has passed.
3. **Preserve all existing changes** — uncommitted work, all 5 stashes, and branches are never incidental; treat every one as intentional until told otherwise. Never pop, drop, or apply any stash without separate explicit approval.
4. **Everything in §B is now either closed** (`COMPLETED`/`COMMITTED, PUSHED & DEPLOYED`/`VERIFIED — APPLIED IN PRODUCTION`) **or deliberately deferred and not a task to pick up unprompted**: `ADMIN-2FA-001` (permanently declined, never revisit), the 17 remaining Moderate npm advisories, the `nunomaduro/pao` rename, and `DEPLOY-SAFETY-001` Tier 2 — mention these only if the user asks, do not propose starting them. The unrecoverable IDs (`REL-001`, `ORD-001`, `ORD-002`, `ARCH-003`, `DEP-SEC-003`) remain unactionable without the user supplying their original source text.
5. **Work on one task at a time**, with its own explicit go-ahead — do not infer broader permission from a "resume" or "continue" instruction.
6. **Update this same file (`docs/MASTER_FIXING_TRACKER.md`) after every completed or reassessed task** — do not create a competing tracker, and do not let this file go stale while reality moves on.
7. **Record evidence, test counts, skipped tests, and remaining risks** for every status change — a status with no evidence line is not acceptable.
8. **Never silently change a task's identity or invent a missing scope** — if an ID's meaning is unconfirmed (`SHIP-001`) or unrecoverable (`REL-001`, `ORD-001`, `ORD-002`, `ARCH-003`, `DEP-SEC-003`), say so plainly rather than guessing and presenting the guess as fact.
9. **Ask for approval before any out-of-scope change, commit, push, or deployment** — these are the load-bearing rules from §E/§F; violating them has real consequences (an unreviewed push can deploy to production and run a real migration).
