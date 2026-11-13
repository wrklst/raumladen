import { Form, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import CheckoutController from '@/actions/App/Http/Controllers/Shop/CheckoutController';
import InputError from '@/components/input-error';
import { AddressFields } from '@/components/shop/address-fields';
import { Field } from '@/components/shop/field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
                        <div className="space-y-2">
                            <h1 className="text-2xl font-semibold">
                                {t('Checkout')}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                {t('Fields marked with * are required.')}
                            </p>
                        </div>

                        <section className="space-y-4">
                            <h2 className="text-lg font-medium">
                                {t('Contact')}
                            </h2>
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

                        <fieldset className="space-y-4">
                            <legend>
                                <h2 className="text-lg font-medium">
                                    {t('Billing address')}
                                </h2>
                            </legend>
                            <AddressFields
                                prefix="billing_address"
                                values={billingDefaults}
                                countries={countries}
                                errors={errors}
                                country={billingCountry}
                                onCountry={setBillingCountry}
                                required
                            />
                            <details
                                open={
                                    Boolean(vatId || errors.vat_id) || undefined
                                }
                            >
                                <summary className="cursor-pointer text-sm underline underline-offset-4">
                                    {t(
                                        'Ordering as a business in the EU? Add your VAT ID',
                                    )}
                                </summary>
                                <Field
                                    className="mt-4 max-w-xs"
                                    label={t('VAT ID (businesses in the EU)')}
                                    name="vat_id"
                                    value={vatId}
                                    onChange={(e) => setVatId(e.target.value)}
                                    placeholder="ATU12345678"
                                    autoComplete="off"
                                    error={errors.vat_id}
                                />
                                <p role="status" className="text-sm">
                                    {vatId && cart.vat_id_valid
                                        ? t('VAT ID confirmed.')
                                        : ''}
                                </p>
                            </details>
                        </fieldset>

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
                                <fieldset className="space-y-4">
                                    <legend>
                                        <h2 className="text-lg font-medium">
                                            {t('Shipping address')}
                                        </h2>
                                    </legend>
                                    <AddressFields
                                        prefix="shipping_address"
                                        values={defaults.shipping_address}
                                        countries={countries}
                                        errors={errors}
                                        country={shippingCountry}
                                        onCountry={setShippingCountry}
                                        required
                                    />
                                </fieldset>
                            )}
                        </section>

                        <fieldset className="space-y-3">
                            <legend>
                                <h2 className="text-lg font-medium">
                                    {t('Payment')}
                                </h2>
                            </legend>
                            {gateways.map((gateway, index) => (
                                <label
                                    key={gateway}
                                    className="flex items-center gap-3 border p-3 text-sm"
                                >
                                    <input
                                        type="radio"
                                        name="gateway"
                                        value={gateway}
                                        defaultChecked={index === 0}
                                        required
                                        className="size-4 accent-primary"
                                    />
                                    {t(gatewayLabels[gateway] ?? gateway)}
                                </label>
                            ))}
                            <InputError message={errors.gateway} />
                        </fieldset>

                        <details>
                            <summary className="cursor-pointer text-sm underline underline-offset-4">
                                {t('Add a note to your order')}
                            </summary>
                            <div className="mt-4 grid gap-1">
                                <Label htmlFor="customer_note">
                                    {t('Note (optional)')}
                                </Label>
                                <textarea
                                    id="customer_note"
                                    name="customer_note"
                                    rows={3}
                                    maxLength={1000}
                                    className="w-full border border-input bg-background px-3 py-2 text-sm"
                                />
                            </div>
                        </details>
                    </div>

                    <section
                        className="h-fit space-y-6 border p-6 lg:sticky lg:top-6"
                        aria-labelledby="checkout-order"
                    >
                        <h2
                            id="checkout-order"
                            className="text-lg font-semibold"
                        >
                            {t('Your order')}
                        </h2>

                        <ul className="divide-y text-sm">
                            {cart.lines.map((line) => (
                                <li
                                    key={line.product_id}
                                    className="flex justify-between gap-4 py-2"
                                >
                                    <span>
                                        {line.quantity} × {line.name}
                                    </span>
                                    <span>
                                        {money(line.line_display_cents)}
                                    </span>
                                </li>
                            ))}
                        </ul>

                        {/* Totals change with country and VAT ID: announce them. */}
                        <dl className="space-y-1 text-sm" aria-live="polite">
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
                                        <div
                                            key={g.rate}
                                            className="flex justify-between"
                                        >
                                            <dt>
                                                {t(':rate VAT', {
                                                    rate: formatRate(g.rate),
                                                })}
                                            </dt>
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
                                            <dt>
                                                {t('incl. :rate VAT', {
                                                    rate: formatRate(g.rate),
                                                })}
                                            </dt>
                                            <dd>{money(g.tax)}</dd>
                                        </div>
                                    ))}
                        </dl>

                        {cart.tax_note && (
                            <p className="text-xs text-muted-foreground">
                                {cart.tax_note}
                            </p>
                        )}

                        {/* No checkbox: German law asks for the terms to be included and the
                            withdrawal and privacy information to be given, not for a tick. */}
                        <p className="text-sm">
                            {t(
                                'By placing your order you agree to our terms. Please note the information on:',
                            )}{' '}
                            {legal_pages.map((page, index) => (
                                <span key={page.slug}>
                                    {index > 0 && ', '}
                                    <a
                                        href={pageShow({ page: page.slug }).url}
                                        className="underline underline-offset-4"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        {page.title}
                                        <span className="sr-only">
                                            {' '}
                                            {t('(opens in a new tab)')}
                                        </span>
                                    </a>
                                </span>
                            ))}
                            .
                        </p>

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
                    </section>
                </>
            )}
        </Form>
    );
}
