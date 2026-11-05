# Forensic Audit Progress

**Directive:** `docs/FORENSIC_AUDIT_DIRECTIVE.md`. **Mode:** audit only, no fixes.
**Audited commit:** `f49bed3` on `main` (level with `origin/main`). **Started:** 2026-11-05.

## Status

| Phase | Scope | Status |
|---|---|---|
| 1 | Discovery and architecture map | DONE, approved 2026-11-05 |
| 2 | Security (2A-2K) | DONE, with limits listed in the main report |
| 3 | Business logic and transactions | DONE, with limits listed in the main report |
| 4 | Database | DONE, with limits listed in the main report |
| 5 | Laravel backend | DONE, with limits listed in the main report |
| 6 | React frontend | DONE, with limits listed in the main report |
| 7 | Performance | DONE, with limits listed in the main report |
| 8 | Reliability and failure handling | DONE, with limits listed in the main report |
| 9 | Jobs, notifications, integrations | DONE, with limits listed in the main report |
| 10 | SEO, accessibility, CX, legal | DONE, with limits listed in the main report |
| 11 | Test execution | DONE, with limits listed in the main report |
| 12 | CI/CD and production readiness | DONE, with limits listed in the main report |
| 13 | Business continuity | DONE, with limits listed in the main report |
| 14 | Scenario matrix | DONE, with limits listed in the main report |

## Findings by severity
None registered. Phase 1 is discovery only; leads to verify are listed below.

## Test database safety (A2): SAFE for the default suite
- `phpunit.xml` forces SQLite `:memory:`, with `DB_URL` empty, mail `array`, queue `sync`, session `array`.
- `tests/TestCase.php::setUpTraits()` calls `Tests\SafeTestDatabase::assertSafe()` before any database trait. It allows only SQLite, or MySQL/MariaDB named `shoppydrive_test_*`.
- No `.env.testing` exists. 102 test files use `RefreshDatabase`. `DatabaseMigrations` and `DatabaseTruncation` appear only in comments in the two guard files.
- Caveat: the guard protects databases, not file storage or HTTP. See the leads below.

## Leads to verify (not findings)
1. Tests appear to write real files to `storage/app/public/products` (14 per full run). Phase 11.
2. `routes.json` is tracked at the repo root. Phase 2F/5.
3. Local `.env` uses `MAIL_MAILER=smtp`. Any dynamic test outside phpunit must override mail (A3).
4. Unthrottled mutating routes outside admin. Phase 2E.
5. Dev-only routes (`_boost`, `_inertia/devtools`, `.well-known`): confirm absent in production. Phase 2I.
6. Earlier reports' FSA and CRIT/HIGH/MED/LOW findings: re-verify, don't assume fixed. Phase 2-3.
7. Payments are manual bKash/Nagad SMS matching plus legacy ZiniPay, not a gateway API. Phase 3F.
8. MariaDB (production) differs from MySQL 8 (local). Phase 4/11.

## Owner decisions (2026-11-05)
Plan approved. Subagents approved. Tools may be downloaded outside the project. Disposable local MariaDB approved. Read-only requests to the live site approved (no attacks or load). Production settings will NOT be supplied, so backups, cron, server config and production `.env` items are BLOCKED.

## Blocked / unavailable
- PHPStan/Larastan, gitleaks, trufflehog, semgrep, Lighthouse: not installed.
- Disposable MariaDB from 2026-11-03: gone. MySQL 8 is available.
- No production access; no payment or courier sandbox credentials known.

## NEXT STEP
Audit complete. Final decision: NOT APPROVED FOR PRODUCTION. Waiting on the owner.
## Checkpoint 2026-11-05 (session 1)
- Register holds session 1 findings from Phase 2A, 2B-2G, 3A-3K. Audit tests: docs/forensic-audit/tests (26 run on SQLite, 25 fail and confirm defects, A16 pending on MariaDB).
- Run with: vendor/bin/pest --configuration=docs/forensic-audit/tests/phpunit.audit.xml --test-directory=docs/forensic-audit/tests --compact
- Disposable MariaDB 11.8.3 lives in the session scratchpad on port 3399 (READ-COMMITTED, snapshot isolation on), databases shoppydrive_test_audit and shoppydrive_test_audit_race.
- Known gap: guest coupon charge (BIZ-003) proven by browser for display and by code for the charge; browser submit not completed.

## Checkpoint 2026-11-05 (module reviews 4-10, 12, 13, 2H, 2I)
- Findings registered from Phase 4, 5, 6, 7, 8, 9, 10, 12, 13, 2H, 2I. See register summary for counts.
- Remaining: Phase 11 (new attack tests, production build into a scratch folder, cache-compat check), Phase 14 matrix, browser checks (FE-005 under production error handling, SEC-021, BIZ-029, BIZ-013), EXPLAIN on MariaDB (PERF-005, PERF-007), re-read every High/Critical, then all Part E reports and the Part F decision.
