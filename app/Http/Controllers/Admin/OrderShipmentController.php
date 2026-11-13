<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Mail\OrderShipped;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class OrderShipmentController extends Controller
{
    public function store(Order $order): RedirectResponse
    {
        abort_unless($order->status === OrderStatus::Paid, 422, __('Only paid orders can be shipped.'));

        $order->update(['status' => OrderStatus::Shipped, 'shipped_at' => now()]);

        Mail::to($order->email)->locale($order->locale)->send(new OrderShipped($order));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order marked as shipped.')]);

        return back();
    }
}
