import { Head, Link } from '@inertiajs/react';
import OrderController from '@/actions/App/Http/Controllers/Admin/OrderController';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatMoney } from '@/lib/money';
import { dashboard } from '@/routes/admin';
import type { Order } from '@/types';

const statusVariant = {
    pending: 'outline',
    paid: 'default',
    shipped: 'secondary',
    cancelled: 'destructive',
    refunded: 'destructive',
} as const;

export default function Dashboard({
    stats,
    recent_orders,
}: {
    stats: {
        revenue_month_cents: number;
        orders_month: number;
        orders_to_ship: number;
        orders_pending_payment: number;
        low_stock: number;
    };
    recent_orders: Order[];
}) {
    const cards: [string, string | number][] = [
        ['Revenue this month', formatMoney(stats.revenue_month_cents, 'EUR', 'de')],
        ['Orders this month', stats.orders_month],
        ['To ship', stats.orders_to_ship],
        ['Awaiting payment', stats.orders_pending_payment],
        ['Low stock', stats.low_stock],
    ];

    return (
        <>
            <Head title="Dashboard" />

            <div className="space-y-6 p-4">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    {cards.map(([label, value]) => (
                        <Card key={label}>
                            <CardHeader>
                                <CardTitle className="text-sm font-medium text-muted-foreground">
                                    {label}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="text-2xl font-semibold">
                                {value}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent orders</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recent_orders.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No orders yet.
                            </p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead className="text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">Number</th>
                                        <th className="px-3 py-2 font-medium">Customer</th>
                                        <th className="px-3 py-2 font-medium">Status</th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            Total
                                        </th>
                                        <th className="px-3 py-2 font-medium">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recent_orders.map((order) => (
                                        <tr key={order.id} className="border-t">
                                            <td className="px-3 py-2">
                                                <Link
                                                    href={OrderController.show({
                                                        order: order.uuid,
                                                    })}
                                                    className="font-medium underline-offset-4 hover:underline"
                                                >
                                                    {order.number}
                                                </Link>
                                            </td>
                                            <td className="px-3 py-2">
                                                {order.customer_name}
                                            </td>
                                            <td className="px-3 py-2">
                                                <Badge
                                                    variant={
                                                        statusVariant[order.status]
                                                    }
                                                >
                                                    {order.status}
                                                </Badge>
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                {formatMoney(
                                                    order.total_cents,
                                                    order.currency,
                                                    'de',
                                                )}
                                            </td>
                                            <td className="px-3 py-2">
                                                {order.created_at
                                                    ? new Date(
                                                          order.created_at,
                                                      ).toLocaleDateString('de-DE')
                                                    : '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
