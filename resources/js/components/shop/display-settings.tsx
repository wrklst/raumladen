import { Accessibility } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useA11yPrefs } from '@/hooks/use-a11y-prefs';
import type { A11yPrefs } from '@/hooks/use-a11y-prefs';
import { useAppearance } from '@/hooks/use-appearance';
import type { Appearance } from '@/hooks/use-appearance';
import { useTrans } from '@/hooks/use-translation';

/**
 * Native radios styled as a segmented control: keyboard and screen reader
 * behaviour comes from the browser.
 */
function Segmented<T extends string>({
    legend,
    name,
    value,
    options,
    onChange,
}: {
    legend: string;
    name: string;
    value: T;
    options: { value: T; label: string }[];
    onChange: (value: T) => void;
}) {
    return (
        <fieldset className="space-y-2">
            <legend className="text-sm font-medium">{legend}</legend>
            <div className="flex flex-wrap gap-1">
                {options.map((option) => (
                    <label
                        key={option.value}
                        className="cursor-pointer rounded-md border border-input px-3 py-1.5 text-sm has-checked:border-primary has-checked:bg-primary has-checked:text-primary-foreground has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-(--focus)"
                    >
                        <input
                            type="radio"
                            name={name}
                            value={option.value}
                            checked={value === option.value}
                            onChange={() => onChange(option.value)}
                            className="sr-only"
                        />
                        {option.label}
                    </label>
                ))}
            </div>
        </fieldset>
    );
}

/**
 * Colour scheme and accessibility tools for the storefront header.
 */
export function DisplaySettings() {
    const { t } = useTrans();
    const { appearance, updateAppearance } = useAppearance();
    const { prefs, setPref, resetPrefs } = useA11yPrefs();

    return (
        <Sheet>
            <SheetTrigger asChild>
                <Button variant="ghost" size="sm">
                    <Accessibility className="size-4" />
                    <span className="sr-only lg:not-sr-only">
                        {t('Display')}
                    </span>
                </Button>
            </SheetTrigger>
            <SheetContent
                closeLabel={t('Close')}
                className="w-full overflow-y-auto sm:max-w-sm"
            >
                <SheetHeader>
                    <SheetTitle>{t('Display and accessibility')}</SheetTitle>
                    <SheetDescription>
                        {t('These settings are saved in this browser only.')}
                    </SheetDescription>
                </SheetHeader>

                <div className="space-y-6 px-4 pb-6">
                    <Segmented<Appearance>
                        legend={t('Colour scheme')}
                        name="appearance"
                        value={appearance}
                        onChange={updateAppearance}
                        options={[
                            { value: 'light', label: t('Light') },
                            { value: 'dark', label: t('Dark') },
                            { value: 'system', label: t('System') },
                        ]}
                    />

                    <Segmented<NonNullable<A11yPrefs['a11yText']> | 'md'>
                        legend={t('Text size')}
                        name="a11y-text"
                        value={prefs.a11yText ?? 'md'}
                        onChange={(size) =>
                            setPref(
                                'a11yText',
                                size === 'md' ? undefined : size,
                            )
                        }
                        options={[
                            { value: 'md', label: '100 %' },
                            { value: 'lg', label: '112 %' },
                            { value: 'xl', label: '125 %' },
                            { value: '2xl', label: '150 %' },
                        ]}
                    />

                    {/* The theme is black on white without motion, so there is no
                        contrast or motion switch: an option has to change something. */}
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            className="size-4 accent-primary"
                            checked={prefs.a11yLinks === 'underline'}
                            onChange={(event) =>
                                setPref(
                                    'a11yLinks',
                                    event.target.checked
                                        ? 'underline'
                                        : undefined,
                                )
                            }
                        />
                        {t('Underline links')}
                    </label>

                    <Button
                        variant="outline"
                        onClick={() => {
                            resetPrefs();
                            updateAppearance('system');
                        }}
                    >
                        {t('Reset to defaults')}
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    );
}
