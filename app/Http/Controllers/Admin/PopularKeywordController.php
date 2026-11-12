<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Concerns\UpdatesStatusInBulk;
use App\Http\Controllers\Controller;
use App\Models\PopularKeyword;
use App\Support\LayoutCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'keyword' => ['required', 'string', 'max:60'],
        ]);

        $maxOrder = PopularKeyword::max('sort_order') ?? 0;

        DB::transaction(fn () => PopularKeyword::create([
            'keyword' => trim($request->keyword),
            'sort_order' => $maxOrder + 1,
            'is_active' => true,
        ]));
        $this->flushLayoutCache();

        return back()->with('success', 'Keyword added.');
    }

    public function update(Request $request, PopularKeyword $popularKeyword): RedirectResponse
    {
        $request->validate([
            'keyword' => ['required', 'string', 'max:60'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ]);

        DB::transaction(fn () => $popularKeyword->update([
            'keyword' => trim($request->keyword),
            'sort_order' => $request->integer('sort_order'),
            'is_active' => $request->boolean('is_active'),
        ]));
        $this->flushLayoutCache();

        return back()->with('success', 'Keyword updated.');
    }

    public function destroy(PopularKeyword $popularKeyword): RedirectResponse
    {
        DB::transaction(fn () => $popularKeyword->delete());
        $this->flushLayoutCache();

        return back()->with('success', 'Keyword deleted.');
    }

    public function updateStatusMany(Request $request): RedirectResponse
    {
        $result = DB::transaction(fn () => $this->updateStatusInBulk($request, PopularKeyword::class));
        $this->flushLayoutCache();

        return redirect()->route('admin.popular-keywords.index')->with('success', $result['count'].' keyword(s) '.($result['active'] ? 'activated' : 'deactivated').'.');
    }

    /** Saves the order the admin dragged the keywords into: position in the list becomes `sort_order` (1 = first). */
    public function reorder(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'max:1000'],
            'ids.*' => ['integer', 'distinct', 'exists:popular_keywords,id'],
        ])['ids'];

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $index => $id) {
                PopularKeyword::whereKey($id)->update(['sort_order' => $index + 1]);
            }
        });

        // Bulk update() fires no model events, so the shared layout cache is cleared by hand.
        $this->flushLayoutCache();

        return back();
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:popular_keywords,id'],
        ])['ids'];

        // One at a time so each deletion is audited.
        DB::transaction(fn () => PopularKeyword::whereIn('id', $ids)->get()->each->delete());
        $this->flushLayoutCache();

        return back()->with('success', count($ids).' keyword(s) deleted.');
    }

    /**
     * The model events flush this before the commit, and it's cached forever,
     * so a reader could otherwise store the old list.
     */
    private function flushLayoutCache(): void
    {
        LayoutCache::forget(...PopularKeyword::LAYOUT_CACHE_KEYS);
    }
}
