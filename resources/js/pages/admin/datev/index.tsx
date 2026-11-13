import { Form, Head } from '@inertiajs/react';
import DatevExportController from '@/actions/App/Http/Controllers/Admin/DatevExportController';
import { Field } from '@/components/shop/field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index } from '@/routes/admin/datev';

type Props = {
    exports: {
        id: number;
        from_date: string;
        to_date: string;
        invoice_count: number;
        booking_count: number;
        sha256: string;
        user: string | null;
        created_at: string | null;
    }[];
    unexported_count: number;
    defaults: { from: string; to: string };
    settings: {
        skr: string;
        debtor_account: string | number;
        accounts: unknown;
        consultant_number: string | number | null;
        client_number: string | number | null;
    };
};

export default function DatevIndex({
    exports,
    unexported_count,
    defaults,
    settings,
}: Props) {
    return (
        <>
            <Head title="DATEV export" />

            <div className="max-w-4xl space-y-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>New export</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                            {unexported_count} documents not exported yet. Each invoice and
                            credit note is exported exactly once (EXTF Buchungsstapel).
                        </p>
                        <Form
                            {...DatevExportController.store.form()}
                            className="flex flex-wrap items-start gap-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Field
                                        label="From"
                                        name="from"
                                        type="date"
                                        required
                                        defaultValue={defaults.from}
                                        error={errors.from}
                                    />
                                    <Field
                                        label="To"
                                        name="to"
                                        type="date"
                                        required
                                        defaultValue={defaults.to}
                                        error={errors.to}
                                    />
                                    <Button disabled={processing} className="mt-5">
                                        Export
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Exports</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {exports.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No exports yet.</p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead className="text-left text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">Period</th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            Documents
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            Bookings
                                        </th>
                                        <th className="px-3 py-2 font-medium">Created</th>
                                        <th className="px-3 py-2 font-medium">SHA-256</th>
                                        <th className="px-3 py-2 font-medium" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {exports.map((row) => (
                                        <tr key={row.id} className="border-t">
                                            <td className="px-3 py-2">
                                                {row.from_date} – {row.to_date}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                {row.invoice_count}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                {row.booking_count}
                                            </td>
                                            <td className="px-3 py-2">
                                                {row.created_at}
                                                {row.user && ` · ${row.user}`}
                                            </td>
                                            <td
                                                className="px-3 py-2 font-mono text-xs"
                                                title={row.sha256}
                                            >
                                                {row.sha256.slice(0, 12)}…
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                <a
                                                    href={DatevExportController.download.url(
                                                        row.id,
                                                    )}
                                                    className="underline underline-offset-4"
                                                >
                                                    CSV
                                                </a>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Settings (config/shop.php)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <pre className="overflow-x-auto text-xs">
                            {JSON.stringify(settings, null, 2)}
                        </pre>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

DatevIndex.layout = {
    breadcrumbs: [{ title: 'DATEV export', href: index() }],
};
