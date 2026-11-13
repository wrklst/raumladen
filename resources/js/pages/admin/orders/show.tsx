import { Form, Head, Link, usePage } from '@inertiajs/react';
import CustomerController from '@/actions/App/Http/Controllers/Admin/CustomerController';
import InvoiceController from '@/actions/App/Http/Controllers/Admin/InvoiceController';
import OrderCancellationController from '@/actions/App/Http/Controllers/Admin/OrderCancellationController';
import OrderController from '@/actions/App/Http/Controllers/Admin/OrderController';
import OrderPaymentConfirmationController from '@/actions/App/Http/Controllers/Admin/OrderPaymentConfirmationController';
import OrderRefundController from '@/actions/App/Http/Controllers/Admin/OrderRefundController';
import OrderShipmentController from '@/actions/App/Http/Controllers/Admin/OrderShipmentController';
import { AddressBlock } from '@/components/shop/address-block';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLabels } from '@/hooks/use-labels';
import { useTrans } from '@/hooks/use-translation';
import { formatDate } from '@/lib/format';
import { formatMoney, formatRate } from '@/lib/money';
import { index } from '@/routes/admin/orders';
import type { Order } from '@/types';

const statusVariant = {
    pending: 'outline',
    paid: 'default',
    shipped: 'secondary',
    cancelled: 'destructive',
    refunded: 'destructive',
} as const;

type Props = {
    order: Order;
    customer: {
        id: number;
        name: string;
        email: string;
        group: string | null;
    } | null;
};

export default function OrderShow({ order, customer }: Props) {
    const { t, locale } = useTrans();
    // Actions are offered by backend area; the routes enforce the same.
    const { areas } = usePage<{ auth: { user: { areas: string[] } } }>().props
        .auth.user;
    const can = (area: string): boolean => areas.includes(area);
    const { orderStatus, invoiceType, paymentStatus, taxTreatment } =
        useLabels();
    const date = (iso: string | null | undefined) => formatDate(iso, locale);
    const money = (cents: number) => formatMoney(cents, order.currency, locale);
    const isOpen = order.status !== 'cancelled' && order.status !== 'refunded';
    const canRefundItems =
        order.status === 'paid' || order.status === 'shipped';
    const refundableItems = (order.items ?? []).filter(
        (item) => item.quantity > item.refunded_quantity,
    );
    const shippingRefundable =
        order.shipping_net_cents + order.shipping_tax_cents > 0 &&
        !order.shipping_refunded;

    return (
        <>
            <Head title={t('Order :number', { number: order.number })} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="flex items-center gap-3 text-xl font-semibold">
                            {order.number}
                            <Badge variant={statusVariant[order.status]}>
                                {orderStatus(order.status)}
                            </Badge>
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'Placed :placed · Paid :paid · Shipped :shipped',
                                {
                                    placed: date(order.created_at),
                                    paid: date(order.paid_at),
                                    shipped: date(order.shipped_at),
                                },
                            )}
                            {order.cancelled_at &&
                                ` · ${t('Cancelled :date', { date: date(order.cancelled_at) })}`}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {order.status === 'pending' && can('payments') && (
                            <Form
                                {...OrderPaymentConfirmationController.store.form(
                                    {
                                        order: order.uuid,
                                    },
                                )}
                                onBefore={() =>
                                    confirm(
                                        t(
                                            'Confirm that the bank transfer for :number has been received? This issues the invoice.',
                                            { number: order.number },
                                        ),
                                    )
                                }
                            >
                                <Button type="submit" size="sm">
                                    {t('Confirm bank transfer received')}
                                </Button>
                            </Form>
                        )}
                        {order.status === 'paid' && can('shipping') && (
                            <Form
                                {...OrderShipmentController.store.form({
                                    order: order.uuid,
                                })}
                            >
                                <Button type="submit" size="sm">
                                    {t('Mark as shipped')}
                                </Button>
                            </Form>
                        )}
                        {isOpen && can('payments') && (
                            <Form
                                {...OrderCancellationController.store.form({
                                    order: order.uuid,
                                })}
                                onBefore={() =>
                                    confirm(
                                        t(
                                            'Cancel this order? Paid orders are refunded and receive a credit note.',
                                        ),
                                    )
                                }
                            >
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="destructive"
                                >
                                    {t('Cancel order / refund')}
                                </Button>
                            </Form>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Customer')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            {customer ? (
                                <>
                                    <div>{customer.name}</div>
                                    <div className="text-muted-foreground">
                                        {customer.email}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {t('Group: :group', {
                                            group: customer.group ?? '—',
                                        })}
                                    </div>
                                    <Link
                                        href={CustomerController.edit({
                                            user: customer.id,
                                        })}
                                        className="underline underline-offset-4"
                                    >
                                        {t('Edit customer')}
                                    </Link>
                                </>
                            ) : (
                                <>
                                    <div>{order.customer_name}</div>
                                    <div className="text-muted-foreground">
                                        {order.email}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {t('Guest checkout')}
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Billing address')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <AddressBlock
                                address={order.billing_address}
                                countryName={order.country_name}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Shipping address')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <AddressBlock address={order.shipping_address} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('VAT')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            <div className="flex items-center gap-2">
                                {order.vat_id ?? t('No VAT ID')}
                                {order.vat_id_valid && (
                                    <Badge>{t('verified')}</Badge>
                                )}
                            </div>
                            <div className="text-muted-foreground">
                                {taxTreatment(order.tax_treatment)}
                            </div>
                            {order.tax_note && (
                                <div className="text-muted-foreground">
                                    {order.tax_note}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Items')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <table className="w-full text-sm">
                            <thead className="text-left text-muted-foreground">
                                <tr>
                                    <th className="px-3 py-2 font-medium">
                                        {t('Name')}
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        {t('SKU')}
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        {t('Qty')}
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        {t('Unit price')}
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        {t('VAT')}
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        {t('Discount')}
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        {t('Total')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {(order.items ?? []).map((item) => (
                                    <tr key={item.id} className="border-t">
                                        <td className="px-3 py-2">
                                            {item.name}
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {item.sku ?? '—'}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            {item.quantity}
                                            {item.refunded_quantity > 0 && (
                                                <span className="text-muted-foreground">
                                                    {' '}
                                                    (
                                                    {t(':count refunded', {
                                                        count: item.refunded_quantity,
                                                    })}
                                                    )
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            {money(item.unit_price_cents)}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            {formatRate(item.tax_rate)}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            {item.discount_cents > 0
                                                ? `− ${money(item.discount_cents)}`
                                                : '—'}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            {money(item.line_gross_cents)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                {canRefundItems &&
                    can('payments') &&
                    (refundableItems.length > 0 || shippingRefundable) && (
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('Refund items')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    {...OrderRefundController.store.form({
                                        order: order.uuid,
                                    })}
                                    className="space-y-4"
                                    resetOnSuccess
                                    onBefore={() =>
                                        confirm(
                                            t(
                                                'Issue a credit note for the selected items and refund the amount?',
                                            ),
                                        )
                                    }
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <div className="space-y-2">
                                                {refundableItems.map((item) => {
                                                    const max =
                                                        item.quantity -
                                                        item.refunded_quantity;

                                                    return (
                                                        <div
                                                            key={item.id}
                                                            className="flex items-center gap-3 text-sm"
                                                        >
                                                            <Input
                                                                id={`refund-qty-${item.id}`}
                                                                type="number"
                                                                name={`quantities[${item.id}]`}
                                                                min={0}
                                                                max={max}
                                                                defaultValue={0}
                                                                className="w-20"
                                                            />
                                                            <Label
                                                                htmlFor={`refund-qty-${item.id}`}
                                                            >
                                                                {t(
                                                                    ':name (max :max)',
                                                                    {
                                                                        name: item.name,
                                                                        max,
                                                                    },
                                                                )}
                                                            </Label>
                                                        </div>
                                                    );
                                                })}
                                            </div>

                                            <div className="space-y-2 text-sm">
                                                {shippingRefundable && (
                                                    <div className="flex items-center gap-2">
                                                        <Checkbox
                                                            id="refund-shipping"
                                                            name="include_shipping"
                                                            value="1"
                                                        />
                                                        <Label htmlFor="refund-shipping">
                                                            {t(
                                                                'Include shipping',
                                                            )}
                                                        </Label>
                                                    </div>
                                                )}
                                                <div className="flex items-center gap-2">
                                                    <Checkbox
                                                        id="refund-restock"
                                                        name="restock"
                                                        value="1"
                                                        defaultChecked
                                                    />
                                                    <Label htmlFor="refund-restock">
                                                        {t(
                                                            'Return items to stock',
                                                        )}
                                                    </Label>
                                                </div>
                                            </div>

                                            {Object.values(errors).map(
                                                (message) => (
                                                    <p
                                                        key={message}
                                                        className="text-sm text-destructive"
                                                    >
                                                        {message}
                                                    </p>
                                                ),
                                            )}

                                            <Button
                                                type="submit"
                                                size="sm"
                                                disabled={processing}
                                            >
                                                {t('Refund selected items')}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    )}

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Totals')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="space-y-1 text-sm">
                                <div className="flex justify-between">
                                    <dt>{t('Subtotal (net)')}</dt>
                                    <dd>{money(order.subtotal_net_cents)}</dd>
                                </div>
                                {order.discount_cents > 0 && (
                                    <div className="flex justify-between">
                                        <dt>
                                            {t('Discount')}
                                            {order.coupon_code &&
                                                ` (${order.coupon_code})`}
                                        </dt>
                                        <dd>− {money(order.discount_cents)}</dd>
                                    </div>
                                )}
                                <div className="flex justify-between">
                                    <dt>{t('Shipping (net)')}</dt>
                                    <dd>
                                        {money(order.shipping_net_cents)}
                                        {order.shipping_tax_cents > 0 && (
                                            <span className="text-muted-foreground">
                                                {' '}
                                                {t('+ :amount VAT', {
                                                    amount: money(
                                                        order.shipping_tax_cents,
                                                    ),
                                                })}
                                            </span>
                                        )}
                                    </dd>
                                </div>
                                {order.tax_summary.map((group) => (
                                    <div
                                        key={group.rate}
                                        className="flex justify-between"
                                    >
                                        <dt>
                                            {t('VAT :rate on :net', {
                                                rate: formatRate(group.rate),
                                                net: money(group.net),
                                            })}
                                        </dt>
                                        <dd>{money(group.tax)}</dd>
                                    </div>
                                ))}
                                <div className="flex justify-between border-t pt-2 text-base font-semibold">
                                    <dt>{t('Total')}</dt>
                                    <dd>{money(order.total_cents)}</dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Invoices')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {(order.invoices ?? []).length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('No invoice issued yet.')}
                                </p>
                            ) : (
                                <ul className="space-y-2 text-sm">
                                    {(order.invoices ?? []).map((invoice) => (
                                        <li
                                            key={invoice.id}
                                            className="flex flex-wrap items-center justify-between gap-2"
                                        >
                                            <span className="flex items-center gap-2">
                                                <span className="font-medium">
                                                    {invoice.number}
                                                </span>
                                                <Badge
                                                    variant={
                                                        invoice.type ===
                                                        'credit_note'
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {invoiceType(invoice.type)}
                                                </Badge>
                                                <span className="text-muted-foreground">
                                                    {date(invoice.issued_at)}
                                                </span>
                                            </span>
                                            <span className="flex items-center gap-3">
                                                {money(
                                                    invoice.total_gross_cents,
                                                )}
                                                <a
                                                    href={InvoiceController.download.url(
                                                        {
                                                            invoice: invoice.id,
                                                        },
                                                    )}
                                                    className="underline underline-offset-4"
                                                >
                                                    {t('Download')}
                                                </a>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Payments')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {(order.payments ?? []).length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('No payments.')}
                            </p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead className="text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            {t('Gateway')}
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            {t('Method')}
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            {t('Status')}
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            {t('Amount')}
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            {t('Refunded')}
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            {t('Date')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(order.payments ?? []).map((payment) => (
                                        <tr
                                            key={payment.id}
                                            className="border-t"
                                        >
                                            <td className="px-3 py-2">
                                                {payment.gateway}
                                            </td>
                                            <td className="px-3 py-2">
                                                {payment.method ?? '—'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {paymentStatus(payment.status)}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                {money(payment.amount_cents)}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                {payment.refunded_cents > 0
                                                    ? money(
                                                          payment.refunded_cents,
                                                      )
                                                    : '—'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {date(payment.created_at)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </CardContent>
                </Card>

                {order.customer_note && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Customer note')}</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm whitespace-pre-line">
                            {order.customer_note}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

OrderShow.layout = ({ order }: Props) => ({
    breadcrumbs: [
        { title: 'Orders', href: index() },
        {
            title: order.number,
            href: OrderController.show({ order: order.uuid }),
        },
    ],
});
