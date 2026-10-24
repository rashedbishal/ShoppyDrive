<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentImageController extends Controller
{
    /**
     * Upload an image for insertion into a WYSIWYG rich-text field and return its public URL.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $path = $request->file('image')->store('content-images', 'public');

        return response()->json(['url' => '/storage/'.$path]);
    }
}
