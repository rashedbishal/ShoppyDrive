<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The permission matrix shown on Admin > Admins > create/edit, and the
 * allow-list enforced server-side by the `admin.can` middleware
 * (EnsureAdminPermission) and by SettingController's field-group checks.
 *
 * Every section always implies `view` — the "visible" checkbox in the UI —
 * plus whichever of create/update/delete the section actually has a route
 * for (e.g. Returns has no create/delete action, so it only lists update).
 *
 * Deliberately excluded: Admins (managing other admin accounts) and the
 * Dashboard. Admins stays exactly as before this feature — only the mother
 * can create/edit-others/delete, everyone can view the list and edit their
 * own account — because delegating it would let a non-mother admin hand out
 * access they can't be trusted with. Dashboard has no CRUD and is every
 * admin's landing page, so it's never gated.
 */
final class AdminSections
{
    /**
     * @var array<string, array{label: string, group: string, abilities: list<string>}>
     */
    public const SECTIONS = [
        // Catalog
        'products' => ['label' => 'Products', 'group' => 'Catalog', 'abilities' => ['create', 'update', 'delete']],
        'categories' => ['label' => 'Categories', 'group' => 'Catalog', 'abilities' => ['create', 'update', 'delete']],
        'sub_categories' => ['label' => 'Sub-categories', 'group' => 'Catalog', 'abilities' => ['create', 'update', 'delete']],
        'brands' => ['label' => 'Brands', 'group' => 'Catalog', 'abilities' => ['create', 'update', 'delete']],
        'attributes' => ['label' => 'Attributes', 'group' => 'Catalog', 'abilities' => ['create', 'update', 'delete']],

        // Sales
        'orders' => ['label' => 'Orders', 'group' => 'Sales', 'abilities' => ['create', 'update', 'delete']],
        'trashed_orders' => ['label' => 'Trashed Orders', 'group' => 'Sales', 'abilities' => ['update', 'delete']],
        'incomplete_orders' => ['label' => 'Incomplete Orders', 'group' => 'Sales', 'abilities' => ['create', 'delete']],
        'returns' => ['label' => 'Returns', 'group' => 'Sales', 'abilities' => ['update']],
        'coupons' => ['label' => 'Coupons', 'group' => 'Sales', 'abilities' => ['create', 'update', 'delete']],
        'flash_sales' => ['label' => 'Flash Sales', 'group' => 'Sales', 'abilities' => ['create', 'update', 'delete']],
        'bundles' => ['label' => 'Bundles', 'group' => 'Sales', 'abilities' => ['create', 'update', 'delete']],
        'received_sms' => ['label' => 'Received SMS', 'group' => 'Sales', 'abilities' => ['delete']],
        'sms_patterns' => ['label' => 'SMS Patterns', 'group' => 'Sales', 'abilities' => ['create', 'update', 'delete']],

        // Content
        'homepage' => ['label' => 'Homepage', 'group' => 'Content', 'abilities' => ['create', 'update', 'delete']],
        'about_page' => ['label' => 'About Page', 'group' => 'Content', 'abilities' => ['update']],
        'contact_page' => ['label' => 'Contact Page', 'group' => 'Content', 'abilities' => ['update']],
        'landing_pages' => ['label' => 'Landing Pages', 'group' => 'Content', 'abilities' => ['create', 'update', 'delete']],
        'blog' => ['label' => 'Blog', 'group' => 'Content', 'abilities' => ['create', 'update', 'delete']],
        'search_keywords' => ['label' => 'Search Keywords', 'group' => 'Content', 'abilities' => ['create', 'update', 'delete']],
        'display_order' => ['label' => 'Display Order', 'group' => 'Content', 'abilities' => ['update']],

        // Users
        'customers' => ['label' => 'Customers', 'group' => 'Users', 'abilities' => ['delete']],
        'reviews' => ['label' => 'Reviews', 'group' => 'Users', 'abilities' => ['update', 'delete']],
        'contact_messages' => ['label' => 'Contact Messages', 'group' => 'Users', 'abilities' => ['delete']],

        // Marketing
        'newsletter' => ['label' => 'Newsletter', 'group' => 'Marketing', 'abilities' => ['create', 'delete']],
        // Custom Header/Footer code's own update stays mother-only regardless
        // of this matrix (MarketingSettingsController::updateCustomCode()) —
        // it runs in every visitor's browser, a bigger capability than the
        // Facebook Conversions API fields this section also gates. Both
        // halves share one `view`/`update` grant here; the harder gate is
        // enforced only on the custom-code save itself.
        'marketing_tracking' => ['label' => 'Marketing & Tracking', 'group' => 'Marketing', 'abilities' => ['update']],

        // System
        'blocked_ips' => ['label' => 'Blocked IPs', 'group' => 'System', 'abilities' => ['create', 'delete']],
        'settings' => ['label' => 'Settings', 'group' => 'System', 'abilities' => ['update']],
        'social_config' => ['label' => 'Social Config', 'group' => 'System', 'abilities' => ['update']],
        'courier_config' => ['label' => 'Courier Config', 'group' => 'System', 'abilities' => ['update']],
        'sms_forwarder' => ['label' => 'SMS Forwarder', 'group' => 'System', 'abilities' => ['update']],
        'ai_assistant' => ['label' => 'AI Assistant', 'group' => 'System', 'abilities' => ['update']],
        'referral_program' => ['label' => 'Referral Program', 'group' => 'System', 'abilities' => ['update']],
        // Security's own update stays mother-only regardless of this matrix
        // (SecuritySettingsController) — it only lists `view` here.
        'security' => ['label' => 'Security', 'group' => 'System', 'abilities' => []],
        // Clearing the log stays mother-only regardless of this matrix
        // (AuditTrailController::destroy()) — it only lists `view` here.
        'audit_trail' => ['label' => 'Audit Trail', 'group' => 'System', 'abilities' => []],
        // `update` retries a job, `delete` removes its record.
        'failed_jobs' => ['label' => 'Failed Jobs', 'group' => 'System', 'abilities' => ['update', 'delete']],
    ];

    /**
     * The `admin.can:<section>,<ability>` middleware string for a given
     * section+ability pair — see EnsureAdminPermission and routes/admin.php.
     */
    public static function middleware(string $section, string $ability): string
    {
        return "admin.can:{$section},{$ability}";
    }

    /**
     * @return list<string>
     */
    public static function abilitiesFor(string $section): array
    {
        return ['view', ...self::SECTIONS[$section]['abilities'] ?? []];
    }

    /**
     * Allow-lists a raw `permissions` payload (e.g. admin/admins create or
     * update form input) down to known sections and the abilities each one
     * actually supports. A section is kept only if at least one of its
     * submitted abilities survives, and `view` is always implied the moment
     * any ability is granted — the UI's single "visible" checkbox for a
     * section.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, list<string>>
     */
    public static function sanitize(array $input): array
    {
        $clean = [];

        foreach (self::SECTIONS as $key => $meta) {
            $requested = $input[$key] ?? null;

            if (! is_array($requested)) {
                continue;
            }

            $granted = array_values(array_intersect(self::abilitiesFor($key), $requested));

            if ($granted !== []) {
                $clean[$key] = array_values(array_unique(['view', ...$granted]));
            }
        }

        return $clean;
    }

    /**
     * Grouped for the Admin > Admins create/edit permission matrix, in the
     * same group order as the admin sidebar.
     *
     * @return list<array{group: string, sections: list<array{key: string, label: string, abilities: list<string>}>}>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::SECTIONS as $key => $meta) {
            $groups[$meta['group']][] = [
                'key' => $key,
                'label' => $meta['label'],
                'abilities' => $meta['abilities'],
            ];
        }

        return collect($groups)
            ->map(fn (array $sections, string $group): array => ['group' => $group, 'sections' => $sections])
            ->values()
            ->all();
    }
}
