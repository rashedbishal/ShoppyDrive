# ShoppyDrive — Session Handoff, 28 November 2026

Prepared at the end of a long Claude Code session covering SEO batches DISC-76 to DISC-96, the image and JavaScript performance work, and the Phase 1/2 CMS content cleanup.

**The authoritative status source is still `docs/SHAPPYDRIVE_AUDIT_REMEDIATION_TRACKER.md`.** This file is a snapshot to help the next session start; where the two disagree, verify against the tracker, git and the live site rather than trusting either.

---

## 1. Current project state (as of this handoff)

### Git

| Item | State |
|---|---|
| Branch | `main` |
| `origin/main` | `6340771` docs(tracker): record DISC-95 deployment and live verification |
| Local vs origin | 0 ahead, 0 behind. **No unpushed commits.** |
| `stash@{0}` | "local CLAUDE.md admin-permissions note + vite.config.ts LAN server disabled". Owner's; never pop, drop or apply. |

**Uncommitted local changes (all DISC-96, intentional):**

| File | State | What |
|---|---|---|
| `resources/js/pages/Home.tsx` | modified | Hero slide title `<motion.h1>` → `<motion.h2>` (+ one comment line) |
| `tests/Feature/AgentFallbackTest.php` | modified | DISC-90 test now counts `<h1` **and** `<motion.h1>` |
| `docs/SHAPPYDRIVE_AUDIT_REMEDIATION_TRACKER.md` | modified | DISC-96 row, work-history line, three "latest" summary entries |

**Untracked files (preserve, don't commit unless the owner asks):**

| File | What |
|---|---|
| `docs/TECHNICAL_SEO_AUDIT_AND_IMPLEMENTATION_RISK_REPORT.md` | The original SEO audit report (SEO-01…SEO-51, F-01…F-15). Never modify. |
| `docs/SHOPPYDRIVE_SEO_CONTENT_DRAFT.md` | CMS content draft package (28 Nov). Phase 1/2 checklists in the conversation superseded parts of its wording. |
| `docs/SHAPPYDRIVE_SESSION_HANDOFF_2026-11-28.md` | This file. |

### Deployment

- Production = `origin/main` = `6340771`, deployed by GitHub Actions run **46050464617** (Deploy to Hostinger: success; Run Tests 46050464806 and Lint 46050464748: success; "Nothing to migrate"; health check passed).
- **DISC-96 is NOT deployed.**
- Pushing `main` auto-deploys to Hostinger and runs `php artisan migrate --force` (see CLAUDE.md).

### Latest test results

- DISC-96 (local, isolated copy, no Vite dev server, run as CI does): **3890 tests: 3818 passed, 72 skipped, 0 failed.** `npm run build` and `route:cache` OK; TypeScript, Prettier, Pint clean; ESLint 0 errors, 1 pre-existing `react-hooks/exhaustive-deps` warning in `Home.tsx`.
- **Testing note:** the owner often has a Vite dev server running (`public/hot` exists), which makes `PreloadLinkHeaderTest`-style tests fail in the working tree. Run the full suite in an isolated copy (sync `app/ resources/ tests/ routes/ config/`, `php artisan optimize:clear`, `php -d memory_limit=-1 vendor/bin/pest`). MySQL-only race tests skip on SQLite (see CLAUDE.md).

### Stack (only what matters here)

Laravel 13, Inertia v3, React 19 rendered in the browser only (**no SSR in production; don't introduce it**), Vite/Rolldown, Tailwind v4, Pest, Hostinger shared hosting behind Hostinger's CDN (`hcdn`). SEO tags are server-printed by `app/Support/PageSeo.php` via `resources/views/app.blade.php`.

---

## 2. SEO and performance remediation history (DISC-76 to DISC-96)

Status terms:
- **FIXED LOCALLY**: in the working tree only.
- **COMMITTED**: in a local commit.
- **DEPLOYED**: pushed and deployed by a recorded GitHub Actions run.
- **LIVE VERIFIED**: checked on the live site and recorded as such in the tracker.

| ID | What | Commit | Deploy run | Tracker status |
|---|---|---|---|---|
| DISC-76 | Server title/description on the 7 main pages | `8e85618` | 45941098879 | DEPLOYED_UNVERIFIED |
| DISC-77 | Product title suffix; single meta description | `c164195` | 45941098879 | DEPLOYED_UNVERIFIED |
| DISC-78 | Twitter card tag | `119d9ef` | 45941098879 | DEPLOYED_UNVERIFIED |
| DISC-79 | Sitemap adds sub-categories, /flash-sales, /bundle-deals | `e0d3cef` | 45941098879 | DEPLOYED_UNVERIFIED |
| DISC-80 | `/page/{slug}` duplicates 301 to main URLs | `6cb288d` | 45941098879 | DEPLOYED_UNVERIFIED |
| DISC-81 | Product JSON-LD url/sku/brand/offer url | `3f5744d` | 45941098879 | DEPLOYED_UNVERIFIED |
| DISC-82 | AggregateRating when approved reviews exist | `d79def0` | 45941098879 | DEPLOYED_UNVERIFIED |
| DISC-83 | BreadcrumbList on product/category pages | `0c15f06` | 45941098879 | DEPLOYED_UNVERIFIED |
| DISC-84 | BlogPosting schema | `ce96836` | 45941098879 | DEPLOYED_UNVERIFIED |
| DISC-85 | BlogPosting author type | `1f8be1b` | 45941098879 | DEPLOYED_UNVERIFIED |
| DISC-86 | Brand SEO fields (migration) | `63b145e` | 45944255755 | DEPLOYED_UNVERIFIED |
| DISC-87 | Home/About/Contact SEO fields | `468a4f4` | 45944255755 | DEPLOYED_UNVERIFIED |
| DISC-88 | Category browser title = saved SEO title | `dc21384` | 45947276156 | DEPLOYED_UNVERIFIED |
| DISC-89 | Category/sub-category slug collisions | `e99523d` | 45947276156 | DEPLOYED_UNVERIFIED |
| DISC-90 | Visible homepage H1 + intro | `4fe9e71` | 45953970029 | Deployed; verified live (default heading/intro) |
| DISC-91 | Slug-change 301s: products/categories (`slug_redirects` migration) | `c704bbe` | 45953970029 | Deployed; 301 behaviour untested live |
| DISC-92 | Slug-change 301s: brands/blogs | `c6e2ab4` | 45964785618 | Deployed; 301 behaviour untested live |
| DISC-93 | Image performance F2–F9 (responsive WebP, preload, hero poster migration) | `66a9ae4` | 45979254845 | Deployed; production backfill done (37 images, 100 WebP) and lab-verified; real-user CWV pending |
| DISC-94 | F1 JavaScript critical-path reductions (LazyMotion, lazy toast/AccountLayout, LazySelect, showcase loop) | `67b1bfa` | 45997085572 | Deployed; functional live checks passed; real-user CWV pending |
| DISC-95 | Title brand guard, description cleanup, no-JS category links, brand BreadcrumbList | `680c242` | 46047483175 | Deployed and live-verified (public HTTP); descriptions PARTIAL at the time (Contact repeat, since fixed in CMS) |
| DISC-96 | Homepage duplicate H1 (hero slide title) | — | — | **FIXED_LOCALLY, NOT COMMITTED, not deployed, not live-verified** |

**Not yet recorded in the tracker:**
- **Live observations of DISC-76–89.** The consolidated SEO audit (28 Nov, read-only public HTTP) saw these working live: per-page titles and descriptions, Twitter card, `/page/*` 301s, product JSON-LD fields, BreadcrumbList, sub-category pages and the sitemap additions. The tracker still says DEPLOYED_UNVERIFIED. Per CLAUDE.md, VERIFIED_IN_PRODUCTION needs owner-supplied production evidence; don't upgrade these rows without the owner's approval.
- **Stale cells:**
  - The DISC-76–85 rows still say "Not pushed / Not verified" in their Deploy/Prod columns, although their status text records run 45941098879.
  - The DISC-88 row's later cells look shifted by one column, probably an unescaped `|` (the same class of problem fixed in the DISC-95 row).
  - Both are housekeeping only; fix them in a tracker-only change with approval.

### CMS content cleanup (done manually by the owner in Admin, live-verified read-only)

| Phase | Changes | Live result |
|---|---|---|
| Phase 1 | Contact intro and SEO; RGB "dd" typo; Toys & Games text; TOMI T106 SEO title; homepage SEO title, description, H1, intro; Home & Living, Watches & Fashion Accessories, Men's Watches SEO | PASS |
| Phase 2 | Heading lines removed from category descriptions; About page rewritten (stats removed, superlative headline replaced, SEO fields); popular keywords cleaned; SEO fields for 5 sub-categories; Press & Twist SEO and short description; Bubu Dudu product name/meta; Home Decor SEO; "Bubu Dudu Items" capitalisation | PASS |
| Admin decisions | Announcement message removed (no free-delivery promise; free-delivery minimum stays 0, consistent); 11 empty sub-categories and Samsung brand deactivated | PASS |

**Bubu Dudu:** the product and the "Bubu Dudu Items" sub-category were briefly 404, then public again at the last check (27 Nov ~21:22 UTC). The "bubu dudu" keyword and the Gifts & Cute Accessories mention of Bubu Dudu are still live; both are consistent while the product is public. The owner's stated edit (removing those references) did not appear live, so **the owner should confirm the intent.**

---

## 3. Latest sitemap verification (read-only, 27 Nov 2026 ~21:25 UTC)

- The owner manually regenerated the sitemap via SSH at about 21:24 UTC on 27 Nov 2026. The verification itself was read-only and changed nothing.
- `https://shoppydrive.com/sitemap.xml`:
  - HTTP 200, `application/xml`, `Last-Modified: 27 Nov 2026 21:24:04 GMT`, valid `urlset` XML
  - **29 URLs, 0 duplicates**
  - **all 29 return 200**, **all 29 canonicals equal their sitemap URL**, no noindex (meta or `X-Robots-Tag`), no stale URLs
- **Absent, and returning 404:** the 11 deactivated sub-categories (leather-strap-watches, casual-fashion-watches, smart-fitness-tracker-watch, home-decor, decorative-led-lamps, bedroom-bedside-lighting, rgb-night-lights, stress-relief-toys, balance-coordination-toys, plush-keychains, gift-items) and `/brand/samsung`.
- **Included, because they're public:** the Bubu Dudu product, `/category/bubu-dudu-items` and `/category/gifts-cute-accessories`.
- **Contents:**
  - 9 categories and sub-categories
  - 5 products
  - Tomi Brand
  - the home page, `/products`, `/brands`, `/about`, `/contact`, `/blog`, `/flash-sales` and `/bundle-deals`
  - 6 policy and FAQ pages
- `/blog` and `/bundle-deals` have no `lastmod`, by design: there are no posts or bundles to date them from.
- **robots.txt:** HTTP 200, allows everything, `Sitemap: https://shoppydrive.com/sitemap.xml`. The Hostinger CDN caches it for up to a year, so purge the CDN after any robots.txt change.
- **Scheduling:** the sitemap also rebuilds nightly at 04:00 Asia/Dhaka (22:00 UTC).

---

## 4. Outstanding items

### Blocking or important

| # | Item | Status | Needs |
|---|---|---|---|
| 1 | **DISC-96** homepage duplicate H1 | Fixed locally; not committed, deployed or live-verified | Owner commit approval, then push approval; after deploy, a headless check that `/` renders exactly one h1 |
| 2 | **Google Search Console** | Domain verification and sitemap submission not done (no access from this session) | Owner: verify the domain (DNS TXT preferred), submit `https://shoppydrive.com/sitemap.xml`, monitor indexing and coverage, and Core Web Vitals after ~28 days |
| 3 | **Bubu Dudu** licensing/name wording | Open decision; product currently public and called "Bubu Dudu" in name, SEO title, sub-category, Gifts text and keyword (the product description says "Bubu Dudu-inspired") | Owner: licensed, or describe as "inspired"? Confirm whether the product should be public. No legal conclusions. |
| 4 | **Delivery coverage** | FAQ and Shipping Policy say "all 64 districts"; site-wide copy says "inside and outside Dhaka" | Owner confirms the claim is true |
| 5 | **Legal business name / trade licence number** | Empty; nothing shown publicly (correct, as there's no placeholder) | Owner supplies real values (Admin › Contact Page) |

### Content consistency (owner confirmation first)

| # | Item | Live evidence |
|---|---|---|
| 6 | Cute Cat Night Light materials | SEO title says "Wooden Legs" and short description "wooden legs", but meta description says "wooden-style legs" |
| 7 | Kung Fu Battle Toy Set materials | Name/title/meta say "Bamboo"; short description says "bamboo-style figures" |
| 8 | Support hours | "Every day, 9 AM–9 PM" is used consistently on Contact and About; the owner hasn't explicitly confirmed it's staffed |

### Optional

- Press & Twist short description: "colour combinations" vs "colorful" (spelling consistency).
- Blog: 0 posts; topics are in the content draft and conversation.
- Tracker housekeeping (stale DISC-76–85 Deploy/Prod cells; the DISC-88 column shift).
- Real-user Core Web Vitals review for DISC-93/94 once Search Console has data.
- F1-03 decision (GTM/Facebook pixel loading timing) is the owner's; deliberately left unchanged.
- Live test of a slug rename → 301 (DISC-91/92), only if the owner wants it.

---

## 5. Safety and operational rules (carry forward)

1. **Never commit, push or deploy without the owner's explicit approval**, one step at a time; approval of one step never covers the next.
2. **Pushing `main` triggers automatic deployment** to Hostinger, including `migrate --force`.
3. Never access production SSH or production databases unless the owner explicitly authorises it for that task. Owner-run SSH actions (like the 27 Nov sitemap regeneration) are the owner's.
4. No production modifications, migrations, dependency changes or architectural changes without approval.
5. Don't touch orders, payments, checkout, pricing, inventory, shipping calculations or other business logic unless explicitly authorised.
6. **No 2FA, ever.** It isn't part of the system; never implement, restore or propose it.
7. Preserve CLAUDE.md, the remediation tracker (never rewrite or remove history; append and prefix), audit reports, untracked files, scratch files and `stash@{0}`.
8. Never overwrite or delete existing documentation or work. Treat uncommitted changes as intentional.
9. Audits are read-only unless the owner explicitly authorises changes.
10. Always give evidence, and always state whether work is **local, committed, pushed, deployed or live-verified**.
11. Don't introduce SSR. Don't change product/category/blog/brand URLs.
12. CMS content changes are the owner's to make in Admin; prepare copy-paste text and checklists rather than editing content.

---

## 6. Starting instructions for the next session

1. Read, in order:
   1. `CLAUDE.md` (including its mandatory session-resume protocol)
   2. `docs/SHAPPYDRIVE_AUDIT_REMEDIATION_TRACKER.md`: the "PRODUCTION REMEDIATION CONTROL" section and the DISC-76 to DISC-96 rows
   3. `docs/SHOPPYDRIVE_FULL_SYSTEM_FORENSIC_AUDIT_REPORT_2026-11-02.md`, `docs/TECHNICAL_SEO_AUDIT_AND_IMPLEMENTATION_RISK_REPORT.md` (untracked; read only) and, if relevant, `docs/SHOPPYDRIVE_SEO_CONTENT_DRAFT.md`
   4. this handoff file
2. Run `git status`, `git log --oneline -10`, `git fetch` and compare with `origin/main`. Expect `main` = `origin/main` = `6340771`, plus the three uncommitted DISC-96 files and the untracked docs listed in §1. Report any discrepancy instead of trusting either side.
3. Read any documentation the tracker references for the task at hand.
4. **Don't modify code or content first.** Confirm the state above, summarise it briefly, and **ask the owner which task to take next**, unless the owner has already given one. Likely candidates:
   - commit and push DISC-96
   - Search Console submission support
   - Bubu Dudu, material and legal decisions
   - tracker housekeeping
