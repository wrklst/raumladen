import { Form, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
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
    /** What to pay with: a gateway, and the method within it where the gateway names its methods. */
    payment_choices: {
        gateway: string;
        method: string | null;
        label: string | null;
        image: string | null;
    }[];
    /** For card fields in the page (Mollie Components). */
    card_fields: { profile_id: string; testmode: boolean } | null;
    /** What Apple Pay's sheet names. */
    wallet: { country: string; label: string };
    defaults: {
        email: string | null;
        billing_address: Address | null;
        shipping_address: Address | null;
        vat_id: string | null;
        company: string | null;
    };
    legal_pages: { slug: string; title: string }[];
};

/** A payment the customer is making in a window over the checkout. */
type Payment = { url: string; status: string; cancel: string };

type MollieComponent = { mount: (target: HTMLElement) => void };
type MollieClient = {
    createComponent: (
        type: 'card',
        options?: { styles: Record<string, Record<string, string>> },
    ) => MollieComponent;
    createToken: () => Promise<{
        token?: string;
        error?: { message: string };
    }>;
};

/** The parts of Apple Pay on the Web this page uses (Safari only). */
type ApplePaySheet = {
    onvalidatemerchant: (event: { validationURL: string }) => void;
    onpaymentauthorized: (event: { payment: { token: unknown } }) => void;
    oncancel: () => void;
    completeMerchantValidation: (session: unknown) => void;
    completePayment: (status: number) => void;
    begin: () => void;
    abort: () => void;
};

declare global {
    interface Window {
        ApplePaySession?: {
            new (version: number, request: object): ApplePaySheet;
            canMakePayments: () => boolean;
            STATUS_SUCCESS: number;
            STATUS_FAILURE: number;
        };
        Mollie?: (
            profileId: string,
            options: { locale: string; testmode: boolean },
        ) => MollieClient;
    }
}

const paymentWindow = 'popup,width=480,height=760';

const xsrfToken = (): string =>
    decodeURIComponent(
        document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? '',
    );

const gatewayLabels: Record<string, string> = {
    mollie: 'Card, PayPal, SEPA and more (Mollie)',
    bank_transfer: 'Prepayment by bank transfer',
};

export default function Checkout({
    cart,
    countries,
    payment_choices,
    card_fields,
    wallet,
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

    // Paying online happens here, at the checkout. A card is entered in the
    // page; whatever a provider or a bank needs to show opens in a window
    // over it. The server answers a submitted order with where that window
    // goes (flash data), and the page asks until the payment is settled.
    // Apple Pay is offered where the browser can show its sheet; known only after the page has loaded.
    const [applePay, setApplePay] = useState(false);

    useEffect(() => {
        try {
            setApplePay(window.ApplePaySession?.canMakePayments() ?? false);
        } catch {
            setApplePay(false);
        }
    }, []);

    const choices = payment_choices.filter(
        (option) => option.method !== 'applepay' || applePay,
    );
    const [chosen, setChosen] = useState(0);
    const choice = choices[chosen] ?? choices[0];
    const sheet = useRef<ApplePaySheet | null>(null);
    const applePayToken = useRef<HTMLInputElement>(null);
    const paysByCard = choice?.method === 'creditcard' && card_fields !== null;
    const [payment, setPayment] = useState<Payment | null>(null);
    const [paymentError, setPaymentError] = useState<string | null>(null);
    const [preparing, setPreparing] = useState(false);
    const popup = useRef<Window | null>(null);
    const mollie = useRef<MollieClient | null>(null);
    const cardFields = useRef<HTMLDivElement>(null);
    const cardToken = useRef<HTMLInputElement>(null);

    const openWindow = (url: string): void => {
        if (popup.current && !popup.current.closed) {
            popup.current.location.href = url;
            popup.current.focus();

            return;
        }

        // Blocked when not opened by a click: the notice over the checkout has a button for it.
        popup.current = window.open(url, 'raumladen-payment', paymentWindow);
    };

    const closeWindow = (): void => {
        popup.current?.close();
        popup.current = null;
    };

    const settle = (answer: { state: string; url?: string }): void => {
        if (answer.state === 'done' && answer.url) {
            closeWindow();
            router.visit(answer.url);
        }

        if (answer.state === 'failed') {
            closeWindow();
            setPayment(null);
            setPaymentError(
                t(
                    'The payment was not completed. Please try again or choose another payment method.',
                ),
            );
        }
    };

    // Card fields: loaded when a card is first chosen, then kept.
    useEffect(() => {
        if (!paysByCard || !card_fields || mollie.current) {
            return;
        }

        const mount = (): void => {
            if (!window.Mollie || !cardFields.current || mollie.current) {
                return;
            }

            mollie.current = window.Mollie(card_fields.profile_id, {
                locale: locale === 'de' ? 'de_DE' : 'en_US',
                testmode: card_fields.testmode,
            });
            // The fields are frames of the provider: they are told the page's text colour, the rest is in app.css.
            mollie.current
                .createComponent('card', {
                    styles: {
                        base: {
                            color: getComputedStyle(cardFields.current).color,
                            fontSize: '16px',
                        },
                    },
                })
                .mount(cardFields.current);
        };

        if (window.Mollie) {
            mount();

            return;
        }

        const script = document.createElement('script');
        script.src = 'https://js.mollie.com/v1/mollie.js';
        script.addEventListener('load', mount);
        document.body.appendChild(script);
    }, [paysByCard, card_fields, locale]);

    // The server's answer to a submitted order: where the payment window goes.
    useEffect(
        () =>
            router.on('flash', (event) => {
                const next = (event as CustomEvent).detail?.flash?.payment as
                    | Payment
                    | undefined;

                if (next) {
                    setPaymentError(null);
                    setPayment(next);
                    openWindow(next.url);
                }
            }),
        [],
    );

    // While the customer pays: ask how it stands, and at once when the window says it is done.
    useEffect(() => {
        if (!payment) {
            return;
        }

        const ask = (): void => {
            void fetch(payment.status, {
                headers: { Accept: 'application/json' },
            })
                .then((response) => (response.ok ? response.json() : null))
                .then((answer) => answer && settle(answer))
                .catch(() => undefined);
        };
        const onMessage = (event: MessageEvent): void => {
            if (
                event.origin === window.location.origin &&
                event.data?.type === 'raumladen-payment'
            ) {
                ask();
            }
        };

        // ponytail: asks every 2.5 s, each time asking the provider; fine for the minutes a payment takes.
        const timer = setInterval(ask, 2500);
        window.addEventListener('message', onMessage);

        return () => {
            clearInterval(timer);
            window.removeEventListener('message', onMessage);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [payment]);

    const giveUp = (): void => {
        if (!payment) {
            return;
        }

        void fetch(payment.cancel, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((answer) => answer && settle(answer))
            .catch(() => undefined);
    };

    /**
     * Before the order is sent. A card is turned into a token first (its
     * number never reaches the shop); for every other online method the
     * window is opened now, while the click still allows it.
     */
    const beforeOrder = (event: React.MouseEvent<HTMLButtonElement>): void => {
        if (!choice || choice.gateway === 'bank_transfer') {
            return;
        }

        const button = event.currentTarget;
        const form = button.form;

        if (!form?.reportValidity()) {
            return;
        }

        setPaymentError(null);

        // Apple Pay: its sheet opens over the checkout; the order is sent when the customer confirms there.
        if (choice.method === 'applepay' && window.ApplePaySession) {
            if (applePayToken.current?.value) {
                return;
            }

            event.preventDefault();

            const ApplePay = window.ApplePaySession;
            const session = new ApplePay(3, {
                countryCode: wallet.country,
                currencyCode: 'EUR',
                supportedNetworks: [
                    'amex',
                    'maestro',
                    'masterCard',
                    'visa',
                    'vPay',
                ],
                merchantCapabilities: ['supports3DS'],
                total: {
                    label: wallet.label,
                    amount: (cart.total_cents / 100).toFixed(2),
                },
            });

            session.onvalidatemerchant = ({ validationURL }) => {
                void fetch(CheckoutController.applePaySession().url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': xsrfToken(),
                    },
                    body: JSON.stringify({ validation_url: validationURL }),
                })
                    .then((response) =>
                        response.ok ? response.json() : Promise.reject(),
                    )
                    .then((answer) =>
                        session.completeMerchantValidation(answer),
                    )
                    .catch(() => {
                        session.abort();
                        setPaymentError(
                            t(
                                'Apple Pay is not available right now. Please choose another payment method.',
                            ),
                        );
                    });
            };
            session.onpaymentauthorized = ({ payment: authorized }) => {
                if (!applePayToken.current) {
                    return;
                }

                sheet.current = session;
                applePayToken.current.value = JSON.stringify(authorized.token);
                form.requestSubmit(button);
                applePayToken.current.value = '';
            };
            session.oncancel = () => {
                sheet.current = null;
            };
            session.begin();

            return;
        }

        if (!paysByCard) {
            popup.current = window.open('', 'raumladen-payment', paymentWindow);
            popup.current?.document.write(
                `<p style="font-family: sans-serif; padding: 2rem">${t('Connecting to the payment provider …')}</p>`,
            );

            return;
        }

        if (cardToken.current?.value || !mollie.current) {
            return;
        }

        event.preventDefault();
        setPreparing(true);

        void mollie.current.createToken().then(({ token, error }) => {
            setPreparing(false);

            if (error || !token || !cardToken.current) {
                setPaymentError(
                    error?.message ?? t('Please check your card details.'),
                );

                return;
            }

            cardToken.current.value = token;
            form.requestSubmit(button);
            // A token pays once: the next try asks for a new one.
            cardToken.current.value = '';
        });
    };

    const billingDefaults: Address = {
        ...defaults.billing_address,
        company: defaults.billing_address?.company ?? defaults.company,
    };

    return (
        <Form
            {...CheckoutController.store.form()}
            // The answer comes back to this page: what was typed stays.
            options={{ preserveState: true, preserveScroll: true }}
            onError={() => {
                closeWindow();
                sheet.current?.completePayment(
                    window.ApplePaySession?.STATUS_FAILURE ?? 1,
                );
                sheet.current = null;
            }}
            onSuccess={() => {
                sheet.current?.completePayment(
                    window.ApplePaySession?.STATUS_SUCCESS ?? 0,
                );
                sheet.current = null;
            }}
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
                            <input
                                type="hidden"
                                name="gateway"
                                value={choice?.gateway ?? ''}
                            />
                            <input
                                type="hidden"
                                name="method"
                                value={choice?.method ?? ''}
                            />
                            <input
                                ref={cardToken}
                                type="hidden"
                                name="card_token"
                            />
                            <input
                                ref={applePayToken}
                                type="hidden"
                                name="apple_pay_token"
                            />
                            {choices.map((option, index) => (
                                <div
                                    key={`${option.gateway}:${option.method}`}
                                    className="border text-sm"
                                >
                                    <label className="flex items-center gap-3 p-3">
                                        <input
                                            type="radio"
                                            name="payment_choice"
                                            value={[
                                                option.gateway,
                                                option.method,
                                            ]
                                                .filter(Boolean)
                                                .join(':')}
                                            checked={index === chosen}
                                            onChange={() => setChosen(index)}
                                            className="size-4 accent-primary"
                                        />
                                        <span className="flex-1">
                                            {option.label ??
                                                t(
                                                    gatewayLabels[
                                                        option.gateway
                                                    ] ?? option.gateway,
                                                )}
                                        </span>
                                        {option.image && (
                                            <img
                                                src={option.image}
                                                alt=""
                                                className="h-6"
                                            />
                                        )}
                                    </label>
                                    {option.method === 'creditcard' &&
                                        card_fields && (
                                            <div
                                                ref={cardFields}
                                                hidden={index !== chosen}
                                                className="border-t p-3"
                                            />
                                        )}
                                </div>
                            ))}
                            {paymentError && (
                                <p role="alert" className="text-sm font-medium">
                                    {paymentError}
                                </p>
                            )}
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
                                        {line.quantity} ×{' '}
                                        {line.series && `${line.series}: `}
                                        {line.name}
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
                            disabled={processing || preparing}
                            onClick={beforeOrder}
                        >
                            {t('Place binding order')}
                        </Button>

                        {payment && (
                            <div className="fixed inset-0 z-50 grid place-items-center bg-black/60 p-4">
                                <div
                                    role="dialog"
                                    aria-modal="true"
                                    aria-labelledby="payment-notice"
                                    className="w-full max-w-md space-y-4 border bg-background p-6"
                                >
                                    <h2
                                        id="payment-notice"
                                        className="text-lg font-semibold"
                                    >
                                        {t('Complete your payment')}
                                    </h2>
                                    <p className="text-sm">
                                        {t(
                                            'Your payment of :total is waiting in a separate window. This page continues by itself as soon as it is confirmed.',
                                            {
                                                total: money(cart.total_cents),
                                            },
                                        )}
                                    </p>
                                    <Button
                                        type="button"
                                        size="lg"
                                        className="w-full"
                                        onClick={() => openWindow(payment.url)}
                                    >
                                        {t('Open the payment window')}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="w-full"
                                        onClick={giveUp}
                                    >
                                        {t('Choose another payment method')}
                                    </Button>
                                </div>
                            </div>
                        )}

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
