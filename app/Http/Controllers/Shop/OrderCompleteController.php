<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Seo\Seo;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrderCompleteController extends Controller
{
    public function __invoke(Order $order): Response
    {
        Gate::authorize('view', $order);

        $order->load(['items', 'payments']);

        return Inertia::render('shop/order-complete', [
            'order' => new OrderResource($order),
            'bank' => $order->payment_gateway === null && $order->payments->last()?->gateway === 'bank_transfer'
                ? [
                    'name' => config('shop.seller.name'),
                    'bank' => config('shop.seller.bank'),
                    'iban' => config('shop.seller.iban'),
                    'bic' => config('shop.seller.bic'),
                    'reference' => $order->number,
                ]
                : null,
            'seo' => Seo::make(__('Thank you'), null, route('shop.orders.complete', $order), [], index: false),
        ]);
    }
}
