# Session Handover — ShoppyDrive Security Audit & Remediation

**Last updated:** 2026-10-28 (session paused by explicit user request, mid-way through Stored XSS remediation planning — no implementation started on that item).

**Status: PAUSED, not blocked.** Nothing is broken. The previous phase of this engagement (git-divergence recovery, merge, CI fix, production deploy, deploy-script self-heal) fully shipped and is live on `main`/`origin/main`. This document now covers the **current, separate phase**: an exhaustive security/correctness audit, a small approved remediation batch (implemented, tested, left uncommitted), and a fully-specified-but-not-yet-implemented Stored XSS fix. The previous handover content (about the git divergence) is obsolete — everything it described has shipped — and has been replaced by this document.

This file exists so a resuming session (or a human) can pick this up without re-deriving context. Read this file, then `CLAUDE.md`, before taking any action.

---

## 1. Project context

ShoppyDrive — Laravel 13 / Inertia v3 / React 19 e-commerce app for the Bangladesh market, two front ends (public storefront + `/admin`) in one Inertia app with separate auth guards, deployed to **Hostinger shared hosting (not a VPS)**.

## 2. Git state (re-verified at pause time, read-only)

- **Branch:** `main`, **Local HEAD:** `8c7c816` — **even with `origin/main`**, no divergence, no pending push blocker.
- **Uncommitted, modified (7 files):**
  1. `CLAUDE.md` — pre-existing, unrelated to this phase's work.
  2. `vite.config.ts` — pre-existing, **must never be modified, staged, discarded, or committed** (the user's own local LAN-IP dev setting).
  3. `app/Console/Commands/SyncPathaoOrderStatuses.php` — Pathao `ConnectionException` fix (see §4).
  4. `app/Console/Commands/ClearResetsCommand.php` — command rename (see §4).
  5. `app/Models/User.php` — `role` removed from `$fillable` (see §4).
  6. `tests/Feature/MassAssignmentProtectionTest.php` — fixture fixed + 1 new regression test.
  7. `tests/Feature/Services/SyncPathaoOrderStatusesTest.php` — 1 new regression test.
- **Untracked (2 items):**
  - `tests/Feature/ClearExpiredResetsCommandTest.php` — new test file, part of the same approved batch.
  - `docs/` — contains only this file.
- **Stashes (3, all pre-existing, none created or touched this phase):**
  ```
  stash@{0}: On main: accidental eslint --fix reformatting, unrelated to audit-trail work
  stash@{1}: On main: local vite.config.ts LAN-server comment-out (superseded by origin 03e9a9a)
  stash@{2}: On main: incidental eslint --fix reformatting - not part of intended work
  ```
- **Safety branch:** `backup/local-main-677c2aa` — confirmed to exist, points at the pre-merge local `main` tip from the earlier phase. No longer strictly needed (that merge is long since pushed and stable), but not removed — removal would need separate explicit approval.

## 3. Completed audit work (prior phase, already shipped, for context only)

A prior phase of this engagement resolved a 4-commit git divergence with `origin/main` via a reviewed merge, fixed the CI pipeline (`.github/workflows/deploy.yml`'s `checks` job wasn't building frontend assets before running tests), fixed a production deploy-blocking bug (stale `bootstrap/cache/packages.php`/`services.php`, 5+ months out of date, crashing every artisan command via a `CauserResolver` config-loading error), and pushed/deployed all of it successfully. **This is all shipped and stable — no action needed on it.** Full detail is not repeated here; ask if the original record is needed.

## 4. Completed, tested, and uncommitted — approved remediation batch (current phase)

An exhaustive multi-phase security/correctness audit (covering architecture inventory, IDOR, loyalty/referral integrity, guest/COD checkout, admin permissions, caching, Pathao/Steadfast, stored XSS, mass assignment, N+1 spot-checks, CORS/CSRF) produced a deduplicated finding register (see §6). From that register, the user approved a small, scoped first batch:

| Fix | File(s) changed | Test(s) added | Last run result |
|---|---|---|---|
| Pathao `ConnectionException` isolation — a single unreachable order no longer aborts syncing the rest of the batch in `pathao:sync-statuses` | `app/Console/Commands/SyncPathaoOrderStatuses.php` | New case in `tests/Feature/Services/SyncPathaoOrderStatusesTest.php` | 2/2 passed |
| `auth:clear-resets` renamed to `app:clear-expired-resets` — fixes a self-recursion bug (the custom command shared Laravel's built-in command's name, so `Artisan::call('auth:clear-resets')` resolved back to itself) | `app/Console/Commands/ClearResetsCommand.php` | New `tests/Feature/ClearExpiredResetsCommandTest.php` | 1/1 passed |
| `User.role` removed from `$fillable` — investigation confirmed no legitimate runtime path depends on mass-assigning it (DB column defaults to `'customer'`; the only prior reference was a test fixture, now fixed to use `forceFill()`) | `app/Models/User.php` | `tests/Feature/MassAssignmentProtectionTest.php` (fixture fixed + 1 new test proving `role` isn't mass-assignable at all) | 5/5 passed |

**Last full-suite run (this batch, most recent):** `php artisan test --compact` → **831 tests, 828 passed, 3 skipped, 0 failed.** The 3 skips are long-standing and expected: 2 tests need a real MySQL FULLTEXT index, 1 is a MySQL-only coupon-concurrency race test that needs a dedicated test database that has never been provisioned. `vendor/bin/pint --dirty --format agent` → passed.

**All three fixes are left uncommitted, as instructed at the time they were made — still uncommitted now.**

## 5. Stored XSS remediation — fully investigated and specified, implementation NOT started

This is the most advanced *unimplemented* item. Investigation is complete; no code has been written.

**Five confirmed write-path → public/admin/email sink pairs, each needing a different enforcement mechanism:**
1. `Blog.content` (via `BlogRequest.php`) → model mutator on `Blog` → public `blog/Show.tsx`.
2. `Product.description` (via `ProductRequest.php`) → model mutator on `Product` → public `ProductDetail.tsx`, `LandingPage.tsx`.
3. About-page `mission_body` → **not a dedicated column** — one row among ~70 in the generic `Setting` key-value store (`Admin\SettingController::update()`'s single big loop). Needs a key-gated `Setting::saving()` hook naming exactly which keys are rich text — a blanket mutator would corrupt stored API credentials/secrets living in the same table.
4. Newsletter `body` → never persisted at all; sanitize in `Admin\NewsletterController::send()` before constructing the Mailable. Confirmed via a full `grep -rn "{!!" resources/views/` sweep to be **the only raw/unescaped Blade output in the entire application** (`emails/newsletter/custom.blade.php`).
5. `LandingPage.sections[*].custom_content` → one string nested inside a whole-JSON-array column shared with unrelated fields (titles, image paths) — needs custom logic inside that attribute's `set()`, not a plain mutator. Also currently has **zero validation** on this nested field (only the parent `sections` key is checked as `'array'`) — the user has approved adding `'sections.*.custom_content' => ['nullable','string','max:20000']` as part of this remediation.

**Dependency recommendation:** `mews/purifier` (wrapping `ezyang/htmlpurifier`). Compatibility verified live against Packagist (not from memory): `mews/purifier` v3.4.4 declares Laravel 13 support; `ezyang/htmlpurifier` v4.19.1 declares PHP 8.3 support. The package's actual default `config/purifier.php` was fetched from its public source and reviewed directly — confirmed its `HTML.Allowed`/`CSS.AllowedProperties` directive syntax, and surfaced a concrete implementation detail: its default HTML4.01 doctype doesn't natively recognize `<u>` without an explicit `custom_elements` registration, which this app's config will need too (the editor has an underline button).

**User decisions already made, to carry forward unchanged:**
- Block `iframe`/`object`/`embed`/SVG/`data:` URIs by default — no confirmed business need found (a read-only query of local dev data found zero products using these in `description`, though this is a small, likely-unrepresentative sample; production should be checked the same way before finalizing).
- Disable inline CSS/`style` entirely in the initial allowlist.
- Never rewrite any existing stored content without a separate, reviewed dry-run diff **and** a separate backfill approval — these are two distinct approval gates, not one.

**Explicitly still unverified, not invented:** the real HTML that `resources/js/components/admin/RichTextEditor.tsx`'s toolbar (`document.execCommand()`-based) actually produces in a live browser — no browser automation tool was available in this session to capture it. This is needed before the final `HTML.Allowed` tag/attribute list is locked in, especially for strikethrough and font-color formatting.

**Pending dependency decision — the single hard gate before any implementation:** `mews/purifier` has been **approved for consideration only**. Neither `composer require` nor any application code change has been authorized. Both remain blocked until the user explicitly says so, separately from this pause.

## 6. Broader audit findings — not part of this batch, still open

From the same exhaustive audit, still unaddressed (not started, not part of the current uncommitted work):
- **Non-atomic loyalty/referral point granting and reversal** (`LoyaltyPointsService`, `ReferralService`) — no `DB::transaction()` wrapping either the scheduled-task granting loops or the webhook/admin-cancel-triggered reversal path; a crash or race can double-grant or double-refund points. Confirmed via code reading, not executed.
- **Queue execution path absent from the repository** — 12 `ShouldQueue` mailables + 1 job, no committed scheduled `queue:work` anywhere compatible with Hostinger shared hosting's lack of a persistent worker process. **Production queue health remains explicitly NOT VERIFIED** — no SSH/production access was used to check it.
- **Landing-page COD duplicate-submission — confirmed structural gap, no mitigating mechanism exists** (`LandingPageController::placeOrder()`'s COD branch has no lock, no cart to empty, no idempotency token at all). The main `CheckoutController` COD path has the same class of risk but with partial incidental protection (cart-emptying) — classified as potential risk there, not confirmed.
- **Missing admin interface for the generic `Page` model** (Privacy Policy/Terms/FAQ) — `CLAUDE.md` describes it as admin-editable; no such admin controller/route/UI exists anywhere in the repo. Content can currently only change via direct DB access or a seeder.
- **Pathao sync resilience beyond `getOrderInfo()`** — the `ConnectionException` fix in this batch only covers that one method; `PathaoCourierService::createOrder()` and other methods were not independently re-checked for the same gap.
- **Full N+1/performance pass** — only 4 pages were spot-checked across the whole audit (`ProductListingController`, `Admin\OrderController`, `Admin\DashboardController`); this was never claimed as exhaustive.
- Several lower-priority items (registration/review bonus check-then-act race — minor, not confirmed exploitable) are documented in the fuller audit record but not repeated here in full.

## 7. Known risks and important files to preserve

- `CLAUDE.md`, `vite.config.ts` — pre-existing, unrelated, never modify.
- `app/Console/Commands/SyncPathaoOrderStatuses.php`, `app/Console/Commands/ClearResetsCommand.php`, `app/Models/User.php`, `tests/Feature/MassAssignmentProtectionTest.php`, `tests/Feature/Services/SyncPathaoOrderStatusesTest.php`, `tests/Feature/ClearExpiredResetsCommandTest.php` — this phase's approved, tested, uncommitted work.
- All 3 stashes, branch `backup/local-main-677c2aa` — preserve exactly as-is unless the user explicitly asks otherwise.
- This file.

## 8. Exact next steps to resume safely, in order

1. Re-run the git-status/stash/safety-branch checks in §2 again — don't trust this snapshot blindly if meaningful time has passed.
2. Confirm the approved-but-uncommitted batch (§4) is still intact and the full suite is still green before building anything new on top of it.
3. Do not assume pausing or resuming grants any new authorization. Specifically still blocked pending explicit approval, each separately:
   - `composer require mews/purifier` (dependency installation).
   - Writing the five XSS enforcement points + regression tests (application code).
   - Capturing real `RichTextEditor` browser output (needed before the allowlist is finalized).
   - Any dry-run diff review of existing stored rich-text content.
   - Any backfill/rewrite of existing stored content — separate approval gate from the dry-run itself.
   - Committing any of the currently-uncommitted work.
4. Only act on whichever specific task the user authorizes next — do not infer broader permission from a "resume" instruction alone.

---

## Suggested opening prompt for a resuming session

> Resume the ShoppyDrive security remediation work. Read `docs/SESSION_HANDOVER.md` first, then `CLAUDE.md`. Re-check git status, the 3 stashes, and the safety branch read-only. Confirm the approved remediation batch (Pathao fix, command rename, User.role hardening) is still intact and tests still pass. Do not install `mews/purifier`, write any XSS remediation code, run a backfill, or commit anything until I explicitly authorize that specific step.
