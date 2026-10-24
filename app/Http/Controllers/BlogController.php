<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Blog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function index(Request $request): Response
    {
        $blogs = Blog::query()
            ->published()
            ->when($request->search, fn ($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->orderByDesc('published_at')
            ->paginate(9)
            ->withQueryString();

        return Inertia::render('blog/Index', [
            'blogs' => $blogs,
            'filters' => $request->only(['search']),
        ]);
    }

    public function show(Blog $blog): Response
    {
        abort_unless($blog->is_published && (! $blog->published_at || $blog->published_at->lessThanOrEqualTo(now())), 404);

        $relatedBlogs = Blog::query()
            ->published()
            ->where('id', '!=', $blog->id)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return Inertia::render('blog/Show', [
            'blog' => $blog,
            'relatedBlogs' => $relatedBlogs,
        ]);
    }
}
