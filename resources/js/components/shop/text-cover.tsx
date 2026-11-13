import { cn } from '@/lib/utils';

/**
 * Space Issues books have text-only covers, so the shop sets them in type
 * instead of loading an image. Decorative where the title is on the page
 * anyway: pass `aria-hidden`.
 */
export function TextCover({
    title,
    authors,
    small = false,
    className,
    ...props
}: {
    title: string;
    authors?: string | null;
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
                    Space Issues
                </p>
            )}
        </div>
    );
}
