import { Form } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Account/ProfileController';
import InputError from '@/components/input-error';
import { AccountShell } from '@/components/shop/account-nav';
import { AddressFields } from '@/components/shop/address-fields';
import { Field } from '@/components/shop/field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/hooks/use-translation';
import type { Address } from '@/types';

type Props = {
    profile: {
        name: string;
        email: string;
        company: string | null;
        vat_id: string | null;
        vat_id_valid: boolean;
        phone: string | null;
        locale: string | null;
        billing_address: Address | null;
        shipping_address: Address | null;
        customer_group: string | null;
    };
    countries: Record<string, string>;
};

export default function Profile({ profile, countries }: Props) {
    const { t, locales } = useTrans();

    return (
        <AccountShell title={t('Your profile')}>
            <Form
                {...ProfileController.update.form()}
                options={{ preserveScroll: true }}
                className="space-y-8"
            >
                {({ processing, errors }) => (
                    <>
                        <section className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label={t('Name')}
                                name="name"
                                defaultValue={profile.name}
                                error={errors.name}
                                autoComplete="name"
                                required
                            />
                            <Field
                                label={t('Email address')}
                                name="email_display"
                                defaultValue={profile.email}
                                readOnly
                                disabled
                            />
                            <Field
                                label={t('Company (optional)')}
                                name="company"
                                defaultValue={profile.company}
                                error={errors.company}
                                autoComplete="organization"
                            />
                            <Field
                                label={t('Phone (optional)')}
                                name="phone"
                                defaultValue={profile.phone}
                                error={errors.phone}
                                autoComplete="tel"
                            />
                            <div className="grid gap-1">
                                <Label htmlFor="vat_id">
                                    {t('VAT ID (businesses in the EU)')}
                                    {profile.vat_id_valid && (
                                        <Badge variant="secondary" className="ml-2">
                                            {t('verified')}
                                        </Badge>
                                    )}
                                </Label>
                                <Field
                                    label=""
                                    name="vat_id"
                                    defaultValue={profile.vat_id}
                                    error={errors.vat_id}
                                    placeholder="ATU12345678"
                                />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="locale">{t('Language')}</Label>
                                <select
                                    id="locale"
                                    name="locale"
                                    defaultValue={profile.locale ?? ''}
                                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs"
                                >
                                    {locales.map((code) => (
                                        <option key={code} value={code}>
                                            {code.toUpperCase()}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.locale} />
                            </div>
                            {profile.customer_group && (
                                <p className="text-sm text-muted-foreground sm:col-span-2">
                                    {t('Customer group')}: {profile.customer_group}
                                </p>
                            )}
                        </section>

                        <section className="space-y-3">
                            <h2 className="text-lg font-medium">{t('Billing address')}</h2>
                            <AddressFields
                                prefix="billing_address"
                                values={profile.billing_address}
                                countries={countries}
                                errors={errors}
                            />
                        </section>

                        <section className="space-y-3">
                            <h2 className="text-lg font-medium">{t('Shipping address')}</h2>
                            <AddressFields
                                prefix="shipping_address"
                                values={profile.shipping_address}
                                countries={countries}
                                errors={errors}
                            />
                        </section>

                        <Button type="submit" disabled={processing}>
                            {t('Save')}
                        </Button>
                    </>
                )}
            </Form>
        </AccountShell>
    );
}
