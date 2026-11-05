# Production Readiness Checklist

Audited commit `f49bed3`, 2026-11-05. Audit only: nothing was fixed, committed or deployed. Full entries: `COMPLETE_FINDINGS_REGISTER.md`.

## Final decision: NOT APPROVED FOR PRODUCTION

**Why:** SEC-001 is an unresolved Critical. Unresolved Highs also sit in security, payments, customer data and core transactions: SEC-002, SEC-003, BIZ-001 to BIZ-006.

**Unverified critical areas that must be cleared regardless:** backups and restore, cron and queue, real client IP, production settings, and real payment, courier and mail flows.

**The site is already live,** so the owner must decide whether to keep trading while fixing these. That decision is theirs. The first section of `FULL_SYSTEM_FORENSIC_AUDIT_REPORT.md` lists steps that reduce risk today without code.

| Item | Status | Reference |
|---|---|---|
| Forged SMS cannot pay | FAIL | SEC-001 |
| Shown price equals charged price | FAIL | BIZ-001, BIZ-003, BIZ-016 |
| Bundles can check out | FAIL | BIZ-002 |
| Stock correct on all order paths | FAIL | BIZ-004 |
| Paid orders can always be refunded | FAIL | BIZ-005 |
| No payment taken for unavailable stock | FAIL | BIZ-006 |
| Secrets limited to authorised admins | FAIL | SEC-002, SEC-007 |
| Email verification proves ownership | FAIL | SEC-003 |
| No secrets in repo | FAIL | SEC-020 |
| Client prices ignored | PASS | C01 |
| IDOR protection | PASS | C04, C05, C07, C15 |
| Webhook authentication | PASS | C08, C09, C10 |
| Double submit and TrxID reuse | PASS | C11, C12 |
| Concurrency on MariaDB settings | PASS | R01-R05, R07; REL-002 is a test issue |
| Debug tools and files not exposed | PASS | L01 |
| Production build and caches work | PASS | Q03, Q04 |
| Project test suite green | PASS | Q01, with limits in REL-001 |
| HSTS and CSP | FAIL | SEC-013 |
| SEO tags on product pages | FAIL | FE-006 |
| Legal pages accurate and complete | FAIL | BIZ-032, BIZ-034; owner or lawyer to confirm |
| Alert when cron, queue or phone stop | FAIL | OPS-002, OPS-005 |
| Deploy safe and reversible | FAIL | INFRA-001, INFRA-002 |
| Migrations tested on MariaDB in CI | FAIL | INFRA-003 |
| Backups and restore tested | BLOCKED | OPS-008 |
| Cron and queue running | BLOCKED | OPS-002 |
| Real client IPs seen | BLOCKED | SEC-019 |
| Production env values correct | BLOCKED | INFRA-005, OPS-007 |
| Live sender rules set | BLOCKED | SEC-001 |
| Real payment, courier, mail and social login | NOT TESTED | Directive forbids; owner to run one controlled test of each |
| Load capacity | NOT TESTED | Directive forbids |

## What could not be covered

- **Production settings, BLOCKED by the owner's decision:** backups and restore, cron, server config, production `.env`, opcache, mail caps, disk and inode quota, and live `sms_patterns`, `pathao_environment` and the current forwarder token.
- **Real money and real services, forbidden by the directive:** real bKash or Nagad payments, real SMS, real email, real Pathao and Steadfast calls, and real Google or Facebook login.
- **Load and scale:** no load testing. Query counts and plans come from small local datasets, so they show shape, not production timing.
- **GitHub settings:** required reviewers on the `production` environment and default token permissions. `deploy.yml` and `CLAUDE.md` disagree on whether a manual gate exists.
- **Browser coverage is partial:** a few flows were driven in headless Chrome. There was no real-device mobile, iOS keyboard, screen reader, or systematic contrast testing.
- **BIZ-003 charge:** the ৳900 display was proven in a browser. The ৳1,000 charge comes from code, because my script could not get through the District dropdown.
- **Lower-severity findings:** these come from code reading by module reviewers and were spot-checked, not all personally re-read. Every Critical and High was re-read.

