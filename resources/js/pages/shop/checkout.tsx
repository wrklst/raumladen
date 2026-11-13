import { Form, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import CheckoutController from '@/actions/App/Http/Controllers/Shop/CheckoutController';
import InputError from '@/components/input-error';
import { AddressFields } from '@/components/shop/address-fields';
import { Field } from '@/components/shop/field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/hooks/use-translation';
import { formatMoney, formatRate } from '@/lib/money';
import { index as cartIndex } from '@/routes/shop/cart';
import { show as pageShow } from '@/routes/shop/pages';
import type { Address, CartTotals } from '@/types';

type Props = {
    cart: CartTotals;
    countries: Record<string, string>;
    gateways: string[];
    defaults: {
        email: string | null;
        billing_address: Address | null;
        shipping_address: Address | null;
        vat_id: string | null;
        company: string | null;
    };
    legal_pages: { slug: string; title: string }[];
};

const gatewayLabels: Record<string, string> = {
    mollie: 'Card, PayPal, SEPA and more (Mollie)',
    bank_transfer: 'Prepayment by bank transfer',
};

export default function Checkout({
    cart,
    countries,
    gateways,
    defaults,
    legal_pages,
}: Props) {
    const { t, locale } = useTrans();
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const money = (cents: number) => formatMoney(cents, 'EUR', locale);

    const [billingCountry, setBillingCountry] = useState(
        defaults.billing_address?.country ?? cart.country,
    );
    const [shipToDifferent, setShipToDifferent] = useState(false);
    const [shippingCountry, setShippingCountry] = useState(
        defaults.shipping_address?.country ?? cart.country,
    );
    const [vatId, setVatId] = useState(defaults.vat_id ?? '');

    const effectiveCountry = shipToDifferent ? shippingCountry : billingCountry;

    // Recalculate VAT and shipping when the destination or VAT ID changes.
    useEffect(() => {
        const handle = setTimeout(() => {
            router.reload({
                data: { country: effectiveCountry, vat_id: vatId },
                only: ['cart'],
            });
        }, 400);

        return () => clearTimeout(handle);
    }, [effectiveCountry, vatId]);

    const billingDefaults: Address = {
        ...defaults.billing_address,
        company: defaults.billing_address?.company ?? defaults.company,
    };

    return (
        <Form
            {...CheckoutController.store.form()}
            className="grid gap-10 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]"
        >
            {({ processing }) => (
                <>
                    <div className="space-y-10">
                        <h1 className="text-2xl font-semibold">{t('Checkout')}</h1>

                        <section className="space-y-4">
                            <h2 className="text-lg font-medium">{t('Contact')}</h2>
                            <Field
                                label={t('Email address')}
                                name="email"
                                type="email"
                                defaultValue={defaults.email}
                                error={errors.email}
                                autoComplete="email"
                                required
                            />
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-lg font-medium">
                                {t('Billing address')}
                            </h2>
                            <AddressFields
                                prefix="billing_address"
                                values={billingDefaults}
                                countries={countries}
                                errors={errors}
                                country={billingCountry}
                                onCountry={setBillingCountry}
                                required
                            />
                            <div className="grid gap-1">
                                <Label htmlFor="vat_id">
                                    {t('VAT ID (businesses in the EU)')}
                                </Label>
                                <Input
                                    id="vat_id"
                                    name="vat_id"
                                    value={vatId}
                                    onChange={(e) => setVatId(e.target.value)}
                                    placeholder="ATU12345678"
                                    className="max-w-xs"
                                />
                                <InputError message={errors.vat_id} />
                                {vatId && cart.vat_id_valid && (
                                    <p className="text-sm text-green-700 dark:text-green-400">
                                        {t('VAT ID confirmed.')}
                                    </p>
                                )}
                            </div>
                        </section>

                        <section className="space-y-4">
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    name="ship_to_different"
                                    value="1"
                                    checked={shipToDifferent}
                                    onCheckedChange={(v) =>
                                        setShipToDifferent(v === true)
                                    }
                                />
                                {t('Ship to a different address')}
                            </label>
                            {shipToDifferent && (
                                <>
                                    <h2 className="text-lg font-medium">
                                        {t('Shipping address')}
                                    </h2>
                                    <AddressFields
                                        prefix="shipping_address"
                                        values={defaults.shipping_address}
                                        countries={countries}
                                        errors={errors}
                                        country={shippingCountry}
                                        onCountry={setShippingCountry}
                                        required
                                    />
                                </>
                            )}
                        </section>

                        <section className="space-y-3">
                            <h2 className="text-lg font-medium">{t('Payment')}</h2>
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
                        </section>

                        <section className="space-y-2">
                            <Label htmlFor="customer_note">
                                {t('Note (optional)')}
                            </Label>
                            <textarea
                                id="customer_note"
                                name="customer_note"
                                rows={3}
                                className="border-input bg-background w-full rounded-md border px-3 py-2 text-sm shadow-xs"
                            />
                        </section>
                    </div>

                    <aside className="h-fit space-y-6 rounded-lg border p-6 lg:sticky lg:top-6">
                        <h2 className="text-lg font-semibold">{t('Your order')}</h2>

                        <ul className="divide-y text-sm">
                            {cart.lines.map((line) => (
                                <li
                                    key={line.product_id}
                                    className="flex justify-between gap-4 py-2"
                                >
                                    <span>
                                        {line.quantity} × {line.name}
                                    </span>
                                    <span>{money(line.line_display_cents)}</span>
                                </li>
                            ))}
                        </ul>

                        <dl className="space-y-1 text-sm">
                            <div className="flex justify-between">
                                <dt>{t('Subtotal')}</dt>
                                <dd>{money(cart.subtotal_display_cents)}</dd>
                            </div>
                            {cart.discount_cents > 0 && (
                                <div className="flex justify-between">
                                    <dt>
                                        {t('Discount')} ({cart.coupon_code})
                                    </dt>
                                    <dd>− {money(cart.discount_cents)}</dd>
                                </div>
                            )}
                            <div className="flex justify-between">
                                <dt>{t('Shipping')}</dt>
                                <dd>
                                    {cart.shipping_display_cents === 0
                                        ? t('Free')
                                        : money(cart.shipping_display_cents)}
                                </dd>
                            </div>
                            {!cart.prices_include_tax &&
                                cart.tax_summary
                                    .filter((g) => g.tax > 0)
                                    .map((g) => (
                                        <div key={g.rate} className="flex justify-between">
                                            <dt>{t(':rate VAT', { rate: formatRate(g.rate) })}</dt>
                                            <dd>{money(g.tax)}</dd>
                                        </div>
                                    ))}
                            <div className="flex justify-between border-t pt-2 text-base font-semibold">
                                <dt>{t('Total')}</dt>
                                <dd>{money(cart.total_cents)}</dd>
                            </div>
                            {cart.prices_include_tax &&
                                cart.tax_summary
                                    .filter((g) => g.tax > 0)
                                    .map((g) => (
                                        <div
                                            key={g.rate}
                                            className="flex justify-between text-xs text-muted-foreground"
                                        >
                                            <dt>{t('incl. :rate VAT', { rate: formatRate(g.rate) })}</dt>
                                            <dd>{money(g.tax)}</dd>
                                        </div>
                                    ))}
                        </dl>

                        {cart.tax_note && (
                            <p className="text-xs text-muted-foreground">{cart.tax_note}</p>
                        )}

                        <label className="flex items-start gap-2 text-sm">
                            <Checkbox name="terms" value="1" className="mt-0.5" />
                            <span>
                                {t('I have read and accept the')}{' '}
                                {legal_pages.map((page, index) => (
                                    <span key={page.slug}>
                                        {index > 0 && ', '}
                                        <Link
                                            href={pageShow({ page: page.slug })}
                                            className="underline underline-offset-4"
                                            target="_blank"
                                        >
                                            {page.title}
                                        </Link>
                                    </span>
                                ))}
                                .
                            </span>
                        </label>
                        <InputError message={errors.terms} />

                        <Button
                            type="submit"
                            size="lg"
                            className="w-full"
                            disabled={processing}
                        >
                            {t('Place binding order')}
                        </Button>

                        <p className="text-center text-xs text-muted-foreground">
                            <Link href={cartIndex()} className="underline">
                                {t('Back to cart')}
                            </Link>
                        </p>
                    </aside>
                </>
            )}
        </Form>
    );
}
