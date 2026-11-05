# MASTER DIRECTIVE: FULL-SCALE E-COMMERCE FORENSIC AUDIT
## Pre-Production Security, Reliability, Performance, Business-Logic & Release-Readiness Assessment
### Laravel backend + React frontend · Market: Bangladesh (BDT) · First production launch

You are performing a FINAL, independent, top-to-bottom forensic audit of this eCommerce application before its first production deployment. Act as a combined enterprise audit team: Principal Architect, Senior Laravel Engineer, Senior React Engineer, Application Security Engineer, Database Architect, Performance Engineer, DevOps Engineer, QA Automation Engineer, Payments & eCommerce Specialist, Reliability/DR Engineer, and Fraud/Abuse Analyst.

Goal: find every reasonably detectable vulnerability, defect, broken workflow, dead end, crash, unhandled exception, missing validation, data inconsistency, business-logic flaw, performance bottleneck, and deployment risk. "It works when I click around" is NOT evidence of production readiness.

IMPORTANT CONTEXT: Most of this codebase was written with AI assistance (Claude CLI). Treat that as a specific risk factor (see Phase 2A).

---

# PART A — RULES OF ENGAGEMENT (NON-NEGOTIABLE)

## A1. Audit only — never fix
- DO NOT modify, refactor, or "quickly fix" any application source code, migrations, schema, dependencies, lock files, .env/config files, or existing tests.
- DO NOT run `composer update`, `npm update`, `npm audit fix`, `php artisan migrate`, `migrate:fresh`, `db:wipe`, `db:seed`, `key:generate`, `storage:link`, `optimize:clear` against the real environment, or any git commit / push / reset / checkout / stash.
- DO NOT deploy anything.
- You MAY create files ONLY inside `docs/forensic-audit/` (reports, evidence, and new audit-only test files or scripts placed under `docs/forensic-audit/tests/`).
- If you find yourself wanting to fix something, record the fix as a recommendation instead.

## A2. Database & data safety (read this twice)
- Before running ANY test suite, inspect `phpunit.xml`, `.env.testing`, `tests/TestCase.php`, and any test using `RefreshDatabase` / `DatabaseMigrations` / `DatabaseTruncation`. If tests would run against the development or any real database, DO NOT run them. These traits WIPE the target database. Report it as a finding and mark tests BLOCKED until an isolated test DB (e.g. SQLite in-memory or a dedicated empty `*_test` database) is confirmed.
- Never run destructive SQL. Read-only queries (`SELECT`, `EXPLAIN`, `SHOW INDEX`) are allowed only on a local/dev database, never production.
- Never print secrets, API keys, passwords, tokens, or real customer PII into reports. Redact as `sk_live_****ab12`.

## A3. External systems
- Never execute real payments. Only sandbox/mocked gateway testing (bKash/Nagad/SSLCommerz/etc. sandbox) if sandbox credentials already exist; otherwise analyze code statically and mark dynamic tests NOT TESTED.
- Never send real SMS, email, or push notifications. Verify mail/SMS drivers are set to `log`/`array`/fake before any dynamic test, or don't run it.
- Never call real courier APIs (Pathao, Steadfast, RedX, Paperfly, etc.) with real data.
- No load testing, DoS, or exploitation against any external system or any public URL. Controlled local tests only.

## A4. Evidence standard — no invented findings
Every finding must cite exact `file:line` references, and where possible a test, command output, or reproducible steps. Classify each as:
1. Confirmed Vulnerability · 2. Confirmed Functional Bug · 3. Confirmed Performance Issue · 4. Potential Risk · 5. Missing Security Control · 6. Untested / Unverified · 7. Informational.
Give each a confidence level (High/Medium/Low). Before finalizing, RE-READ the cited code for every High/Critical finding to eliminate false positives (e.g. check whether middleware is applied at route-group level, whether a policy is auto-discovered, whether a global scope already filters by user). Never mark something PASS that you did not actually verify.

---

# PART B — HOW TO WORK (CLAUDE CODE EXECUTION MODEL)

This audit is too large for one context window. Work in checkpointed sessions.

1. **Progress tracker:** Create and continuously maintain `docs/forensic-audit/AUDIT_PROGRESS.md` containing: phase checklist with status, modules completed, findings count by severity, open questions, blocked items, and "NEXT STEP" (exactly where to resume). Update it after every module. If the session is interrupted or context is compacted, re-read this file first and resume from the last verified checkpoint.
2. **Findings are written as you go** into `docs/forensic-audit/COMPLETE_FINDINGS_REGISTER.md` (append, with stable IDs like `SEC-001`, `BIZ-001`, `DB-001`, `PERF-001`, `FE-001`, `BE-001`, `INFRA-001`, `REL-001`, `OPS-001`). Don't hold findings in memory until the end.
3. **Parallelize read-only exploration** with subagents where useful (e.g. one per module: auth, cart/checkout, payments, admin, frontend). Subagents must follow all rules in Part A and must report findings with file:line evidence. You verify their findings before registering them.
4. **STOP after Phase 1** and present: architecture summary, module inventory, test infrastructure status, test DB safety status, tool availability, environment limitations, and the proposed audit plan. Wait for my approval before Phase 2 onward.
5. **Don't stop early.** After Phase 1 approval, continue phase by phase until the full scope is covered or every remaining gap is explicitly documented as a limitation.
6. If you need a tool (Larastan, PHPStan, etc.) that isn't installed, do NOT add it to the project's composer.json/package.json. Either use it from a separate temporary location outside the project (e.g. `composer global` or a temp directory) or mark the check NOT RUN and say what's needed.

---

# PART C — AUDIT SCOPE

## PHASE 1: System discovery & architecture map
Verify everything against source, not docs. Map: directory structure; Laravel version & architecture; React architecture (Inertia.js vs separate SPA + API, Vite, state management, routing); every route (`php artisan route:list` is read-only and allowed) with its middleware; controllers, models, services, repositories, middleware, policies/gates, form requests, events/listeners, jobs/queues, notifications, mail, console commands, scheduler; migrations, seeders, factories; config files; auth (session/Sanctum/Passport/Breeze/Jetstream/Fortify/Socialite); payment, courier, SMS, email and other third-party integrations; file storage; cache/session/queue drivers; logging; error handling; CI/CD and deployment files.
Produce: module inventory (customer-facing + admin-facing), full endpoint list, business workflow list, external dependency list, ER summary of all tables, background process list, and a coverage matrix that later phases fill in.

## PHASE 2: Security audit (OWASP Top 10, ASVS, API Top 10, Laravel specifics)

### 2A. AI-generated code risk sweep (do this early)
- Placeholder/stub logic: `TODO`, `FIXME`, "for now", "simplified", "in a real app", "mock", fake success returns, hardcoded `return true` in verification code.
- Payment verification that is mocked, skipped, or commented out; sandbox URLs/credentials hardcoded where production would use them.
- Commented-out middleware, `withoutMiddleware`, auth checks removed "temporarily".
- Hardcoded credentials, default admin accounts in seeders (e.g. admin@admin.com / password), test users that would ship to production.
- Hallucinated or wrong-version packages/APIs: methods that don't exist, wrong namespaces, imports of non-existent classes, config keys that are never defined.
- Inconsistent implementations of the same logic in multiple places (e.g. price/total calculated differently in cart, checkout, order, invoice, admin).
- Features half-implemented: routes pointing to missing methods, UI buttons with no backend, backend endpoints with no UI, unused models/migrations.
- Duplicated/contradicting migrations or columns added and never used.

### 2B. Authentication
Registration, login, password hashing & rules, reset flow (token expiry, single-use, enumeration), email/phone verification bypass, account enumeration, brute-force & credential-stuffing throttling, session fixation/regeneration on login, session lifetime, remember-me, logout invalidation (other devices), social login/OAuth state validation, Sanctum/Passport token expiry & scope, admin auth isolation (separate guard? 2FA? can a customer session reach admin?).
**Phone/OTP login (common in BD):** OTP length, expiry, attempt limits, resend throttling, OTP bound to phone+purpose, OTP not leaked in response/logs, SMS pumping/bombing (cost abuse) protection, BD phone format normalization (`01XXXXXXXXX`, `+8801…`, `8801…` treated as same user?).

### 2C. Authorization & access control
IDOR on orders, invoices, addresses, payments, refunds, reviews, wishlist, cart items, downloads; horizontal & vertical privilege escalation; customer → admin routes; role/permission consistency (Spatie etc.); missing policies/ownership checks; route model binding without scoping; frontend-only permission enforcement; admin actions with no authorization beyond "is logged in".

### 2D. Injection & input handling
SQLi (`DB::raw`, `whereRaw`, `orderByRaw`, unvalidated sort/column params), mass assignment (`$guarded = []`, `$request->all()` into create/update, `is_admin`/`role`/`price`/`status` fillable), XSS (stored/reflected/DOM — Blade `{!! !!}`, React `dangerouslySetInnerHTML`, rich-text product descriptions, reviews, admin notes rendered in admin panel), CSRF (excluded routes in `VerifyCsrfToken`/bootstrap config), command injection, SSTI, path traversal, open redirect (`redirect($request->input('url'))`, post-login `intended`), header injection, parameter pollution, type juggling, oversized input, Unicode/Bangla text edge cases, null bytes, ReDoS, unsafe deserialization.

### 2E. API security
Per endpoint: authn, authz, rate limiting, validation, output filtering (API Resources vs returning whole models with hidden fields), pagination caps (`per_page=100000`), enumeration, HTTP method misuse, CORS, error leakage, replay, webhook authentication.

### 2F. Inertia / SPA-specific data exposure
- `HandleInertiaRequests::share()` — is the full user model, permissions, settings, or secrets shared on every page?
- Props passed to pages containing more data than displayed (e.g. full order with other customers' info, cost price, supplier info, internal notes).
- Ziggy/route lists exposing admin routes to the public bundle.
- Secrets in `VITE_*` env vars (anything prefixed `VITE_` ends up in the public JS bundle).
- Production source maps.

### 2G. File uploads
MIME/extension/signature validation, PHP/executable/SVG uploads, image processing (Intervention/GD), size limits, filename sanitization, path traversal, public vs private disk, unauthorized access to uploaded files (e.g. invoices, NID/payment screenshots), storage exhaustion.

### 2H. Sensitive data & secrets
Secrets committed in repo or git history (scan history read-only: `git log -p`, gitleaks/trufflehog if available externally), `.env` committed, `.env.example` containing real values, PII in logs, payment data storage, encryption in transit, cookie flags (Secure, HttpOnly, SameSite), `APP_KEY` handling.

### 2I. Laravel production exposure
APP_DEBUG, APP_ENV, Ignition, Telescope, Horizon, Debugbar, Log Viewer, Pulse, phpinfo, `/storage` listing, `.git` directory, `.env`, backups or `*.sql` in `public/`, default `/admin` without protection, exception pages leaking stack traces, `config/` values that differ between local and production.

### 2J. Security headers & transport
HTTPS enforcement, HSTS, CSP, frame-ancestors/X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, CORS, TrustProxies configuration (important behind Cloudflare/load balancer — affects IP-based rate limiting and HTTPS detection).

### 2K. Dependencies
`composer audit`, `npm audit` (report only — no fix), abandoned packages, PHP/Laravel/Node version support status.

## PHASE 3: eCommerce business logic & transaction integrity (HIGHEST PRIORITY)

**Golden rule to verify everywhere:** the server independently computes every price, discount, tax/VAT, shipping fee, and total from the database. Any client-sent price, total, discount, shipping fee, or payment amount that is trusted = Critical.

### 3A. Products
CRUD, draft/published visibility (can unpublished products be bought via direct URL or API?), variants, SKU uniqueness, regular vs sale price, negative/zero prices, sale price > regular price, sale schedule dates, decimal precision, stock, images, categories, attributes, search/filter/sort/pagination, out-of-stock, deleted/soft-deleted product behavior.

### 3B. Cart
Add/remove/update, variant switching, guest vs logged-in, persistence, merge on login, multiple tabs/devices, price change after add, product deleted/unpublished after add, stock change after add, duplicate lines, quantity 0 / negative / decimal / huge / non-numeric, manipulated product/variant IDs, max-per-order limits, cart recalculation, expiry.

### 3C. Checkout
Guest & registered, address validation (BD division/district/upazila/area if used), shipping method, payment method, coupon, totals, order summary vs final charged amount, double-click/double-submit, duplicate orders, back/refresh, session expiry, stock/price change mid-checkout, network interruption, idempotency keys, atomic DB transaction around order + items + stock + coupon usage.

### 3D. Orders
Full lifecycle (pending → confirmed → processing → shipped → delivered; cancelled, returned, refunded, failed). Invalid transitions, who can change status, cancellation after shipping, duplicate cancellation, order number uniqueness/collision/guessability, historical price snapshot (order items must store price/name at purchase time, not reference live product price), deleted products in old orders, audit trail of status changes, consistency across customer view, admin panel, invoices, and notifications.

### 3E. Inventory
Deduction timing (at order? at payment?), reservation & release, concurrent purchases of last item (`lockForUpdate` / atomic decrement with `where stock >= qty`), overselling, negative stock, double deduction, restoration on cancel/fail/refund/return (and not twice), variant-level stock, manual admin adjustments, unpaid online orders holding stock forever.

### 3F. Payments (check every method actually implemented)
- **Cash on Delivery:** COD limits, fake/bot order abuse, phone verification before COD, COD fee calculation.
- **Online gateways (bKash, Nagad, SSLCommerz, aamarPay, ShurjoPay, Stripe, etc.):** payment initiation; server-side verification via gateway's validation/query API (e.g. SSLCommerz validation API with `val_id`, bKash execute/query payment) — NOT trusting the browser redirect or success URL query params; IPN/webhook authenticity; amount, currency, and order reference match; store-id/merchant matching; duplicate/delayed/out-of-order callbacks; replay; failed/cancelled/abandoned payments; retry; partial payment; refunds (full/partial/duplicate); transaction ID uniqueness; payment ↔ order status sync; reconciliation possibility; payment logs (without secrets).
- Can a user mark an order paid by visiting the success URL directly? Can one transaction ID be reused for two orders?

### 3G. Coupons & promotions
Validity window (timezone: Asia/Dhaka vs UTC), usage limits total & per user, concurrent redemption race, min order, max discount cap, % vs fixed, stacking, discount > subtotal (negative total), first-order abuse via new accounts/phones, coupon applied to excluded products, usage restored on cancel, coupon still applied after cart changes drop below minimum.

### 3H. Shipping & delivery
Zone-based charges (inside/outside Dhaka etc.), free-shipping thresholds, fee manipulation, weight-based logic, invalid addresses, courier API integration (failure, timeout, malformed response, tracking sync, webhook auth), delivery status sync.

### 3I. Refunds, returns, cancellations
Authorization, calculation, partial/duplicate refunds, refund without payment, return window, stock restoration, status sync, audit trail.

### 3J. Fraud & abuse (eCommerce-specific)
Fake COD orders, bot checkout, rate limits on checkout/OTP/coupon/review/contact endpoints, review spam, account farming for first-order coupons, scraping of prices/catalog, inventory hoarding via unpaid orders.

### 3K. Reviews, wishlist, customer accounts, misc
Reviews only by verified buyers (if intended), review XSS, wishlist IDOR, address book IDOR, account deletion, newsletter/contact forms abuse.

## PHASE 4: Database forensics
- **Schema:** types, PK/FK, unique constraints (SKU, order number, transaction ID, coupon code, email/phone), indexes & composite indexes, nullability, defaults, money columns (DECIMAL not FLOAT; consistent precision), timestamps & timezone, soft deletes, cascade rules (does deleting a product/user cascade-delete orders?), normalization/redundancy.
- **Integrity:** orphans, duplicates, invalid statuses (enum vs free string), totals that don't equal sum of items, stock that can go negative. Query local/dev data read-only if available.
- **Performance:** missing indexes on FK/filter/sort columns, N+1 (look for relationships in loops, Resources, Inertia props), full scans, `SELECT *`, offset pagination on large tables, expensive aggregations on dashboards.
- **Transactions:** boundaries, rollback correctness, external API calls inside DB transactions, locks, deadlock handling, isolation, idempotency.
- **Migrations & backups:** order, duplicates, `down()` safety, production-compatibility, large-table risks, MySQL vs MariaDB vs SQLite differences (tests on SQLite may hide MySQL-specific bugs), backup/restore strategy, RPO/RTO. Do not run migrations.

## PHASE 5: Laravel backend forensics
Routes (duplicates, unused, wrong names, missing middleware, closures preventing route cache), controllers (fat controllers, missing validation), Form Requests (`authorize()` returning true blindly), models (`$fillable`, `$hidden`, `$casts`, accessors leaking data), relationships, scopes, policies, services, events/listeners, jobs (serialized models, `ShouldBeUnique`, `tries`, `timeout`, `failed()`), notifications, mail, commands, scheduler, exception handler (swallowed exceptions, empty catch blocks, generic catch returning success), logging, cache keys/invalidation, HTTP client usage (timeouts set? retries?), type errors, null-access risks, dead/unreachable code, deprecated APIs, `env()` called outside config files (breaks under `config:cache`), static analysis results.

## PHASE 6: React frontend forensics
- **Stability:** runtime/console errors, null access, missing keys, bad props, infinite renders, effect dependency bugs, stale closures, unhandled promises, error boundaries, lazy/Suspense.
- **Security:** XSS sinks, tokens in localStorage, secrets/internal URLs in bundle, unsafe redirects, client-side-only validation.
- **UI/UX integrity — every page and state:** home, listing, PDP, search, filters, cart, checkout, auth pages, password recovery, dashboard, profile, addresses, orders, order detail, payment success/fail/cancel pages, wishlist, all admin CRUD, settings, 404/500/419/403/503 pages, empty states, loading states, offline/network-failure states. Check dead buttons, dead links, dead ends (pages with no way forward/back), missing redirects, broken forms, missing success/error messages, double-click issues, modals/dropdowns, responsive & mobile layout (most BD traffic is mobile), accessibility.
- **Frontend-backend consistency:** field names, status codes (419 CSRF expiry, 422 validation, 429 throttle, 500), currency formatting (৳ / BDT, decimals), Bangla/English text, date/time & timezone, pagination, auth state sync.

## PHASE 7: Performance & scalability
Backend: slow endpoints, queries, N+1, payload sizes, sync work that should be queued (emails, SMS, image processing, PDF invoices). Frontend: production build size, code splitting, unused JS/CSS, images (size, format, lazy loading), fonts, third-party scripts (analytics, FB pixel, chat widgets), Core Web Vitals (LCP, CLS, INP) — measure locally only if possible and state the environment. Infrastructure: OPcache, PHP-FPM, web server, Redis, queue workers, CDN, compression, caching headers, log growth, disk. Scalability: estimate behavior as products/orders/users grow; identify bottlenecks. No uncontrolled load tests.

## PHASE 8: Reliability, failure handling & edge cases
What happens when: DB down, Redis down, queue worker stopped, mail/SMS provider down, payment gateway down or slow, courier API down, timeouts, malformed external responses, disk full, invalid JSON, expired session mid-action, CSRF token expired (419) on checkout, duplicate submissions, browser closed during payment, server restart mid-request, job failure/retry/duplicate execution, partial transaction failure. Identify paths that can create orphaned orders, lost payments, double charges, wrong inventory, or orders stuck in pending forever (and whether any cleanup/reconciliation job exists).

## PHASE 9: Jobs, notifications & integrations
Queue config, retries, backoff, timeouts, failed jobs table & monitoring, idempotency, scheduler overlap (`withoutOverlapping`, `onOneServer`), email templates & correctness, SMS gateway integration & cost-abuse protection, admin notifications for new orders, duplicate notification prevention, all webhook handlers, integration credentials handling.

## PHASE 10: SEO, accessibility, customer experience, legal pages
- **SEO:** titles, meta descriptions, canonical, robots.txt (not blocking production / not left as "Disallow: /"), sitemap, Product & Breadcrumb structured data, Open Graph (Facebook sharing matters in BD), duplicate URLs, slugs, 404 handling, redirect chains. Note Inertia/SPA SEO limitations (is SSR enabled?).
- **Accessibility:** semantics, labels, alt text, contrast, keyboard, focus, modals, error messages.
- **CX:** empty/loading/error states, payment failure recovery path, order confirmation clarity, mobile experience.
- **Legal/trust pages exist and are linked:** privacy policy, terms, return/refund policy, shipping policy, contact info. Note (informational, not legal advice) alignment considerations with Bangladesh's Digital Commerce Operation Guidelines (e.g. clear pricing, delivery timelines, refund handling) and flag for the owner to verify with a professional.

## PHASE 11: Testing & QA execution
Only after A2 test-DB safety is confirmed:
- Run existing PHPUnit/Pest tests; report pass/fail and coverage gaps (which critical flows have zero tests).
- Run frontend tests/lint/type-check if configured; run a production build (`npm run build`) into a non-deployed location and report warnings/errors. If building would overwrite committed build artifacts (e.g. `public/build` tracked in git), skip and report.
- Static analysis: Larastan/PHPStan, ESLint, TypeScript `tsc --noEmit`, `composer audit`, `npm audit`, secret scanning — per rule B6.
- Write NEW audit-only tests under `docs/forensic-audit/tests/` for critical scenarios (price tampering, IDOR, coupon race, stock race, payment callback spoofing, double submit). Run them only against the isolated test DB. Report actual results.
- Check `php artisan config:cache` / `route:cache` / `view:cache` compatibility by static inspection (closures in routes, `env()` outside config). Do not leave caches altered on the real environment.

## PHASE 12: CI/CD, deployment & production readiness
Git hygiene (uncommitted changes, `.gitignore` covering `.env`, `vendor`, `node_modules`, storage, logs), CI pipeline, tests in CI, build pipeline, deployment scripts, rollback plan, environment separation, production `.env` requirements (list every required variable and whether it has a safe production value — without printing values), migration strategy, maintenance mode, queue worker supervision (Supervisor/systemd) and restart on deploy, cron for scheduler, storage symlink, file permissions, cache commands, asset deployment, web server config (document root must be `public/`), PHP version & extensions, HTTPS & domain, pre-deploy backup, post-deploy health checks, monitoring, alerting, error tracking (Sentry/Flare/etc.), uptime monitoring. Produce a step-by-step deployment checklist.

## PHASE 13: Business continuity & operations
Backups (frequency, off-site, encrypted, tested restore), DR procedure, monitoring coverage, admin activity logs, order & payment audit trails, payment/inventory reconciliation process, log retention & rotation, customer data deletion, privacy, admin account management (shared accounts? strong passwords? 2FA?), incident response, operational documentation for the owner.

## PHASE 14: Test scenario matrix
For each critical feature: normal, invalid input, missing input, unauthorized, malicious, boundary, duplicate, concurrent, network failure, DB failure, external service failure, recovery. Each row: Test ID, module, scenario, preconditions, steps, expected, actual, PASS/FAIL/BLOCKED/NOT TESTED, evidence, related finding ID. Never mark PASS without execution.

---

# PART D — FINDING FORMAT & SEVERITY

Every finding: ID, title, module, category (A4 list), severity, confidence, description, technical root cause, exact `file:line` references, reproduction steps, evidence, business impact, security impact, likelihood, recommended remediation (described, not applied), verification procedure, deployment implication, remediation priority (P0–P3).

- **CRITICAL:** direct threat to payment integrity, money, customer data, system compromise, or core checkout/order flow.
- **HIGH:** serious vulnerability, financial manipulation, unauthorized access, data corruption, major functional failure.
- **MEDIUM:** meaningful weakness, reliability issue, notable bottleneck, workflow defect.
- **LOW:** limited impact, minor UX, maintainability.
- **INFORMATIONAL:** observations and improvements.

Use CVSS 4.0 vectors only for security vulnerabilities; do not assign CVSS to functional/performance issues.

---

# PART E — DELIVERABLES (all in `docs/forensic-audit/`)

1. `AUDIT_PROGRESS.md` — live tracker (Part B).
2. `FULL_SYSTEM_FORENSIC_AUDIT_REPORT.md` — executive summary (plain language a non-technical business owner can understand), architecture, scope, methodology, environment, tools, all findings by area, risk analysis, limitations, final assessment.
3. `SECURITY_AUDIT_REPORT.md`
4. `BUSINESS_LOGIC_AND_PAYMENTS_REPORT.md`
5. `DATABASE_FORENSIC_REPORT.md`
6. `PERFORMANCE_AUDIT_REPORT.md`
7. `FUNCTIONAL_QA_REPORT.md` (includes the Phase 14 scenario matrix)
8. `FRONTEND_UX_AUDIT_REPORT.md`
9. `INFRASTRUCTURE_DEPLOYMENT_AUDIT.md` (includes deployment checklist and required production `.env` variable list without values)
10. `COMPLETE_FINDINGS_REGISTER.md` — every finding, one table + detail sections.
11. `PRODUCTION_READINESS_CHECKLIST.md` — each item PASS / FAIL / BLOCKED / NOT TESTED / N/A.
12. `REMEDIATION_ROADMAP.md` — P0 (immediate), P1 (must fix before launch), P2 (before/shortly after launch), P3 (post-launch). Include dependencies between fixes, recommended order, tests needed to verify each fix, and regression risk. Group fixes so they can be handed back to Claude CLI in small, safe batches.
13. `AUDIT_EVIDENCE_INDEX.md` — index of command outputs, test results, code references, logs.
14. `tests/` — audit-only test files written during the audit.

---

# PART F — FINAL RELEASE DECISION

Choose exactly one: **APPROVED FOR PRODUCTION** / **APPROVED WITH CONDITIONS** / **NOT APPROVED FOR PRODUCTION**.

Rules:
- Any unresolved Critical → NOT APPROVED.
- Any unresolved High affecting security, payments, customer data, or core transactions → cannot be unconditionally approved.
- Critical areas left unverified (e.g. payment verification never tested even in sandbox) must be listed as conditions, never silently treated as passing.
- "No obvious issues found" is never grounds for approval by itself.

List every condition that must be met before release, and state honestly what this audit could NOT cover and why.

---

# START NOW

1. Read this directive fully.
2. Create `docs/forensic-audit/` and `AUDIT_PROGRESS.md`.
3. Execute Phase 1 (read-only discovery), including the A2 test-database safety check.
4. Report: architecture, module inventory, testing infrastructure, test-DB safety status, available/missing tools, environment limitations, and your proposed execution plan with session breakdown.
5. STOP and wait for my approval.

DO NOT FIX ANYTHING. The only deliverable is an evidence-backed forensic audit and release recommendation.
