<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Concerns\UpdatesStatusInBulk;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CouponRequest;
use App\Models\Coupon;
use App\Support\AdminDateTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    use UpdatesStatusInBulk;

    public function index(Request $request): Response
    {
        $coupons = Coupon::when($request->search, fn ($q) => $q->where('code', 'like', "%{$request->search}%")->orWhere('name', 'like', "%{$request->search}%"))
            ->latest()->paginate(20)->withQueryString();

        return Inertia::render('admin/coupons/index', [
            'coupons' => $coupons,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/coupons/create');
    }

    public function store(CouponRequest $request): RedirectResponse
    {
        $data = AdminDateTime::fieldsFromInput($request->validated(), ['starts_at', 'expires_at']);
        $data['is_active'] = $data['is_active'] ?? true;
        Coupon::create($data);

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon created.');
    }

    public function edit(Coupon $coupon): Response
    {
        return Inertia::render('admin/coupons/edit', [
            'coupon' => [
                ...$coupon->toArray(),
                'starts_at_input' => AdminDateTime::toInput($coupon->starts_at),
                'expires_at_input' => AdminDateTime::toInput($coupon->expires_at),
            ],
        ]);
    }

    public function update(CouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update(AdminDateTime::fieldsFromInput($request->validated(), ['starts_at', 'expires_at']));

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon updated.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $coupon->delete();

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon deleted.');
    }

    public function updateStatusMany(Request $request): RedirectResponse
    {
        $result = $this->updateStatusInBulk($request, Coupon::class);

        return redirect()->route('admin.coupons.index')->with('success', $result['count'].' coupon(s) '.($result['active'] ? 'activated' : 'deactivated').'.');
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:coupons,id'],
        ])['ids'];

        Coupon::whereIn('id', $ids)->delete();

        return redirect()->route('admin.coupons.index')->with('success', count($ids).' coupon(s) deleted.');
    }
}
