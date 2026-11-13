import { Link, usePage } from '@inertiajs/react';
import { AddressBlock } from '@/components/shop/address-block';
import { OrderSummary } from '@/components/shop/order-summary';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-translation';
import { formatMoney } from '@/lib/money';
import { home } from '@/routes/shop';
import { index as accountOrders } from '@/routes/shop/account/orders';
import type { Order, ShopSharedProps } from '@/types';

type Props = {
    order: Order;
    bank: {
        name: string;
        bank: string;
        iban: string;
        bic: string;
        reference: string;
    } | null;
};

export default function OrderComplete({ order, bank }: Props) {
    const { t, locale } = useTrans();
    const { auth } = usePage<ShopSharedProps>().props;

    return (
        <div className="mx-auto max-w-2xl space-y-8">
            <header className="space-y-2">
                <h1 className="text-3xl font-semibold tracking-tight">
                    {t('Thank you for your order')}
                </h1>
                <p className="text-muted-foreground">
                    {t('Order :number. A confirmation has been sent to :email.', {
                        number: order.number,
                        email: order.email,
                    })}
                </p>
            </header>

            {bank && (
                <Alert>
                    <AlertTitle>{t('Please transfer the amount')}</AlertTitle>
                    <AlertDescription>
                        <dl className="mt-2 grid grid-cols-[max-content_1fr] gap-x-4 gap-y-1 text-sm">
                            <dt>{t('Amount')}</dt>
                            <dd className="font-medium">
                                {formatMoney(order.total_cents, order.currency, locale)}
                            </dd>
                            <dt>{t('Recipient')}</dt>
                            <dd>{bank.name}</dd>
                            <dt>IBAN</dt>
                            <dd className="font-mono">{bank.iban}</dd>
                            <dt>BIC</dt>
                            <dd className="font-mono">{bank.bic}</dd>
                            <dt>{t('Bank')}</dt>
                            <dd>{bank.bank}</dd>
                            <dt>{t('Reference')}</dt>
                            <dd className="font-medium">{bank.reference}</dd>
                        </dl>
                        <p className="mt-2">
                            {t('We ship as soon as the payment arrives; your invoice follows by email.')}
                        </p>
                    </AlertDescription>
                </Alert>
            )}

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
                </div>
            </div>

            <div className="flex gap-3">
                <Button asChild>
                    <Link href={home()}>{t('Continue shopping')}</Link>
                </Button>
                {auth.user && (
                    <Button asChild variant="outline">
                        <Link href={accountOrders()}>{t('Your orders')}</Link>
                    </Button>
                )}
            </div>
        </div>
    );
}
