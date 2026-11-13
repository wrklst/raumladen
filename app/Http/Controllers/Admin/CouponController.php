<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CouponType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CouponRequest;
use App\Models\Coupon;
use App\Models\CustomerGroup;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/coupons/index', [
            'coupons' => Coupon::query()->with('customerGroup')->latest('id')->paginate(25)->through(fn (Coupon $coupon) => [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'type' => $coupon->type->value,
                'value' => $coupon->value,
                'min_order_cents' => $coupon->min_order_cents,
                'uses' => $coupon->uses,
                'max_uses' => $coupon->max_uses,
                'expires_at' => $coupon->expires_at?->toDateString(),
                'is_active' => $coupon->is_active,
                'group' => $coupon->customerGroup?->name,
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/coupons/form', $this->formProps(null));
    }

    public function store(CouponRequest $request): RedirectResponse
    {
        Coupon::query()->create($request->couponAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Coupon created.']);

        return to_route('admin.coupons.index');
    }

    public function edit(Coupon $coupon): Response
    {
        return Inertia::render('admin/coupons/form', $this->formProps($coupon));
    }

    public function update(CouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($request->couponAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Coupon updated.']);

        return to_route('admin.coupons.index');
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $coupon->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Coupon deleted.']);

        return to_route('admin.coupons.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?Coupon $coupon): array
    {
        return [
            'coupon' => $coupon === null ? null : [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'type' => $coupon->type->value,
                'value' => $coupon->type === CouponType::Fixed ? number_format($coupon->value / 100, 2, '.', '') : (string) $coupon->value,
                'min_order' => number_format($coupon->min_order_cents / 100, 2, '.', ''),
                'starts_at' => $coupon->starts_at?->format('Y-m-d'),
                'expires_at' => $coupon->expires_at?->format('Y-m-d'),
                'max_uses' => $coupon->max_uses,
                'customer_group_id' => $coupon->customer_group_id,
                'is_active' => $coupon->is_active,
            ],
            'groups' => CustomerGroup::query()->orderBy('id')->get(['id', 'name']),
            'types' => array_map(fn (CouponType $type) => $type->value, CouponType::cases()),
        ];
    }
}
