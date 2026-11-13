import { AddressAutocomplete } from '@/components/shop/address-autocomplete';
import { Field, RequiredMark } from '@/components/shop/field';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/hooks/use-translation';
import type { Address } from '@/types';

type Props = {
    prefix: 'billing_address' | 'shipping_address';
    values: Address | null | undefined;
    countries: Record<string, string>;
    errors: Record<string, string | undefined>;
    /** Controlled country so callers can react to a change (VAT, shipping). */
    country?: string;
    onCountry?: (country: string) => void;
    required?: boolean;
};

const setValue = (id: string, value: string): void => {
    const element = document.getElementById(id);

    if (element instanceof HTMLInputElement) {
        element.value = value;
    }
};

/**
 * Name and address. The country comes first because it narrows the street
 * suggestions; picking one fills street, postcode and city. Rarely needed
 * fields sit behind one disclosure.
 */
export function AddressFields({
    prefix,
    values,
    countries,
    errors,
    country,
    onCountry,
    required = false,
}: Props) {
    const { t } = useTrans();
    // Lets browsers autofill billing and shipping separately (WCAG 1.3.5).
    const section = prefix === 'billing_address' ? 'billing' : 'shipping';
    const countryId = `${prefix}-country`;
    const countryError = errors[`${prefix}.country`];
    const hasExtras = Boolean(
        values?.company ||
        values?.address2 ||
        errors[`${prefix}.company`] ||
        errors[`${prefix}.address2`],
    );

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <Field
                label={t('First name')}
                name={`${prefix}[first_name]`}
                defaultValue={values?.first_name}
                error={errors[`${prefix}.first_name`]}
                autoComplete={`${section} given-name`}
                required={required}
            />
            <Field
                label={t('Last name')}
                name={`${prefix}[last_name]`}
                defaultValue={values?.last_name}
                error={errors[`${prefix}.last_name`]}
                autoComplete={`${section} family-name`}
                required={required}
            />
            <div className="grid gap-1 sm:col-span-2">
                <Label htmlFor={countryId}>
                    {t('Country')}
                    {required && <RequiredMark />}
                </Label>
                <select
                    id={countryId}
                    name={`${prefix}[country]`}
                    value={onCountry ? country : undefined}
                    defaultValue={
                        onCountry ? undefined : (values?.country ?? '')
                    }
                    onChange={
                        onCountry ? (e) => onCountry(e.target.value) : undefined
                    }
                    autoComplete={`${section} country`}
                    required={required}
                    aria-invalid={countryError ? true : undefined}
                    aria-describedby={
                        countryError ? `${countryId}-error` : undefined
                    }
                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                >
                    {!onCountry && <option value="">—</option>}
                    {Object.entries(countries).map(([code, name]) => (
                        <option key={code} value={code}>
                            {name}
                        </option>
                    ))}
                </select>
                <InputError id={`${countryId}-error`} message={countryError} />
            </div>
            <AddressAutocomplete
                className="sm:col-span-2"
                label={t('Street and number')}
                name={`${prefix}[address1]`}
                defaultValue={values?.address1}
                error={errors[`${prefix}.address1`]}
                autoComplete={`${section} address-line1`}
                required={required}
                country={() =>
                    (
                        document.getElementById(
                            countryId,
                        ) as HTMLSelectElement | null
                    )?.value ?? ''
                }
                onSelect={(suggestion) => {
                    setValue(`${prefix}[zip]`, suggestion.zip);
                    setValue(`${prefix}[city]`, suggestion.city);
                }}
            />
            <Field
                label={t('Postal code')}
                name={`${prefix}[zip]`}
                defaultValue={values?.zip}
                error={errors[`${prefix}.zip`]}
                autoComplete={`${section} postal-code`}
                required={required}
            />
            <Field
                label={t('City')}
                name={`${prefix}[city]`}
                defaultValue={values?.city}
                error={errors[`${prefix}.city`]}
                autoComplete={`${section} address-level2`}
                required={required}
            />
            <details className="sm:col-span-2" open={hasExtras || undefined}>
                <summary className="cursor-pointer text-sm underline underline-offset-4">
                    {t('Add company or address line 2')}
                </summary>
                <div className="mt-4 grid gap-4">
                    <Field
                        label={t('Company (optional)')}
                        name={`${prefix}[company]`}
                        defaultValue={values?.company}
                        error={errors[`${prefix}.company`]}
                        autoComplete={`${section} organization`}
                    />
                    <Field
                        label={t('Address line 2 (optional)')}
                        name={`${prefix}[address2]`}
                        defaultValue={values?.address2}
                        error={errors[`${prefix}.address2`]}
                        autoComplete={`${section} address-line2`}
                    />
                </div>
            </details>
        </div>
    );
}
