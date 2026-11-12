<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageRequest;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Mews\Purifier\Facades\Purifier;

/**
 * DISC-05: Admin › Pages — edit (never create or delete) the six legal/info
 * pages listed in Page::ADMIN_EDITABLE_SLUGS. Content is sanitized here, on
 * save, with the dedicated `pages` purifier profile (keeps the FAQ's H2/H3
 * structure); existing rows are never rewritten until an admin saves them.
 */
class PageController extends Controller
{
    public function index(): Response
    {
        $pages = Page::query()
            ->whereIn('slug', Page::ADMIN_EDITABLE_SLUGS)
            ->get(['id', 'title', 'slug', 'is_published', 'updated_at'])
            ->sortBy(fn (Page $page): int => array_search($page->slug, Page::ADMIN_EDITABLE_SLUGS, true))
            ->values();

        return Inertia::render('admin/pages/index', [
            'pages' => $pages,
        ]);
    }

    public function edit(Page $page): Response
    {
        $this->ensureEditable($page);

        return Inertia::render('admin/pages/edit', [
            'page' => $page->only(['id', 'title', 'slug', 'content', 'meta_title', 'meta_description', 'is_published']),
            'publicUrl' => $this->publicUrl($page),
        ]);
    }

    public function update(PageRequest $request, Page $page): RedirectResponse
    {
        $this->ensureEditable($page);

        $data = $request->validated();
        $data['content'] = Purifier::clean($data['content'], 'pages');
        $data['is_published'] = $data['is_published'] ?? false;

        $page->update($data);

        return redirect()->route('admin.pages.index')->with('success', "\"{$page->title}\" saved.");
    }

    private function ensureEditable(Page $page): void
    {
        abort_unless(in_array($page->slug, Page::ADMIN_EDITABLE_SLUGS, true), 404);
    }

    /**
     * All six editable pages are served at a top-level URL equal to their
     * slug (routes/web.php: /privacy-policy, /terms, /faq, /return-policy,
     * /refund-policy, /shipping-policy).
     */
    private function publicUrl(Page $page): string
    {
        return url('/'.$page->slug);
    }
}
