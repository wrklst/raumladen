import { Form, Link } from '@inertiajs/react';
import CartController from '@/actions/App/Http/Controllers/Shop/CartController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/hooks/use-translation';
import { formatMoney, formatRate } from '@/lib/money';
import { home } from '@/routes/shop';
import { show as pageShow } from '@/routes/shop/pages';
import type { ProductDetail } from '@/types';

const specLabels: Record<string, string> = {
    isbn: 'ISBN',
    authors: 'Authors',
    publisher: 'Publisher',
    pages: 'Pages',
    binding: 'Binding',
    language: 'Language',
    published: 'Published',
};

export default function Product({ product }: { product: ProductDetail }) {
    const { t, locale } = useTrans();

    return (
        <article className="grid gap-10 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
            <nav className="text-sm text-muted-foreground lg:col-span-2">
                <Link href={home()} className="hover:text-foreground">
                    {t('Books')}
                </Link>
                <span className="mx-2">/</span>
                <span>{product.title}</span>
            </nav>

            <div className="space-y-3">
                <div className="aspect-[3/4] overflow-hidden rounded-lg border bg-muted">
                    {product.images[0] ? (
                        <img
                            src={product.images[0]}
                            alt={product.title}
                            className="size-full object-cover"
                        />
                    ) : (
                        <div className="flex size-full items-center justify-center p-6 text-center text-muted-foreground">
                            {product.title}
                        </div>
                    )}
                </div>
                {product.images.length > 1 && (
                    <div className="grid grid-cols-4 gap-2">
                        {product.images.slice(1).map((image) => (
                            <img
                                key={image}
                                src={image}
                                alt=""
                                className="aspect-square rounded border object-cover"
                            />
                        ))}
                    </div>
                )}
            </div>

            <div className="space-y-6">
                <header className="space-y-2">
                    <h1 className="text-3xl font-semibold tracking-tight">
                        {product.title}
                    </h1>
                    {product.authors && (
                        <p className="text-lg text-muted-foreground">
                            {product.authors}
                        </p>
                    )}
                </header>

                <div className="space-y-1">
                    <p className="text-2xl font-semibold">
                        {formatMoney(product.price_cents, product.currency, locale)}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {product.prices_include_tax
                            ? t('incl. :rate VAT, plus shipping', {
                                  rate: formatRate(product.tax_rate),
                              })
                            : t('net, plus VAT and shipping')}
                        {' · '}
                        <Link
                            href={pageShow({ page: 'shipping' })}
                            className="underline underline-offset-4"
                        >
                            {t('Shipping & payment')}
                        </Link>
                    </p>
                </div>

                {product.in_stock ? (
                    <Form
                        {...CartController.store.form()}
                        className="flex flex-wrap items-end gap-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="product_id"
                                    value={product.id}
                                />
                                <div className="grid gap-1">
                                    <Label htmlFor="quantity">
                                        {t('Quantity')}
                                    </Label>
                                    <Input
                                        id="quantity"
                                        name="quantity"
                                        type="number"
                                        min={1}
                                        max={product.stock ?? 99}
                                        defaultValue={1}
                                        className="w-24"
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    size="lg"
                                    disabled={processing}
                                >
                                    {t('Add to cart')}
                                </Button>
                                <InputError
                                    className="basis-full"
                                    message={errors.quantity}
                                />
                            </>
                        )}
                    </Form>
                ) : (
                    <Badge variant="outline">{t('Sold out')}</Badge>
                )}

                {product.description && (
                    <div className="prose prose-neutral max-w-none dark:prose-invert">
                        {product.description.split(/\n{2,}/).map((para, i) => (
                            <p key={i}>{para}</p>
                        ))}
                    </div>
                )}

                <dl className="grid grid-cols-[max-content_1fr] gap-x-6 gap-y-1 text-sm">
                    {product.spec_fields
                        .filter((field) => product.specs[field])
                        .map((field) => (
                            <div key={field} className="contents">
                                <dt className="text-muted-foreground">
                                    {t(specLabels[field] ?? field)}
                                </dt>
                                <dd>{product.specs[field]}</dd>
                            </div>
                        ))}
                    {product.sku && (
                        <div className="contents">
                            <dt className="text-muted-foreground">
                                {t('Article no.')}
                            </dt>
                            <dd>{product.sku}</dd>
                        </div>
                    )}
                </dl>
            </div>
        </article>
    );
}
