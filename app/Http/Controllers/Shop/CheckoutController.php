<?php

namespace App\Http\Controllers\Shop;

use App\Cart\Cart;
use App\Cart\CartCalculator;
use App\Checkout\OutOfStockException;
use App\Checkout\PlaceOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\CheckoutRequest;
use App\Mail\OrderReceived;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Page;
use App\Orders\DiscardDraft;
use App\Payments\MollieGateway;
use App\Payments\PaymentGateway;
use App\Payments\PaymentManager;
use App\Policies\OrderPolicy;
use App\Seo\Seo;
use App\Shop\CurrentCustomer;
use App\Support\Countries;
use App\Tax\VatIdValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class CheckoutController extends Controller
{
    /**
     * Session key: the uuid of the order this visitor is paying for online.
     */
    public const string DRAFT_KEY = 'checkout.draft';

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
        $vatId = $request->string('vat_id')->toString() ?: ($user->vat_id ?? '');
        $vatIdValid = $vatId !== '' && $this->vatIdValidator->validate($vatId)->valid;
        $coupon = $this->cart->couponCode() === null ? null : Coupon::findByCode($this->cart->couponCode());

        $totals = $this->calculator->calculate($this->cart->lines(), CurrentCustomer::group(), $country, $vatIdValid, $coupon);

        return Inertia::render('shop/checkout', [
            'cart' => $totals->toArray(),
            'countries' => Countries::options(),
            'gateways' => $this->payments->availableKeys(),
            'payment_choices' => $this->payments->choices(app()->getLocale()),
            // For card fields in the page (Mollie Components); null without Mollie.
            'card_fields' => in_array('mollie', $this->payments->availableKeys(), true) ? app(MollieGateway::class)->components() : null,
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

    public function store(CheckoutRequest $request, PlaceOrder $placeOrder, DiscardDraft $discardDraft): SymfonyResponse
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

        $gateway = $this->payments->gateway($request->validated('gateway'));

        // A second try: the attempt before it is over, unless its money did arrive after all.
        if ($previous = $this->draft($request)) {
            if (! $discardDraft->handle($previous)) {
                return $this->afterPayment($request, $previous);
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
                // Paid online, the order is placed when the payment is confirmed.
                'placed' => $gateway->paysLater(),
            ], $coupon);
        } catch (OutOfStockException $e) {
            return to_route('shop.cart.index')->withErrors([
                'cart' => __(':title is no longer available in the requested quantity.', ['title' => $e->product->translate('title')]),
            ]);
        }

        // A guest order under an existing customer's email must not rewrite
        // that customer's stored profile: anyone can type any email.
        $ownsProfile = $request->user() !== null || $order->user?->billing_address === null;

        ($ownsProfile ? $order->user : null)?->update([
            'billing_address' => $order->billing_address,
            'shipping_address' => $order->shipping_address,
            'company' => $order->billing_address['company'] ?? null,
            'vat_id' => $vatId ?: null,
            'vat_id_validated_at' => $vatIdValid ? now() : null,
        ]);

        $request->session()->push(OrderPolicy::SESSION_KEY, $order->uuid);

        return $gateway->paysLater()
            ? $this->placeAndPayLater($request, $order, $gateway)
            : $this->payNow($request, $order, $gateway, $discardDraft);
    }

    /**
     * Bank transfer: the order stands, the customer is told where to send the money.
     */
    private function placeAndPayLater(CheckoutRequest $request, Order $order, PaymentGateway $gateway): SymfonyResponse
    {
        $this->cart->clear();

        try {
            Mail::to($order->email)->locale($order->locale)->send(new OrderReceived($order, true));
        } catch (Throwable $e) {
            // The order stands; a mail problem must not strand the customer before payment.
            report($e);
        }

        try {
            $url = $gateway->start($order, route('shop.payments.return', $order), route('webhooks.payments', $gateway->key()));
        } catch (Throwable $e) {
            report($e);

            return to_route('shop.payments.return', $order);
        }

        return Inertia::location($url);
    }

    /**
     * Online payment: the customer stays at the checkout. The cart is kept
     * and no mail goes out until the payment is confirmed; whatever the
     * provider needs from the customer opens in a window over the checkout
     * (see pages/shop/checkout.tsx), which is told where to look by flash data.
     */
    private function payNow(CheckoutRequest $request, Order $order, PaymentGateway $gateway, DiscardDraft $discardDraft): SymfonyResponse
    {
        $request->session()->put(self::DRAFT_KEY, $order->uuid);

        try {
            $url = $gateway->start(
                $order,
                route('shop.payments.return', ['order' => $order, 'window' => 1]),
                route('webhooks.payments', $gateway->key()),
                ['method' => $request->validated('method'), 'card_token' => $request->validated('card_token')],
            );
        } catch (Throwable $e) {
            report($e);
            $discardDraft->handle($order);

            return back()->withErrors(['gateway' => __('The payment could not be started. Please try again or choose another payment method.')]);
        }

        $order->refresh();
        $payment = $order->payments()->latest('id')->first();

        // A card that its bank does not ask about is charged, or refused, at once.
        if ($order->isPlaced()) {
            return $this->afterPayment($request, $order);
        }

        if ($payment?->status->hasFailed()) {
            $discardDraft->handle($order);

            return back()->withErrors(['gateway' => __('The payment was declined. Please check your details or choose another payment method.')]);
        }

        Inertia::flash('payment', [
            'url' => $url,
            'status' => route('shop.payments.status', $order),
            'cancel' => route('shop.payments.cancel', $order),
        ]);

        return back();
    }

    /**
     * The draft this visitor is paying for, if any.
     */
    private function draft(Request $request): ?Order
    {
        $uuid = $request->session()->get(self::DRAFT_KEY);

        return $uuid === null ? null : Order::query()->where('uuid', $uuid)->first();
    }

    /**
     * The money is here or on its way: the cart is done with.
     */
    private function afterPayment(Request $request, Order $order): RedirectResponse
    {
        $this->cart->clear();
        $request->session()->forget(self::DRAFT_KEY);

        return to_route('shop.orders.complete', $order);
    }
}
