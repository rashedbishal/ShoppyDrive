<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Concerns\UpdatesStatusInBulk;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlogRequest;
use App\Models\Blog;
use App\Support\AdminDateTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    use UpdatesStatusInBulk;

    public function index(Request $request): Response
    {
        $blogs = Blog::query()
            ->when($request->search, fn ($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/blogs/index', [
            'blogs' => $blogs,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/blogs/create');
    }

    public function store(BlogRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['slug']);

        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = '/storage/'.$request->file('featured_image')->store('blogs', 'public');
        }

        $data = AdminDateTime::fieldsFromInput($data, ['published_at']);
        $data['is_published'] = $data['is_published'] ?? false;
        if ($data['is_published'] && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        Blog::create($data);

        return redirect()->route('admin.blogs.index')->with('success', 'Blog post created.');
    }

    public function edit(Blog $blog): Response
    {
        return Inertia::render('admin/blogs/edit', [
            'blog' => [
                ...$blog->toArray(),
                'published_at_input' => AdminDateTime::toInput($blog->published_at),
            ],
        ]);
    }

    public function update(BlogRequest $request, Blog $blog): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['slug']);

        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = '/storage/'.$request->file('featured_image')->store('blogs', 'public');
        } else {
            unset($data['featured_image']);
        }

        $data = AdminDateTime::fieldsFromInput($data, ['published_at']);
        $data['is_published'] = $data['is_published'] ?? false;
        if ($data['is_published'] && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        $blog->update($data);

        return redirect()->route('admin.blogs.index')->with('success', 'Blog post updated.');
    }

    public function destroy(Blog $blog): RedirectResponse
    {
        $blog->delete();

        return redirect()->route('admin.blogs.index')->with('success', 'Blog post deleted.');
    }

    public function updateStatusMany(Request $request): RedirectResponse
    {
        $result = $this->updateStatusInBulk($request, Blog::class, 'is_published');

        return redirect()->route('admin.blogs.index')->with('success', $result['count'].' blog(s) '.($result['active'] ? 'published' : 'unpublished').'.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:blogs,id'],
        ])['ids'];

        Blog::whereIn('id', $ids)->delete();

        return redirect()->route('admin.blogs.index')->with('success', count($ids).' blog post(s) deleted.');
    }
}
