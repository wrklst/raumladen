import { Form } from '@inertiajs/react';
import WithdrawalController from '@/actions/App/Http/Controllers/Shop/WithdrawalController';
import { Field } from '@/components/shop/field';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/hooks/use-translation';

type Props = {
    defaults: {
        order_number: string;
        name: string | null;
        email: string | null;
    };
    received_at: string | null;
};

/**
 * Electronic withdrawal: one form, one confirming button, then the
 * acknowledgement with the time of receipt (also sent by email).
 */
export default function Withdrawal({ defaults, received_at }: Props) {
    const { t, locale } = useTrans();

    if (received_at) {
        return (
            <div className="max-w-2xl space-y-4" role="status">
                <h1 className="text-3xl leading-[1.1]">
                    {t('We have received your withdrawal')}
                </h1>
                <p>
                    {t('Received on :date at :time.', {
                        date: new Date(received_at).toLocaleDateString(locale, {
                            timeZone: 'Europe/Berlin',
                        }),
                        time: new Date(received_at).toLocaleTimeString(locale, {
                            timeZone: 'Europe/Berlin',
                            timeStyle: 'short',
                        }),
                    })}{' '}
                    {t('A confirmation is on its way to your email address.')}
                </p>
            </div>
        );
    }

    return (
        <div className="max-w-2xl space-y-6">
            <div className="space-y-2">
                <h1 className="text-3xl leading-[1.1]">
                    {t('Withdraw from contract')}
                </h1>
                <p>
                    {t(
                        'Use this form to withdraw from a purchase. You will find the order number in your order confirmation.',
                    )}
                </p>
                <p className="text-sm">
                    {t('Fields marked with * are required.')}
                </p>
            </div>

            <Form {...WithdrawalController.store.form()} className="space-y-4">
                {({ processing, errors }) => (
                    <>
                        <Field
                            label={t('Order no.')}
                            name="order_number"
                            defaultValue={defaults.order_number}
                            error={errors.order_number}
                            required
                        />
                        <Field
                            label={t('Name')}
                            name="name"
                            defaultValue={defaults.name}
                            error={errors.name}
                            autoComplete="name"
                            required
                        />
                        <Field
                            label={t('Email address')}
                            name="email"
                            type="email"
                            defaultValue={defaults.email}
                            error={errors.email}
                            autoComplete="email"
                            required
                        />
                        <div className="grid gap-1">
                            <Label htmlFor="statement">
                                {t(
                                    'Which items do you withdraw from? (optional, leave empty for the whole order)',
                                )}
                            </Label>
                            <textarea
                                id="statement"
                                name="statement"
                                rows={3}
                                maxLength={2000}
                                className="w-full border border-input bg-background px-3 py-2 text-sm"
                            />
                        </div>
                        <Button type="submit" size="lg" disabled={processing}>
                            {t('Confirm withdrawal')}
                        </Button>
                    </>
                )}
            </Form>
        </div>
    );
}
