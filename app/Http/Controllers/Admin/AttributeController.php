<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use Inertia\Inertia;
use Inertia\Response;

class AttributeController extends Controller
{
    public function index(): Response
    {
        $attributes = Attribute::query()
            ->withCount('values')
            ->with(['values' => fn ($query) => $query->orderBy('sort_order')->orderBy('value')])
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/attributes/index', [
            'attributes' => $attributes,
        ]);
    }
}
