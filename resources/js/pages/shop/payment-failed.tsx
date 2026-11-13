import { Form } from '@inertiajs/react';
import PaymentController from '@/actions/App/Http/Controllers/Shop/PaymentController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-translation';
import { formatMoney } from '@/lib/money';
import type { Order } from '@/types';

const gatewayLabels: Record<string, string> = {
    mollie: 'Card, PayPal, SEPA and more (Mollie)',
    bank_transfer: 'Prepayment by bank transfer',
};

export default function PaymentFailed({
    order,
    gateways,
}: {
    order: Order;
    gateways: string[];
}) {
    const { t, locale } = useTrans();

    return (
        <div className="mx-auto max-w-lg space-y-6">
            <header className="space-y-2">
                <h1 className="text-2xl font-semibold">
                    {t('Payment not completed')}
                </h1>
                <p className="text-muted-foreground">
                    {t('Your order :number (:total) is reserved. Choose a payment method to try again.', {
                        number: order.number,
                        total: formatMoney(order.total_cents, order.currency, locale),
                    })}
                </p>
            </header>

            <Form
                {...PaymentController.retry.form({ order: order.uuid })}
                className="space-y-4"
            >
                {({ processing, errors }) => (
                    <>
                        {gateways.map((gateway, index) => (
                            <label
                                key={gateway}
                                className="flex items-center gap-3 rounded-md border p-3 text-sm"
                            >
                                <input
                                    type="radio"
                                    name="gateway"
                                    value={gateway}
                                    defaultChecked={index === 0}
                                    required
                                />
                                {t(gatewayLabels[gateway] ?? gateway)}
                            </label>
                        ))}
                        <InputError message={errors.gateway} />
                        <Button type="submit" size="lg" disabled={processing}>
                            {t('Pay now')}
                        </Button>
                    </>
                )}
            </Form>
        </div>
    );
}
