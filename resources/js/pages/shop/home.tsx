import { Link } from '@inertiajs/react';
import { Pagination } from '@/components/pagination';
import { useTrans } from '@/hooks/use-translation';
import { formatMoney, formatRate } from '@/lib/money';
import { home } from '@/routes/shop';
import type { ProductCard, ResourcePaginated } from '@/types';

export default function Home({
    products,
    filters,
}: {
    products: ResourcePaginated<ProductCard>;
    filters?: { q: string; author: string };
}) {
    const { t, locale } = useTrans();
    const filtered = Boolean(filters?.q || filters?.author);
    const heading = filters?.q
        ? t('Search: :query', { query: filters.q })
        : filters?.author || t('Issues');

    return (
        <div className="space-y-6">
            <div className="space-y-1">
                <h1 className="text-3xl leading-[1.1] sm:text-4xl">
                    {heading}
                </h1>
                {filtered && (
                    <p>
                        <span role="status">
                            {t('Results: :count', {
                                count: products.meta.total,
                            })}
                        </span>
                        {' · '}
                        <Link
                            href={home()}
                            className="underline underline-offset-4"
                        >
                            {t('Show all issues')}
                        </Link>
                    </p>
                )}
            </div>

            {products.data.length === 0 ? (
                <p>
                    {filtered
                        ? t('Nothing found.')
                        : t('No books available yet.')}
                </p>
            ) : (
                // Each cell carries its own border and overlaps its neighbours by 1px,
                // so a short last row leaves neither a coloured block nor a stray line.
                <ul className="grid grid-cols-1 pt-px pl-px sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {products.data.map((product, index) => (
                        <li
                            key={product.id}
                            className="-mt-px -ml-px border border-foreground"
                        >
                            <Link
                                href={product.url}
                                className="flex aspect-[3/4] flex-col justify-between gap-4 p-4 hover:bg-foreground hover:text-background"
                            >
                                {product.image ? (
                                    <img
                                        src={product.image}
                                        // The title follows as text in the same link.
                                        alt=""
                                        width={300}
                                        height={400}
                                        loading={index < 4 ? 'eager' : 'lazy'}
                                        decoding="async"
                                        className="min-h-0 flex-1 object-contain object-left-top"
                                    />
                                ) : null}
                                <h2 className="text-2xl leading-[1.1] text-balance hyphens-auto sm:text-3xl">
                                    {product.title}
                                </h2>
                                <p className="text-sm">
                                    {product.authors}
                                    {product.authors && <br />}
                                    {formatMoney(
                                        product.price_cents,
                                        product.currency,
                                        locale,
                                    )}
                                    {!product.in_stock && ` · ${t('Sold out')}`}
                                    <br />
                                    {product.prices_include_tax
                                        ? t('incl. :rate VAT, plus shipping', {
                                              rate: formatRate(
                                                  product.tax_rate,
                                              ),
                                          })
                                        : t('net, plus VAT and shipping')}
                                </p>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}

            <Pagination links={products.meta.links} />
        </div>
    );
}
