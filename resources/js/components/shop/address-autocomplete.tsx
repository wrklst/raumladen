import { useEffect, useId, useRef, useState } from 'react';
import AddressSuggestionController from '@/actions/App/Http/Controllers/Shop/AddressSuggestionController';
import InputError from '@/components/input-error';
import { RequiredMark } from '@/components/shop/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

export type AddressSuggestion = {
    address1: string;
    zip: string;
    city: string;
    country: string;
    label: string;
};

/**
 * Street field with address suggestions (WAI-ARIA combobox with a listbox).
 * Suggestions come from our own server, which caches Photon; the field works
 * as a plain input when they are slow, empty or unavailable.
 */
export function AddressAutocomplete({
    label,
    name,
    defaultValue,
    error,
    required,
    autoComplete,
    className,
    country,
    onSelect,
}: {
    label: string;
    name: string;
    defaultValue?: string | null;
    error?: string;
    required?: boolean;
    autoComplete?: string;
    className?: string;
    /** Reads the country at request time, so suggestions match the form. */
    country: () => string;
    onSelect: (suggestion: AddressSuggestion) => void;
}) {
    const { t } = useTrans();
    const listId = useId();
    const input = useRef<HTMLInputElement>(null);
    const [query, setQuery] = useState('');
    const [suggestions, setSuggestions] = useState<AddressSuggestion[]>([]);
    const [attribution, setAttribution] = useState('');
    const [active, setActive] = useState(-1);
    const open = suggestions.length > 0;

    useEffect(() => {
        // The server does not ask below three characters either.
        if (query.trim().length < 3) {
            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(() => {
            fetch(
                AddressSuggestionController.url(undefined, {
                    query: { q: query, country: country() },
                }),
                {
                    signal: controller.signal,
                    headers: { Accept: 'application/json' },
                },
            )
                .then((response) => (response.ok ? response.json() : null))
                .then((data) => {
                    setSuggestions(data?.suggestions ?? []);
                    setAttribution(data?.attribution ?? '');
                    setActive(-1);
                })
                .catch(() => {});
        }, 300);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
        // `country` is a getter, read when the request is sent.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [query]);

    const close = (): void => {
        setSuggestions([]);
        setActive(-1);
    };

    const choose = (suggestion: AddressSuggestion): void => {
        if (input.current) {
            input.current.value = suggestion.address1;
        }

        onSelect(suggestion);
        close();
    };

    const errorId = `${name}-error`;

    return (
        <div className={cn('grid gap-1', className)}>
            <Label htmlFor={name}>
                {label}
                {required && <RequiredMark />}
            </Label>
            <div className="relative">
                <Input
                    ref={input}
                    id={name}
                    name={name}
                    defaultValue={defaultValue ?? ''}
                    required={required}
                    autoComplete={autoComplete}
                    role="combobox"
                    aria-expanded={open}
                    aria-controls={listId}
                    aria-autocomplete="list"
                    aria-activedescendant={
                        active >= 0 ? `${listId}-${active}` : undefined
                    }
                    aria-invalid={error ? true : undefined}
                    aria-describedby={error ? errorId : undefined}
                    onChange={(event) => {
                        setQuery(event.target.value);

                        if (event.target.value.trim().length < 3) {
                            close();
                        }
                    }}
                    onBlur={close}
                    onKeyDown={(event) => {
                        if (!open) {
                            return;
                        }

                        if (event.key === 'ArrowDown') {
                            event.preventDefault();
                            setActive((active + 1) % suggestions.length);
                        } else if (event.key === 'ArrowUp') {
                            event.preventDefault();
                            setActive(
                                (active - 1 + suggestions.length) %
                                    suggestions.length,
                            );
                        } else if (event.key === 'Enter' && active >= 0) {
                            // Enter picks the suggestion instead of placing the order.
                            event.preventDefault();
                            choose(suggestions[active]);
                        } else if (event.key === 'Escape') {
                            close();
                        }
                    }}
                />
                <div
                    className={cn(
                        'absolute inset-x-0 top-full z-10 border border-t-0 border-foreground bg-background',
                        !open && 'hidden',
                    )}
                >
                    <ul id={listId} role="listbox" aria-label={label}>
                        {suggestions.map((suggestion, index) => (
                            <li
                                key={suggestion.label}
                                id={`${listId}-${index}`}
                                role="option"
                                aria-selected={index === active}
                                className={cn(
                                    'cursor-pointer px-3 py-2 text-sm',
                                    index === active &&
                                        'bg-foreground text-background',
                                )}
                                // mousedown, not click: the input's blur would close the list first.
                                onMouseDown={(event) => {
                                    event.preventDefault();
                                    choose(suggestion);
                                }}
                                onMouseEnter={() => setActive(index)}
                            >
                                {suggestion.label}
                            </li>
                        ))}
                    </ul>
                    {/* ODbL: the source is named where the data is shown. */}
                    <p className="border-t border-foreground px-3 py-1 text-xs">
                        {attribution}
                    </p>
                </div>
            </div>
            <p role="status" className="sr-only">
                {open
                    ? t(':count address suggestions available', {
                          count: suggestions.length,
                      })
                    : ''}
            </p>
            <InputError id={errorId} message={error} />
        </div>
    );
}
