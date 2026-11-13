import { Link } from '@inertiajs/react';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { useTrans } from '@/hooks/use-translation';
import { formatMoney, formatRate } from '@/lib/money';
import type { ProductCard, ResourcePaginated } from '@/types';

export default function Home({
    products,
}: {
    products: ResourcePaginated<ProductCard>;
}) {
    const { t, locale } = useTrans();

    return (
        <div className="space-y-8">
            <header>
                <h1 className="text-3xl font-semibold tracking-tight">
                    {t('Books')}
                </h1>
            </header>

            {products.data.length === 0 ? (
                <p className="text-muted-foreground">
                    {t('No books available yet.')}
                </p>
            ) : (
                <ul className="grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-4">
                    {products.data.map((product) => (
                        <li key={product.id} className="group flex flex-col">
                            <Link
                                href={product.url}
                                className="flex flex-1 flex-col gap-3"
                            >
                                <div className="aspect-[3/4] overflow-hidden rounded-lg border bg-muted">
                                    {product.image ? (
                                        <img
                                            src={product.image}
                                            alt={product.title}
                                            loading="lazy"
                                            className="size-full object-cover transition group-hover:scale-[1.02]"
                                        />
                                    ) : (
                                        <div className="flex size-full items-center justify-center p-4 text-center text-sm text-muted-foreground">
                                            {product.title}
                                        </div>
                                    )}
                                </div>
                                <div className="flex-1">
                                    <h2 className="font-medium leading-snug">
                                        {product.title}
                                    </h2>
                                    {product.authors && (
                                        <p className="text-sm text-muted-foreground">
                                            {product.authors}
                                        </p>
                                    )}
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="font-semibold">
                                        {formatMoney(
                                            product.price_cents,
                                            product.currency,
                                            locale,
                                        )}
                                    </span>
                                    {!product.in_stock && (
                                        <Badge variant="outline">
                                            {t('Sold out')}
                                        </Badge>
                                    )}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {product.prices_include_tax
                                        ? t('incl. :rate VAT, plus shipping', {
                                              rate: formatRate(product.tax_rate),
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
