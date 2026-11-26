<?php

namespace App\Http\Controllers\Shop;

use App\Cart\Cart;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Payment;
use App\Orders\DiscardDraft;
use App\Payments\PaymentManager;
use App\Seo\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(private PaymentManager $payments) {}

    /**
     * Customer returns from the payment provider: in the window the checkout
     * opened (it tells the checkout and closes), or in the page itself.
     */
    public function return(Request $request, Order $order, DiscardDraft $discardDraft): Response|RedirectResponse|View
    {
        Gate::authorize('view', $order);

        $payment = $this->sync($order);

        if ($request->boolean('window')) {
            return view('payment-window', ['continue' => route('shop.payments.return', $order)]);
        }

        if ($order->isPaid() || $payment?->gateway === 'bank_transfer' || ($order->isOpen() && $payment?->status->isUnderway())) {
            return $this->finished($request, $order);
        }

        // An online payment that came to nothing: back to the checkout, cart and all.
        if (! $order->isPlaced()) {
            $discardDraft->handle($order);

            return to_route('shop.checkout.show')->withErrors(['gateway' => __('The payment was not completed. Please try again or choose another payment method.')]);
        }

        return Inertia::render('shop/payment-failed', [
            'order' => new OrderResource($order),
            'gateways' => $this->payments->availableKeys(),
            'seo' => Seo::make(__('Payment'), null, route('shop.payments.return', $order), [], index: false),
        ]);
    }

    /**
     * Asked by the checkout while the customer pays in the window.
     */
    public function status(Request $request, Order $order, DiscardDraft $discardDraft): JsonResponse
    {
        Gate::authorize('view', $order);

        $payment = $this->sync($order);

        if ($order->isPaid() || ($order->isOpen() && $payment?->status->isUnderway())) {
            $this->finished($request, $order);

            return response()->json(['state' => 'done', 'url' => route('shop.orders.complete', $order)]);
        }

        if (! $order->isOpen() || $payment === null || $payment->status->hasFailed()) {
            $discardDraft->handle($order);

            return response()->json(['state' => 'failed']);
        }

        return response()->json(['state' => 'open']);
    }

    /**
     * The customer gives up the attempt at the checkout, to choose another method.
     */
    public function cancel(Request $request, Order $order, DiscardDraft $discardDraft): JsonResponse
    {
        Gate::authorize('view', $order);

        if ($discardDraft->handle($order)) {
            return response()->json(['state' => 'failed']);
        }

        $this->finished($request, $order);

        return response()->json(['state' => 'done', 'url' => route('shop.orders.complete', $order)]);
    }

    /**
     * Ask the provider how the latest attempt stands, unless the order is paid.
     */
    private function sync(Order $order): ?Payment
    {
        $payment = $order->payments()->latest('id')->first();

        if ($payment !== null && ! $order->isPaid()) {
            try {
                $this->payments->gateway($payment->gateway)->sync($payment);
            } catch (Throwable $e) {
                report($e);
            }
        }

        $order->refresh();

        return $payment?->refresh();
    }

    /**
     * The money is here or on its way: the cart the order came from is done with.
     */
    private function finished(Request $request, Order $order): RedirectResponse
    {
        if ($request->session()->pull(CheckoutController::DRAFT_KEY) === $order->uuid) {
            app(Cart::class)->clear();
        }

        return to_route('shop.orders.complete', $order);
    }

    /**
     * Start a new payment attempt for an unpaid order.
     */
    public function retry(Request $request, Order $order): SymfonyResponse
    {
        Gate::authorize('view', $order);

        if ($order->isPaid() || ! $order->isOpen()) {
            return to_route('shop.orders.complete', $order);
        }

        $gateway = $request->validate(['gateway' => ['required', 'string']])['gateway'];

        if (! in_array($gateway, $this->payments->availableKeys(), true)) {
            abort(422);
        }

        $url = $this->payments->gateway($gateway)->start(
            $order,
            route('shop.payments.return', $order),
            route('webhooks.payments', $gateway),
        );

        return Inertia::location($url);
    }
}
