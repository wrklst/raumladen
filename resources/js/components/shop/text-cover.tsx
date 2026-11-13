import { cn } from '@/lib/utils';

/**
 * For books with text-only covers (and any book without an image) the shop
 * sets the cover in type
 * instead of loading an image. Decorative where the title is on the page
 * anyway: pass `aria-hidden`.
 */
export function TextCover({
    title,
    authors,
    series,
    small = false,
    className,
    ...props
}: {
    title: string;
    authors?: string | null;
    /** The line under the authors: the shop's name. */
    series?: string;
    /** Thumbnail (cart): title only, in tiny type. */
    small?: boolean;
    className?: string;
    'aria-hidden'?: boolean;
}) {
    return (
        <div
            className={cn(
                'flex aspect-[3/4] flex-col justify-between border border-foreground',
                small ? 'p-1' : 'p-4',
                className,
            )}
            {...props}
        >
            <p
                className={cn(
                    'leading-[1.1] text-balance hyphens-auto',
                    small ? 'text-[0.5rem]' : 'text-2xl sm:text-3xl',
                )}
            >
                {title}
            </p>
            {!small && (
                <p className="text-sm">
                    {authors}
                    {authors && <br />}
                    {series}
                </p>
            )}
        </div>
    );
}
