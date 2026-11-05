# Full System Forensic Audit Report

Audited commit `f49bed3`, 2026-11-05. Audit only: nothing was fixed, committed or deployed. Full entries: `COMPLETE_FINDINGS_REGISTER.md`.

## Decision: NOT APPROVED FOR PRODUCTION

The directive requires this result while any Critical is unresolved, and SEC-001 is unresolved. **The site is already live**, so this is not a launch gate. These problems are exposed to customers today, and the order below is about reducing live risk.

## Summary in plain language

ShoppyDrive's foundations are better than this list may suggest. The server decides prices, customers cannot reach each other's orders, and concurrency tests pass on the same database settings as production. The serious problems are elsewhere:

1. **Payments can be faked.** A text message that merely looks like a bKash receipt can mark an order as paid (SEC-001).
2. **What the customer sees is not always what they pay.** Price changes (BIZ-001), Cart page coupons (BIZ-003) and landing pages (BIZ-016) can all show one amount and charge another. "Total Paid" appears on unpaid COD orders (BIZ-030).
3. **Some sales cannot complete, or money gets stuck.** Bundles can never check out (BIZ-002), paid orders can be cancelled and then not refunded (BIZ-005), and customers can pay for out-of-stock goods and get no order (BIZ-006).
4. **Stock can drift.** Converting an incomplete order skips stock, and cancelling it adds stock (BIZ-004).
5. **Staff can see too much.** Any admin with one settings page receives all courier and login secrets (SEC-002).
6. **Nothing warns you when things stop.** Cron, the queue and the forwarder phone can all stop silently (OPS-002, OPS-005), and backups could not be confirmed (OPS-008).

## Counts

120 findings: 1 Critical, 10 High, 43 Medium, 60 Low, 6 Info. Two of the Highs (SEC-019, OPS-008) are High until verified, because they could not be checked.

## Critical and High

| ID | Severity | Priority | Title |
|---|---|---|---|
| SEC-001 | CRITICAL | P0 | A forged text message can mark an order as paid |
| BIZ-001 | HIGH | P0 | COD can charge more than the price the customer saw |
| BIZ-002 | HIGH | P0 | A cart containing a complete bundle can never be checked out from the website |
| BIZ-003 | HIGH | P0 | Guests see a coupon discount at checkout but are charged full price |
| BIZ-004 | HIGH | P0 | Converting an incomplete order ignores stock, and cancelling it creates stock |
| BIZ-005 | HIGH | P0 | Paid orders can still be cancelled and then cannot be refunded |
| BIZ-006 | HIGH | P0 | Customers can pay for out-of-stock items and receive no order |
| OPS-008 | HIGH until verified | P0 | Backups, cron and server settings could not be verified |
| SEC-002 | HIGH | P1 | Any admin with one settings page can read every stored secret |
| SEC-003 | HIGH | P1 | A verification code sent to one email can verify a different email |
| SEC-019 | HIGH if the app sees the CDN IP, otherwise INFO | P1 | Whether the app sees real customer IPs behind the CDN is unknown |

## Do these first (recommendations, nothing applied)

These need no code:
1. Confirm a recent off-site backup exists and try one restore (OPS-008).
2. Compare the forwarder token with `.claude/settings.local.json` and regenerate it if it matches (SEC-020).
3. Set the real bKash and Nagad sender rules and check that the forwarder phone only relays those senders. This narrows SEC-001 but does not close it, because the sender value is relayed by the phone.
4. Until BIZ-003 is fixed, tell staff that Cart page coupons do not apply. Check whether any bundle is active (BIZ-002).
5. Review who holds limited admin accounts, and rotate secrets if any exist (SEC-002).
6. Compare IPs on recent orders in Admin to check SEC-019.

Then follow `REMEDIATION_ROADMAP.md`.

## What held up

- Client-sent prices, totals and discounts are ignored (C01). Negative and zero quantities, and hidden products, are refused (C02, C03).
- One customer cannot read or cancel another's order, invoice, checkout or address (C04, C05, C07, C15).
- Webhooks fail closed on a bad token or signature (C08, C09, C10). One transaction ID cannot pay two orders (C11). Double submit makes one order (C12).
- Admin permissions hold outside the settings data leak, and mass assignment cannot grant privileges (C13, C14). HTML is purified (C16).
- Race tests on MariaDB 11.8.3 with READ-COMMITTED and snapshot isolation: product stock 3/3, cart 4/4, coupon 1/1, courier 15/15, loyalty 12/12, return and refund 1/2. The one failure is a wrong test assumption, not a bug (REL-002).
- `.env`, `.git` and SQL paths are blocked on the live site, debug tools are absent, and production npm packages have no advisories.
- Full project suite at this commit: 2,026 tests, 1,963 passed, 63 skipped, 0 failed.

## Corrections made during the audit

- I first reported no secrets in git history. SEC-020 corrects that: the scanner missed a token.
- I told the owner earlier in this session that guests can use coupons. This holds only for the Checkout page box (BIZ-003).
- My search fix's claim that junk cannot fill the disk is narrowed by PERF-008.

## What could not be covered

- **Production settings, BLOCKED by the owner's decision:** backups and restore, cron, server config, production `.env`, opcache, mail caps, disk and inode quota, and live `sms_patterns`, `pathao_environment` and the current forwarder token.
- **Real money and real services, forbidden by the directive:** real bKash or Nagad payments, real SMS, real email, real Pathao and Steadfast calls, and real Google or Facebook login.
- **Load and scale:** no load testing. Query counts and plans come from small local datasets, so they show shape, not production timing.
- **GitHub settings:** required reviewers on the `production` environment and default token permissions. `deploy.yml` and `CLAUDE.md` disagree on whether a manual gate exists.
- **Browser coverage is partial:** a few flows were driven in headless Chrome. There was no real-device mobile, iOS keyboard, screen reader, or systematic contrast testing.
- **BIZ-003 charge:** the ৳900 display was proven in a browser. The ৳1,000 charge comes from code, because my script could not get through the District dropdown.
- **Lower-severity findings:** these come from code reading by module reviewers and were spot-checked, not all personally re-read. Every Critical and High was re-read.

## Reports

`SECURITY_AUDIT_REPORT.md`, `BUSINESS_LOGIC_AND_PAYMENTS_REPORT.md`, `DATABASE_FORENSIC_REPORT.md`, `PERFORMANCE_AUDIT_REPORT.md`, `FUNCTIONAL_QA_REPORT.md`, `FRONTEND_UX_AUDIT_REPORT.md`, `INFRASTRUCTURE_DEPLOYMENT_AUDIT.md`, `PRODUCTION_READINESS_CHECKLIST.md`, `REMEDIATION_ROADMAP.md`, `AUDIT_EVIDENCE_INDEX.md`.

**Warning:** do not commit `docs/forensic-audit/` until SEC-022 is fixed. `deploy.yml` would copy this list of unfixed problems to the server.
