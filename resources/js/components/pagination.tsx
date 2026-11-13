import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/hooks/use-translation';
import type { PaginationLink } from '@/types';

/**
 * Laravel paginator links: the first and last entries are previous / next.
 */
export function Pagination({ links }: { links: PaginationLink[] }) {
    const { t } = useTrans();

    if (links.length <= 3) {
        return null;
    }

    return (
        <nav
            className="flex flex-wrap items-center gap-1"
            aria-label={t('Pagination')}
        >
            {links.map((link, index) => {
                const label =
                    index === 0
                        ? t('Previous')
                        : index === links.length - 1
                          ? t('Next')
                          : link.label;

                return (
                    <Button
                        key={index}
                        asChild={link.url !== null}
                        size="sm"
                        variant={link.active ? 'default' : 'outline'}
                        disabled={link.url === null}
                    >
                        {link.url !== null ? (
                            <Link
                                href={link.url}
                                aria-current={link.active ? 'page' : undefined}
                            >
                                {label}
                            </Link>
                        ) : (
                            <span>{label}</span>
                        )}
                    </Button>
                );
            })}
        </nav>
    );
}
