// @vitest-environment node
import { renderToString } from 'react-dom/server';
import { expect, test } from 'vite-plus/test';
import ShopLayout from '@/layouts/shop-layout';
import Home from '@/pages/shop/home';
import Product from '@/pages/shop/product';
import { setPage } from '@/test-utils';
import type { ProductCard, ProductDetail, ResourcePaginated } from '@/types';

const card: ProductCard = {
    id: 1,
    type: 'book',
    title: 'Die Kunst des langsamen Lesens',
    slug: 'die-kunst',
    url: 'https://shop.test/de/products/die-kunst',
    price_cents: 2400,
    prices_include_tax: true,
    tax_rate: 7,
    currency: 'EUR',
    image: null,
    in_stock: true,
    authors: 'Lena Hartmann',
};

// The storefront must render on the server, where window, document and
// localStorage do not exist. A browser-only access anywhere in these trees
// throws here.
test('the home page renders to HTML without a browser', () => {
    setPage({ seo: { title: 'Books', alternates: {} } });

    const products = {
        data: [card],
        meta: { links: [] },
    } as unknown as ResourcePaginated<ProductCard>;
    const html = renderToString(
        <ShopLayout>
            <Home products={products} />
        </ShopLayout>,
    );

    expect(typeof window).toBe('undefined');
    expect(html).toContain('<h1');
    expect(html).toContain('Die Kunst des langsamen Lesens');
    expect(html).toContain('24,00');
});

test('the product page renders to HTML without a browser', () => {
    setPage({ seo: { title: card.title, alternates: {} } });

    const product: ProductDetail = {
        ...card,
        sku: 'BK-0001',
        author_list: ['Lena Hartmann'],
        description: 'Ein Essay.',
        images: [],
        specs: { isbn: '9783000000011' },
        spec_fields: ['isbn'],
        stock: 5,
        weight_grams: 420,
    };
    const html = renderToString(
        <ShopLayout>
            <Product product={product} delivery_days={[2, 4]} />
        </ShopLayout>,
    );

    expect(html).toContain('9783000000011');
    expect(html).toContain('Add to cart');
    expect(html).toContain('working days');
});
