import { Head, Link } from '@inertiajs/react';
import OrderController from '@/actions/App/Http/Controllers/Admin/OrderController';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useLabels } from '@/hooks/use-labels';
import { useTrans } from '@/hooks/use-translation';
import { formatDate } from '@/lib/format';
import { formatMoney } from '@/lib/money';
import { index } from '@/routes/admin/orders';
import type { Order, ResourcePaginated } from '@/types';

const statusVariant = {
    pending: 'outline',
    paid: 'default',
    shipped: 'secondary',
    cancelled: 'destructive',
    refunded: 'destructive',
} as const;

export default function OrdersIndex({
    orders,
    filters,
    statuses,
}: {
    orders: ResourcePaginated<Order>;
    filters: { status: string; q: string };
    statuses: string[];
}) {
    const { t, locale } = useTrans();
    const { orderStatus, taxTreatment } = useLabels();

    return (
        <>
            <Head title={t('Orders')} />

            <div className="space-y-6 p-4">
                <form
                    method="get"
                    action={index.url()}
                    className="flex flex-wrap items-center gap-2"
                >
                    <select
                        name="status"
                        defaultValue={filters.status}
                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                    >
                        <option value="">{t('All')}</option>
                        {statuses.map((status) => (
                            <option key={status} value={status}>
                                {orderStatus(status)}
                            </option>
                        ))}
                    </select>
                    <Input
                        name="q"
                        defaultValue={filters.q}
                        placeholder={t('Number, name or email')}
                        className="w-64"
                    />
                    <Button type="submit" variant="outline">
                        {t('Filter')}
                    </Button>
                </form>

                {orders.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('No orders found.')}
                    </p>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="text-left text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    {t('Number')}
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    {t('Date')}
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    {t('Customer')}
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    {t('Country')}
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    {t('Tax')}
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    {t('Status')}
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    {t('Total')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {orders.data.map((order) => (
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
                                        {formatDate(order.created_at, locale)}
                                    </td>
                                    <td className="px-3 py-2">
                                        <div>{order.customer_name}</div>
                                        <div className="text-xs text-muted-foreground">
                                            {order.email}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2">
                                        {order.country_name}
                                    </td>
                                    <td className="px-3 py-2 text-muted-foreground">
                                        {taxTreatment(order.tax_treatment)}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Badge
                                            variant={
                                                statusVariant[order.status]
                                            }
                                        >
                                            {orderStatus(order.status)}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {formatMoney(
                                            order.total_cents,
                                            order.currency,
                                            locale,
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}

                <Pagination links={orders.meta.links} />
            </div>
        </>
    );
}

OrdersIndex.layout = {
    breadcrumbs: [{ title: 'Orders', href: index() }],
};
