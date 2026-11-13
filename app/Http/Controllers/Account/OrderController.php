<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Invoice;
use App\Models\Order;
use App\Seo\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = $request->user()->orders()
            ->with('invoices')
            ->latest('id')
            ->paginate(20);

        return Inertia::render('account/orders', [
            'orders' => OrderResource::collection($orders),
            'seo' => Seo::make(__('Your orders'), null, route('shop.account.orders.index'), [], index: false),
        ]);
    }

    public function show(Order $order): Response
    {
        Gate::authorize('view', $order);

        $order->load(['items', 'invoices', 'payments']);

        return Inertia::render('account/order', [
            'order' => new OrderResource($order),
            'seo' => Seo::make(__('Order :number', ['number' => $order->number]), null, route('shop.account.orders.show', $order), [], index: false),
        ]);
    }

    public function invoice(Order $order, Invoice $invoice): StreamedResponse
    {
        Gate::authorize('view', $order);
        abort_unless($invoice->order_id === $order->id && $invoice->pdf_path !== null, 404);

        return Storage::disk(config('shop.invoice.disk'))->download($invoice->pdf_path, $invoice->number.'.pdf');
    }
}
