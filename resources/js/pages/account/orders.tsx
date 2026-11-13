import { Link } from '@inertiajs/react';
import { Pagination } from '@/components/pagination';
import { AccountShell } from '@/components/shop/account-nav';
import { OrderStatusBadge } from '@/components/shop/order-status-badge';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-translation';
import { formatMoney } from '@/lib/money';
import { home } from '@/routes/shop';
import { show } from '@/routes/shop/account/orders';
import type { Order, ResourcePaginated } from '@/types';

export default function Orders({ orders }: { orders: ResourcePaginated<Order> }) {
    const { t, locale } = useTrans();

    return (
        <AccountShell title={t('Your orders')}>
            {orders.data.length === 0 ? (
                <div className="space-y-3">
                    <p className="text-muted-foreground">{t('You have not ordered anything yet.')}</p>
                    <Button asChild>
                        <Link href={home()}>{t('Browse books')}</Link>
                    </Button>
                </div>
            ) : (
                <table className="w-full text-sm">
                    <thead className="text-left text-muted-foreground">
                        <tr>
                            <th className="py-2 font-medium">{t('Order')}</th>
                            <th className="py-2 font-medium">{t('Date')}</th>
                            <th className="py-2 font-medium">{t('Status')}</th>
                            <th className="py-2 text-right font-medium">{t('Total')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {orders.data.map((order) => (
                            <tr key={order.uuid} className="border-t">
                                <td className="py-2">
                                    <Link
                                        href={show({ order: order.uuid })}
                                        className="font-medium hover:underline"
                                    >
                                        {order.number}
                                    </Link>
                                </td>
                                <td className="py-2">
                                    {order.created_at &&
                                        new Date(order.created_at).toLocaleDateString(locale)}
                                </td>
                                <td className="py-2">
                                    <OrderStatusBadge status={order.status} />
                                </td>
                                <td className="py-2 text-right">
                                    {formatMoney(order.total_cents, order.currency, locale)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
            <Pagination links={orders.meta.links} />
        </AccountShell>
    );
}
