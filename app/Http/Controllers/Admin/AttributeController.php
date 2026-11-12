<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AttributeRequest;
use App\Models\Attribute;
use Illuminate\Http\RedirectResponse;
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
            ->get()
            ->each(fn (Attribute $attribute) => $attribute->setAttribute('in_use', $attribute->isInUse()));

        return Inertia::render('admin/attributes/index', [
            'attributes' => $attributes,
        ]);
    }

    public function store(AttributeRequest $request): RedirectResponse
    {
        Attribute::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Attribute added.']);

        return redirect()->route('admin.attributes.index');
    }

    /** A rename rewrites every variant's labels (see Attribute::booted()). */
    public function update(AttributeRequest $request, Attribute $attribute): RedirectResponse
    {
        $attribute->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Attribute updated.']);

        return redirect()->route('admin.attributes.index');
    }

    /**
     * Only an attribute nothing uses can go: deleting it also deletes its
     * values (cascade), which variants, images and filters depend on.
     */
    public function destroy(Attribute $attribute): RedirectResponse
    {
        if ($attribute->isInUse()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "Can't delete \"{$attribute->name}\" — products use it. Remove it from those products first.",
            ]);

            return redirect()->route('admin.attributes.index');
        }

        $attribute->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Attribute deleted.']);

        return redirect()->route('admin.attributes.index');
    }
}
