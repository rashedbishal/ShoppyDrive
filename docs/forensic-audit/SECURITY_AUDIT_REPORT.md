# Security Audit Report

Audited commit `f49bed3`, 2026-11-05. Audit only: nothing was fixed, committed or deployed. Full entries: `COMPLETE_FINDINGS_REGISTER.md`.

| ID | Severity | Priority | Title |
|---|---|---|---|
| SEC-001 | CRITICAL | P0 | A forged text message can mark an order as paid |
| SEC-002 | HIGH | P1 | Any admin with one settings page can read every stored secret |
| SEC-003 | HIGH | P1 | A verification code sent to one email can verify a different email |
| SEC-019 | HIGH if the app sees the CDN IP, otherwise INFO | P1 | Whether the app sees real customer IPs behind the CDN is unknown |
| SEC-004 | MEDIUM | P1 | Customers' Google/Facebook tokens are sent to browsers |
| SEC-005 | MEDIUM | P1 | Changing an admin password does not log out other sessions |
| SEC-006 | MEDIUM | P1 | Order tracking returns the whole order record |
| SEC-007 | MEDIUM | P1 | View-only SMS forwarder permission reveals the full token |
| SEC-008 | MEDIUM | P2 | A social link planted on an unverified account can survive the real owner's recovery |
| SEC-010 | MEDIUM | P1 | Weaker password policy and no per-account login limit for admins |
| SEC-017 | MEDIUM | P1 | composer audit lists new Symfony advisories |
| SEC-020 | MEDIUM (HIGH if still current) | P0 | A forwarder token is committed in the repository |
| SEC-021 | MEDIUM | P1 | Back button after logout shows account data |
| SEC-009 | LOW | P2 | Customer email can be changed without the password and without notifying the old address |
| SEC-011 | LOW | P2 | Password reset link is built from the request Host |
| SEC-012 | LOW | P2 | Coupon check reveals coupon details and whether a phone has used it |
| SEC-013 | LOW | P2 | No HSTS or CSP on the live site; API answers over plain HTTP |
| SEC-014 | LOW | P2 | Customer 2FA can be enrolled but is never asked for at login |
| SEC-015 | LOW | P3 | Register, login and forgot-password reveal whether an email exists |
| SEC-018 | LOW | P3 | Some admin uploads have no size cap |
| SEC-022 | LOW | P1 | Internal audit documents and agent config are deployed to the server |
| SEC-023 | LOW | P2 | Personal data can reach logs |
| SEC-024 | LOW | P3 | Token regeneration rewrites .env from a web request |
| SEC-016 | INFO | P3 | Every page publishes all admin route names to guests |

## Checked and clean

- **Injection:** raw SQL is static or bound, sort input is matched, and there is no `$request->all()` into models, no `unserialize` and no `eval` (code reading).
- **XSS:** every `dangerouslySetInnerHTML` and `{!! !!}` traced to a purifier or server-generated source (code reading). Purifier behaviour tested in C16. `Page` still has no write path, so XSS-SINK-GAP-001 stands as accepted.
- **Access control:** the 11 admin routes without a route-level gate are checked in the controller, or are intended for any admin. IDOR attempts failed (C04, C05, C07, C15). Escalation attempts failed (C13, C14).
- **CSRF:** no exclusions. **CORS:** no origins. **Open redirect and SSRF:** none found (code reading).
- **Uploads:** SVG excluded, random names, content-sniffed extensions.
- **Exposure (live GET, 2026-11-05):** `/.env`, `/.git/config`, `/backup.sql`, `/storage/logs/laravel.log` return 403. `/telescope`, `/horizon`, `/_debugbar`, `/_ignition/health-check`, `/_boost/browser-logs`, `/_inertia/devtools/entries`, `/routes.json`, `/composer.json` return 404. No source maps.
- **Cookies (live):** session cookie is secure, httponly and samesite.
- **Secrets scan:** gitleaks over git history flagged only two skill-doc examples, and missed SEC-020, so a clean scanner result is not proof.
- **Dependencies:** production npm audit 0. See SEC-017 for Composer.
- **Static analysis:** Larastan level 5 gave 594 messages, mostly untyped relation noise, with no missing classes. See `evidence/phase2/larastan_level5_summary.txt`.

Accepted risks, not re-raised: ADMIN-2FA-001, DEPLOY-SAFETY-001 C1, M1, 17 moderate npm advisories (dev), XSS-SINK-GAP-001, MED-19, LOW-01, LOW-02, LOW-09.

## Details

### SEC-001: A forged text message can mark an order as paid

Payment is accepted because an SMS body looks like a bKash/Nagad receipt. Unless a sender rule is set on the pattern, any sender is accepted. Example: a buyer texts the shop number "You have received Tk 500.00 ... TrxID FAKE123" from their own phone, enters FAKE123, and the order becomes paid with nothing to collect on delivery.

- **Severity / confidence:** CRITICAL / High for the code. Production pattern values and the forwarder phone filter could not be checked
- **Files:** app/Services/SmsParserService.php:87-88; app/Http/Controllers/SmsTestController.php:71-72; app/Http/Controllers/MobilePaymentController.php:146-184; app/Http/Controllers/LandingPageController.php:404-430; database/seeders/SmsPatternSeeder.php (no sender_regex)
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A01 FAILS: 1 paid order created from a forged message. Tracker row FSA-02 records the allowlist as open.
- **Recommendation (not applied):** Make an empty sender rule fail closed for active patterns, set the real bKash/Nagad sender IDs, and hold SMS-only payments for reconciliation against the provider statement.

### SEC-002: Any admin with one settings page can read every stored secret

All six settings pages send the whole settings table to the browser. Example: a content editor allowed only the About page receives the Pathao password, Steadfast keys, webhook secret and OAuth client secrets in the page data.

- **Severity / confidence:** HIGH / High
- **Files:** app/Http/Controllers/Admin/SettingController.php:71,78,85,92,99,106,319-321; routes/admin.php:184-190; app/Models/Setting.php:45-47
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A07 FAILS: secret values present in the prop.
- **Recommendation (not applied):** Send each page only its own keys. Return secrets as a write-only "is set" flag. Encrypt secret values at rest.

### SEC-003: A verification code sent to one email can verify a different email

Changing the email does not cancel the pending code. Example: an attacker registers with their own email, gets a code, changes the account email to victim@gmail.com, and enters the code. The account now counts as verified for the victim's address. When the victim later uses "Continue with Google", they land in the attacker's account, where the attacker still holds the password and can read whatever the victim enters.

- **Severity / confidence:** HIGH / High for the first step (tested). The Google-login continuation is from code reading
- **Files:** app/Http/Controllers/Settings/ProfileController.php:33-36; app/Http/Controllers/Auth/EmailVerificationCodeController.php:29-38; routes/settings.php:12; app/Http/Controllers/Auth/SocialAuthController.php:113,131,160
- **Evidence:** docs/forensic-audit/tests/AccessControlAuditTest.php S01 FAILS: account verified for an address that never received a code.
- **Recommendation (not applied):** Clear the code on any email change and bind the code to the address it was sent to.

### SEC-019: Whether the app sees real customer IPs behind the CDN is unknown

The live site sits behind Hostinger CDN (server: hcdn) and no trusted proxy is configured. If the app sees the CDN address, all guests share rate-limit buckets and blocking one customer blocks many. If the origin takes the IP from a client header, limits can be bypassed.

- **Severity / confidence:** HIGH if the app sees the CDN IP, otherwise INFO / Could not verify
- **Files:** bootstrap/app.php; app/Http/Middleware/BlockBlockedIp.php:36; app/Models/Order.php:216; app/Support/SecurityRateLimits.php:138
- **Evidence:** docs/forensic-audit/evidence/phase2/live_headers.txt.
- **Recommendation (not applied):** The owner can compare IPs on recent orders in Admin against known customer locations. Then configure the exact trusted proxy range if needed.

### SEC-004: Customers' Google/Facebook tokens are sent to browsers

The social account model has no hidden fields, so live access and refresh tokens appear in the admin customer page data and in the customer's own connections page data. Nothing in the app ever reads them back.

- **Severity / confidence:** MEDIUM / High
- **Files:** app/Models/SocialAccount.php:15-29; app/Http/Controllers/Admin/CustomerController.php:37; app/Http/Controllers/Settings/ConnectionsController.php:24; app/Http/Middleware/HandleInertiaRequests.php:120
- **Evidence:** docs/forensic-audit/tests/AccessControlAuditTest.php S02 and S03 FAIL.
- **Recommendation (not applied):** Stop storing the tokens, or hide and encrypt them.

### SEC-005: Changing an admin password does not log out other sessions

If an admin password is stolen and then changed, the thief's open session keeps working, including with remember-me.

- **Severity / confidence:** MEDIUM / High (code reading, not run)
- **Files:** routes/admin.php:83; app/Http/Controllers/Admin/AdminController.php:86; app/Http/Controllers/Admin/AdminAuthController.php:32
- **Evidence:** Code reading only.
- **Recommendation (not applied):** Compare a password hash per admin session and rotate remember_token on change.

### SEC-006: Order tracking returns the whole order record

Anyone with the order number and phone (both on the parcel label) receives the buyer's IP address, browser details, email, full address, staff notes and internal history notes, which can name staff.

- **Severity / confidence:** MEDIUM / High
- **Files:** app/Http/Controllers/OrderTrackingController.php:33-52; app/Models/Order.php:52; app/Http/Controllers/LandingPageController.php:520-528
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A08 FAILS.
- **Recommendation (not applied):** Use an allow-listed payload like OrderConfirmationAccess::payload and customer-safe history text.

### SEC-007: View-only SMS forwarder permission reveals the full token

An admin who may only view this page receives the full forwarder token, which allows posting SMS to the payment matcher, so it combines with SEC-001.

- **Severity / confidence:** MEDIUM / High
- **Files:** app/Http/Controllers/Admin/SmsForwarderSettingsController.php:25; routes/admin.php:198
- **Evidence:** docs/forensic-audit/tests/AccessControlAuditTest.php S04 FAILS.
- **Recommendation (not applied):** Send the masked token; reveal only on regenerate or with update permission.

### SEC-008: A social link planted on an unverified account can survive the real owner's recovery

An unverified account can be created with a social link. The real owner resets the password and verifies, but the link remains and can still log in.

- **Severity / confidence:** MEDIUM / Low. Depends on whether a provider returns an unconfirmed email
- **Files:** app/Http/Controllers/Auth/SocialAuthController.php:85,122,131,211; app/Actions/Fortify/CreateNewUser.php:84
- **Evidence:** Code reading only. Accepted residual in the tracker covers pre-fix links; this is a post-fix path.
- **Recommendation (not applied):** Delete non-vouching links when an unverified account is first proven by code or reset.

### SEC-010: Weaker password policy and no per-account login limit for admins

Admins may use 8-character passwords, while customers in production need stronger ones. Login limits key on email+IP and IP, so a slow attack from many IPs on one admin is not limited. Failed admin logins are not logged. Self-edit needs no current password.

- **Severity / confidence:** MEDIUM / High
- **Files:** app/Http/Controllers/Admin/AdminController.php:45,76; app/Console/Commands/CreateMotherAdminCommand.php:57; app/Providers/AppServiceProvider.php:135-141; app/Http/Controllers/Admin/AdminAuthController.php:32-34
- **Evidence:** Not run.
- **Recommendation (not applied):** Apply the customer password policy, add an email-only limit, log failures.

### SEC-017: composer audit lists new Symfony advisories

Advisories dated 2026-11-04 affect symfony/http-foundation, http-kernel, routing, serializer and yaml at 7.4.21. Several need components this app may not use, such as access_control rules or trusted proxy shortcuts. nunomaduro/pao is also abandoned.

- **Severity / confidence:** MEDIUM / High for presence. Exploitability in this app not assessed per advisory yet
- **Files:** composer.lock
- **Evidence:** docs/forensic-audit/evidence/phase2/composer_audit_summary.txt. Production npm audit: 0 vulnerabilities.
- **Recommendation (not applied):** Update Symfony patch versions in a separate approved change and run the full suite.

### SEC-020: A forwarder token is committed in the repository

A 64-hex SMS forwarder token (redacted ****b7b0) sits in a tracked local agent settings file. gitleaks did not flag it, which corrects my earlier statement that no secrets were in git history. The repo is private and the file returns 403 on the live site, but it is shipped to the server. If it is the current token, anyone with repo access can post fake payment SMS directly, which is SEC-001 without needing a phone.

- **Severity / confidence:** MEDIUM (HIGH if still current) / High that it exists. Unknown whether it is live
- **Files:** .claude/settings.local.json:82; .github/workflows/deploy.yml:193-203
- **Evidence:** gh repo view: PRIVATE. Live GET /.claude/settings.local.json: 403. Value length 64.
- **Recommendation (not applied):** Regenerate the token, untrack the file, add it to .gitignore.

### SEC-021: Back button after logout shows account data

Inertia keeps page data in browser history. On a shared PC, the next person pressing Back sees the previous customer's orders with no server request.

- **Severity / confidence:** MEDIUM / High. Reproduced in a browser
- **Files:** config/inertia.php; resources/js/components/layout/AccountLayout.tsx:137
- **Evidence:** docs/forensic-audit/evidence/phase2/back_after_logout_browser.txt: after logout and Back, path /account/orders still shows the order number, and history.state holds it.
- **Recommendation (not applied):** Enable history encryption and clear on logout.

### SEC-009: Customer email can be changed without the password and without notifying the old address

A hijacked session can change the email and then reset the password, making the takeover permanent.

- **Severity / confidence:** LOW / High
- **Files:** routes/settings.php:12; app/Http/Controllers/Settings/ProfileController.php:33-36
- **Evidence:** Exercised during S01.
- **Recommendation (not applied):** Require the current password and notify the old address.

### SEC-011: Password reset link is built from the request Host

The app itself would email a reset link pointing at a host chosen by the requester. On the live site, requests with an unknown Host were dropped by the CDN (connection reset), so this is defence in depth unless the origin can be reached directly.

- **Severity / confidence:** LOW / High for the code. Not reachable from outside in my live checks
- **Files:** bootstrap/app.php (no trustHosts); app/Http/Middleware/RedirectToCanonicalDomain.php:25-31
- **Evidence:** docs/forensic-audit/tests/AccessControlAuditTest.php S05 FAILS in the app. Live: two GETs with unknown Host got connection reset.
- **Recommendation (not applied):** Add trustHosts or force the root URL.

### SEC-012: Coupon check reveals coupon details and whether a phone has used it

Anonymous /coupon/validate returns the whole coupon record (usage count, limits, dates) and different messages for disabled, future and expired codes. With a known limited code, guests can also test whether a phone number has used it.

- **Severity / confidence:** LOW / High
- **Files:** routes/commerce.php:70,95-96,111,132-133
- **Evidence:** docs/forensic-audit/tests/BusinessLogicAuditTest.php A09 FAILS.
- **Recommendation (not applied):** Return only code, type, value, eligible_keys and one generic error.

### SEC-013: No HSTS or CSP on the live site; API answers over plain HTTP

Live headers contain no Strict-Transport-Security, Content-Security-Policy or Permissions-Policy. POST http://shoppydrive.com/api/sms/receive answered 401 directly instead of redirecting, so a misconfigured forwarder would send its token unencrypted.

- **Severity / confidence:** LOW / High
- **Files:** app/Http/Middleware/SecurityHeaders.php:24-28; bootstrap/app.php:50
- **Evidence:** docs/forensic-audit/evidence/phase2/live_headers.txt; live POST result recorded in session 1.
- **Recommendation (not applied):** Enable HSTS, refuse plain-HTTP API calls, add a CSP in report-only first.

### SEC-014: Customer 2FA can be enrolled but is never asked for at login

Settings tells customers 2FA is enabled, but login never challenges them, so they have false assurance.

- **Severity / confidence:** LOW / High
- **Files:** app/Providers/FortifyServiceProvider.php:200-220; config/fortify.php:151
- **Evidence:** Code reading.
- **Recommendation (not applied):** Remove enrolment or enforce the challenge.

### SEC-015: Register, login and forgot-password reveal whether an email exists

Messages confirm an email is registered and which provider it uses.

- **Severity / confidence:** LOW / High
- **Files:** app/Actions/Fortify/CreateNewUser.php:84; app/Providers/FortifyServiceProvider.php:83; app/Http/Controllers/Auth/SocialAuthController.php:106
- **Evidence:** Code reading.
- **Recommendation (not applied):** Generic forgot-password response. Provider hints are an owner trade-off.

### SEC-018: Some admin uploads have no size cap

Category, sub-category, bundle and banner images check type but set no maximum size. Limited only by the PHP upload limit, which could not be checked.

- **Severity / confidence:** LOW / High
- **Files:** app/Http/Requests/Admin/CategoryRequest.php:70; SubCategoryRequest.php:31; ProductBundleRequest.php:24; app/Http/Controllers/Admin/PromotionalBannerController.php:45,77
- **Evidence:** Code reading.
- **Recommendation (not applied):** Add max rules.

### SEC-022: Internal audit documents and agent config are deployed to the server

docs/, tests/, .claude and similar folders are not excluded from rsync. They include past audit reports, the tracker, and the SSH host and path in the handoff. Warning: committing docs/forensic-audit would ship this list of unfixed problems on the next deploy.

- **Severity / confidence:** LOW / High that they ship. Live GETs for /docs/ and /CLAUDE.md returned 404
- **Files:** .github/workflows/deploy.yml:193-203; docs/SHAPPYDRIVE_PROJECT_HANDOFF_2026-11-03.md:38
- **Evidence:** Live: /docs/ 404, /docs/SHAPPYDRIVE_AUDIT_REMEDIATION_TRACKER.md 404, /CLAUDE.md 404.
- **Recommendation (not applied):** Exclude them, and update CourierVerificationReadinessService::EXCLUDED_PATHS so fingerprints still match.

### SEC-023: Personal data can reach logs

Webhook payloads, courier error bodies and SMS senders are logged. In the mobile payment path catch(RuntimeException) can also catch database errors, whose message includes SQL bindings such as the address.

- **Severity / confidence:** LOW / Medium
- **Files:** app/Http/Controllers/SteadfastWebhookController.php:32,44; app/Services/PathaoCourierService.php:83-86; app/Services/SteadfastCourierService.php:68-71; app/Http/Controllers/SmsTestController.php:110-115; app/Http/Controllers/MobilePaymentController.php:282,319
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Recommendation (not applied):** Log allow-listed fields and catch domain exceptions only.

### SEC-024: Token regeneration rewrites .env from a web request

Regenerating writes .env without a lock, temp file or audit entry and then caches config. A full disk could truncate .env and take the site down.

- **Severity / confidence:** LOW / High for the code. Failure not tested
- **Files:** app/Http/Controllers/Admin/SmsForwarderSettingsController.php:29-43,74,86
- **Evidence:** Code reading by the module reviewer; spot-checked, not run.
- **Recommendation (not applied):** Store it as a hashed Setting or write atomically and audit.

### SEC-016: Every page publishes all admin route names to guests

The live home page contains 203 admin.* route names. Access is still enforced server-side; this only maps the admin area.

- **Severity / confidence:** INFO / High
- **Files:** resources/views/app.blade.php:52 (no config/ziggy.php)
- **Evidence:** Live home page: 203 admin route names counted.
- **Recommendation (not applied):** Filter admin routes for guests.

