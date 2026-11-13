import { Form, Link } from '@inertiajs/react';
import CartController from '@/actions/App/Http/Controllers/Shop/CartController';
import InputError from '@/components/input-error';
import { TextCover } from '@/components/shop/text-cover';
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

export default function Product({
    product,
    delivery_days,
    manufacturer,
}: {
    product: ProductDetail;
    delivery_days: [number, number] | null;
    manufacturer?: string;
}) {
    const { t, locale } = useTrans();

    return (
        <article className="grid gap-10 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
            <nav
                className="text-sm text-muted-foreground lg:col-span-2"
                aria-label={t('Breadcrumb')}
            >
                <ol className="flex flex-wrap items-center gap-2">
                    <li>
                        <Link
                            href={home()}
                            className="underline underline-offset-4 hover:text-foreground"
                        >
                            {t('Issues')}
                        </Link>
                    </li>
                    <li aria-hidden="true">/</li>
                    <li aria-current="page">{product.title}</li>
                </ol>
            </nav>

            <div className="space-y-3">
                {product.images[0] ? (
                    <img
                        src={product.images[0]}
                        alt={t('Cover of :title', { title: product.title })}
                        width={600}
                        height={800}
                        fetchPriority="high"
                        className="aspect-[3/4] w-full border border-foreground object-cover"
                    />
                ) : (
                    <TextCover
                        title={product.title}
                        authors={product.authors}
                        aria-hidden
                    />
                )}
                {product.images.length > 1 && (
                    <div className="grid grid-cols-4 gap-2">
                        {product.images.slice(1).map((image, index) => (
                            <img
                                key={image}
                                src={image}
                                alt={t(':title, image :number', {
                                    title: product.title,
                                    number: index + 2,
                                })}
                                width={150}
                                height={150}
                                loading="lazy"
                                className="aspect-square border border-foreground object-cover"
                            />
                        ))}
                    </div>
                )}
            </div>

            <div className="space-y-6">
                <header className="space-y-2">
                    <h1 className="text-3xl leading-[1.1] sm:text-4xl">
                        {product.title}
                    </h1>
                    {product.author_list.length > 0 && (
                        <p className="text-lg">
                            {product.author_list.map((author, index) => (
                                <span key={author}>
                                    {index > 0 && ', '}
                                    <Link
                                        href={home(undefined, {
                                            query: { author },
                                        })}
                                        className="underline underline-offset-4"
                                    >
                                        {author}
                                    </Link>
                                </span>
                            ))}
                        </p>
                    )}
                </header>

                <div className="space-y-1">
                    <p className="text-2xl font-semibold">
                        {formatMoney(
                            product.price_cents,
                            product.currency,
                            locale,
                        )}
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
                    {delivery_days && product.in_stock && (
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'Delivery within Germany: :min–:max working days',
                                {
                                    min: delivery_days[0],
                                    max: delivery_days[1],
                                },
                            )}
                        </p>
                    )}
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
                                        className="h-10 w-24"
                                        aria-invalid={
                                            errors.quantity ? true : undefined
                                        }
                                        aria-describedby={
                                            errors.quantity
                                                ? 'quantity-error'
                                                : undefined
                                        }
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
                                    id="quantity-error"
                                    className="basis-full"
                                    message={errors.quantity}
                                />
                            </>
                        )}
                    </Form>
                ) : (
                    <p>{t('Sold out')}</p>
                )}

                {product.description && (
                    <div className="prose">
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
                    {manufacturer && (
                        <div className="contents">
                            <dt className="text-muted-foreground">
                                {t('Manufacturer')}
                            </dt>
                            <dd>{manufacturer}</dd>
                        </div>
                    )}
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
