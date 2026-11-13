import { Form, Head, Link } from '@inertiajs/react';
import CustomerController from '@/actions/App/Http/Controllers/Admin/CustomerController';
import InvoiceController from '@/actions/App/Http/Controllers/Admin/InvoiceController';
import OrderCancellationController from '@/actions/App/Http/Controllers/Admin/OrderCancellationController';
import OrderController from '@/actions/App/Http/Controllers/Admin/OrderController';
import OrderPaymentConfirmationController from '@/actions/App/Http/Controllers/Admin/OrderPaymentConfirmationController';
import OrderShipmentController from '@/actions/App/Http/Controllers/Admin/OrderShipmentController';
import { AddressBlock } from '@/components/shop/address-block';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    customer: { id: number; name: string; email: string; group: string | null } | null;
};

const date = (iso: string | null | undefined) =>
    iso ? new Date(iso).toLocaleDateString('de-DE') : '—';

export default function OrderShow({ order, customer }: Props) {
    const money = (cents: number) => formatMoney(cents, order.currency, 'de');
    const isOpen = order.status !== 'cancelled' && order.status !== 'refunded';

    return (
        <>
            <Head title={`Order ${order.number}`} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="flex items-center gap-3 text-xl font-semibold">
                            {order.number}
                            <Badge variant={statusVariant[order.status]}>
                                {order.status}
                            </Badge>
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Placed {date(order.created_at)} · Paid {date(order.paid_at)} ·
                            Shipped {date(order.shipped_at)}
                            {order.cancelled_at && ` · Cancelled ${date(order.cancelled_at)}`}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {order.status === 'pending' && (
                            <Form
                                {...OrderPaymentConfirmationController.store.form({
                                    order: order.uuid,
                                })}
                                onBefore={() =>
                                    confirm(
                                        `Confirm that the bank transfer for ${order.number} has been received? This issues the invoice.`,
                                    )
                                }
                            >
                                <Button type="submit" size="sm">
                                    Confirm bank transfer received
                                </Button>
                            </Form>
                        )}
                        {order.status === 'paid' && (
                            <Form
                                {...OrderShipmentController.store.form({
                                    order: order.uuid,
                                })}
                            >
                                <Button type="submit" size="sm">
                                    Mark as shipped
                                </Button>
                            </Form>
                        )}
                        {isOpen && (
                            <Form
                                {...OrderCancellationController.store.form({
                                    order: order.uuid,
                                })}
                                onBefore={() =>
                                    confirm(
                                        'Cancel this order? Paid orders are refunded and receive a credit note.',
                                    )
                                }
                            >
                                <Button type="submit" size="sm" variant="destructive">
                                    Cancel order / refund
                                </Button>
                            </Form>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Customer</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            {customer ? (
                                <>
                                    <div>{customer.name}</div>
                                    <div className="text-muted-foreground">
                                        {customer.email}
                                    </div>
                                    <div className="text-muted-foreground">
                                        Group: {customer.group ?? '—'}
                                    </div>
                                    <Link
                                        href={CustomerController.edit({ user: customer.id })}
                                        className="underline underline-offset-4"
                                    >
                                        Edit customer
                                    </Link>
                                </>
                            ) : (
                                <>
                                    <div>{order.customer_name}</div>
                                    <div className="text-muted-foreground">{order.email}</div>
                                    <div className="text-muted-foreground">Guest checkout</div>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Billing address</CardTitle>
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
                            <CardTitle>Shipping address</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <AddressBlock address={order.shipping_address} />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>VAT</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            <div className="flex items-center gap-2">
                                {order.vat_id ?? 'No VAT ID'}
                                {order.vat_id_valid && <Badge>verified</Badge>}
                            </div>
                            <div className="text-muted-foreground">{order.tax_treatment}</div>
                            {order.tax_note && (
                                <div className="text-muted-foreground">{order.tax_note}</div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Items</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <table className="w-full text-sm">
                            <thead className="text-left text-muted-foreground">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Name</th>
                                    <th className="px-3 py-2 font-medium">SKU</th>
                                    <th className="px-3 py-2 text-right font-medium">Qty</th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        Unit price
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">VAT</th>
                                    <th className="px-3 py-2 text-right font-medium">
                                        Discount
                                    </th>
                                    <th className="px-3 py-2 text-right font-medium">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {(order.items ?? []).map((item) => (
                                    <tr key={item.id} className="border-t">
                                        <td className="px-3 py-2">{item.name}</td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {item.sku ?? '—'}
                                        </td>
                                        <td className="px-3 py-2 text-right">{item.quantity}</td>
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

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Totals</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="space-y-1 text-sm">
                                <div className="flex justify-between">
                                    <dt>Subtotal (net)</dt>
                                    <dd>{money(order.subtotal_net_cents)}</dd>
                                </div>
                                {order.discount_cents > 0 && (
                                    <div className="flex justify-between">
                                        <dt>
                                            Discount
                                            {order.coupon_code && ` (${order.coupon_code})`}
                                        </dt>
                                        <dd>− {money(order.discount_cents)}</dd>
                                    </div>
                                )}
                                <div className="flex justify-between">
                                    <dt>Shipping (net)</dt>
                                    <dd>
                                        {money(order.shipping_net_cents)}
                                        {order.shipping_tax_cents > 0 && (
                                            <span className="text-muted-foreground">
                                                {' '}
                                                + {money(order.shipping_tax_cents)} VAT
                                            </span>
                                        )}
                                    </dd>
                                </div>
                                {order.tax_summary.map((group) => (
                                    <div key={group.rate} className="flex justify-between">
                                        <dt>
                                            VAT {formatRate(group.rate)} on {money(group.net)}
                                        </dt>
                                        <dd>{money(group.tax)}</dd>
                                    </div>
                                ))}
                                <div className="flex justify-between border-t pt-2 text-base font-semibold">
                                    <dt>Total</dt>
                                    <dd>{money(order.total_cents)}</dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Invoices</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {(order.invoices ?? []).length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No invoice issued yet.
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
                                                        invoice.type === 'credit_note'
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                >
                                                    {invoice.type}
                                                </Badge>
                                                <span className="text-muted-foreground">
                                                    {date(invoice.issued_at)}
                                                </span>
                                            </span>
                                            <span className="flex items-center gap-3">
                                                {money(invoice.total_gross_cents)}
                                                <a
                                                    href={InvoiceController.download.url({
                                                        invoice: invoice.id,
                                                    })}
                                                    className="underline underline-offset-4"
                                                >
                                                    Download
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
                        <CardTitle>Payments</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {(order.payments ?? []).length === 0 ? (
                            <p className="text-sm text-muted-foreground">No payments.</p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead className="text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">Gateway</th>
                                        <th className="px-3 py-2 font-medium">Method</th>
                                        <th className="px-3 py-2 font-medium">Status</th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            Amount
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            Refunded
                                        </th>
                                        <th className="px-3 py-2 font-medium">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(order.payments ?? []).map((payment) => (
                                        <tr key={payment.id} className="border-t">
                                            <td className="px-3 py-2">{payment.gateway}</td>
                                            <td className="px-3 py-2">
                                                {payment.method ?? '—'}
                                            </td>
                                            <td className="px-3 py-2">{payment.status}</td>
                                            <td className="px-3 py-2 text-right">
                                                {money(payment.amount_cents)}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                {payment.refunded_cents > 0
                                                    ? money(payment.refunded_cents)
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
                            <CardTitle>Customer note</CardTitle>
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
        { title: order.number, href: OrderController.show({ order: order.uuid }) },
    ],
});
