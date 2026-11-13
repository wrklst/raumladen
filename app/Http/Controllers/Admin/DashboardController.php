<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $monthStart = now()->startOfMonth();

        return Inertia::render('admin/dashboard', [
            'stats' => [
                'revenue_month_cents' => (int) Order::query()->whereNotNull('paid_at')->where('paid_at', '>=', $monthStart)->sum('total_cents'),
                'orders_month' => Order::query()->where('created_at', '>=', $monthStart)->count(),
                'orders_to_ship' => Order::query()->where('status', OrderStatus::Paid)->count(),
                'orders_pending_payment' => Order::query()->where('status', OrderStatus::Pending)->count(),
                'low_stock' => Product::query()->active()->where('track_stock', true)->where('stock', '<=', 3)->count(),
            ],
            'recent_orders' => OrderResource::collection(Order::query()->latest('id')->limit(10)->get()),
        ]);
    }
}
