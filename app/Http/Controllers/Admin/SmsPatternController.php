<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsPattern;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SmsPatternController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/sms-patterns/index', [
            'patterns' => SmsPattern::orderBy('provider')->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'in:bkash,nagad'],
            'name' => ['required', 'string', 'max:100'],
            'identifier' => ['required', 'string', 'max:255'],
            'txn_id_regex' => ['required', 'string', 'max:500'],
            'amount_regex' => ['required', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        SmsPattern::create($data + ['is_active' => true, 'sort_order' => $data['sort_order'] ?? 99]);

        return back()->with('success', 'Pattern added.');
    }

    public function update(Request $request, SmsPattern $smsPattern): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'in:bkash,nagad'],
            'name' => ['required', 'string', 'max:100'],
            'identifier' => ['required', 'string', 'max:255'],
            'txn_id_regex' => ['required', 'string', 'max:500'],
            'amount_regex' => ['required', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $smsPattern->update($data);

        return back()->with('success', 'Pattern updated.');
    }

    public function destroy(SmsPattern $smsPattern): RedirectResponse
    {
        $smsPattern->delete();

        return back()->with('success', 'Pattern deleted.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:sms_patterns,id'],
        ])['ids'];

        SmsPattern::whereIn('id', $ids)->delete();

        return back()->with('success', count($ids).' pattern(s) deleted.');
    }
}
