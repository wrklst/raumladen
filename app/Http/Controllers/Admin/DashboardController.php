<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Figures follow the user's areas: an editor is not shown the revenue.
     */
    public function __invoke(Request $request): Response
    {
        $monthStart = now()->startOfMonth();
        $user = $request->user();
        $orders = $user->worksIn('orders');

        return Inertia::render('admin/dashboard', [
            'stats' => [
                'revenue_month_cents' => $user->worksIn('payments') ? (int) Order::query()->whereNotNull('paid_at')->where('paid_at', '>=', $monthStart)->sum('total_cents') : null,
                'orders_month' => $orders ? Order::query()->where('created_at', '>=', $monthStart)->count() : null,
                'orders_to_ship' => $orders ? Order::query()->where('status', OrderStatus::Paid)->count() : null,
                'orders_pending_payment' => $orders ? Order::query()->where('status', OrderStatus::Pending)->count() : null,
                'low_stock' => $user->worksIn('catalog') ? Product::query()->active()->where('track_stock', true)->where('stock', '<=', 3)->count() : null,
            ],
            'recent_orders' => $orders ? OrderResource::collection(Order::query()->latest('id')->limit(10)->get()) : null,
        ]);
    }
}
