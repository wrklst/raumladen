import type { ComponentProps, ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/**
 * Visual marker for required fields; the input's `required` attribute is
 * what assistive technology reads.
 */
export function RequiredMark() {
    return (
        <span aria-hidden="true" className="text-destructive">
            {' '}
            *
        </span>
    );
}

/**
 * Labelled input with its validation error wired up for assistive
 * technology. Uncontrolled unless `value` is passed.
 */
export function Field({
    label,
    name,
    error,
    defaultValue,
    className,
    ...props
}: {
    label: ReactNode;
    name: string;
    error?: string;
    defaultValue?: string | null;
    className?: string;
} & Omit<ComponentProps<typeof Input>, 'defaultValue'>) {
    const errorId = `${name}-error`;

    return (
        <div className={cn('grid gap-1', className)}>
            <Label htmlFor={name}>
                {label}
                {props.required && <RequiredMark />}
            </Label>
            <Input
                id={name}
                name={name}
                aria-invalid={error ? true : undefined}
                aria-describedby={error ? errorId : undefined}
                {...(props.value === undefined
                    ? { defaultValue: defaultValue ?? '' }
                    : {})}
                {...props}
            />
            <InputError id={errorId} message={error} />
        </div>
    );
}
