<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BlogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $blogId = $this->route('blog')?->id;

        return [
            'title' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:150', 'unique:blogs,slug,'.($blogId ?? 'NULL').',id'],
            'excerpt' => ['nullable', 'string', 'max:400'],
            'content' => ['required', 'string', 'max:50000'],
            'featured_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'author_name' => ['nullable', 'string', 'max:80'],
            'meta_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'is_published' => ['boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
