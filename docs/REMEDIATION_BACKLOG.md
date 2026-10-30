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

> **2026-10-30 — working environment changed.** The user authorized `/home/octagon/Desktop/ShoppyDrive` (a different machine, LAN `192.168.0.153`) as the working checkout. At authorization it was `main` @ `569f474`, clean, even with `origin/main`, 1 unrelated stash. The two unstaged changes, four stashes, `dev` branch and `backup/local-main-677c2aa` listed below **exist only on the previous machine's checkout** and were not found here (filesystem-wide search). They must be left untouched and not recreated here. The bullets below describe that previous checkout.

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
- **Status:** `COMMITTED_NOT_PUSHED` (P0) — commit `aec14e0` on local branch `fix/remediation-arch001-promoloy002-courier-refresh` (2026-10-30). **Not pushed, not deployed.** Not `DONE` until it ships.
- **Bug (re-confirmed 2026-10-30):** `origin/main` @ `569f474` still called `route('password.store')`, which is not a registered route (`php artisan route:list --path=reset-password` → only `password.update` `POST reset-password` and `password.reset` `GET reset-password/{token}`). Submitting the reset form throws a Ziggy "not in the route list" error, so customer password reset is broken on every pushed commit. The earlier fix only ever existed as an unstaged edit on the previous machine's checkout.
- **Fix:** `resources/js/pages/auth/reset-password.tsx:75` → `route('password.update')`.
- **Tests:** new `reset password page only calls registered route names` in `tests/Feature/Auth/PasswordResetTest.php` (scans the page's `route('…')` calls, asserts each is registered and that `password.update` is used). Confirmed failing against the old `password.store` source, passing with the fix. File: 6/6 passed. The existing backend tests already posted to `password.update` directly, which is why they never caught this.
- **Not verified:** a real browser submission of the reset form (no browser test tooling installed).
- **Cross-machine note:** the previous machine still holds the same change as an unstaged edit; when that checkout next pulls this fix, its identical local edit will need reconciling there (by that machine's owner, not from here).
- **Next safe action:** push/merge to `main` only on explicit approval (a push to `main` auto-deploys).

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
- **Related:** `PROMO-LOY-002` (§4) — a separate, adjacent gap found 2026-10-30: the double-refund protection here is correct, but the *status change* that triggers it was not in the same transaction. PROMO-LOY-001 itself is not reopened.

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

### PROMO-LOY-002 — cancel/refund status change not atomic with its financial side effects
- **Status:** `COMMITTED_NOT_PUSHED` (P1) — commit `5516714` on local branch `fix/remediation-arch001-promoloy002-courier-refresh` (2026-10-30). **Not pushed, not deployed.** Not `DONE` until it ships.
- **Relationship:** follow-up to `PROMO-LOY-001`, which made the redeemed-points refund itself idempotent (Order row locked, `points_refunded_at` re-checked). That remains correct and was not changed.
- **Root cause:** three entry points wrote the order's CANCELLED/REFUNDED status in autocommit mode, outside any transaction: `Admin\OrderController::markRefunded()`, `::cancel()`, and `CourierOrderStatusMapper::sync()` (Pathao cron, Steadfast webhook, admin send/refresh actions). `Order::booted()`'s `updated` hook then ran the refund, referral reversal, stock restore and coupon release in their own, separate transactions. If any of those failed, the status (and history row, and courier raw status) stayed committed while the side effect did not — and because the hook only fires on a status *change*, nothing ever retried it: the customer's redeemed points were lost. Both admin actions also checked the status on the request-time model, so two concurrent clicks could both transition the order (duplicate history row and customer email). `ReturnController` was already correct (single transaction, Order locked first).
- **Fix:** each of the three entry points now runs in `DB::transaction(..., 3)`, locks the Order row first (`lockForUpdate`), re-checks status / raw courier status on the locked row, then writes status + history; the hook's side effects run inside that transaction (their own `DB::transaction` calls become savepoints, and their exceptions propagate and roll everything back). Lock order is unchanged: Order → Users (ascending) → OrderItems → Products/Variants/FlashSaleItems → Coupon — the same set `ReturnController` already holds in one transaction, and consistent with checkout's User → Products → Coupon. Mails are already `afterCommit()`, so a rollback sends nothing. `sync()` keeps `withTrashed()` behavior and refreshes the caller's instance after commit. `pathao:sync-statuses` now isolates a per-order sync failure (`report()` + warn + continue) so an order whose side effect keeps failing can't abort every later order on each run.
- **Files:** `app/Http/Controllers/Admin/OrderController.php`, `app/Services/CourierOrderStatusMapper.php`, `app/Console/Commands/SyncPathaoOrderStatuses.php`; tests `tests/Feature/OrderCancellationAtomicityTest.php` (new), `tests/Feature/LoyaltyPointsConcurrencyRaceTest.php` (5 tests added).
- **Tests — SQLite (logic and transaction boundaries, not concurrency):** 10/10 in `OrderCancellationAtomicityTest.php` (9 below + the Steadfast webhook redelivery test) — normal refund; repeated mark-refunded; cancel-then-refund; already-refunded; stale duplicate cancel (1 history row, 1 `OrderCancelledMail`); REFUND-ledger failure rollback; referral-reversal failure rollback; courier cancellation failure rollback + successful retry; Pathao command continues past a rolled-back order. Against the original code the three rollback tests fail and the Pathao-isolation test does not pass. Full suite (final): 946 tests, 934 passed, 12 skipped (6 pre-existing + 6 MySQL-only), 0 failed.
- **Tests — real MySQL 8.0.46 (`shoppydrive_test_loyalty`, local 127.0.0.1:3306):** all 7 original-run tests passed, plus the deadlock test (8 total). All 7 in `LoyaltyPointsConcurrencyRaceTest.php` passed. The 5 new ones run real application code on both sides: an in-process admin action holds the Order lock in an open transaction while a second PHP process runs a real admin action or courier sync, confirmed blocked in InnoDB `LOCK WAIT` before commit — duplicate cancel, duplicate mark-refunded, cancel vs mark-refunded, cancel vs Steadfast cancellation, plus a real-InnoDB rollback test (state unchanged, row lock released per `FOR UPDATE NOWAIT`).
- **Deadlock/retry — verified 2026-10-30 on real MySQL:** Laravel 13.39 rethrows a deadlock in a nested transaction as `DeadlockException` and only the outermost `transaction(..., 3)` retries; rollback to level 0 discards pending `afterCommit` callbacks. New test `retries mark-refunded after a real InnoDB deadlock without duplicating any side effect` reproduces a genuine InnoDB deadlock (`lock_deadlocks` +1; `SHOW ENGINE INNODB STATUS` shows the app's refund `select … users … for update` as the rolled-back victim) and verifies after the retry: status, 1 history row, 1 REFUND entry, balance credited once, stock restocked once, exactly 1 queued `OrderStatusUpdatedMail` job. Control: with `markRefunded()` temporarily set to 1 attempt, the same test fails with `1213 Deadlock found` (fix restored byte-identical). Note: `Lock wait timeout exceeded` is also treated as retryable by Laravel, so a long wait can repeat up to 3 × 50s.
- **Steadfast webhook:** code-level — a failing `sync()` propagates out of `SteadfastWebhookController::handle()` as HTTP 500 with nothing persisted (raw `steadfast_status` included), so a redelivery of the same payload is processed normally, and a duplicate delivery is a no-op (locked re-read of the raw status). Covered by a webhook-level test in `OrderCancellationAtomicityTest.php` (500 → redelivery 200 → duplicate 200; one refund, one history row). **Externally unverified:** whether/how Steadfast retries non-2xx deliveries — no Steadfast documentation found; if it does not retry, the order catches up only via a later status report or an admin refresh.
- **Admin courier actions (verified with a temporary probe, since deleted):** cancellation-with-failing-refund via refresh → 500, fully rolled back; repeated identical refresh → no duplicate history/refund; send succeeding remotely but `sync()` failing → 500 while the consignment ID and "Sent to …" history (written before `sync()`, unchanged behavior) persist, and a resend is correctly rejected. A pre-existing defect was found in the refresh actions — see `COURIER-REFRESH-CONN-001` (not fixed).
- **Not verified:** Steadfast's real retry policy; production.
- **Accepted behavior change:** a failing side effect now surfaces as an error (500 for admin actions/webhook; warning in the Pathao command) with nothing persisted, instead of a silently half-applied cancellation.
- **Next safe action:** user review; commit/push only on explicit approval.

### COURIER-REFRESH-CONN-001 — admin courier "refresh status" actions 500 on a connection failure
- **Status:** `COMMITTED_NOT_PUSHED` — found 2026-10-30 while verifying PROMO-LOY-002 (pre-existing, not caused by it); fixed and tested the same day; commit `8315ea3` on local branch `fix/remediation-arch001-promoloy002-courier-refresh`. **Not pushed, not deployed.**
- **Description:** `Admin\OrderController::refreshPathaoStatus()` and `::refreshSteadfastStatus()` catch only `RuntimeException`, but `PathaoCourierService::getOrderInfo()` / `SteadfastCourierService::getOrderInfo()` let `Illuminate\Http\Client\ConnectionException` (not a `RuntimeException`) propagate. A courier timeout therefore shows the admin a 500 instead of the clean error the two send actions already give (they catch `RuntimeException|ConnectionException`). Confirmed by a temporary probe: both refresh routes returned 500 with no flash error. No data is written in that path, so this is UX/robustness, not data integrity.
- **Related observation (not a defect claim):** the send actions record the consignment before calling `sync()`; if `sync()` then fails the admin sees a 500 even though the parcel was created (the consignment is saved and resend is blocked, so no duplicate). They also check "already sent" on the request-time model without a lock, so two simultaneous send clicks could create two remote consignments — unverified, outside PROMO-LOY-002.
- **Fix:** `refreshPathaoStatus()` and `refreshSteadfastStatus()` now catch `RuntimeException|ConnectionException` (same as the send actions), redirecting back with a `Pathao: …` / `Steadfast: …` flash error. The catch wraps only the courier call, which precedes any write, so a connection failure reaches no `sync()`, status, history or financial code.
- **Tests:** one new test each in `tests/Feature/Admin/PathaoCourierOrderTest.php` and `SteadfastCourierOrderTest.php` (`a connection failure while refreshing … status shows a clean error and changes nothing`): redirect + flash error; order status, raw courier status, `points_refunded_at`, history count, ledger rows and customer balance all unchanged (order set up with a customer and 60 redeemed points so an accidental cancellation side effect would show). Both fail with a 500 against the old catch clauses (control run; fix restored byte-identical). Courier admin files 12/12; focused set 64/64; full suite 948 tests, 936 passed, 12 skipped, 0 failed.
- **Out of scope (unchanged):** the simultaneous-send observation above was not investigated.
- **Next safe action:** user review; commit/push only on explicit approval.


### COURIER-SEND-RACE-001 — simultaneous or retried admin "send to courier" requests create duplicate consignments
- **Status:** `IMPLEMENTED_UNCOMMITTED` — implemented and verified locally 2026-10-30 on branch `fix/remediation-arch001-promoloy002-courier-refresh` (on top of `8315ea3`), **not committed, not pushed, not deployed**. Not `DONE`: the external prerequisites below are unresolved. (Investigation, reproduction and design history follow.)
- **Flow (both couriers):** `Admin\OrderController::sendToPathao()` / `::sendToSteadfast()` check `$order->pathao_consignment_id` / `steadfast_consignment_id` on the route-model-bound (request-time) model → call the courier's create-order API (`PathaoCourierService::createOrder()` / `SteadfastCourierService::createOrder()`, up to 30 s timeout) → only then write the consignment ID + a "Sent to …" history row. No transaction, no `lockForUpdate`, no `Cache::lock`, no idempotency key stored locally; the consignment columns have a plain (non-unique) index, and a unique index would not help anyway (both requests write the same row; last write wins).
- **Existing protection:** UI only — a confirm dialog and a `courierSending` flag disable the button while one request is in flight (same tab). Nothing protects against two tabs, two admins, or a resubmitted request. `invoice` (Steadfast) / `merchant_order_id` (Pathao) = `order_number` is sent to the courier, but whether either courier rejects a duplicate on it is **external and unverified**.
- **Confirmed (reproduced 2026-10-30, real MySQL `shoppydrive_test_loyalty`, two separate PHP processes running the real controller actions simultaneously, against a local fake courier server that records requests and answers in 2 s — no real courier contacted):**
  - Steadfast: both requests saw no consignment, both got "Order sent to Steadfast for pickup." The fake courier received **2 create requests 1 ms apart** for the same invoice, issuing 2 consignments; the order row kept only the last ID, so the other consignment is orphaned (never synced or tracked) while both "Sent to Steadfast" history rows exist. Reproduced in 2 of 2 runs.
  - Pathao: same result — 2 create requests 2 ms apart, 2 consignments, last write wins, 2 history rows (1 of 1 run).
  - Timeout after acceptance: with `STEADFAST_TIMEOUT=1` and the fake courier accepting then answering after 3 s, the admin sees "Steadfast: cURL error 28 … timed out", nothing is saved locally, and the next send creates a **second** consignment (fake courier logged 2 creates). Whether the real courier would dedupe the second request on `invoice` is unverified.
- **Theoretical / code-level only (not reproduced):** a stale tab can send an order already sent to one courier to the *other* courier — the UI hides the send form once either ID exists, but the server only checks the same-provider column.
- **Impact if the real couriers do not dedupe:** two physical pickups/deliveries and two COD collections booked for one order, one of which the app never tracks.
- **Limitations:** fake courier server, not Pathao/Steadfast; real courier dedupe behavior unknown; harness is a scratch script (not committed) — a permanent regression test should come with the fix.
- **Design investigation (2026-10-30, planning only — no code changed):**
  - *Courier API capabilities — evidence is unofficial only.* No official Pathao or Steadfast documentation was found. Neither courier documents idempotency keys or duplicate-invoice behavior. **Steadfast:** three unofficial SDKs list `GET /status_by_invoice/{invoice}` (not used by this app; response shape — in particular whether it returns the consignment ID — unconfirmed); one says "the invoice is your uniqueness key" without stating what a duplicate `create_order` does; Steadfast publishes **no sandbox**, so any verification hits the live API. **Pathao:** the 11 Merchant API endpoints listed by an unofficial SDK include **no lookup by `merchant_order_id`** — the only order lookup is `GET /orders/{consignment_id}/info`, which that SDK says returns an `InvoiceId` (this app does not parse it today); Pathao has a sandbox where duplicate behavior could be verified. Verified from our own code only: create (both), `status_by_cid` (Steadfast), `orders/{id}/info` (Pathao).
  - *Ambiguity is detectable.* Laravel's `ConnectionException` wraps Guzzle's `ConnectException`, whose handler context carries the cURL `errno`, so "could not connect" (6/7: request never sent) can be told apart from "timed out / connection dropped after sending" (28, 52, 56: outcome unknown).
  - *Schema:* `orders.pathao_consignment_id` / `steadfast_consignment_id` have plain, non-unique indexes; no claim/state columns exist. A unique index on them would not prevent the race (both requests write the same row).
  - *Additional risk found:* `SteadfastWebhookController::findOrder()` matches by `invoice` first, so status webhooks for an orphaned duplicate consignment (same invoice) would update the real order's Steadfast status.
  - *Recommended design (pending approval):* a new `courier_submissions` table holding one row per send attempt, with a nullable **unique** `active_order_id` (set while an attempt is submitting/unknown/sent, NULL once definitively failed) as the database-enforced single-active-claim across both couriers; the claim is a short transaction (Order row `lockForUpdate`, legacy consignment-column and status checks, insert) committed **before** the courier call; outcome transitions are conditional single-row updates; ambiguous outcomes and stale `submitting` rows become `unknown`, which blocks all sends until an admin reconciles (record a verified consignment ID, or explicitly confirm "not created"). Full plan, state machine, crash cases, test plan and decisions are in the session report of 2026-10-30.
- **Approved decisions (user, 2026-10-30):** (1) keep the plain order number as the courier reference; (2) HTTP 5xx → `unknown`; (3) 5-minute stale threshold; (4) reconciliation by admins with `orders.update`, mandatory note; (5) manual reconciliation only — no automatic courier lookups in the first release; (6) server-side refusal to send cancelled/refunded/delivered orders; (7) block order cancellation while a submission is `submitting`/`unknown`, with a clear admin-facing error, no status change and no claim release; (8) Steadfast webhook: exact consignment-ID match first, never update on an ambiguous match; (9) preserve every existing consignment ID and tracking history.
- **Approved design corrections:** `active_order_id` = `order_id` only while `submitting`/`unknown`; NULL for `sent` and `failed` (a `sent` order is blocked by the consignment columns, checked under the same Order lock in the claim transaction). Legacy orders holding both a Pathao and a Steadfast ID get one `sent` history row per courier (both inactive, so no constraint conflict), with no "primary" assumed; the backfill is idempotent. Failure classification uses the cURL error number plus `request_size` from Guzzle's handler context: only "never transmitted" outcomes release the claim; anything that may have reached the courier is `unknown`. Pathao reconciliation requires the info endpoint's order reference to equal the order number; Steadfast reconciliation requires manual portal verification + note (`status_by_cid` only proves existence); who/when/how is recorded.
- **Implementation (2026-10-30, user-authorized):**
  - *Atomic cross-courier claim:* new `courier_submissions` table (one row per attempt; `courier`, `state`, `reference` = plain order number, consignment/tracking, failure evidence incl. cURL errno and request bytes sent, claimed/resolved by and when, resolution + note). `active_order_id` is set to the order id only while an attempt is `submitting`/`unknown` and has a **unique** index, so the database allows at most one unresolved attempt per order across Pathao and Steadfast. `CourierSubmissionService::claim()` locks the Order row, refuses cancelled/refunded/delivered orders, orders already holding either consignment ID, and orders with an unresolved attempt, inserts the attempt and commits **before** the courier call — no lock is held during the HTTP request. `SENT` clears the claim in the same Order-locked transaction that writes the order's consignment column, so a successful send keeps blocking via that column.
  - *Outcome classification:* `createOrder()` in both courier services builds the request (settings, Pathao token) before sending — failures there are definite (`failed`, claim released). For the create request itself: cURL 5/6/7 (never connected) and HTTP 4xx other than 408/409 are definite failures; timeouts, dropped connections, TLS errors, 408/409, 5xx, 2xx error payloads, success without a consignment ID and unexpected exceptions are `unknown`.
  - *UNKNOWN and manual reconciliation:* `unknown` attempts are never retried automatically and keep the claim; a `submitting` attempt older than 5 minutes becomes `unknown` (`stale`). Reconciliation (routes gated by `orders.update`, mandatory note + explicit confirmation, audited on the attempt and in order history): *Confirm not created* (both couriers) releases the claim; *Record consignment* — Steadfast only, the ID must exist per `status_by_cid` and not be on another order, ownership attested by the admin's portal check; **Pathao recording is disabled (fail closed)**. A late courier response never overwrites a reconciled or settled attempt — it is logged as critical and noted on the order as a possible duplicate.
  - *Cancellation and refund guards:* `Admin\OrderController::cancel()` and `::markRefunded()` refuse, inside their existing Order-locked transaction, while an attempt is `submitting`/`unknown` — no status change, no claim release, clear admin-facing message. PROMO-LOY-002 behavior otherwise unchanged.
  - *Legacy backfill:* separate idempotent migration records every existing consignment as a `sent`, inactive (`active_order_id` NULL) row; an order with both a Pathao and a Steadfast ID gets one row per courier, no primary assumed; soft-deleted orders included; `orders` columns untouched; `down()` removes only backfilled rows.
  - *Steadfast webhook:* `findOrder()` matches only on an exact `consignment_id` present on exactly one order; invoice-only payloads, unknown consignments (e.g. an orphaned duplicate with the same invoice), multiple matches, an invoice contradicting the matched order, and trashed orders are logged and not updated (still acknowledged with 200).
  - *Admin UI:* order page Courier panel shows in-progress / unknown (with both reconciliation forms) / last definite failure; Send is disabled for cancelled, refunded and delivered orders.
  - *Files:* modified — `app/Http/Controllers/Admin/OrderController.php`, `app/Http/Controllers/SteadfastWebhookController.php`, `app/Models/Order.php`, `app/Services/PathaoCourierService.php`, `app/Services/SteadfastCourierService.php`, `resources/js/pages/admin/orders/show.tsx`, `routes/admin.php`, `tests/Feature/SteadfastWebhookTest.php`, `tests/Feature/OrderCancellationAtomicityTest.php` (webhook payload now carries `consignment_id`); new — `app/Enums/CourierSubmissionState.php`, `app/Exceptions/CourierSubmissionException.php`, `app/Exceptions/CourierSubmissionBlockedException.php`, `app/Http/Requests/Admin/RecordCourierConsignmentRequest.php`, `app/Http/Requests/Admin/ConfirmCourierNotCreatedRequest.php`, `app/Models/CourierSubmission.php`, `app/Services/CourierSubmissionService.php`, `database/factories/CourierSubmissionFactory.php`, `database/migrations/2026_10_30_073622_create_courier_submissions_table.php`, `database/migrations/2026_10_30_073623_backfill_legacy_courier_submissions.php`, `resources/js/components/admin/CourierSubmissionPanel.tsx`, `tests/Feature/Admin/CourierSubmissionTest.php`, `tests/Feature/CourierSendConcurrencyRaceTest.php`, `tests/Fixtures/fake_courier_server.php`.
- **Verification at COURIER-SEND-RACE-001 completion (2026-10-30, local only — no production access; historical — superseded by the final combined evidence under `COURIER-SEND-RACE-002`):**
  - *Real MySQL (8.0.46, disposable `shoppydrive_test_loyalty`), final sequential runs on the current code, each uninterrupted:* `CourierSendConcurrencyRaceTest.php` **9/9 passed**, 0 failed, 0 skipped (342 s, exit 0); `LoyaltyPointsConcurrencyRaceTest.php` **8/8 passed**, 0 failed, 0 skipped (570 s, exit 0) — the latter re-verifies PROMO-LOY-002 (incl. the real InnoDB deadlock-retry test) with the new `cancel()`/`markRefunded()` guards. Courier tests run the real send actions in separate PHP processes against a local fake courier (`tests/Fixtures/fake_courier_server.php`, `php -S`; no real courier contacted) and assert from the fake courier's own request log: Pathao vs Pathao, Steadfast vs Steadfast and Pathao vs Steadfast → exactly 1 create; timeout after acceptance → `unknown` (cURL 28, request bytes sent > 0), next send blocked, 1 create; definite 422 → `failed`, retry succeeds; sender killed mid-request → stale → `unknown`, still blocking; late response after an admin "not created" decision → decision preserved, anomaly recorded; cancel and mark-refunded refused while a send is in flight; legacy backfill keeps both IDs, idempotent. Command per file: `DB_CONNECTION=mysql DB_DATABASE=shoppydrive_test_loyalty COURIER_RACE_TEST_DATABASE=shoppydrive_test_loyalty LOYALTY_RACE_TEST_DATABASE=shoppydrive_test_loyalty vendor/bin/pest --compact tests/Feature/<File>.php`. The schema rebuild completed (68 tables, all migrations applied).
  - *Migrations on MySQL (disposable database only):* `migrate:rollback --step=2` and re-`migrate` of both new migrations succeeded.
  - *SQLite:* `CourierSubmissionTest.php` 37/37; `SteadfastWebhookTest.php` 15/15; full suite **998 tests, 977 passed, 0 failed, 21 skipped** (12 pre-existing + 9 MySQL-only). Pint passed, `git diff --check` clean; ESLint/Prettier/tsc clean for the changed files (7 pre-existing ESLint warnings in untouched files).
  - *Development database `ShoppyDrive`:* unchanged throughout (never written to).
- **Resolved prerequisites:** "Mark refunded" is also blocked (user decision); editing `cancel()`/`markRefunded()` authorized; ARCH-001, PROMO-LOY-002 and COURIER-REFRESH-CONN-001 committed first (`aec14e0`, `5516714`, `8315ea3`); production holds no business data yet per the user, so implementation proceeded with synthetic local data.
- **Unresolved external prerequisites (must be settled before this is `DONE`):**
  1. *Pathao invoice identity verification* — confirm in the Pathao sandbox which field of `GET /orders/{consignment_id}/info` carries the merchant order reference (an unofficial SDK calls it `InvoiceId`). Until then Pathao "Record consignment" stays disabled, so a Pathao attempt that is genuinely `unknown` **and** was actually created cannot be settled in the app and keeps blocking send/cancel/refund for that order.
  2. *Steadfast webhook `consignment_id` behavior* — confirm whether `delivery_status` webhooks always include `consignment_id`. If some do not, those webhooks are now ignored (logged), and the order's Steadfast status only updates via admin refresh.
  3. *Production migration and deployment verification* — not run, not authorized. A push to `main` auto-deploys and runs `migrate --force`; before that: confirm the production DB engine/version, run the read-only checks (orders holding both consignment IDs; any consignment ID on more than one order), then verify both migrations and the backfill after deploy.
- **Observed, not changed:** after a successful send, the courier's initial status (e.g. Steadfast `in_review`) is synced and moves a `processing` order back to `pending` — pre-existing behavior of the send actions.
- **Follow-up:** a read-only review (PASS WITH CONCERNS) led to `COURIER-SEND-RACE-002` (below), which hardens this implementation in the same uncommitted change set: courier-returned IDs, finalization-failure recovery (Pathao included), the deadlock fix, unique consignment indexes, audit-history preservation, rollback guards and test-safety guards. Both tasks are verified together; see that entry for the final evidence and remaining limitations.
- **Next safe action:** commit only on explicit authorization (together with `COURIER-SEND-RACE-002`); push/deploy separately, after the production prerequisites in `COURIER-SEND-RACE-002`.

### COURIER-SEND-RACE-002 — recovery, concurrency and audit hardening of COURIER-SEND-RACE-001
- **Status:** `IMPLEMENTED_UNCOMMITTED` — implemented and verified locally 2026-10-30 on branch `fix/remediation-arch001-promoloy002-courier-refresh` (on top of `8315ea3`), **not committed, not pushed, not deployed**. Not `DONE`: production prerequisites and external courier behavior below are unverified.
- **Origin:** read-only review of COURIER-SEND-RACE-001 (PASS WITH CONCERNS); findings H1, M1, M2, M3, L2, L3, then a second review's D1/D3 and a third review's N1/N3/N4 plus the D2/N2 runbook.
- **Implemented:**
  - *H1 — courier-returned ID recovery (Pathao and Steadfast):* new columns `returned_consignment_id`, `returned_tracking_code`, `returned_at`. `CourierSubmissionService::recordReturnedConsignment()` saves the courier's response in its own write before finalization (first write wins; never touches state, claim or resolution). If `markSent()` then fails, a warning log records submission/order/courier/consignment/tracking IDs (no payloads), the attempt becomes `unknown` (`finalization_failed`) with the claim held, and the admin gets a clear message (`CourierSubmissionFinalizationException`). New reconciliation *Link returned consignment* (`confirmReturnedConsignment()`, route `admin.orders.courier.confirmReturnedConsignment`, `orders.update`, mandatory note + confirmation, audited) links that exact courier-returned ID — usable for Pathao. Hand-typed Pathao IDs remain disabled. Late responses still never override a reconciliation (they only fill the `returned_*` evidence).
  - *UNKNOWN state and claim:* unchanged guarantees — no automatic retry, every send to either courier refused while an attempt is `submitting`/`unknown`.
  - *M1 — deadlock fix:* the unresolved-attempt check is a plain read (the Order row lock already serializes every claim change; the unique `active_order_id` index remains the backstop), removing a gap lock that made simultaneous claims for *different* orders deadlock.
  - *M2 — unique consignment IDs:* nullable unique indexes on `orders.pathao_consignment_id` and `orders.steadfast_consignment_id` (replacing the plain ones). The migration first runs a read-only duplicate preflight (incl. soft-deleted orders and empty strings) and refuses, changing nothing, if any exist. Same check standalone: `php artisan courier:check-consignment-duplicates` (lists column/value/count/order IDs; exit code success/failure). Both reconciliation paths turn a unique violation into a clear "already recorded on another order" message.
  - *M3 — audit history:* `courier_submissions.order_id` nullable with `ON DELETE SET NULL` (not RESTRICT — customer self-deletion cascades orders via `orders.user_id`, and RESTRICT would break it). Permanently deleting an order keeps its attempts (order number in `reference`, IDs, timestamps, notes) detached.
  - *Rollback guards (M3 + D1):* create-table `down()` refuses while any non-backfilled row exists; returned-ID columns `down()` refuses while any returned ID is stored; FK migration `down()` refuses — before any schema change — while any non-backfilled row exists (D1, same definition as the create-table guard) or any row is detached.
  - *L2/L3:* order page disables Cancel, Mark-as-Refunded and address Edit with an explanation while an attempt is unresolved; `updateShippingAddress()` is now Order-locked and refused server-side while an attempt is unresolved.
  - *D3:* the 7 tests that change the schema inside the test transaction (migration `down()`/`up()`, index or table drops) skip on any non-SQLite driver, per test.
  - *H1 MySQL test:* real failure injection via a temporary `BEFORE INSERT` trigger on `order_status_histories` in the disposable database only.
  - *N1:* that trigger is created inside the test's `try`, so `finally` always drops it (`DROP TRIGGER IF EXISTS`), even if setup fails.
  - *N4:* `Tests\TestCase::setUpTraits()` checks the effective test connection (`Tests\SafeTestDatabase`, resolving any `DB_URL`) before `RefreshDatabase`/`DatabaseMigrations`/`DatabaseTruncation` run: SQLite allowed; MySQL/MariaDB only for a database named `shoppydrive_test_*`; anything else is refused immediately.
  - *D2/N2 runbook:* `docs/COURIER_MIGRATION_RECOVERY_RUNBOOK.md`.
- **Files (this task, on top of COURIER-SEND-RACE-001):** modified `app/Http/Controllers/Admin/OrderController.php`, `app/Services/CourierSubmissionService.php`, `app/Models/CourierSubmission.php`, `routes/admin.php`, `resources/js/components/admin/CourierSubmissionPanel.tsx`, `resources/js/pages/admin/orders/show.tsx`, `database/migrations/2026_10_30_073622_create_courier_submissions_table.php` (rollback guard), `tests/TestCase.php`, `tests/Fixtures/fake_courier_server.php`, `tests/Feature/CourierSendConcurrencyRaceTest.php`, `tests/Feature/Admin/CourierSubmissionTest.php` (one asserted message), `tests/Feature/SteadfastWebhookTest.php` (one test adapted and SQLite-guarded); new `app/Console/Commands/CheckConsignmentDuplicatesCommand.php`, `app/Support/ConsignmentDuplicateCheck.php`, `app/Exceptions/CourierSubmissionFinalizationException.php`, `app/Http/Requests/Admin/ConfirmReturnedConsignmentRequest.php`, migrations `2026_10_30_085856_add_returned_consignment_to_courier_submissions_table.php`, `2026_10_30_085857_preserve_courier_submissions_when_orders_are_deleted.php`, `2026_10_30_085859_add_unique_consignment_indexes_to_orders_table.php`, `tests/Feature/Admin/CourierSubmissionHardeningTest.php`, `tests/SafeTestDatabase.php`, `tests/Unit/SafeTestDatabaseTest.php`, `tests/Feature/SafeTestDatabaseGuardTest.php`, `docs/COURIER_MIGRATION_RECOVERY_RUNBOOK.md`.
- **Final evidence (2026-10-30, local only — no production access; RACE-001 + RACE-002 together):**
  - *Real MySQL 8.0.46, disposable `shoppydrive_test_loyalty`:* `CourierSendConcurrencyRaceTest.php` **12/12 passed**, 121 assertions, 0 skipped, exit 0 — run uninterrupted at the D1/D3/H1 step (596 s) and again after N1/N4 (657 s, code unchanged during each run). Includes: same-order Pathao/Pathao, Steadfast/Steadfast, Pathao/Steadfast (1 create each); timeout after acceptance; definite rejection and retry; killed sender → stale → unknown; late response after reconciliation; cancel/refund refused in flight; MySQL backfill; 4 different orders claiming at once with **0 InnoDB deadlocks** (control with the old locking read: **4 deadlocks**); concurrent same-ID reconciliation (exactly one succeeds); H1 finalization failure (returned ID kept, `unknown`, claim held, second create refused, recovery links the returned ID, 1 create total; 23 assertions).
  - `LoyaltyPointsConcurrencyRaceTest.php` **8/8 passed** (52 assertions, 0 skipped, exit 0) on the COURIER-SEND-RACE-002 code before the D1/D3/H1/N1/N4 steps; those steps changed no cancel/refund/service logic, and it was not re-run after them.
  - *N1:* restructured H1 test passed (23 assertions) and, with a setup failure injected after trigger creation (`FakeCourier` `mkdir()` failing via a non-existent `TMPDIR`), left **0 triggers**.
  - *N4:* `SafeTestDatabaseTest` (unit) and `SafeTestDatabaseGuardTest` 11/11 — a real Feature test pointed at a MySQL database named `ShoppyDrive` (on an unused port, so no real database is reachable) is refused by the guard before any connection; `shoppydrive_test_*` gets past it.
  - *D3 on MySQL:* `CourierSubmissionHardeningTest.php` + `SteadfastWebhookTest.php` → 36 tests, 29 passed, exactly the 7 guarded tests skipped; schema intact afterwards.
  - *SQLite:* at the D1/D3/H1 step — full suite **1022 tests, 998 passed, 0 failed, 24 skipped**; affected files 73/73. After N1/N4 — full suite **1033 tests, 1009 passed, 0 failed, 0 errors, 24 skipped** (12 pre-existing + 12 MySQL-only).
  - *Static:* Pint, Prettier, TypeScript and `git diff --check` clean; ESLint 0 errors, 7 pre-existing warnings in untouched files.
  - *Databases:* development `ShoppyDrive` unchanged throughout (67 tables, 111 migrations); disposable `shoppydrive_test_loyalty` kept (123 migrations, 0 triggers).
- **Remaining limitations (accepted for commit, not fixed):**
  - *D2/N2:* the FK and unique-index migrations each issue several DDL statements, which MySQL/MariaDB do not roll back — a failure part-way can leave the FK missing or only one column unique (no data is altered); a refused batch rollback removes the unique indexes before the FK migration refuses. Recovery: `docs/COURIER_MIGRATION_RECOVERY_RUNBOOK.md`.
  - *D4:* `claimed_by_admin_id`/`resolved_by_admin_id` are `nullOnDelete` and admins are hard-deleted; once both the order (with its history notes) and the admin are gone, a detached attempt keeps what/when/how but not who.
  - *D5:* a customer deleting their account during an in-flight send leaves a detached `unknown` attempt that cannot be reconciled in the UI (history kept).
- **External prerequisites (unverified):**
  1. *Pathao:* which `GET /orders/{consignment_id}/info` field carries the merchant order reference — hand-typed Pathao IDs stay disabled until confirmed (returned-ID linking does not need it).
  2. *Steadfast:* whether `delivery_status` webhooks always include `consignment_id` — invoice-only webhooks are ignored and logged.
  3. *Courier duplicate handling:* whether either courier deduplicates on the order number.
- **Production prerequisites before deploy (not run — Claude must not access production):** run `php artisan courier:check-consignment-duplicates` read-only and resolve any listed duplicates/empty strings by hand; confirm the production DB engine/version; have a backup and approval ready per the runbook. A push to `main` auto-deploys and runs `migrate --force`.
- **Next safe action:** commit on explicit authorization (together with COURIER-SEND-RACE-001); deploy only after the production prerequisites above.


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
- **Status:** `USER_VERIFIED`.
- **Description:** whether `php artisan schedule:run` is actually cron-triggered on Hostinger, and whether the database queue is actually draining in production (the repo's mechanism is the every-minute `app:drain-queue` scheduled in `routes/console.php`).
- **Verification source (2026-10-30):** user confirmation based on their own Hostinger panel checks and operational observation — the Laravel scheduler cron is configured and running, database queue processing works, and transactional emails are delivered.
- **Independent verification by Claude:** not performed. No session has inspected Hostinger, production logs, or the production database.
- **Priority:** closed at the user's direction; no further investigation planned.
- **Next safe action:** none. Do not change queue, scheduler, cron, or email configuration under this item.

---

## 6. MySQL concurrency and CI verification tasks

### MYSQL-CONCURRENCY-001 — PROMO-LOY-001 and PAY-RETURN-001 real InnoDB locking/deadlock behavior
- **Status:** `PARTIALLY_COMPLETE` — `LoyaltyPointsConcurrencyRaceTest.php` (2 original + 5 PROMO-LOY-002 tests) ran and passed against the user-authorized disposable database `shoppydrive_test_loyalty` on local MySQL 8.0.46 on 2026-10-30 (run with `DB_CONNECTION=mysql DB_DATABASE=shoppydrive_test_loyalty LOYALTY_RACE_TEST_DATABASE=shoppydrive_test_loyalty vendor/bin/pest tests/Feature/LoyaltyPointsConcurrencyRaceTest.php`). `CouponConcurrencyRaceTest.php` and `ReturnFullRefundConcurrencyRaceTest.php` have still never run against MySQL (not authorized). Under MySQL the global `RefreshDatabase` re-runs `migrate:fresh` around each test (~35s+ of DDL each, growing per test; no lock waits observed), so the file takes ~13 minutes.
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
- ~~`OPS-001`~~ — user-verified 2026-10-30 (see §5); no longer needs access.
- `SHIP-001`, `REL-001`, `ORD-001`, `ORD-002`, `DEP-SEC-003` — source recovery of the original audit document, or explicit instruction to proceed without it.

---

## 12. Change log for this file

- 2026-10-29 — file created from the most recent read-only remediation status audit. No prior version existed.
- 2026-10-30 — working checkout switched (see §1 note). ARCH-001 re-opened as a live P0 bug on `origin/main` and implemented + tested (uncommitted). OPS-001 → `USER_VERIFIED` on user confirmation (not independently verified).
- 2026-10-30 — PROMO-LOY-002 added (follow-up to PROMO-LOY-001, which stays DONE) and implemented + tested (uncommitted). MYSQL-CONCURRENCY-001 → `PARTIALLY_COMPLETE` (loyalty race file run on real MySQL).
- 2026-10-30 — PROMO-LOY-002 final verification: real InnoDB deadlock retry reproduced and verified; Steadfast webhook failure/redelivery tested; admin courier actions probed. Stays `IMPLEMENTED_UNCOMMITTED` (uncommitted). New `COURIER-REFRESH-CONN-001` recorded (open, not fixed).
- 2026-10-30 — COURIER-REFRESH-CONN-001 implemented + tested (uncommitted) → `IMPLEMENTED_UNCOMMITTED`.
- 2026-10-30 — COURIER-SEND-RACE-001 investigated and reproduced locally (Steadfast, Pathao, timeout-then-retry); recorded as `OPEN`, no code changed.
- 2026-10-30 — COURIER-SEND-RACE-001 design investigation recorded (courier API evidence, schema findings, recommended design); still `OPEN`, no code changed.
- 2026-10-30 — COURIER-SEND-RACE-001 approved decisions, design corrections and implementation prerequisites recorded; still `OPEN`, no code changed.
- 2026-10-30 — ARCH-001 (`aec14e0`), PROMO-LOY-002 (`5516714`) and COURIER-REFRESH-CONN-001 (`8315ea3`) committed on local branch `fix/remediation-arch001-promoloy002-courier-refresh`; not pushed.
- 2026-10-30 — COURIER-SEND-RACE-001 implemented and verified locally (MySQL courier 9/9 and loyalty 8/8, SQLite full suite green) → `IMPLEMENTED_UNCOMMITTED`; Pathao invoice verification, Steadfast webhook `consignment_id` behavior and production migration/deploy verification remain open.
- 2026-10-30 — COURIER-SEND-RACE-002 (recovery, concurrency and audit hardening, incl. D1/D3/H1/N1/N4 and the D2/N2 runbook) implemented and verified locally (MySQL courier 12/12, SQLite full suite 1033/1009 passed/0 failed) → `IMPLEMENTED_UNCOMMITTED`; COURIER-SEND-RACE-001 entry linked to it.
