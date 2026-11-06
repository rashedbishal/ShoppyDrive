<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * SEC-013: receives browser reports for the report-only Content Security
 * Policy, so a real policy can later be written from what the site actually
 * loads. Each distinct violation is logged once a day, so one noisy page
 * can't fill the log. Query strings are dropped: they can carry tokens.
 */
class CspReportController extends Controller
{
    private const DEDUPE_HOURS = 24;

    public function __invoke(Request $request): Response
    {
        $report = json_decode($request->getContent(), true)['csp-report'] ?? null;

        if (is_array($report)) {
            $directive = Str::limit((string) ($report['effective-directive'] ?? $report['violated-directive'] ?? ''), 100);
            $blocked = Str::limit((string) strtok((string) ($report['blocked-uri'] ?? ''), '?#'), 200);

            if (Cache::add('csp-report:'.sha1($directive.'|'.$blocked), 1, now()->addHours(self::DEDUPE_HOURS))) {
                Log::warning('CSP violation (report-only)', [
                    'directive' => $directive,
                    'blocked' => $blocked,
                    'page' => Str::limit((string) strtok((string) ($report['document-uri'] ?? ''), '?#'), 200),
                ]);
            }
        }

        return response()->noContent();
    }
}
