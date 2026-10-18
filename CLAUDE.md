# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/socialite (SOCIALITE) - v5
- laravel/wayfinder (WAYFINDER) - v0
- tightenco/ziggy (ZIGGY) - v2
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/react (INERTIA_REACT) - v3
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `fortify-development` — ACTIVATE when the user works on authentication in Laravel. This includes login, registration, password reset, email verification, two-factor authentication (2FA/TOTP/QR codes/recovery codes), profile updates, password confirmation, or any auth-related routes and controllers. Activate when the user mentions Fortify, auth, authentication, login, register, signup, forgot password, verify email, 2FA, or references app/Actions/Fortify/, CreateNewUser, UpdateUserProfileInformation, FortifyServiceProvider, config/fortify.php, or auth guards. Fortify is the frontend-agnostic authentication backend for Laravel that registers all auth routes and controllers. Also activate when building SPA or headless authentication, customizing login redirects, overriding response contracts like LoginResponse, or configuring login throttling. Do NOT activate for Laravel Passport (OAuth2 API tokens), Socialite (OAuth social login), or non-auth Laravel features.
- `laravel-best-practices` — Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, validation, error handling, queue and job configuration, route definitions, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns.
- `socialite-development` — Manages OAuth social authentication with Laravel Socialite. Activate when adding social login providers; configuring OAuth redirect/callback flows; retrieving authenticated user details; customizing scopes or parameters; setting up community providers; testing with Socialite fakes; or when the user mentions social login, OAuth, Socialite, or third-party authentication.
- `wayfinder-development` — Use this skill for Laravel Wayfinder which auto-generates typed functions for Laravel controllers and routes. ALWAYS use this skill when frontend code needs to call backend routes or controller actions. Trigger when: connecting any React/Vue/Svelte/Inertia frontend to Laravel controllers, routes, building end-to-end features with both frontend and backend, wiring up forms or links to backend endpoints, fixing route-related TypeScript errors, importing from @/actions or @/routes, or running wayfinder:generate. Use Wayfinder route functions instead of hardcoded URLs. Covers: wayfinder() vite plugin, .url()/.get()/.post()/.form(), query params, route model binding, tree-shaking. Do not use for backend-only task
- `pest-testing` — Use this skill for Pest PHP testing in Laravel projects only. Trigger whenever any test is being written, edited, fixed, or refactored — including fixing tests that broke after a code change, adding assertions, converting PHPUnit to Pest, adding datasets, and TDD workflows. Always activate when the user asks how to write something in Pest, mentions test files or directories (tests/Feature, tests/Unit, tests/Browser), or needs browser testing, smoke testing multiple pages for JS errors, or architecture tests. Covers: test()/it()/expect() syntax, datasets, mocking, browser testing (visit/click/fill), smoke testing, arch(), Livewire component tests, RefreshDatabase, and all Pest 4 features. Do not use for factories, seeders, migrations, controllers, models, or non-test PHP code.
- `inertia-react-development` — Develops Inertia.js v3 React client-side applications. Activates when creating React pages, forms, or navigation; using <Link>, <Form>, useForm, useHttp, setLayoutProps, or router; working with deferred props, prefetching, optimistic updates, instant visits, or polling; or when user mentions React with Inertia, React pages, React forms, or React navigation.
- `tailwindcss-development` — Always invoke when the user's message includes 'tailwind' in any form. Also invoke for: building responsive grid layouts (multi-column card grids, product grids), flex/grid page structures (dashboards with sidebars, fixed topbars, mobile-toggle navs), styling UI components (cards, tables, navbars, pricing sections, forms, inputs, badges), adding dark mode variants, fixing spacing or typography, and Tailwind v3/v4 work. The core use case: writing or fixing Tailwind utility classes in HTML templates (Blade, JSX, Vue). Skip for backend PHP logic, database queries, API routes, JavaScript with no HTML/CSS component, CSS file audits, build tool configuration, and vanilla CSS.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>

## Project Overview

ShoppyDrive is a Bangladesh-focused ecommerce storefront + admin panel built on the Laravel React starter kit (Laravel 13, Inertia v3, React 19, Tailwind v4). It has two front ends sharing one Inertia app: a public storefront and an `/admin` back office, each with its own auth guard.

## Common Commands

Full local dev stack (server, queue listener, log tailer, Vite) in one command:
```
composer dev
```

Individual pieces:
```
php artisan serve
npm run dev              # Vite dev server
php artisan queue:listen --tries=1 --timeout=0
php artisan pail         # live log viewer
```

Build frontend for production:
```
npm run build
npm run build:ssr
```

Tests:
```
php artisan test --compact
php artisan test --compact --filter=testName
composer test            # config:clear + pint --test + full test suite
```

Linting / formatting:
```
vendor/bin/pint --dirty --format agent   # PHP, only changed files (run before finishing any PHP change)
npm run lint              # eslint --fix
npm run format             # prettier --write resources/
npm run types:check       # tsc --noEmit
composer ci:check          # eslint check + prettier check + tsc + full test suite
```

Wayfinder (regenerates `resources/js/actions` / `resources/js/routes` typed route helpers — runs automatically via the Vite plugin during `npm run dev`/`build`, but can be run manually):
```
php artisan wayfinder:generate
```

## Architecture

**Dual auth guards.** There are two separate user tables/guards configured in `config/auth.php`: `web` (provider `users`, model `App\Models\User`) for customers, and `admin` (provider `admins`, model `App\Models\Admin`) for staff. Admin routes live under `routes/admin.php` behind the `admin` middleware alias (`App\Http\Middleware\EnsureAdmin`) plus `redirect-customer-from-admin`. `RedirectAdminToDashboard` and `RedirectCustomerFromAdmin` middleware (registered globally in `bootstrap/app.php`) keep the two sessions from bleeding into each other's pages. Exactly one `Admin` can have `is_mother = true` (the super admin); `php artisan admin:create-mother` creates or replaces it.

**Social login** (`App\Http\Controllers\Auth\SocialAuthController`) uses Socialite for customer-facing Google/Facebook OAuth, configured via `GOOGLE_*`/`FACEBOOK_*` env vars in `config/services.php`. This is separate from Fortify's email/password + 2FA flow and only applies to the `web` guard, not admins.

**Email verification is a custom 6-digit OTP, not Fortify's default signed link.** `User::sendEmailVerificationNotification()` (`app/Models/User.php`) overrides the default to generate and hash a 6-digit code (`email_verification_code`, 15-minute expiry, 60s resend cooldown) and mail it via `App\Mail\VerifyEmailCodeMail`; `App\Http\Controllers\Auth\EmailVerificationCodeController` verifies/consumes it. `App\Http\Responses\LoginResponse`/`RegisterResponse` override Fortify's contracts to send unverified users straight to `verification.notice` instead of the home page. The `verification-code` rate limiter (`FortifyServiceProvider`) throttles code submission/resend. `account.php`, `settings.php`, and the cart/checkout group in `commerce.php` all gate on the `verified` middleware, so an unverified customer can browse but can't check out or reach account/settings pages.

**Route files are split by domain**, all required from `routes/web.php`: `admin.php` (back office, prefixed `/admin`, named `admin.*`), `auth.php` (Fortify-backed customer auth), `catalog.php` (products/categories/brands browsing), `commerce.php` (cart/checkout/payments), `account.php` (customer dashboard: orders, addresses, wishlist), `settings.php` (profile/password/2FA), `api.php` (JSON API under `Http/Controllers/Api/V1`).

**Inertia pages** live in `resources/js/pages`, mirroring the route domains: `catalog/`, `checkout/`, `account/`, `admin/`, `landing/`, `auth/`, `static/`, `order/`, `cart/`, `compare/`, `search/`. Admin pages are visually and structurally separate from storefront pages — check the matching subfolder for existing conventions before adding new admin UI.

**Payments** go through two paths:
- `App\Services\ZiniPayService` — hosted payment gateway integration (invoice creation + webhook handling via `ZiniPayWebhookController`).
- `App\Http\Controllers\MobilePaymentController` + `App\Services\SmsParserService` — manual bKash/Nagad-style mobile banking flow where customers submit a transaction ID and an incoming SMS (forwarded via the `sms_forwarder` service token in `config/services.php`) is parsed against configurable `SmsPattern` records to auto-verify payment.

**Courier shipping** is handled by `App\Services\PathaoCourierService`, which authenticates against Pathao's API (credentials stored in the `Setting` model, not `.env`, under the `pathao_*` keys) and creates/tracks consignments for placed orders.

**Static/legal content** (About, Contact, FAQ, Privacy Policy, Terms) is admin-editable and served from the `Page` model keyed by `slug` (`is_published` gate), rendered via `resources/js/pages/static/Page.tsx`, rather than hardcoded Blade/React content.

**Landing pages** (`App\Models\LandingPage`, `LandingPageController`, `resources/js/pages/landing/`) are a separate guest-checkout funnel (`/lp/{slug}`) distinct from the main storefront cart/checkout — used for single-product ad-driven campaigns with their own order + payment + confirmation flow.

**Order lifecycle** is modeled with the `OrderStatus`/`PaymentStatus`/`PaymentMethod` enums (`app/Enums`), `OrderStatusHistory` for auditing, and `App\Services\OrderService` for placement logic. Transactional emails for each stage live in `app/Mail` (e.g. `OrderPlacedMail`, `ShippedWithTrackingMail`, `OrderDeliveredMail`, `OrderCancelledMail`) and are dispatched around status transitions.

**Response caching**: `spatie/laravel-responsecache` is installed — be aware that public GET storefront pages may be cached at the HTTP layer; cache-busting on content changes (e.g. product/homepage updates in the admin panel) should be handled where those admin actions occur.

**Database**: MySQL locally (`DB_CONNECTION=mysql`), SQLite in-memory for tests (`phpunit.xml`). Queue and cache both use the `database` driver locally.

**Shadcn/ui** components (`components.json`, style `new-york`, base color `neutral`) live under `resources/js/components/ui`; prefer these + `resources/js/lib/utils` (`cn()`) over ad hoc styling for shared UI primitives.

**Client-side state**: `zustand` stores in `resources/js/stores` (`cartStore`, `wishlistStore`, `uiStore`) hold cross-page client state that doesn't belong in Inertia props.

**PDF invoices**: `barryvdh/laravel-dompdf` renders the `invoices.order` Blade view (`Pdf::loadView('invoices.order', ...)`) for order invoice downloads, used from both `routes/account.php` (storefront) and `LandingPageController` (landing-page funnel orders).

**Sitemap**: `php artisan sitemap:generate` (`App\Console\Commands\GenerateSitemapCommand`, using `spatie/laravel-sitemap`) rebuilds the public sitemap from active categories/products/pages — run manually or via an external cron, not Laravel's scheduler (`routes/console.php` has no `schedule()` calls).
