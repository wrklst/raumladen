import { Form, Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import CustomerController from '@/actions/App/Http/Controllers/Admin/CustomerController';
import InputError from '@/components/input-error';
import { AddressBlock } from '@/components/shop/address-block';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/admin/customers';
import type { Address } from '@/types';

type Props = {
    customer: {
        id: number;
        name: string;
        email: string;
        company: string | null;
        vat_id: string | null;
        vat_id_valid: boolean;
        customer_group_id: number | null;
        billing_address: Address | null;
        shipping_address: Address | null;
    };
    groups: { id: number; name: string }[];
};

function Field({
    label,
    htmlFor,
    error,
    children,
}: {
    label: string;
    htmlFor?: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

export default function CustomerEdit({ customer, groups }: Props) {
    return (
        <>
            <Head title={customer.name} />

            <div className="max-w-3xl space-y-6 p-4">
                <Form
                    {...CustomerController.update.form({ user: customer.id })}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <Card>
                            <CardHeader>
                                <CardTitle>Customer</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <Field label="Name" htmlFor="name" error={errors.name}>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        defaultValue={customer.name}
                                    />
                                </Field>
                                <Field label="Email" htmlFor="email" error={errors.email}>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        required
                                        defaultValue={customer.email}
                                    />
                                </Field>
                                <Field label="Company" htmlFor="company" error={errors.company}>
                                    <Input
                                        id="company"
                                        name="company"
                                        defaultValue={customer.company ?? ''}
                                    />
                                </Field>
                                <Field label="VAT ID" htmlFor="vat_id" error={errors.vat_id}>
                                    <Input
                                        id="vat_id"
                                        name="vat_id"
                                        defaultValue={customer.vat_id ?? ''}
                                    />
                                </Field>
                                <Field
                                    label="Customer group"
                                    error={errors.customer_group_id}
                                >
                                    <Select
                                        name="customer_group_id"
                                        defaultValue={
                                            customer.customer_group_id
                                                ? String(customer.customer_group_id)
                                                : undefined
                                        }
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="Select group" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {groups.map((group) => (
                                                <SelectItem
                                                    key={group.id}
                                                    value={String(group.id)}
                                                >
                                                    {group.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                                <div className="flex items-end">
                                    <label className="flex h-9 items-center gap-2 text-sm">
                                        <Checkbox
                                            name="vat_id_valid"
                                            value="1"
                                            defaultChecked={customer.vat_id_valid}
                                        />
                                        Treat VAT ID as verified (manual override)
                                    </label>
                                </div>
                                <InputError message={errors.vat_id_valid} />
                                <div className="sm:col-span-2">
                                    <Button disabled={processing}>Save</Button>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </Form>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Billing address</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {customer.billing_address ? (
                                <AddressBlock address={customer.billing_address} />
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No address on file.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Shipping address</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {customer.shipping_address ? (
                                <AddressBlock address={customer.shipping_address} />
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No address on file.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

CustomerEdit.layout = ({ customer }: Props) => ({
    breadcrumbs: [
        { title: 'Customers', href: index() },
        { title: customer.name, href: CustomerController.edit({ user: customer.id }) },
    ],
});
