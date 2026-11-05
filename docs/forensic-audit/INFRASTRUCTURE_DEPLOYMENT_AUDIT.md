# Infrastructure and Deployment Audit

Audited commit `f49bed3`, 2026-11-05. Audit only: nothing was fixed, committed or deployed. Full entries: `COMPLETE_FINDINGS_REGISTER.md`.

| ID | Severity | Priority | Title |
|---|---|---|---|
| OPS-008 | HIGH until verified | P0 | Backups, cron and server settings could not be verified |
| INFRA-001 | MEDIUM | P1 | Deploy has no maintenance mode and is not atomic |
| INFRA-002 | MEDIUM | P1 | A broken release may not be fixable by pushing, and there is no rollback |
| INFRA-003 | MEDIUM | P1 | Migrations are never run on MariaDB before production |
| OPS-002 | MEDIUM | P1 | If cron or the queue stops, nobody is told and customer mail stops |
| OPS-003 | MEDIUM | P1 | A short mail outage fails queued mail, and recovery is one click per row |
| OPS-004 | MEDIUM | P2 | Newsletter has no send-rate limit and shares the order mail account and queue |
| OPS-005 | MEDIUM | P1 | A dead forwarder phone disables mobile payment with a misleading message |
| OPS-006 | MEDIUM | P1 | Abandoned-cart mail has no opt-out or cap and reaches deleted accounts |
| INFRA-004 | LOW | P2 | Workflow hardening gaps |
| INFRA-005 | LOW | P2 | Deploy env check is thin and .env.example is incomplete |
| INFRA-006 | LOW | P2 | rsync --delete removes server-created files |
| OPS-001 | LOW | P2 | sitemap.xml is missing on the live site |
| OPS-007 | LOW | P2 | Logging defaults to one unrotated file at debug level |
| INFRA-007 | INFO | P3 | Leftover packages and public assets |

## Checked and clean (code reading)

- `APP_DEBUG` and `APP_ENV` default safely, and deploy enforces them.
- Dev packages use `require-dev` with `--no-dev` in deploy. No `env()` outside config.
- `.env`, `.git`, uploads, sessions and logs are excluded from rsync. Deploy `needs: checks`.
- Queue timeout 45s is below `retry_after` 90s. Order mail is queued `afterCommit`. Failed jobs are visible, allow-listed and never unserialized.
- Single points of failure to note: the forwarder phone, the one mother admin (recovery only by SSH, a recorded decision), one SMTP account, one shared host.

## Required production environment variables (names only)

**Must set:** `APP_NAME APP_ENV APP_KEY APP_DEBUG APP_URL DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD QUEUE_CONNECTION CACHE_STORE SESSION_DRIVER MAIL_MAILER MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD MAIL_SCHEME MAIL_FROM_ADDRESS MAIL_FROM_NAME SMS_FORWARDER_TOKEN LOG_CHANNEL LOG_STACK LOG_LEVEL`

**Feature-dependent:** `GOOGLE_CLIENT_ID GOOGLE_CLIENT_SECRET GOOGLE_REDIRECT_URI FACEBOOK_CLIENT_ID FACEBOOK_CLIENT_SECRET FACEBOOK_REDIRECT_URI ZINIPAY_API_KEY ZINIPAY_BASE_URL`

**Safe defaults, review:** `APP_PREVIOUS_KEYS SESSION_LIFETIME SESSION_SECURE_COOKIE SESSION_ENCRYPT SESSION_SAME_SITE SESSION_DOMAIN CORS_ALLOWED_ORIGINS FILESYSTEM_DISK CACHE_LIMITER_STORE CACHE_PREFIX DB_QUEUE_RETRY_AFTER LOG_DAILY_DAYS PATHAO_TIMEOUT PATHAO_PRODUCTION_BASE_URL PATHAO_SANDBOX_BASE_URL STEADFAST_BASE_URL STEADFAST_TIMEOUT ZINIPAY_TIMEOUT MAIL_EHLO_DOMAIN ACTIVITY_LOGGER_ENABLED`

Pathao and Steadfast credentials and the webhook secret live in the `settings` table, not `.env`. Keep `DB_QUEUE_RETRY_AFTER` above the 45-second drain timeout.

## Deployment checklist

**Before a push**
1. Run `composer ci:check`.
2. Run migrations on a disposable MariaDB database.
3. Check that `docs/forensic-audit` and other internal files will not ship (SEC-022).
4. Confirm whether reviewers are configured on the `production` environment.
5. Confirm a recent database and uploads backup (OPS-008).
6. Choose a low-traffic window for schema changes.

**During the deploy**
7. Watch the pre-check, rsync and migrate steps.
8. Do not rely on `artisan down` surviving a deploy (INFRA-006).

**After the deploy**
9. Check `/up`, the home page, a product image and the checkout page.
10. Confirm `public/storage` resolves and config, route and event caches exist.
11. Confirm `schedule:list` shows every task, the `jobs` table is draining and `failed_jobs` is unchanged.
12. Send one test mail, confirm the forwarder delivered a recent SMS and a Pathao poll ran.
13. Re-attest courier readiness, because a deploy invalidates it.
14. If it fails, recovery is manual over SSH, because no rollback exists (INFRA-002).

## Details

### OPS-008: Backups, cron and server settings could not be verified

The repo has no backup code and BackupFailedMail is a stub. Pre-deploy backup was accepted as a risk (C1), and is not re-raised, but docs/MASTER_FIXING_TRACKER.md records that acceptance as conditional on production holding no important business data, and says to revisit once real data accumulates. The site is now live with orders. Also unverified: restore test, cron entry, opcache, mail caps, disk and inode quota.

- **Severity / confidence:** HIGH until verified / Could not verify. Owner declined to share production settings
- **Files:** docs/MASTER_FIXING_TRACKER.md; app/Mail/BackupFailedMail.php
- **Evidence:** None available.
- **Recommendation (not applied):** Owner confirms a recent off-site backup and one restore test.

### INFRA-001: Deploy has no maintenance mode and is not atomic

rsync writes in place, then migrate runs, so new code runs before its schema. public/storage is removed by --delete until recreated, so images break briefly. Old chunks are deleted and there is no vite:preloadError handler.

- **Severity / confidence:** MEDIUM / High
- **Files:** .github/workflows/deploy.yml:168,191-204,236-237,257
- **Evidence:** grep "artisan down": none. Not exercised.
- **Recommendation (not applied):** Use artisan down or a release symlink, and expand-first migrations.

### INFRA-002: A broken release may not be fixable by pushing, and there is no rollback

The pre-deploy check boots the old code. If it crashes on boot, the check exits before rsync, so the fix never lands. There is no workflow_dispatch and no general runbook.

- **Severity / confidence:** MEDIUM / High
- **Files:** .github/workflows/deploy.yml:3-6,115,172-178,242-250,264-265
- **Evidence:** Code reading. Not exercised.
- **Recommendation (not applied):** Clear stale caches first or allow a bypass, and write a runbook.

### INFRA-003: Migrations are never run on MariaDB before production

CI uses SQLite only. The first real MariaDB run of each migration is production with --force, and MariaDB DDL does not roll back.

- **Severity / confidence:** MEDIUM / High
- **Files:** .github/workflows/deploy.yml:34,69-70,257; .github/workflows/tests.yml:45-48
- **Evidence:** In this audit, MariaDB 11.8.3 ran the suite subset and race files without schema errors. A16 only failed on MariaDB.
- **Recommendation (not applied):** Add a gated MariaDB CI job.

### OPS-002: If cron or the queue stops, nobody is told and customer mail stops

A stopped queue creates no failed rows, so the dashboard stays at 0. Order mail and Pathao updates stop while the site looks healthy.

- **Severity / confidence:** MEDIUM / High
- **Files:** routes/console.php:19-47; app/Http/Controllers/Admin/DashboardController.php:110-112; app/Console/Commands/DrainQueueCommand.php:81-86,118
- **Evidence:** Code reading. Cron state BLOCKED.
- **Recommendation (not applied):** Show last-drain time and oldest job age; add a dead-man ping.

### OPS-003: A short mail outage fails queued mail, and recovery is one click per row

Three tries with 30s backoff are used up in a few minutes (estimate), and staff must retry each mail. mail.timeout is null, so a hung SMTP uses up the job time.

- **Severity / confidence:** MEDIUM / High for the code. Not exercised
- **Files:** app/Console/Commands/DrainQueueCommand.php:42-43,163-164; config/mail.php:48; app/Http/Controllers/Admin/FailedJobController.php:50-62
- **Evidence:** Code reading.
- **Recommendation (not applied):** Longer backoff, a 15s SMTP timeout, retry-all.

### OPS-004: Newsletter has no send-rate limit and shares the order mail account and queue

A large send can use up the host mail cap, then order mail is rejected, and order mail waits behind chunks. No List-Unsubscribe header.

- **Severity / confidence:** MEDIUM / Medium. Host limits unverified
- **Files:** app/Jobs/SendNewsletterChunk.php:64-66; app/Http/Controllers/Admin/NewsletterController.php:30,136-150; app/Console/Commands/DrainQueueCommand.php:49,61; app/Mail/NewsletterCustomMail.php:24-27
- **Evidence:** Code reading.
- **Recommendation (not applied):** Throttle, separate queue, add the header.

### OPS-005: A dead forwarder phone disables mobile payment with a misleading message

If the phone is off, customers who paid are told their ID was not found, and no one is alerted. The sidebar badge stays at 0 because nothing arrives. Single point of failure.

- **Severity / confidence:** MEDIUM / High
- **Files:** app/Http/Controllers/MobilePaymentController.php:133,161,298-300,339-344; app/Http/Controllers/Admin/ReceivedSmsController.php:46-48
- **Evidence:** Code reading. Phone behaviour unverified.
- **Recommendation (not applied):** Heartbeat, alert, and a "not received yet" message.

### OPS-006: Abandoned-cart mail has no opt-out or cap and reaches deleted accounts

Three checkout sessions make three mails. After a cron outage every old row is mailed at once. A customer who deletes their account is still mailed. There is no unsubscribe link.

- **Severity / confidence:** MEDIUM / High (code reading)
- **Files:** app/Console/Commands/RemindAbandonedCarts.php:19-23; app/Jobs/AbandonedCartReminderJob.php:59; resources/views/emails/cart/abandoned.blade.php:1-25; app/Models/User.php:188; resources/js/pages/checkout/Checkout.tsx:517-525
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Recommendation (not applied):** Opt-out, cooldown, 72h ceiling, skip anonymized.

### INFRA-004: Workflow hardening gaps

No permissions key, third-party actions pinned by tag including the one holding the deploy key, and secrets interpolated into shell text.

- **Severity / confidence:** LOW / High
- **Files:** .github/workflows/deploy.yml:31,130,164,176-178,204,213
- **Evidence:** Code reading. Mitigations: ignore-scripts, SSH agent loaded late.
- **Recommendation (not applied):** Pin SHAs, set permissions, use env.

### INFRA-005: Deploy env check is thin and .env.example is incomplete

Only 5 keys are checked. A missing MAIL_MAILER defaults to log, so verification codes go to a log with a green deploy. .env.example lacks SMS_FORWARDER_TOKEN, GOOGLE, FACEBOOK and ZINIPAY names.

- **Severity / confidence:** LOW / High
- **Files:** .github/workflows/deploy.yml:224; config/mail.php:17; config/app.php:55
- **Evidence:** Code reading.
- **Recommendation (not applied):** Extend the check.

### INFRA-006: rsync --delete removes server-created files

public/sitemap.xml and maintenance mode files are not excluded, so a deploy lifts maintenance mode mid-incident and removes a generated sitemap. This is a second cause for OPS-001.

- **Severity / confidence:** LOW / High
- **Files:** .github/workflows/deploy.yml:192-203; app/Console/Commands/GenerateSitemapCommand.php:38
- **Evidence:** Code reading.
- **Recommendation (not applied):** Add excludes or generate post-deploy.

### OPS-001: sitemap.xml is missing on the live site

GET /sitemap.xml returns 404, and sitemap:generate is not scheduled. robots.txt has no sitemap line.

- **Severity / confidence:** LOW / High
- **Files:** app/Console/Commands/GenerateSitemapCommand.php; routes/console.php
- **Evidence:** docs/forensic-audit/evidence/phase2/live_paths.txt.
- **Recommendation (not applied):** Schedule it and reference it in robots.txt.

### OPS-007: Logging defaults to one unrotated file at debug level

If production kept the defaults, the log grows forever on a fixed quota, threatening sessions and uploads.

- **Severity / confidence:** LOW / Could not verify. Production setting BLOCKED
- **Files:** config/logging.php:21,57,61-66; .env.example; .github/workflows/deploy.yml:202
- **Evidence:** Code reading.
- **Recommendation (not applied):** daily and warning.

### INFRA-007: Leftover packages and public assets

spatie/laravel-responsecache ^0.0.1 is an empty skeleton. public/vendor/moonshine has assets for a removed package. laravel/tinker is in require.

- **Severity / confidence:** INFO / High
- **Files:** composer.json; public/vendor/moonshine; routes.json
- **Evidence:** Live /vendor/moonshine/ returns 403 as a directory.
- **Recommendation (not applied):** Remove them.

