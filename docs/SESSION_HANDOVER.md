# Session Handover — ShoppyDrive Security Audit & Remediation

**Last updated:** 2026-10-29 (session paused by explicit user request, immediately after locally committing PROMO-LOY-001 — no push performed).

**Status: PAUSED, not blocked.** Nothing is broken. This document covers the audit/remediation phase that started after the git-divergence recovery (that earlier phase fully shipped and is live — see §3). Since this file was first written, one more audit-approved fix has been implemented, reviewed, and committed locally. Read this file, then `CLAUDE.md`, before taking any action.

---

## 1. Project context

ShoppyDrive — Laravel 13 / Inertia v3 / React 19 e-commerce app for the Bangladesh market, two front ends (public storefront + `/admin`) in one Inertia app with separate auth guards, deployed to **Hostinger shared hosting (not a VPS)**.

## 2. Git state (re-verified at pause time, read-only)

- **Branch:** `main`, **Local HEAD:** `710f88d` (`fix: make loyalty and referral points operations atomic`).
- **`origin/main`:** `f99d83f` — **local `main` is 2 commits ahead of `origin/main`, not pushed:**
  ```
  710f88d fix: make loyalty and referral points operations atomic   (PROMO-LOY-001, see §4a)
  dd63873 fix: anonymize customers instead of deleting accounts     (DB-001, prior session)
  ```
  **No push has been authorized or performed.** A resuming session must get explicit approval before pushing either commit.
- **Uncommitted, modified (2 files) — both pre-existing, unrelated to any audit work, must never be modified/staged/discarded/committed:**
  1. `vite.config.ts` — the user's own local LAN-IP dev setting (`192.168.0.113` → `192.168.0.111`).
  2. `resources/js/pages/auth/reset-password.tsx` — previously approved ARCH-001 change (`route('password.store')` → `route('password.update')`).
- **Untracked:** none outside `docs/` (this file).
- **Stashes (3, all pre-existing, none created or touched since the previous handover):**
  ```
  stash@{0}: On main: accidental eslint --fix reformatting, unrelated to audit-trail work
  stash@{1}: On main: local vite.config.ts LAN-server comment-out (superseded by origin 03e9a9a)
  stash@{2}: On main: incidental eslint --fix reformatting - not part of intended work
  ```
- **Safety branch:** `backup/local-main-677c2aa` — still exists, points at the pre-merge local `main` tip from the earliest phase of this engagement. Not strictly needed anymore; not removed without separate explicit approval. A `dev` branch also exists locally (not investigated as part of this phase — do not assume its state).

## 3. Completed audit work (prior phases, already shipped, for context only)

A prior phase resolved a 4-commit git divergence with `origin/main` via a reviewed merge, fixed the CI pipeline, fixed a production deploy-blocking bug, and pushed/deployed all of it successfully. **This is all shipped and stable — no action needed on it.**

## 4. Completed and committed work (this phase)

### 4a. PROMO-LOY-001 — non-atomic loyalty/referral point operations (just committed, NOT pushed)

Resolves the item previously tracked in §6 as "Non-atomic loyalty/referral point granting and reversal." Wrapped all seven affected methods in `DB::transaction($callback, 3)` with correct lock ordering and idempotency re-checks on the locked row, fixed a stale-snapshot risk in the referral monthly-cap check (`lockForUpdate()->sum()`), and fixed a real nested-transaction/deadlock-retry hazard discovered on the Socialite registration path.

- **Commit:** `710f88d` — `fix: make loyalty and referral points operations atomic`.
- **Files changed:**
  - `app/Services/LoyaltyPointsService.php` — `refundRedeemedPointsIfApplicable()`, `grantRegistrationBonus()`, `grantReviewBonus()`, `grantDueOrderEarnPoints()`, `claimDailyLogin()`.
  - `app/Services/ReferralService.php` — `grantDueReferralRewards()`, `handleOrderCancelledOrRefunded()`.
  - `app/Http/Controllers/Auth/SocialAuthController.php` — the outer registration transaction now retries (`, 3`), because `grantRegistrationBonus()`'s own nested transaction cannot retry itself when a real concurrency error rolls back the whole enclosing transaction (verified against Laravel 13's actual `Connection::handleTransactionException()` source, not assumed).
  - `tests/Feature/LoyaltyPointsConcurrencyRaceTest.php` (new) — two MySQL-gated race tests, mirroring `tests/Feature/CouponConcurrencyRaceTest.php`'s safety-gating convention exactly.
- **Test results:** Pint passed. `LoyaltyPointsTest.php` + `ReferralProgramTest.php`: 31 passed, 160 assertions. Full suite: 852 tests, 847 passed, 5 skipped, 0 failed.
- **Explicitly NOT verified:** the two new MySQL-gated race tests were skipped (`LOYALTY_RACE_TEST_DATABASE` not configured) — genuine InnoDB locking/deadlock behavior for this fix has not been empirically verified against real MySQL. No automated test exercises the exact nested-transaction-retry path fixed in `SocialAuthController.php` either; that fix's correctness rests on direct inspection of Laravel's framework source, not an executed reproduction.
- **Not pushed.** Local-only commit, 1 ahead of `dd63873` (DB-001), 2 ahead of `origin/main`.

### 4b. Prior approved remediation batch — now confirmed shipped

The batch previously tracked here as "completed, tested, and uncommitted" is **now committed and part of `main`** (commit `d5b4383`, `fix: resolve priority ecommerce audit findings batch 3`): the Pathao `ConnectionException` isolation fix, the `auth:clear-resets` → `app:clear-expired-resets` rename, and the `User.role` mass-assignment hardening. No action needed.

### 4c. `mews/purifier` dependency — added, not yet wired up

`composer require mews/purifier` has been done (commit `38e51bf`). This is **only the dependency** — confirmed via `grep -rln "Purifier\|HtmlPurifier" app` returning no results, no application code uses it yet. The Stored XSS remediation itself (§5) is still fully unimplemented.

## 5. Stored XSS remediation — fully investigated and specified, implementation NOT started

Still the most advanced *unimplemented* item. Investigation is complete; no remediation code has been written (the dependency from §4c is in `composer.json` but unused).

**Five confirmed write-path → public/admin/email sink pairs, each needing a different enforcement mechanism:**
1. `Blog.content` (via `BlogRequest.php`) → model mutator on `Blog` → public `blog/Show.tsx`.
2. `Product.description` (via `ProductRequest.php`) → model mutator on `Product` → public `ProductDetail.tsx`, `LandingPage.tsx`.
3. About-page `mission_body` → **not a dedicated column** — one row among ~70 in the generic `Setting` key-value store (`Admin\SettingController::update()`'s single big loop). Needs a key-gated `Setting::saving()` hook naming exactly which keys are rich text — a blanket mutator would corrupt stored API credentials/secrets living in the same table.
4. Newsletter `body` → never persisted at all; sanitize in `Admin\NewsletterController::send()` before constructing the Mailable. Confirmed via a full `grep -rn "{!!" resources/views/` sweep to be **the only raw/unescaped Blade output in the entire application** (`emails/newsletter/custom.blade.php`).
5. `LandingPage.sections[*].custom_content` → one string nested inside a whole-JSON-array column shared with unrelated fields (titles, image paths) — needs custom logic inside that attribute's `set()`, not a plain mutator. Also currently has **zero validation** on this nested field (only the parent `sections` key is checked as `'array'`) — the user has approved adding `'sections.*.custom_content' => ['nullable','string','max:20000']` as part of this remediation.

**Dependency recommendation:** `mews/purifier` (now installed, see §4c) wrapping `ezyang/htmlpurifier`. Its default `config/purifier.php` was fetched and reviewed directly; its default HTML4.01 doctype doesn't natively recognize `<u>` without an explicit `custom_elements` registration, which this app's config will also need (the editor has an underline button).

**User decisions already made, to carry forward unchanged:**
- Block `iframe`/`object`/`embed`/SVG/`data:` URIs by default — no confirmed business need found (a read-only query of local dev data found zero products using these in `description`, though this is a small, likely-unrepresentative sample; production should be checked the same way before finalizing).
- Disable inline CSS/`style` entirely in the initial allowlist.
- Never rewrite any existing stored content without a separate, reviewed dry-run diff **and** a separate backfill approval — these are two distinct approval gates, not one.

**Explicitly still unverified, not invented:** the real HTML that `resources/js/components/admin/RichTextEditor.tsx`'s toolbar (`document.execCommand()`-based) actually produces in a live browser — no browser automation tool was available in the original investigation session to capture it. This is needed before the final `HTML.Allowed` tag/attribute list is locked in, especially for strikethrough and font-color formatting.

**Still blocked, pending explicit approval, each separately:**
- Writing the five XSS enforcement points + regression tests (application code) — `mews/purifier`'s presence in `composer.json` is not itself authorization to use it.
- Capturing real `RichTextEditor` browser output.
- Any dry-run diff review of existing stored rich-text content.
- Any backfill/rewrite of existing stored content — separate approval gate from the dry-run itself.

## 6. Broader audit findings — not part of shipped/committed work, still open

From the same exhaustive audit, still unaddressed:
- ~~Non-atomic loyalty/referral point granting and reversal~~ — **resolved, see §4a (commit `710f88d`, not yet pushed).**
- **Queue execution path absent from the repository** — 12 `ShouldQueue` mailables + 1 job, no committed scheduled `queue:work` anywhere compatible with Hostinger shared hosting's lack of a persistent worker process. **Production queue health remains explicitly NOT VERIFIED** — no SSH/production access was used to check it.
- **Landing-page COD duplicate-submission — confirmed structural gap, no mitigating mechanism exists** (`LandingPageController::placeOrder()`'s COD branch has no lock, no cart to empty, no idempotency token at all). The main `CheckoutController` COD path has the same class of risk but with partial incidental protection (cart-emptying) — classified as potential risk there, not confirmed.
- **Missing admin interface for the generic `Page` model** (Privacy Policy/Terms/FAQ) — `CLAUDE.md` describes it as admin-editable; no such admin controller/route/UI exists anywhere in the repo. Content can currently only change via direct DB access or a seeder.
- **Pathao sync resilience beyond `getOrderInfo()`** — the `ConnectionException` fix (§4b) only covers that one method; `PathaoCourierService::createOrder()` and other methods were not independently re-checked for the same gap.
- **Full N+1/performance pass** — only 4 pages were spot-checked across the whole audit (`ProductListingController`, `Admin\OrderController`, `Admin\DashboardController`); this was never claimed as exhaustive.
- Several lower-priority items are documented in the fuller audit record but not repeated here in full.

None of these were re-verified in the PROMO-LOY-001 session — don't assume they're still accurate without re-checking if meaningful time has passed.

## 7. Known risks and important files to preserve

- `vite.config.ts`, `resources/js/pages/auth/reset-password.tsx` — pre-existing, unrelated, never modify (see §2).
- All 3 stashes, branch `backup/local-main-677c2aa` — preserve exactly as-is unless the user explicitly asks otherwise.
- Local commits `dd63873` and `710f88d` — not pushed; do not push without explicit approval.
- This file.

## 8. Exact next steps to resume safely, in order

1. Re-run the git-status/stash/safety-branch/ahead-of-origin checks in §2 again — don't trust this snapshot blindly if meaningful time has passed.
2. Do not assume pausing or resuming grants any new authorization. Specifically still blocked pending explicit approval, each separately:
   - Pushing `dd63873`/`710f88d` to `origin/main`.
   - Writing the five XSS enforcement points + regression tests (application code), per §5.
   - Capturing real `RichTextEditor` browser output.
   - Any dry-run diff review or backfill of existing stored rich-text content.
   - Setting up/provisioning a real MySQL test database to empirically verify the two skipped tests in `tests/Feature/LoyaltyPointsConcurrencyRaceTest.php`.
3. Only act on whichever specific task the user authorizes next — do not infer broader permission from a "resume" instruction alone.

---

## Suggested opening prompt for a resuming session

> Resume the ShoppyDrive security remediation work. Read `docs/SESSION_HANDOVER.md` first, then `CLAUDE.md`. Re-check git status, the 3 stashes, the safety branch, and whether local `main` is still ahead of `origin/main`, all read-only. Do not push, install new dependencies, write any XSS remediation code, run a backfill, or connect to any MySQL test database until I explicitly authorize that specific step.
