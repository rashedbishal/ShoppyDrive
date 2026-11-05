# Audit Evidence Index

Audited commit `f49bed3`, 2026-11-05. Audit only: nothing was fixed, committed or deployed. Full entries: `COMPLETE_FINDINGS_REGISTER.md`.

| Path | What it shows |
|---|---|
| `evidence/phase1/ARCHITECTURE_AND_INVENTORY.md` | Stack, modules, workflows, coverage |
| `evidence/phase1/ENDPOINTS.md` | 318 routes |
| `evidence/phase1/ER_SUMMARY.md` | 76 tables, keys, indexes |
| `evidence/phase2/composer_audit.txt`, `composer_audit_summary.txt` | Composer advisories |
| `evidence/phase2/npm_audit_prod.txt`, `npm_audit_all.txt` | npm advisories |
| `evidence/phase2/gitleaks_history_summary.txt` | Git history scan, which missed SEC-020 |
| `evidence/phase2/larastan_level5_summary.txt` | Static analysis |
| `evidence/phase2/live_paths.txt`, `live_headers.txt`, `live_timing.txt` | Read-only live checks |
| `evidence/phase2/guest_coupon_browser.txt` | BIZ-003 display |
| `evidence/phase2/cart_loss_on_500_browser.txt` | FE-005 |
| `evidence/phase2/back_after_logout_browser.txt` | SEC-021 |
| `evidence/phase2/audit_tests_sqlite.txt`, `audit_tests_perf.txt`, `audit_tests_mariadb_subset.txt` | Earlier test runs, including A16 on MariaDB |
| `evidence/phase4/schema_checks.txt` | Read-only dev schema queries |
| `evidence/phase4/explain_mariadb.txt` | Query plans |
| `evidence/phase11/mariadb_race_run.txt` | Race tests as shipped; four files skipped |
| `evidence/phase11/mariadb_race_copies_run.txt` | Race copies with only the port changed |
| `evidence/phase11/attack_controls_sqlite.txt` | Attack controls C01 to C17 |
| `evidence/phase11/audit_suite_final_sqlite.txt` | Final audit suite run |
| `tests/*AuditTest.php`, `tests/Pest.php`, `tests/phpunit.audit.xml` | Audit tests and config |
| `tests/race/*CopyTest.php` | Copies of four project race tests, port changed only |

Secrets are redacted throughout. Browser scripts and tool downloads were kept in the session scratch folder outside the project, and are not preserved.

Tools: Larastan 3.14 with PHPStan 2.3, gitleaks 8.28, MariaDB 11.8.3 (READ-COMMITTED, `innodb_snapshot_isolation` ON), headless Chrome. All outside the project; `composer.json` and `package.json` unchanged. Production is MariaDB 11.8.9 per the handoff, so a small version difference remains.
