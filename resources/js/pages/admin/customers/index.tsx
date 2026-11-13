import { Head, Link } from '@inertiajs/react';
import CustomerController from '@/actions/App/Http/Controllers/Admin/CustomerController';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index } from '@/routes/admin/customers';
import type { Paginated } from '@/types';

type CustomerRow = {
    id: number;
    name: string;
    email: string;
    company: string | null;
    group: string | null;
    vat_id: string | null;
    vat_id_valid: boolean;
    orders_count: number;
    created_at: string | null;
};

export default function CustomersIndex({
    customers,
    filters,
}: {
    customers: Paginated<CustomerRow>;
    filters: { q: string };
}) {
    return (
        <>
            <Head title="Customers" />

            <div className="space-y-6 p-4">
                <form method="get" action={index.url()} className="flex gap-2">
                    <Input
                        name="q"
                        defaultValue={filters.q}
                        placeholder="Search name, email or company"
                        className="w-72"
                    />
                    <Button type="submit" variant="outline">
                        Search
                    </Button>
                </form>

                {customers.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No customers found.</p>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="text-left text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">Name</th>
                                <th className="px-3 py-2 font-medium">Email</th>
                                <th className="px-3 py-2 font-medium">Group</th>
                                <th className="px-3 py-2 font-medium">VAT ID</th>
                                <th className="px-3 py-2 text-right font-medium">Orders</th>
                                <th className="px-3 py-2 font-medium">Since</th>
                                <th className="px-3 py-2 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            {customers.data.map((customer) => (
                                <tr key={customer.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <div className="font-medium">{customer.name}</div>
                                        {customer.company && (
                                            <div className="text-xs text-muted-foreground">
                                                {customer.company}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-3 py-2">{customer.email}</td>
                                    <td className="px-3 py-2">{customer.group ?? '—'}</td>
                                    <td className="px-3 py-2">
                                        <span className="flex items-center gap-2">
                                            {customer.vat_id ?? '—'}
                                            {customer.vat_id_valid && <Badge>verified</Badge>}
                                        </span>
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {customer.orders_count}
                                    </td>
                                    <td className="px-3 py-2">
                                        {customer.created_at
                                            ? new Date(customer.created_at).toLocaleDateString(
                                                  'de-DE',
                                              )
                                            : '—'}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <Link
                                            href={CustomerController.edit({ user: customer.id })}
                                            className="underline underline-offset-4"
                                        >
                                            Edit
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}

                <Pagination links={customers.links} />
            </div>
        </>
    );
}

CustomersIndex.layout = {
    breadcrumbs: [{ title: 'Customers', href: index() }],
};
