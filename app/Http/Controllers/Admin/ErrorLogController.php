<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminListUrl;
use App\Support\LaravelLogReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Admin > Error Log: Laravel's log files shown as a table, one row per log
 * record, straight from storage/logs (no database). Mother admin only, like
 * Database Backup: log records can hold customer details, file paths and
 * request data.
 *
 * Clearing empties the chosen file rather than deleting it, so logging
 * carries on into it, and is recorded in the audit trail.
 */
class ErrorLogController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request, LaravelLogReader $reader): Response|RedirectResponse
    {
        $files = $reader->files();
        $names = array_column($files, 'name');

        $validated = $request->validate([
            'file' => ['nullable', 'string', Rule::in($names)],
            'level' => ['nullable', 'string', Rule::in(LaravelLogReader::LEVELS)],
        ]);

        $file = $validated['file'] ?? ($names[0] ?? null);
        $level = $validated['level'] ?? null;
        $read = $file !== null ? $reader->read($file) : ['entries' => [], 'truncated' => false];

        $levelCounts = array_fill_keys(LaravelLogReader::LEVELS, 0);

        foreach ($read['entries'] as $entry) {
            if (isset($levelCounts[$entry['level']])) {
                $levelCounts[$entry['level']]++;
            }
        }

        $shown = $level === null
            ? $read['entries']
            : array_values(array_filter($read['entries'], fn (array $entry): bool => $entry['level'] === $level));

        $page = max(1, (int) $request->integer('page', 1));
        $entries = new LengthAwarePaginator(
            array_slice($shown, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            count($shown),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        $pastLastPage = AdminListUrl::redirectIfPastLastPage($entries, $request);

        if ($pastLastPage !== null) {
            return $pastLastPage;
        }

        return Inertia::render('admin/error-log/index', [
            'files' => $files,
            'file' => $file,
            'level' => $level,
            'levelCounts' => $levelCounts,
            'entries' => $entries,
            'truncated' => $read['truncated'],
            'windowBytes' => LaravelLogReader::WINDOW_BYTES,
        ]);
    }

    public function download(Request $request, LaravelLogReader $reader): BinaryFileResponse
    {
        $path = $reader->path((string) $request->query('file', ''));

        abort_if($path === null, 404);

        return response()->download($path);
    }

    public function clear(Request $request, LaravelLogReader $reader): RedirectResponse
    {

        $validated = $request->validate([
            'confirmed' => ['accepted'],
            'file' => ['required', 'string', Rule::in(array_column($reader->files(), 'name'))],
        ]);

        if (! $reader->clear($validated['file'])) {
            return back()->with('error', "Couldn't clear {$validated['file']}. Check the file's permissions on the server.");
        }

        activity('admin')
            ->causedBy(Auth::guard('admin')->user())
            ->event('cleared')
            ->withProperties(['file' => $validated['file']])
            ->log("Error log {$validated['file']} cleared.");

        return redirect()->route('admin.error-log.index', ['file' => $validated['file']])
            ->with('success', "Cleared {$validated['file']}.");
    }
}
