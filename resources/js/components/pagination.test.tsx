import { render, screen } from '@testing-library/react';
import { expect, test } from 'vite-plus/test';
import { Pagination } from '@/components/pagination';

const link = (label: string, url: string | null, active = false) => ({
    label,
    url,
    active,
});

test('renders nothing for a single page', () => {
    const { container } = render(
        <Pagination
            links={[
                link('&laquo; Previous', null),
                link('1', '/de', true),
                link('Next &raquo;', null),
            ]}
        />,
    );

    expect(container.innerHTML).toBe('');
});

test('marks the current page and labels previous and next', () => {
    render(
        <Pagination
            links={[
                link('&laquo; Previous', null),
                link('1', '/de?page=1', true),
                link('2', '/de?page=2'),
                link('Next &raquo;', '/de?page=2'),
            ]}
        />,
    );

    expect(screen.getByRole('navigation', { name: 'Pagination' })).toBeTruthy();
    expect(
        screen.getByRole('link', { name: '1' }).getAttribute('aria-current'),
    ).toBe('page');
    expect(
        screen.getByRole('link', { name: '2' }).hasAttribute('aria-current'),
    ).toBe(false);
    expect(
        screen.getByRole('link', { name: 'Next' }).getAttribute('href'),
    ).toBe('/de?page=2');
    // No previous page: plain text, not a link.
    expect(screen.queryByRole('link', { name: 'Previous' })).toBeNull();
});
