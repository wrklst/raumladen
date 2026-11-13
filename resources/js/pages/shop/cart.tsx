import { Form, Link, usePage } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import CartController from '@/actions/App/Http/Controllers/Shop/CartController';
import CartCouponController from '@/actions/App/Http/Controllers/Shop/CartCouponController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/hooks/use-translation';
import { formatMoney, formatRate } from '@/lib/money';
import { home } from '@/routes/shop';
import { show as checkout } from '@/routes/shop/checkout';
import type { CartTotals } from '@/types';

const couponErrors: Record<string, string> = {
    inactive: 'This coupon is not active.',
    not_started: 'This coupon is not valid yet.',
    expired: 'This coupon has expired.',
    exhausted: 'This coupon has been used up.',
    wrong_group: 'This coupon is not available for your account.',
    below_minimum: 'Your order does not reach the minimum amount for this coupon.',
};

export default function Cart({ cart }: { cart: CartTotals }) {
    const { t, locale } = useTrans();
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const money = (cents: number) => formatMoney(cents, 'EUR', locale);

    if (cart.lines.length === 0) {
        return (
            <div className="space-y-4 py-12 text-center">
                <h1 className="text-2xl font-semibold">{t('Your cart is empty')}</h1>
                <Button asChild>
                    <Link href={home()}>{t('Browse books')}</Link>
                </Button>
            </div>
        );
    }

    return (
        <div className="grid gap-10 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
            <section className="space-y-4">
                <h1 className="text-2xl font-semibold">{t('Cart')}</h1>

                {errors.cart && <InputError message={errors.cart} />}
                {errors.quantity && <InputError message={errors.quantity} />}

                <ul className="divide-y">
                    {cart.lines.map((line) => (
                        <li
                            key={line.product_id}
                            className="flex gap-4 py-4"
                        >
                            <div className="size-20 shrink-0 overflow-hidden rounded border bg-muted">
                                {line.image && (
                                    <img
                                        src={line.image}
                                        alt=""
                                        className="size-full object-cover"
                                    />
                                )}
                            </div>
                            <div className="flex flex-1 flex-col gap-2">
                                <div className="flex justify-between gap-4">
                                    <Link
                                        href={line.url}
                                        className="font-medium hover:underline"
                                    >
                                        {line.name}
                                    </Link>
                                    <span className="font-medium">
                                        {money(line.line_display_cents)}
                                    </span>
                                </div>
                                <div className="flex items-center gap-3 text-sm text-muted-foreground">
                                    <span>{money(line.unit_price_cents)}</span>
                                    <Form
                                        {...CartController.update.form({
                                            product: line.product_id,
                                        })}
                                        className="flex items-center gap-2"
                                    >
                                        <Input
                                            type="number"
                                            name="quantity"
                                            min={0}
                                            max={99}
                                            defaultValue={line.quantity}
                                            className="h-8 w-20"
                                            aria-label={t('Quantity')}
                                        />
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            size="sm"
                                        >
                                            {t('Update')}
                                        </Button>
                                    </Form>
                                    <Form
                                        {...CartController.destroy.form({
                                            product: line.product_id,
                                        })}
                                    >
                                        <Button
                                            type="submit"
                                            variant="ghost"
                                            size="sm"
                                            aria-label={t('Remove')}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </Form>
                                </div>
                            </div>
                        </li>
                    ))}
                </ul>
            </section>

            <aside className="h-fit space-y-6 rounded-lg border p-6">
                <h2 className="text-lg font-semibold">{t('Summary')}</h2>

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

                <p className="text-xs text-muted-foreground">
                    {t('Shipping and VAT are calculated for :country; final amounts are shown at checkout.', {
                        country: cart.country,
                    })}
                </p>

                {cart.coupon_code ? (
                    <Form
                        {...CartCouponController.destroy.form()}
                        className="flex items-center justify-between text-sm"
                    >
                        <span>
                            {t('Coupon')}: <strong>{cart.coupon_code}</strong>
                        </span>
                        <Button type="submit" variant="ghost" size="sm">
                            {t('Remove')}
                        </Button>
                    </Form>
                ) : (
                    <Form
                        {...CartCouponController.store.form()}
                        className="space-y-2"
                    >
                        {({ errors: couponFormErrors, processing }) => (
                            <>
                                <div className="flex gap-2">
                                    <Input
                                        name="code"
                                        placeholder={t('Coupon code')}
                                        aria-label={t('Coupon code')}
                                    />
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        {t('Apply')}
                                    </Button>
                                </div>
                                <InputError message={couponFormErrors.code} />
                                {cart.coupon_error && (
                                    <InputError
                                        message={t(
                                            couponErrors[cart.coupon_error] ??
                                                'This coupon code is not valid.',
                                        )}
                                    />
                                )}
                            </>
                        )}
                    </Form>
                )}

                <Button asChild size="lg" className="w-full">
                    <Link href={checkout()}>{t('Checkout')}</Link>
                </Button>
            </aside>
        </div>
    );
}
