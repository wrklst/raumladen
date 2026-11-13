import { Link } from '@inertiajs/react';
import OrderController from '@/actions/App/Http/Controllers/Account/OrderController';
import { AccountShell } from '@/components/shop/account-nav';
import { AddressBlock } from '@/components/shop/address-block';
import { OrderStatusBadge } from '@/components/shop/order-status-badge';
import { OrderSummary } from '@/components/shop/order-summary';
import { useTrans } from '@/hooks/use-translation';
import { formatMoney } from '@/lib/money';
import { create as withdraw } from '@/routes/shop/withdrawal';
import type { Order } from '@/types';

export default function OrderDetail({ order }: { order: Order }) {
    const { t, locale } = useTrans();
    const date = (iso: string | null) =>
        iso ? new Date(iso).toLocaleDateString(locale) : null;

    return (
        <AccountShell title={t('Order :number', { number: order.number })}>
            <div className="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                <OrderStatusBadge status={order.status} />
                <span>
                    {t('Ordered on :date', {
                        date: date(order.created_at) ?? '',
                    })}
                </span>
                {order.shipped_at && (
                    <span>
                        {t('Shipped on :date', {
                            date: date(order.shipped_at) ?? '',
                        })}
                    </span>
                )}
            </div>

            <OrderSummary order={order} />

            <div className="grid gap-6 sm:grid-cols-2">
                <div>
                    <h2 className="mb-1 text-sm font-medium text-muted-foreground">
                        {t('Shipping address')}
                    </h2>
                    <AddressBlock
                        address={order.shipping_address}
                        countryName={order.country_name}
                    />
                </div>
                <div>
                    <h2 className="mb-1 text-sm font-medium text-muted-foreground">
                        {t('Billing address')}
                    </h2>
                    <AddressBlock address={order.billing_address} />
                    {order.vat_id && (
                        <p className="mt-1 text-sm">
                            {t('VAT ID')}: {order.vat_id}
                        </p>
                    )}
                </div>
            </div>

            {order.invoices && order.invoices.length > 0 && (
                <div>
                    <h2 className="mb-2 text-sm font-medium text-muted-foreground">
                        {t('Documents')}
                    </h2>
                    <ul className="space-y-1 text-sm">
                        {order.invoices.map((invoice) => (
                            <li
                                key={invoice.id}
                                className="flex justify-between gap-4"
                            >
                                <a
                                    href={OrderController.invoice.url({
                                        order: order.uuid,
                                        invoice: invoice.id,
                                    })}
                                    className="underline underline-offset-4"
                                >
                                    {invoice.type === 'credit_note'
                                        ? t('Credit note :number', {
                                              number: invoice.number,
                                          })
                                        : t('Invoice :number', {
                                              number: invoice.number,
                                          })}
                                </a>
                                <span className="text-muted-foreground">
                                    {date(invoice.issued_at)} ·{' '}
                                    {formatMoney(
                                        invoice.total_gross_cents,
                                        order.currency,
                                        locale,
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <p className="text-sm">
                <Link
                    href={withdraw(undefined, {
                        query: { order: order.number },
                    })}
                    className="underline underline-offset-4"
                >
                    {t('Withdraw from contract')}
                </Link>
            </p>
        </AccountShell>
    );
}
