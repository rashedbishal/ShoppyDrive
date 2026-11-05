# Phase 1: Architecture and inventory

Verified from source at `f49bed3`. Endpoint list: `ENDPOINTS.md`. Tables: `ER_SUMMARY.md`.

## Stack
Laravel 13.39, PHP 8.3, Inertia v3, React 19, TypeScript, Vite 8, Tailwind 4, zustand. One Inertia app with SSR, excluded per path. Fortify and Socialite for customer auth; a separate hand-rolled `admin` guard. Spatie activitylog, dompdf, Mews purifier, spatie sitemap. Pest 4. No Sanctum, Passport, Spatie permissions, Telescope, Horizon or Sentry.
Cache, sessions: file. Queue: database, drained by scheduled `app:drain-queue`. Rate limits: database store.

## Size
318 routes (203 admin), 76 tables, 95 foreign keys, 139 migrations, 60 models, 72 controllers (37 admin), 29 form requests, 22 services, 18 support classes, 16 middleware, 18 mailables, 2 jobs, 14 commands, 0 policies, 0 events, 0 listeners, 116 page components (67 admin), 218 PHP test files, 0 JS tests.

## Modules
**Customer:** catalog, search, brands, categories, product page, reviews, flash sales, bundles, cart, wishlist, checkout (guest and account), COD, manual mobile banking, landing-page funnel, order tracking and confirmation, account, addresses, orders, invoices, returns, social login, email OTP verification, 2FA, referrals, loyalty points, blog, static pages, contact, newsletter.
**Admin:** dashboard, products and variants, categories, attributes, brands, coupons, flash sales, bundles, orders, trash, manual orders, incomplete orders, returns and refunds, delivery verification, lost parcels, courier dispatch, readiness, customers, reviews, homepage, hero slides, banners, blogs, pages, landing pages, newsletter, contact messages, SMS patterns, received SMS, blocked IPs, keywords, settings, security, admins and permissions, audit trail, failed jobs.

## Workflows
Guest and account checkout; COD order; mobile payment staging, then SMS match, then order; landing-page order; legacy ZiniPay callback and webhook; cancellation; return, approval, refund; courier dispatch, status sync, webhook; lost parcel; permanent order deletion; coupon consumption and release; flash sale; bundle assembly; referral and loyalty; registration, OTP, login, 2FA, reset; social link and claim; newsletter send; abandoned-cart reminder.

## External dependencies
Pathao, Steadfast, ZiniPay, Google and Facebook OAuth, SMTP, SMS-forwarder device (inbound), Google Analytics, Hostinger shared hosting, GitHub Actions.

## Background processes
Scheduled: `pathao:sync-statuses` (15 min), `rewards:grant-due` (daily), `cart:remind-abandoned` (hourly), `pending-checkouts:prune` (daily), `newsletter:prune-attachments` (daily), `app:drain-queue` (each minute). Jobs: `AbandonedCartReminderJob`, `SendNewsletterChunk`, plus queued mailables.

## Deployment
Push to `main`: Pint and Pest, build, runtime check, `rsync`, `migrate --force`, cache rebuild, health check. Files go live as they sync, with no atomic release and no automatic backup.

## Coverage matrix

| Area | Security | Business logic | Database | Backend | Frontend | Performance | Reliability | Tests |
|---|---|---|---|---|---|---|---|---|
| Auth and accounts | - | - | - | - | - | - | - | - |
| Catalog and search | - | - | - | - | - | - | - | - |
| Cart | - | - | - | - | - | - | - | - |
| Checkout | - | - | - | - | - | - | - | - |
| Payments | - | - | - | - | - | - | - | - |
| Orders | - | - | - | - | - | - | - | - |
| Inventory | - | - | - | - | - | - | - | - |
| Coupons and promotions | - | - | - | - | - | - | - | - |
| Returns and refunds | - | - | - | - | - | - | - | - |
| Couriers | - | - | - | - | - | - | - | - |
| Landing pages | - | - | - | - | - | - | - | - |
| Reviews, wishlist, content | - | - | - | - | - | - | - | - |
| Admin and permissions | - | - | - | - | - | - | - | - |
| Jobs and email | - | - | - | - | - | - | - | - |
| Deployment and operations | - | - | - | - | - | - | - | - |

`-` means not yet audited.
