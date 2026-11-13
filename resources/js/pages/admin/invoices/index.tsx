import { Head, Link } from '@inertiajs/react';
import InvoiceController from '@/actions/App/Http/Controllers/Admin/InvoiceController';
import OrderController from '@/actions/App/Http/Controllers/Admin/OrderController';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatMoney } from '@/lib/money';
import { index } from '@/routes/admin/invoices';
import type { Paginated } from '@/types';

type InvoiceRow = {
    id: number;
    number: string;
    type: 'invoice' | 'credit_note';
    issued_at: string;
    buyer: string;
    country: string;
    tax_treatment: string;
    total_net_cents: number;
    total_tax_cents: number;
    total_gross_cents: number;
    order_uuid: string;
    order_number: string;
    exported: boolean;
};

export default function InvoicesIndex({
    invoices,
    filters,
}: {
    invoices: Paginated<InvoiceRow>;
    filters: { q: string };
}) {
    return (
        <>
            <Head title="Invoices" />

            <div className="space-y-6 p-4">
                <form method="get" action={index.url()} className="flex gap-2">
                    <Input
                        name="q"
                        defaultValue={filters.q}
                        placeholder="Search invoice number"
                        className="w-72"
                    />
                    <Button type="submit" variant="outline">
                        Search
                    </Button>
                </form>

                {invoices.data.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No invoices found.</p>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="text-left text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">Number</th>
                                <th className="px-3 py-2 font-medium">Date</th>
                                <th className="px-3 py-2 font-medium">Order</th>
                                <th className="px-3 py-2 font-medium">Buyer</th>
                                <th className="px-3 py-2 font-medium">Tax</th>
                                <th className="px-3 py-2 text-right font-medium">Net</th>
                                <th className="px-3 py-2 text-right font-medium">VAT</th>
                                <th className="px-3 py-2 text-right font-medium">Gross</th>
                                <th className="px-3 py-2 font-medium">DATEV</th>
                                <th className="px-3 py-2 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            {invoices.data.map((invoice) => (
                                <tr key={invoice.id} className="border-t">
                                    <td className="px-3 py-2 font-medium">
                                        {invoice.number}
                                        {invoice.type === 'credit_note' && (
                                            <Badge variant="outline" className="ml-2">
                                                Credit note
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-3 py-2">{invoice.issued_at}</td>
                                    <td className="px-3 py-2">
                                        <Link
                                            href={OrderController.show(invoice.order_uuid)}
                                            className="underline underline-offset-4"
                                        >
                                            {invoice.order_number}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2">
                                        {invoice.buyer} ({invoice.country})
                                    </td>
                                    <td className="px-3 py-2">{invoice.tax_treatment}</td>
                                    <td className="px-3 py-2 text-right">
                                        {formatMoney(invoice.total_net_cents)}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {formatMoney(invoice.total_tax_cents)}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {formatMoney(invoice.total_gross_cents)}
                                    </td>
                                    <td className="px-3 py-2">
                                        {invoice.exported ? 'exported' : '—'}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <a
                                            href={InvoiceController.download.url(invoice.id)}
                                            className="underline underline-offset-4"
                                        >
                                            PDF
                                        </a>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}

                <Pagination links={invoices.links} />
            </div>
        </>
    );
}

InvoicesIndex.layout = {
    breadcrumbs: [{ title: 'Invoices', href: index() }],
};
