<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * FE-010: receives a report when a page crashes while rendering in the
 * browser (caught by resources/js/components/error-boundary.tsx), so these
 * crashes reach laravel.log instead of going unnoticed. Logs only the
 * shortened error, the page path (no query string, which can carry
 * tokens) and the browser; nothing about the customer or their cart.
 */
class ClientErrorController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'path' => ['required', 'string', 'max:2000'],
            'component_stack' => ['nullable', 'string', 'max:4000'],
        ]);

        Log::warning('Browser page crash', [
            'message' => Str::limit($validated['message'], 500),
            'path' => Str::limit((string) strtok($validated['path'], '?#'), 300),
            'component_stack' => Str::limit((string) ($validated['component_stack'] ?? ''), 1500),
            'user_agent' => Str::limit((string) $request->userAgent(), 300),
        ]);

        return response()->noContent();
    }
}
