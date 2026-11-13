<?php

namespace App\Http\Controllers\Shop;

use App\Cart\Cart;
use App\Cart\CartCalculator;
use App\Checkout\OutOfStockException;
use App\Checkout\PlaceOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\CheckoutRequest;
use App\Models\Coupon;
use App\Models\Page;
use App\Payments\PaymentManager;
use App\Policies\OrderPolicy;
use App\Seo\Seo;
use App\Shop\CurrentCustomer;
use App\Support\Countries;
use App\Tax\VatIdValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(
        private Cart $cart,
        private CartCalculator $calculator,
        private VatIdValidator $vatIdValidator,
        private PaymentManager $payments,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        if ($this->cart->isEmpty()) {
            return to_route('shop.cart.index');
        }

        $user = $request->user();
        $country = strtoupper($request->string('country')->toString() ?: CurrentCustomer::country());
        $vatId = $request->string('vat_id')->toString() ?: ($user?->vat_id ?? '');
        $vatIdValid = $vatId !== '' && $this->vatIdValidator->validate($vatId)->valid;
        $coupon = $this->cart->couponCode() === null ? null : Coupon::findByCode($this->cart->couponCode());

        $totals = $this->calculator->calculate($this->cart->lines(), CurrentCustomer::group(), $country, $vatIdValid, $coupon);

        return Inertia::render('shop/checkout', [
            'cart' => $totals->toArray(),
            'countries' => Countries::options(),
            'gateways' => $this->payments->availableKeys(),
            'defaults' => [
                'email' => $user?->email,
                'billing_address' => $user?->billing_address,
                'shipping_address' => $user?->shipping_address,
                'vat_id' => $user?->vat_id,
                'company' => $user?->company,
            ],
            'legal_pages' => Page::query()->where('is_published', true)->whereIn('slug', ['terms', 'privacy', 'withdrawal'])->get()
                ->map(fn (Page $page) => ['slug' => $page->slug, 'title' => $page->translate('title')]),
            'seo' => Seo::make(__('Checkout'), null, route('shop.checkout.show'), Seo::alternates('shop.checkout.show'), index: false),
        ]);
    }

    public function store(CheckoutRequest $request, PlaceOrder $placeOrder): SymfonyResponse
    {
        $lines = $this->cart->lines();

        if ($lines->isEmpty()) {
            return to_route('shop.cart.index');
        }

        $vatId = $request->validated('vat_id');
        $vatIdValid = false;

        if ($vatId) {
            $result = $this->vatIdValidator->validate($vatId);
            $vatId = $result->vatId;

            if (! $result->valid && $result->serviceAvailable) {
                return back()->withErrors(['vat_id' => __('This VAT ID could not be confirmed. Remove it to order as a consumer.')])->withInput();
            }

            $vatIdValid = $result->valid;

            if (! $result->serviceAvailable) {
                Inertia::flash('toast', ['type' => 'warning', 'message' => __('The VAT ID check is temporarily unavailable; VAT has been charged.')]);
            }
        }

        $coupon = $this->cart->couponCode() === null ? null : Coupon::findByCode($this->cart->couponCode());

        try {
            $order = $placeOrder->handle($lines, $request->user(), CurrentCustomer::group(), [
                'email' => $request->validated('email'),
                'billing_address' => $request->validated('billing_address'),
                'shipping_address' => $request->shippingAddress(),
                'vat_id' => $vatId ?: null,
                'vat_id_valid' => $vatIdValid,
                'customer_note' => $request->validated('customer_note'),
                'locale' => app()->getLocale(),
            ], $coupon);
        } catch (OutOfStockException $e) {
            return to_route('shop.cart.index')->withErrors([
                'cart' => __(':title is no longer available in the requested quantity.', ['title' => $e->product->translate('title')]),
            ]);
        }

        $order->user?->update([
            'billing_address' => $order->billing_address,
            'shipping_address' => $order->shipping_address,
            'company' => $order->billing_address['company'] ?? null,
            'vat_id' => $vatId ?: null,
            'vat_id_validated_at' => $vatIdValid ? now() : null,
        ]);

        $request->session()->push(OrderPolicy::SESSION_KEY, $order->uuid);
        $this->cart->clear();

        try {
            $url = $this->payments->gateway($request->validated('gateway'))->start(
                $order,
                route('shop.payments.return', $order),
                route('webhooks.payments', $request->validated('gateway')),
            );
        } catch (Throwable $e) {
            report($e);

            return to_route('shop.payments.return', $order);
        }

        return Inertia::location($url);
    }
}
