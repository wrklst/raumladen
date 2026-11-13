import { Head, Link } from '@inertiajs/react';
import OrderController from '@/actions/App/Http/Controllers/Admin/OrderController';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useLabels } from '@/hooks/use-labels';
import { useTrans } from '@/hooks/use-translation';
import { formatDate } from '@/lib/format';
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
        revenue_month_cents: number | null;
        orders_month: number | null;
        orders_to_ship: number | null;
        orders_pending_payment: number | null;
        low_stock: number | null;
    };
    recent_orders: Order[] | null;
}) {
    const { t, locale } = useTrans();
    const { orderStatus } = useLabels();
    // The server sends only the figures of this user's areas.
    const cards = (
        [
            [
                t('Revenue this month'),
                stats.revenue_month_cents === null
                    ? null
                    : formatMoney(stats.revenue_month_cents, 'EUR', locale),
            ],
            [t('Orders this month'), stats.orders_month],
            [t('To ship'), stats.orders_to_ship],
            [t('Awaiting payment'), stats.orders_pending_payment],
            [t('Low stock'), stats.low_stock],
        ] as [string, string | number | null][]
    ).filter(([, value]) => value !== null);

    return (
        <>
            <Head title={t('Dashboard')} />

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

                {recent_orders && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Recent orders')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {recent_orders.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('No orders yet.')}
                                </p>
                            ) : (
                                <table className="w-full text-sm">
                                    <thead className="text-left text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">
                                                {t('Number')}
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                {t('Customer')}
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                {t('Status')}
                                            </th>
                                            <th className="px-3 py-2 text-right font-medium">
                                                {t('Total')}
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                {t('Date')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recent_orders.map((order) => (
                                            <tr
                                                key={order.id}
                                                className="border-t"
                                            >
                                                <td className="px-3 py-2">
                                                    <Link
                                                        href={OrderController.show(
                                                            {
                                                                order: order.uuid,
                                                            },
                                                        )}
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
                                                            statusVariant[
                                                                order.status
                                                            ]
                                                        }
                                                    >
                                                        {orderStatus(
                                                            order.status,
                                                        )}
                                                    </Badge>
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    {formatMoney(
                                                        order.total_cents,
                                                        order.currency,
                                                        locale,
                                                    )}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {formatDate(
                                                        order.created_at,
                                                        locale,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
