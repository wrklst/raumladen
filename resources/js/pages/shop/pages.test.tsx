import { render, screen } from '@testing-library/react';
import { expect, test } from 'vite-plus/test';
import Home from '@/pages/shop/home';
import Product from '@/pages/shop/product';
import Withdrawal from '@/pages/shop/withdrawal';
import type { ProductCard, ProductDetail, ResourcePaginated } from '@/types';

const card: ProductCard = {
    id: 1,
    type: 'book',
    title: 'The Mourning of Space',
    slug: 'the-mourning-of-space',
    url: '/de/products/the-mourning-of-space',
    price_cents: 1500,
    prices_include_tax: true,
    tax_rate: 7,
    currency: 'EUR',
    image: null,
    in_stock: true,
    authors: 'Blanca Borrell',
};

const list = (data: ProductCard[]) =>
    ({
        data,
        meta: { links: [], total: data.length },
    }) as unknown as ResourcePaginated<ProductCard>;

test('the catalogue sets each issue as a text-only cover inside one link', () => {
    render(<Home products={list([card])} filters={{ q: '', author: '' }} />);

    expect(
        screen.getByRole('heading', { level: 1, name: 'Issues' }),
    ).toBeTruthy();

    const link = screen.getByRole('link', { name: /The Mourning of Space/ });

    expect(link.getAttribute('href')).toBe(card.url);
    expect(link.textContent).toContain('Blanca Borrell');
    expect(link.textContent).toContain('15,00');
    expect(screen.queryByRole('img')).toBeNull();
    expect(screen.queryByRole('status')).toBeNull();
});

test('a search names the query, counts the results and offers the way back', () => {
    render(
        <Home
            products={list([card])}
            filters={{ q: 'mourning', author: '' }}
        />,
    );

    expect(
        screen.getByRole('heading', { level: 1, name: 'Search: mourning' }),
    ).toBeTruthy();
    expect(screen.getByRole('status').textContent).toBe('Results: 1');
    expect(screen.getByRole('link', { name: 'Show all issues' })).toBeTruthy();
});

test('an empty search says so; an author listing is headed by the name', () => {
    const view = render(
        <Home products={list([])} filters={{ q: 'xyz', author: '' }} />,
    );

    expect(screen.getByText('Nothing found.')).toBeTruthy();

    view.rerender(
        <Home
            products={list([card])}
            filters={{ q: '', author: 'Blanca Borrell' }}
        />,
    );

    expect(
        screen.getByRole('heading', { level: 1, name: 'Blanca Borrell' }),
    ).toBeTruthy();
});

test('each author on an issue links to all issues by that author', () => {
    const product: ProductDetail = {
        ...card,
        sku: 'SI-1',
        author_list: ['Ludwig Engel', 'Blanca Borrell'],
        description: 'Text.',
        images: [],
        specs: {},
        spec_fields: [],
        stock: 5,
        weight_grams: null,
    };

    render(
        <Product
            product={product}
            delivery_days={null}
            manufacturer="Space Issues e.V., Berlin"
        />,
    );

    expect(
        screen
            .getByRole('link', { name: 'Blanca Borrell' })
            .getAttribute('href'),
    ).toMatch(/author=Blanca(\+|%20)Borrell$/);
    expect(screen.getByRole('link', { name: 'Ludwig Engel' })).toBeTruthy();
    // Product safety law wants the manufacturer on the offer.
    expect(screen.getByText('Space Issues e.V., Berlin')).toBeTruthy();
});

test('the withdrawal form asks for order, name and email and confirms with one button', () => {
    render(
        <Withdrawal
            defaults={{ order_number: 'B-00042', name: null, email: null }}
            received_at={null}
        />,
    );

    expect(screen.getByRole('textbox', { name: 'Order no.' })).toHaveProperty(
        'value',
        'B-00042',
    );
    expect(screen.getByRole('textbox', { name: 'Name' })).toHaveProperty(
        'required',
        true,
    );
    expect(
        screen.getByRole('textbox', { name: 'Email address' }),
    ).toHaveProperty('required', true);
    expect(
        screen.getByRole('button', { name: 'Confirm withdrawal' }),
    ).toBeTruthy();
});

test('after a withdrawal the page states when it was received', () => {
    render(
        <Withdrawal
            defaults={{ order_number: '', name: null, email: null }}
            received_at="2026-11-13T14:05:00+00:00"
        />,
    );

    expect(screen.getByRole('status').textContent).toContain('13.11.2026');
    expect(screen.getByRole('status').textContent).toContain('15:05');
    expect(
        screen.queryByRole('button', { name: 'Confirm withdrawal' }),
    ).toBeNull();
});
