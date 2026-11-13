import InputError from '@/components/input-error';
import { Field } from '@/components/shop/field';
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

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <Field
                label={t('First name')}
                name={`${prefix}[first_name]`}
                defaultValue={values?.first_name}
                error={errors[`${prefix}.first_name`]}
                autoComplete="given-name"
                required={required}
            />
            <Field
                label={t('Last name')}
                name={`${prefix}[last_name]`}
                defaultValue={values?.last_name}
                error={errors[`${prefix}.last_name`]}
                autoComplete="family-name"
                required={required}
            />
            <Field
                className="sm:col-span-2"
                label={t('Company (optional)')}
                name={`${prefix}[company]`}
                defaultValue={values?.company}
                error={errors[`${prefix}.company`]}
                autoComplete="organization"
            />
            <Field
                className="sm:col-span-2"
                label={t('Street and number')}
                name={`${prefix}[address1]`}
                defaultValue={values?.address1}
                error={errors[`${prefix}.address1`]}
                autoComplete="address-line1"
                required={required}
            />
            <Field
                className="sm:col-span-2"
                label={t('Address line 2 (optional)')}
                name={`${prefix}[address2]`}
                defaultValue={values?.address2}
                error={errors[`${prefix}.address2`]}
                autoComplete="address-line2"
            />
            <Field
                label={t('Postal code')}
                name={`${prefix}[zip]`}
                defaultValue={values?.zip}
                error={errors[`${prefix}.zip`]}
                autoComplete="postal-code"
                required={required}
            />
            <Field
                label={t('City')}
                name={`${prefix}[city]`}
                defaultValue={values?.city}
                error={errors[`${prefix}.city`]}
                autoComplete="address-level2"
                required={required}
            />
            <div className="grid gap-1 sm:col-span-2">
                <Label htmlFor={`${prefix}-country`}>{t('Country')}</Label>
                <select
                    id={`${prefix}-country`}
                    name={`${prefix}[country]`}
                    value={onCountry ? country : undefined}
                    defaultValue={onCountry ? undefined : (values?.country ?? '')}
                    onChange={onCountry ? (e) => onCountry(e.target.value) : undefined}
                    autoComplete="country"
                    required={required}
                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs"
                >
                    {!onCountry && <option value="">—</option>}
                    {Object.entries(countries).map(([code, name]) => (
                        <option key={code} value={code}>
                            {name}
                        </option>
                    ))}
                </select>
                <InputError message={errors[`${prefix}.country`]} />
            </div>
        </div>
    );
}
