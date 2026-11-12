<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\ImageOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContentImageController extends Controller
{
    /**
     * DISC-06: the AdminSections sections whose pages use the
     * rich-text editor (RichTextEditor.tsx). Uploading needs create or update
     * on at least one of them; abilities a section doesn't support (e.g.
     * update on newsletter) are never granted, so checking both is safe.
     */
    private const EDITOR_SECTIONS = ['blog', 'landing_pages', 'newsletter', 'products', 'about_page', 'pages'];

    /**
     * Upload an image for insertion into a WYSIWYG rich-text field and return its public URL.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($this->canUseRichTextEditor(Auth::guard('admin')->user()), 403);

        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $path = app(ImageOptimizer::class)->store($request->file('image'), 'content-images');

        return response()->json(['url' => '/storage/'.$path]);
    }

    private function canUseRichTextEditor(?Admin $admin): bool
    {
        if ($admin === null) {
            return false;
        }

        foreach (self::EDITOR_SECTIONS as $section) {
            if ($admin->canAccess($section, 'create') || $admin->canAccess($section, 'update')) {
                return true;
            }
        }

        return false;
    }
}
