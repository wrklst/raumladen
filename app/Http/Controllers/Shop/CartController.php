<?php

namespace App\Http\Controllers\Shop;

use App\Cart\Cart;
use App\Cart\CartCalculator;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Product;
use App\Seo\Seo;
use App\Shop\CurrentCustomer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(private Cart $cart, private CartCalculator $calculator) {}

    public function index(): Response
    {
        $coupon = $this->cart->couponCode() === null ? null : Coupon::findByCode($this->cart->couponCode());

        $totals = $this->calculator->calculate(
            $this->cart->lines(),
            CurrentCustomer::group(),
            CurrentCustomer::country(),
            CurrentCustomer::vatIdValid(),
            $coupon,
        );

        return Inertia::render('shop/cart', [
            'cart' => $totals->toArray(),
            'seo' => Seo::make(__('Cart'), null, route('shop.cart.index'), Seo::alternates('shop.cart.index'), index: false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $product = Product::query()->active()->findOrFail($request->integer('product_id'));
        $quantity = (int) ($data['quantity'] ?? 1);
        $inCart = $this->cart->items()[$product->id] ?? 0;

        if (! $product->hasStockFor($inCart + $quantity)) {
            return back()->withErrors(['quantity' => __('Only :count left in stock.', ['count' => $product->stock])]);
        }

        $this->cart->add($product, $quantity);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':title added to cart.', ['title' => $product->translate('title')])]);

        return to_route('shop.cart.index');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:99']]);

        if (! $product->hasStockFor($data['quantity'])) {
            return back()->withErrors(['quantity' => __('Only :count left in stock.', ['count' => $product->stock])]);
        }

        $this->cart->update($product->id, $data['quantity']);

        return back();
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->cart->remove($product->id);

        return back();
    }
}
