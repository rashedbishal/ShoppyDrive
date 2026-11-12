<?php

declare(strict_types=1);

use App\Support\AdminSections;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * LOW-05. `AdminPermissionsTest` already proves the gating mechanism
 * (EnsureAdminPermission + AdminSections) is correct in the abstract, via a
 * handful of representative sections (orders, customers, categories,
 * blocked_ips). It never checks that every *other* admin route is actually
 * wired to that mechanism, or wired to a real section/ability pair — a
 * mistyped or copy-pasted `AdminSections::middleware(...)` call on any of
 * the other ~24 sections would pass every existing test silently.
 *
 * This file walks the real registered route table (routes/admin.php)
 * instead of hand-listing routes, so it never goes stale as routes are
 * added, renamed or removed. No HTTP requests, no database, no model
 * binding — pure route/middleware introspection.
 */

/**
 * Every `admin.*`-named route NOT listed here is expected to carry exactly
 * one `admin.can:<section>,<ability>` middleware entry. Each exception
 * below was verified directly against its route definition in
 * routes/admin.php (not assumed from a comment) before being added here.
 *
 * @var list<string>
 */
const LOW05_EXEMPT_ADMIN_ROUTES = [
    // Pre-authentication — reachable before the `admin` guard middleware
    // even runs, so there is no logged-in admin yet to check permissions for.
    'admin.login',
    'admin.login.submit',
    'admin.logout',

    // A plain redirect to the dashboard, and the dashboard itself — every
    // admin's own landing page, with no CRUD of its own to gate.
    'admin.index',
    'admin.dashboard',

    // One endpoint shared by six sidebar sections (Settings, Social Config,
    // Courier Config, About Page, Contact Page, Referral Program) —
    // SettingController::update() checks permission per submitted field
    // group itself, not via a single route-level gate. Confirmed: this
    // route carries no middleware call at all in routes/admin.php.
    'admin.settings.update',

    // Hardcoded mother-only *inside* SecuritySettingsController::update()
    // — confirmed no route middleware of any kind (not even admin.mother)
    // gates this one; the controller itself checks is_mother.
    'admin.settings.security.update',

    // OPS-002: the cron-monitoring ping URL, saved from the same Security page
    // and hardcoded mother-only inside SecuritySettingsController::updateHeartbeat().
    'admin.settings.security.heartbeat.update',

    // Mother-only via the `admin.mother` route middleware (creates the VAPID key pair
    // for instant SMS notifications); not part of the Permissions matrix.
    'admin.settings.sms-forwarder.push-keys.generate',

    // Hardcoded mother-only *inside*
    // MarketingSettingsController::updateCustomCode() — same pattern as
    // settings.security.update above; confirmed no route middleware of any
    // kind gates this one. The pasted code runs in every visitor's browser,
    // a bigger capability than anything else marketing_tracking:update
    // grants, so it's deliberately not delegable via the Permissions matrix
    // at all. (facebook-conversions-api.update is NOT here — it carries a
    // normal admin.can:marketing_tracking,update middleware.)
    'admin.settings.marketing.custom-code.update',

    // Shared rich-text-editor image upload helper used by several sections
    // (Blog, About Page, Contact Page, Landing Pages, ...) — not worth
    // gating to any single one of them. Confirmed no middleware call.
    'admin.content-images.store',

    // Admin-account management is deliberately excluded from the
    // AdminSections catalog entirely (see its own docblock) — mother-only /
    // self-only rules are enforced inside AdminController itself. Confirmed:
    // `Route::resource('admins', AdminController::class)->except(['show'])`
    // has no $guardResource wrapper and no individual middleware calls —
    // every registered action in this family is ungated by admin.can, not
    // just one of them. ('show' isn't registered at all, so there's
    // nothing named admin.admins.show to account for either way.)
    'admin.admins.index',
    'admin.admins.create',
    'admin.admins.store',
    'admin.admins.edit',
    'admin.admins.update',
    'admin.admins.destroy',

    // Clearing the audit trail is hardcoded mother-only via the stricter
    // `admin.mother` route middleware, not the section-permission system —
    // `audit_trail` has no `delete` ability in the catalog at all (its
    // abilities list is empty; only `view` is ever implied). Confirmed:
    // `admin.audit-trail.destroy`'s only middleware is
    // ['admin.mother', 'throttle:mother-actions']. Viewing it
    // (`admin.audit-trail.index`) IS still gated normally via
    // admin.can:audit_trail,view and is deliberately NOT in this list.
    'admin.audit-trail.destroy',

    // OPS-008: database backup and restore are mother-admin only for every
    // admin, enforced by `admin.mother` on the route group. A backup holds all
    // customer data and courier keys, so it's deliberately not a grantable
    // AdminSections entry.
    'admin.database-backup.index',
    'admin.database-backup.download',
    'admin.database-backup.inspect',
    'admin.database-backup.apply',
    'admin.database-backup.discard',

    // Error Log: Laravel's log files read straight from storage/logs. Mother
    // admin only, enforced by `admin.mother` on the route group (verified by
    // ErrorLogTest): log records can hold customer details, file paths and
    // request data, so it's deliberately not a grantable AdminSections entry.
    'admin.error-log.index',
    'admin.error-log.download',
    'admin.error-log.clear',

    // Storage Clean-up: the storage:audit command as a page. Mother admin
    // only, enforced by `admin.mother` on the route group (verified by
    // StorageCleanupPageTest): it moves and deletes files the whole site
    // serves, so it's deliberately not a grantable AdminSections entry.
    'admin.storage-cleanup.index',
    'admin.storage-cleanup.quarantine',
    'admin.storage-cleanup.restore',
    'admin.storage-cleanup.destroy',

    // Security › "Permanently delete a customer": mother admin only, enforced
    // by `admin.mother` on the route group (verified by CustomerErasureTest),
    // with two password checks; deliberately not a grantable AdminSections entry.
    'admin.settings.security.customer-erasure.search',
    'admin.settings.security.customer-erasure.summary',
    'admin.settings.security.customer-erasure.confirm',
    'admin.settings.security.customer-erasure.destroy',

    // DB-001: permanent order deletion is mother-admin only, confirmed
    // gated only by ['admin.mother', 'throttle:mother-actions'].
    'admin.orders.permanentDelete.confirm',
    'admin.orders.forceDelete',

    // PAY-RETURN-001: courier delivery verification and courier readiness
    // are mother-admin only, confirmed gated only by admin.mother (plus a
    // throttle on the write actions).
    'admin.orders.deliveryVerifications.store',
    'admin.courierReadiness.index',
    'admin.courierReadiness.store',

    // FSA-11: confirming a parcel lost is mother-admin only, confirmed
    // gated only by ['admin.mother', 'throttle:30,15'] — the identical
    // pattern as admin.orders.deliveryVerifications.store above, not the
    // section-permission system.
    'admin.orders.confirmLostParcel',
];

/**
 * @return Collection<int, Illuminate\Routing\Route>
 */
function low05AdminNamedRoutes(): Collection
{
    return collect(Route::getRoutes())
        ->filter(fn ($route) => is_string($route->getName()) && str_starts_with($route->getName(), 'admin.'))
        ->values();
}

// ── Test A ───────────────────────────────────────────────────────────────────
// Every admin route that is expected to be permission-gated must carry
// exactly one valid admin.can: middleware entry.
test('every non-exempt admin route carries exactly one admin.can middleware entry', function () {
    $allNames = low05AdminNamedRoutes()->map(fn ($route) => $route->getName())->all();

    // Keeps the exception list itself honest: if a route it names was
    // renamed or removed, this fails loudly instead of the exemption
    // silently covering nothing (or, worse, no longer matching the route it
    // was meant to document).
    foreach (LOW05_EXEMPT_ADMIN_ROUTES as $exemptName) {
        expect(in_array($exemptName, $allNames, true))->toBeTrue(
            "Exempt route name [{$exemptName}] is not a currently registered route — the exception list is stale and must be updated or removed."
        );
    }

    $gatedRoutes = low05AdminNamedRoutes()->reject(
        fn ($route) => in_array($route->getName(), LOW05_EXEMPT_ADMIN_ROUTES, true)
    );

    // Sanity check on the test itself: never silently pass on an empty set.
    expect($gatedRoutes)->not->toBeEmpty();

    foreach ($gatedRoutes as $route) {
        $canEntries = collect($route->gatherMiddleware())
            ->filter(fn (string $middleware) => str_starts_with($middleware, 'admin.can:'))
            ->values();

        expect($canEntries)->toHaveCount(
            1,
            "Route [{$route->getName()}] must carry exactly one admin.can: middleware entry; found {$canEntries->count()} (".$canEntries->implode(', ').'). '
                .'If this route is intentionally ungated, add it to LOW05_EXEMPT_ADMIN_ROUTES with a documented, verified reason — do not weaken this assertion.'
        );
    }
});

// ── Test B ───────────────────────────────────────────────────────────────────
// Every admin.can:<section>,<ability> reference must resolve to a valid
// section in AdminSections::SECTIONS and a supported ability from
// AdminSections::abilitiesFor($section). Malformed entries (wrong number of
// parts) are failed explicitly, never silently skipped.
test('every admin.can reference names a section and ability that actually exist in AdminSections', function () {
    $gatedRoutes = low05AdminNamedRoutes()->reject(
        fn ($route) => in_array($route->getName(), LOW05_EXEMPT_ADMIN_ROUTES, true)
    );

    expect($gatedRoutes)->not->toBeEmpty();

    foreach ($gatedRoutes as $route) {
        $canEntry = collect($route->gatherMiddleware())
            ->first(fn (string $middleware) => str_starts_with($middleware, 'admin.can:'));

        // Test A already guarantees exactly one such entry exists for every
        // route reaching here; a missing one here would mean the two tests
        // disagree about the route set, which should never happen.
        expect($canEntry)->not->toBeNull("Route [{$route->getName()}] has no admin.can: middleware to inspect.");

        $parameters = explode(',', substr((string) $canEntry, strlen('admin.can:')));

        expect($parameters)->toHaveCount(
            2,
            "Route [{$route->getName()}]'s admin.can middleware [{$canEntry}] does not parse to exactly a section and an ability — malformed middleware string."
        );

        [$section, $ability] = $parameters;

        expect(array_key_exists($section, AdminSections::SECTIONS))->toBeTrue(
            "Route [{$route->getName()}] is gated against unknown section [{$section}]."
        );

        expect(in_array($ability, AdminSections::abilitiesFor($section), true))->toBeTrue(
            "Route [{$route->getName()}] is gated against ability [{$ability}], which section [{$section}] does not support."
        );
    }
});
