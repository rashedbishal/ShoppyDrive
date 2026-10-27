<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Concerns\UpdatesStatusInBulk;
use App\Http\Controllers\Controller;
use App\Models\PopularKeyword;
use App\Support\LayoutCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PopularKeywordController extends Controller
{
    use UpdatesStatusInBulk;

    public function index(): Response
    {
        $keywords = PopularKeyword::orderBy('sort_order')->orderBy('id')->get();

        return Inertia::render('admin/popular-keywords/index', [
            'keywords' => $keywords,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'keyword' => ['required', 'string', 'max:100'],
        ]);

        $maxOrder = PopularKeyword::max('sort_order') ?? 0;

        PopularKeyword::create([
            'keyword' => trim($request->keyword),
            'sort_order' => $maxOrder + 1,
            'is_active' => true,
        ]);

        return back()->with('success', 'Keyword added.');
    }

    public function update(Request $request, PopularKeyword $popularKeyword): RedirectResponse
    {
        $request->validate([
            'keyword' => ['required', 'string', 'max:100'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $popularKeyword->update([
            'keyword' => trim($request->keyword),
            'sort_order' => $request->integer('sort_order'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Keyword updated.');
    }

    public function destroy(PopularKeyword $popularKeyword): RedirectResponse
    {
        $popularKeyword->delete();

        return back()->with('success', 'Keyword deleted.');
    }

    public function updateStatusMany(Request $request): RedirectResponse
    {
        $result = $this->updateStatusInBulk($request, PopularKeyword::class);

        return redirect()->route('admin.popular-keywords.index')->with('success', $result['count'].' keyword(s) '.($result['active'] ? 'activated' : 'deactivated').'.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:popular_keywords,id'],
        ])['ids'];

        PopularKeyword::whereIn('id', $ids)->delete();

        // Bulk delete() fires no model events, so clear the cache here.
        LayoutCache::forget(...PopularKeyword::LAYOUT_CACHE_KEYS);

        return back()->with('success', count($ids).' keyword(s) deleted.');
    }
}
