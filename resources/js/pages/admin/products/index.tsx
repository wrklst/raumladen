import { Head, Link } from '@inertiajs/react';
import ProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatMoney } from '@/lib/money';
import { create, index } from '@/routes/admin/products';
import type { Paginated } from '@/types';

type ProductRow = {
    id: number;
    title: string;
    sku: string | null;
    type: string;
    price_cents: number;
    stock: number | null;
    is_active: boolean;
    image: string | null;
};

export default function ProductsIndex({
    products,
    filters,
}: {
    products: Paginated<ProductRow>;
    filters: { q: string };
}) {
    return (
        <>
            <Head title="Products" />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <form method="get" action={index.url()} className="flex gap-2">
                        <Input
                            name="q"
                            defaultValue={filters.q}
                            placeholder="Search title or SKU"
                            className="w-64"
                        />
                        <Button type="submit" variant="outline">
                            Search
                        </Button>
                    </form>
                    <Button asChild>
                        <Link href={create()}>New product</Link>
                    </Button>
                </div>

                {products.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No products found.</p>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="text-left text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium" />
                                <th className="px-3 py-2 font-medium">Title</th>
                                <th className="px-3 py-2 font-medium">SKU</th>
                                <th className="px-3 py-2 text-right font-medium">Price</th>
                                <th className="px-3 py-2 text-right font-medium">Stock</th>
                                <th className="px-3 py-2 font-medium">Active</th>
                                <th className="px-3 py-2 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            {products.data.map((product) => (
                                <tr key={product.id} className="border-t">
                                    <td className="w-14 px-3 py-2">
                                        {product.image && (
                                            <img
                                                src={product.image}
                                                alt=""
                                                className="size-10 rounded border object-cover"
                                            />
                                        )}
                                    </td>
                                    <td className="px-3 py-2 font-medium">
                                        {product.title}
                                    </td>
                                    <td className="px-3 py-2 text-muted-foreground">
                                        {product.sku ?? '—'}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {formatMoney(product.price_cents, 'EUR', 'de')}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {product.stock ?? '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Badge
                                            variant={
                                                product.is_active ? 'default' : 'outline'
                                            }
                                        >
                                            {product.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <Link
                                            href={ProductController.edit({
                                                product: product.id,
                                            })}
                                            className="underline underline-offset-4"
                                        >
                                            Edit
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}

                <Pagination links={products.links} />
            </div>
        </>
    );
}

ProductsIndex.layout = {
    breadcrumbs: [{ title: 'Products', href: index() }],
};
