import { useTrans } from '@/hooks/use-translation';
import { formatMoney, formatRate } from '@/lib/money';
import type { Order } from '@/types';

/**
 * Items and totals of a placed order, in the customer's price basis.
 */
export function OrderSummary({ order }: { order: Order }) {
    const { t, locale } = useTrans();
    const money = (cents: number) => formatMoney(cents, order.currency, locale);
    const gross = order.prices_include_tax;

    return (
        <div className="space-y-4">
            <table className="w-full text-sm">
                <caption className="sr-only">{t('Ordered items')}</caption>
                <thead className="text-left text-muted-foreground">
                    <tr>
                        <th scope="col" className="py-2 font-medium">
                            {t('Item')}
                        </th>
                        <th scope="col" className="py-2 text-right font-medium">
                            {t('Qty')}
                        </th>
                        <th scope="col" className="py-2 text-right font-medium">
                            {t('Total')}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {order.items?.map((item) => (
                        <tr key={item.id} className="border-t">
                            <td className="py-2">
                                {item.name}
                                {item.sku && (
                                    <span className="ml-2 text-xs text-muted-foreground">
                                        {item.sku}
                                    </span>
                                )}
                            </td>
                            <td className="py-2 text-right">{item.quantity}</td>
                            <td className="py-2 text-right">
                                {money(
                                    gross
                                        ? item.line_gross_cents
                                        : item.line_net_cents,
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            <dl className="ml-auto max-w-xs space-y-1 text-sm">
                {order.discount_cents > 0 && (
                    <div className="flex justify-between">
                        <dt>
                            {t('Discount')}
                            {order.coupon_code && ` (${order.coupon_code})`}
                        </dt>
                        <dd>− {money(order.discount_cents)}</dd>
                    </div>
                )}
                {order.shipping_net_cents + order.shipping_tax_cents > 0 && (
                    <div className="flex justify-between">
                        <dt>{t('Shipping')}</dt>
                        <dd>
                            {money(
                                gross
                                    ? order.shipping_net_cents +
                                          order.shipping_tax_cents
                                    : order.shipping_net_cents,
                            )}
                        </dd>
                    </div>
                )}
                {!gross && (
                    <div className="flex justify-between">
                        <dt>{t('Net total')}</dt>
                        <dd>
                            {money(
                                order.subtotal_net_cents +
                                    order.shipping_net_cents,
                            )}
                        </dd>
                    </div>
                )}
                {order.tax_summary
                    .filter((group) => group.tax > 0)
                    .map((group) => (
                        <div
                            key={group.rate}
                            className="flex justify-between text-muted-foreground"
                        >
                            <dt>
                                {gross
                                    ? t('incl. :rate VAT', {
                                          rate: formatRate(group.rate),
                                      })
                                    : t(':rate VAT', {
                                          rate: formatRate(group.rate),
                                      })}
                            </dt>
                            <dd>{money(group.tax)}</dd>
                        </div>
                    ))}
                <div className="flex justify-between border-t pt-1 font-semibold">
                    <dt>{t('Total')}</dt>
                    <dd>{money(order.total_cents)}</dd>
                </div>
            </dl>
            {order.tax_note && (
                <p className="ml-auto max-w-xs text-xs text-muted-foreground">
                    {order.tax_note}
                </p>
            )}
        </div>
    );
}
