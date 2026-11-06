<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin-tunable bot protection (Admin > Security). Every limit is a pair:
 * `*_times` attempts allowed per `*_minutes` window.
 *
 * Values always fall back to the defaults below and are clamped to their
 * min/max on read as well as on save, so a missing, blank or out-of-range
 * stored value can never switch a limit off or lock real customers out.
 * Admin login is deliberately NOT configurable here — see AppServiceProvider.
 */
final class SecurityRateLimits
{
    /**
     * BE-001: a request field as text (rate-limit keys, lookups). A bot
     * sending `email[]=x` used to crash every limiter that lower-cased the
     * raw input, before validation could refuse it; non-text is now "".
     */
    public static function textInput(Request $request, string $key): string
    {
        $value = $request->input($key);

        return is_string($value) ? $value : '';
    }

    public const SETTING_KEY = 'security_rate_limits';

    private const CACHE_KEY = 'settings.security_rate_limits';

    /**
     * @var array<string, array{default: int, min: int, max: int}>
     */
    public const FIELDS = [
        'orders_per_network_times' => ['default' => 10, 'min' => 3, 'max' => 200],
        'orders_per_network_minutes' => ['default' => 10, 'min' => 1, 'max' => 1440],
        'orders_per_phone_times' => ['default' => 5, 'min' => 2, 'max' => 100],
        'orders_per_phone_minutes' => ['default' => 60, 'min' => 1, 'max' => 1440],
        'payment_per_order_times' => ['default' => 6, 'min' => 3, 'max' => 50],
        'payment_per_order_minutes' => ['default' => 10, 'min' => 1, 'max' => 1440],
        'payment_per_network_times' => ['default' => 15, 'min' => 5, 'max' => 300],
        'payment_per_network_minutes' => ['default' => 10, 'min' => 1, 'max' => 1440],
        'signups_per_network_times' => ['default' => 5, 'min' => 2, 'max' => 200],
        'signups_per_network_minutes' => ['default' => 60, 'min' => 1, 'max' => 1440],
        'password_reset_per_network_times' => ['default' => 5, 'min' => 2, 'max' => 200],
        'password_reset_per_network_minutes' => ['default' => 10, 'min' => 1, 'max' => 1440],
        'password_reset_per_email_times' => ['default' => 3, 'min' => 1, 'max' => 50],
        'password_reset_per_email_minutes' => ['default' => 60, 'min' => 1, 'max' => 1440],
        'coupon_checks_times' => ['default' => 10, 'min' => 3, 'max' => 300],
        'coupon_checks_minutes' => ['default' => 1, 'min' => 1, 'max' => 1440],
        'sign_in_per_network_times' => ['default' => 20, 'min' => 5, 'max' => 500],
        'sign_in_per_network_minutes' => ['default' => 1, 'min' => 1, 'max' => 1440],
        'contact_messages_times' => ['default' => 5, 'min' => 2, 'max' => 100],
        'contact_messages_minutes' => ['default' => 10, 'min' => 1, 'max' => 1440],
        'newsletter_signups_times' => ['default' => 5, 'min' => 2, 'max' => 100],
        'newsletter_signups_minutes' => ['default' => 10, 'min' => 1, 'max' => 1440],
        'newsletter_checks_times' => ['default' => 30, 'min' => 10, 'max' => 600],
        'newsletter_checks_minutes' => ['default' => 1, 'min' => 1, 'max' => 1440],
        'newsletter_manage_per_network_times' => ['default' => 5, 'min' => 2, 'max' => 100],
        'newsletter_manage_per_network_minutes' => ['default' => 10, 'min' => 1, 'max' => 1440],
        'newsletter_manage_per_email_times' => ['default' => 3, 'min' => 1, 'max' => 50],
        'newsletter_manage_per_email_minutes' => ['default' => 60, 'min' => 1, 'max' => 1440],
        'checkout_tracking_times' => ['default' => 60, 'min' => 20, 'max' => 600],
        'checkout_tracking_minutes' => ['default' => 1, 'min' => 1, 'max' => 1440],
        'order_tracking_times' => ['default' => 30, 'min' => 10, 'max' => 600],
        'order_tracking_minutes' => ['default' => 1, 'min' => 1, 'max' => 1440],
        'search_suggestions_times' => ['default' => 60, 'min' => 20, 'max' => 1000],
        'search_suggestions_minutes' => ['default' => 1, 'min' => 1, 'max' => 1440],
        'product_browsing_times' => ['default' => 120, 'min' => 30, 'max' => 2000],
        'product_browsing_minutes' => ['default' => 1, 'min' => 1, 'max' => 1440],
        'invoice_downloads_times' => ['default' => 10, 'min' => 3, 'max' => 200],
        'invoice_downloads_minutes' => ['default' => 1, 'min' => 1, 'max' => 1440],
        'reviews_times' => ['default' => 5, 'min' => 1, 'max' => 100],
        'reviews_minutes' => ['default' => 60, 'min' => 1, 'max' => 1440],
        'password_checks_times' => ['default' => 5, 'min' => 3, 'max' => 50],
        'password_checks_minutes' => ['default' => 1, 'min' => 1, 'max' => 1440],
        'account_actions_times' => ['default' => 60, 'min' => 20, 'max' => 1000],
        'account_actions_minutes' => ['default' => 1, 'min' => 1, 'max' => 1440],
    ];

    /**
     * @return array<string, int>
     */
    public static function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, function (): array {
            $value = Setting::find(self::SETTING_KEY)?->value;

            return is_array($value) ? $value : [];
        });

        $values = [];

        foreach (self::FIELDS as $field => $bounds) {
            $raw = $stored[$field] ?? null;
            $value = is_numeric($raw) ? (int) $raw : $bounds['default'];
            $values[$field] = max($bounds['min'], min($bounds['max'], $value));
        }

        return $values;
    }

    public static function get(string $field): int
    {
        return self::all()[$field];
    }

    /**
     * @param  array<string, int>  $values
     */
    public static function save(array $values): void
    {
        Setting::updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => array_intersect_key($values, self::FIELDS), 'group' => 'security'],
        );

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return collect(self::FIELDS)
            ->map(fn (array $bounds): array => ['required', 'integer', 'min:'.$bounds['min'], 'max:'.$bounds['max']])
            ->all();
    }

    /**
     * Rate-limit identity for "one device or network": the signed-in
     * customer when there is one (so many shoppers behind one shared mobile
     * network IP don't throttle each other), otherwise the IP address.
     */
    public static function visitorKey(Request $request): string
    {
        $userId = $request->user()?->getAuthIdentifier();

        return $userId !== null ? 'user:'.$userId : 'ip:'.$request->ip();
    }

    /**
     * What a throttled visitor sees, depending on the kind of request:
     *  - fetch() callers (coupon box, live order lookup): JSON `error` + 429.
     *  - page loads / downloads (GET): the themed 429 "Slow down a little"
     *    error page. Never a redirect — redirecting a throttled page back to
     *    another throttled page would loop.
     *  - form submissions: the standard error toast plus a validation error,
     *    so Inertia forms take their onError path and no success side
     *    effects run.
     */
    public static function tooManyAttempts(string $message): Closure
    {
        return function (Request $request, array $headers) use ($message): Response {
            if (! $request->header('X-Inertia') && $request->expectsJson()) {
                return response()->json(['error' => $message, 'message' => $message], 429, $headers);
            }

            if ($request->isMethod('GET')) {
                abort(429, $message, $headers);
            }

            Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

            return back()->withErrors(['rate_limit' => $message]);
        };
    }
}
