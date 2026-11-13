import type { ComponentProps } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Labelled uncontrolled input with its validation error.
 */
export function Field({
    label,
    name,
    error,
    defaultValue,
    className,
    ...props
}: {
    label: string;
    name: string;
    error?: string;
    defaultValue?: string | null;
    className?: string;
} & Omit<ComponentProps<typeof Input>, 'defaultValue'>) {
    return (
        <div className={`grid gap-1 ${className ?? ''}`}>
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                defaultValue={defaultValue ?? ''}
                {...props}
            />
            <InputError message={error} />
        </div>
    );
}
