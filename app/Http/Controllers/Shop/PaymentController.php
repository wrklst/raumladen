<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Payments\PaymentManager;
use App\Seo\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(private PaymentManager $payments) {}

    /**
     * Customer returns from the payment provider.
     */
    public function return(Order $order): Response|RedirectResponse
    {
        Gate::authorize('view', $order);

        $payment = $order->payments()->latest('id')->first();

        if ($payment !== null && ! $order->isPaid()) {
            try {
                $this->payments->gateway($payment->gateway)->sync($payment);
            } catch (Throwable $e) {
                report($e);
            }
        }

        $order->refresh();

        if ($order->isPaid() || $payment?->gateway === 'bank_transfer') {
            return to_route('shop.orders.complete', $order);
        }

        return Inertia::render('shop/payment-failed', [
            'order' => new OrderResource($order),
            'gateways' => $this->payments->availableKeys(),
            'seo' => Seo::make(__('Payment'), null, route('shop.payments.return', $order), [], index: false),
        ]);
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
