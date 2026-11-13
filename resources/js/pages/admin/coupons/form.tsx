import { Form, Head } from '@inertiajs/react';
import CouponController from '@/actions/App/Http/Controllers/Admin/CouponController';
import InputError from '@/components/input-error';
import { Field } from '@/components/shop/field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/hooks/use-translation';
import { index } from '@/routes/admin/coupons';

type Props = {
    coupon: {
        id: number;
        code: string;
        type: string;
        value: string;
        min_order: string;
        starts_at: string | null;
        expires_at: string | null;
        max_uses: number | null;
        customer_group_id: number | null;
        is_active: boolean;
    } | null;
    groups: { id: number; name: string }[];
    types: string[];
};

const selectClass =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm shadow-xs';

export default function CouponForm({ coupon, groups, types }: Props) {
    const { t } = useTrans();

    return (
        <>
            <Head title={coupon?.code ?? t('New coupon')} />

            <div className="max-w-3xl space-y-6 p-4">
                <Form
                    {...(coupon
                        ? CouponController.update.form(coupon.id)
                        : CouponController.store.form())}
                >
                    {({ processing, errors }) => (
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('Coupon')}</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label={t('Code')}
                                    name="code"
                                    required
                                    defaultValue={coupon?.code}
                                    error={errors.code}
                                />
                                <div className="grid gap-1">
                                    <Label htmlFor="type">{t('Type')}</Label>
                                    <select
                                        id="type"
                                        name="type"
                                        defaultValue={coupon?.type ?? types[0]}
                                        className={selectClass}
                                    >
                                        {types.map((type) => (
                                            <option key={type} value={type}>
                                                {type === 'percent'
                                                    ? t('Percent (%)')
                                                    : t(
                                                          'Fixed amount (€, gross)',
                                                      )}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.type} />
                                </div>
                                <Field
                                    label={t('Value')}
                                    name="value"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    required
                                    defaultValue={coupon?.value}
                                    error={errors.value}
                                />
                                <Field
                                    label={t('Minimum order (€)')}
                                    name="min_order"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    defaultValue={coupon?.min_order}
                                    error={errors.min_order}
                                />
                                <Field
                                    label={t('Valid from')}
                                    name="starts_at"
                                    type="date"
                                    defaultValue={coupon?.starts_at}
                                    error={errors.starts_at}
                                />
                                <Field
                                    label={t('Expires')}
                                    name="expires_at"
                                    type="date"
                                    defaultValue={coupon?.expires_at}
                                    error={errors.expires_at}
                                />
                                <Field
                                    label={t('Maximum uses')}
                                    name="max_uses"
                                    type="number"
                                    min="1"
                                    defaultValue={coupon?.max_uses?.toString()}
                                    error={errors.max_uses}
                                />
                                <div className="grid gap-1">
                                    <Label htmlFor="customer_group_id">
                                        {t('Customer group')}
                                    </Label>
                                    <select
                                        id="customer_group_id"
                                        name="customer_group_id"
                                        defaultValue={
                                            coupon?.customer_group_id ?? ''
                                        }
                                        className={selectClass}
                                    >
                                        <option value="">
                                            {t('All groups')}
                                        </option>
                                        {groups.map((group) => (
                                            <option
                                                key={group.id}
                                                value={group.id}
                                            >
                                                {group.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.customer_group_id}
                                    />
                                </div>
                                <label className="flex items-center gap-2 text-sm sm:col-span-2">
                                    <Checkbox
                                        name="is_active"
                                        value="1"
                                        defaultChecked={
                                            coupon?.is_active ?? true
                                        }
                                    />
                                    {t('Active')}
                                </label>
                                <div className="sm:col-span-2">
                                    <Button disabled={processing}>
                                        {t('Save')}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </Form>

                {coupon && (
                    <Form
                        {...CouponController.destroy.form(coupon.id)}
                        onBefore={() =>
                            confirm(t('Really delete this coupon?'))
                        }
                    >
                        <Button type="submit" variant="destructive">
                            {t('Delete coupon')}
                        </Button>
                    </Form>
                )}
            </div>
        </>
    );
}

CouponForm.layout = ({ coupon }: Props) => ({
    breadcrumbs: [
        { title: 'Coupons', href: index() },
        coupon
            ? { title: coupon.code, href: CouponController.edit(coupon.id) }
            : { title: 'New coupon', href: CouponController.create() },
    ],
});
