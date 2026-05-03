<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CouponRequest;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(Request $request): Response
    {
        $coupons = Coupon::when($request->search, fn ($q) => $q->where('code', 'ilike', "%{$request->search}%")->orWhere('name', 'ilike', "%{$request->search}%"))
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
        $data = $request->validated();
        $data['is_active'] = $data['is_active'] ?? true;
        Coupon::create($data);

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon created.');
    }

    public function edit(Coupon $coupon): Response
    {
        return Inertia::render('admin/coupons/edit', ['coupon' => $coupon]);
    }

    public function update(CouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($request->validated());

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon updated.');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $coupon->delete();

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon deleted.');
    }
}
