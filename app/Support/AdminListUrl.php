<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Keeps an admin list page's position (page number, search, filters) across
 * an edit or delete. `RememberAdminListUrl` records the query string of each
 * admin list page the admin views; actions that used to redirect to the bare
 * list route (and so always landed on page 1) redirect through
 * `redirect()` instead, and the form pages' Back/Cancel links read the same
 * remembered URLs via the shared `admin_list_urls` prop.
 *
 * Only the query string is stored, and the URL is always rebuilt from the
 * route's own path, so a remembered value can never point anywhere else.
 */
class AdminListUrl
{
    private const SESSION_KEY = 'admin_list_urls';

    /**
     * Admin GET routes that show a list but aren't named `*.index`.
     *
     * @var list<string>
     */
    private const EXTRA_LIST_ROUTES = ['admin.orders.trashed'];

    public static function isListRoute(?string $routeName): bool
    {
        if ($routeName === null || $routeName === 'admin.index' || ! str_starts_with($routeName, 'admin.')) {
            return false;
        }

        return str_ends_with($routeName, '.index') || in_array($routeName, self::EXTRA_LIST_ROUTES, true);
    }

    /**
     * Remember (or, for a plain unfiltered page 1, forget) this list page's query string.
     */
    public static function remember(Request $request, string $routeName): void
    {
        $queryString = (string) $request->getQueryString();

        if ($queryString === '') {
            $request->session()->forget(self::SESSION_KEY.'.'.self::sessionSlot($routeName));

            return;
        }

        $request->session()->put(self::SESSION_KEY.'.'.self::sessionSlot($routeName), $queryString);
    }

    /**
     * The list URL the admin last viewed for this route, or the plain route URL.
     */
    public static function for(string $routeName): string
    {
        $url = route($routeName);
        $queryString = session(self::SESSION_KEY.'.'.self::sessionSlot($routeName));

        return is_string($queryString) && $queryString !== '' ? $url.'?'.$queryString : $url;
    }

    public static function redirect(string $routeName): RedirectResponse
    {
        return redirect()->to(self::for($routeName));
    }

    /**
     * Every remembered list URL, keyed by route name, for the frontend's Back/Cancel links.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        $remembered = session(self::SESSION_KEY, []);

        if (! is_array($remembered)) {
            return [];
        }

        $urls = [];

        foreach (array_keys($remembered) as $slot) {
            $routeName = str_replace('|', '.', (string) $slot);

            if (self::isListRoute($routeName) && app('router')->has($routeName)) {
                $urls[$routeName] = self::for($routeName);
            }
        }

        return $urls;
    }

    /**
     * When the page asked for no longer exists (its last rows were just
     * deleted or filtered away), go to the last page that does instead of
     * showing an empty table.
     */
    public static function redirectIfPastLastPage(LengthAwarePaginator $paginator, Request $request): ?RedirectResponse
    {
        if ($paginator->currentPage() <= 1 || $paginator->currentPage() <= $paginator->lastPage()) {
            return null;
        }

        $lastPage = $paginator->lastPage();
        $query = $request->query();

        if ($lastPage > 1) {
            $query[$paginator->getPageName()] = $lastPage;
        } else {
            unset($query[$paginator->getPageName()]);
        }

        return redirect()->to($query === [] ? $request->url() : $request->url().'?'.Arr::query($query));
    }

    /**
     * Route names contain dots, which the session's dot-notation would split
     * into nested arrays; store them under a dot-free slot instead.
     */
    private static function sessionSlot(string $routeName): string
    {
        return str_replace('.', '|', $routeName);
    }
}
