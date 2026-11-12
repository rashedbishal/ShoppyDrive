<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Concerns\UpdatesStatusInBulk;
use App\Http\Controllers\Controller;
use App\Models\SmsPattern;
use App\Rules\SafeSmsRegex;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SmsPatternController extends Controller
{
    use UpdatesStatusInBulk;

    public function index(): Response
    {
        return Inertia::render('admin/sms-patterns/index', [
            'patterns' => SmsPattern::orderBy('provider')->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['sender_regex' => $request->filled('sender_regex') ? $request->input('sender_regex') : null]);

        $data = $request->validate([
            'provider' => ['required', 'in:bkash,nagad'],
            'name' => ['required', 'string', 'max:100'],
            'identifier' => ['required', 'string', 'max:100'],
            'sender_regex' => ['nullable', 'string', 'max:255', new SafeSmsRegex],
            'txn_id_regex' => ['required', 'string', 'max:255', new SafeSmsRegex],
            'amount_regex' => ['required', 'string', 'max:255', new SafeSmsRegex],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        SmsPattern::create($data + ['is_active' => true, 'sort_order' => $data['sort_order'] ?? 99]);

        return back()->with('success', 'Pattern added.');
    }

    public function update(Request $request, SmsPattern $smsPattern): RedirectResponse
    {
        $request->merge(['sender_regex' => $request->filled('sender_regex') ? $request->input('sender_regex') : null]);

        $data = $request->validate([
            'provider' => ['required', 'in:bkash,nagad'],
            'name' => ['required', 'string', 'max:100'],
            'identifier' => ['required', 'string', 'max:100'],
            'sender_regex' => ['nullable', 'string', 'max:255', new SafeSmsRegex],
            'txn_id_regex' => ['required', 'string', 'max:255', new SafeSmsRegex],
            'amount_regex' => ['required', 'string', 'max:255', new SafeSmsRegex],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $smsPattern->update($data);

        return back()->with('success', 'Pattern updated.');
    }

    public function destroy(SmsPattern $smsPattern): RedirectResponse
    {
        $smsPattern->delete();

        return back()->with('success', 'Pattern deleted.');
    }

    public function updateStatusMany(Request $request): RedirectResponse
    {
        $result = $this->updateStatusInBulk($request, SmsPattern::class);

        return redirect()->route('admin.sms-patterns.index')->with('success', $result['count'].' SMS pattern(s) '.($result['active'] ? 'activated' : 'deactivated').'.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:sms_patterns,id'],
        ])['ids'];

        // One at a time so each deletion is audited and clears the pattern cache.
        SmsPattern::whereIn('id', $ids)->get()->each->delete();

        return back()->with('success', count($ids).' pattern(s) deleted.');
    }
}
