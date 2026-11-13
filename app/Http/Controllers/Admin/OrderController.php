<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $search = $request->string('q')->toString();

        $orders = Order::query()
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('number', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/orders/index', [
            'orders' => OrderResource::collection($orders),
            'filters' => ['status' => $status, 'q' => $search],
            'statuses' => array_map(fn (OrderStatus $s) => $s->value, OrderStatus::cases()),
        ]);
    }

    public function show(Order $order): Response
    {
        $order->load(['items', 'invoices', 'payments', 'user']);

        return Inertia::render('admin/orders/show', [
            'order' => new OrderResource($order),
            'customer' => $order->user === null ? null : [
                'id' => $order->user->id,
                'name' => $order->user->name,
                'email' => $order->user->email,
                'group' => $order->user->customerGroup?->name,
            ],
        ]);
    }
}
