<?php

namespace App\Http\Controllers\Shop;

use App\Cart\Cart;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartCouponController extends Controller
{
    public function __construct(private Cart $cart) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:50']]);

        $coupon = Coupon::findByCode($data['code']);

        if ($coupon === null) {
            return back()->withErrors(['code' => __('This coupon code is not valid.')]);
        }

        $this->cart->setCouponCode($coupon->code);

        return back();
    }

    public function destroy(): RedirectResponse
    {
        $this->cart->setCouponCode(null);

        return back();
    }
}
