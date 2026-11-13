import { Head, Link } from '@inertiajs/react';
import PageController from '@/actions/App/Http/Controllers/Admin/PageController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/admin/pages';

type PageRow = {
    id: number;
    slug: string;
    title: string | null;
    is_published: boolean;
    updated_at: string | null;
};

export default function PagesIndex({ pages }: { pages: PageRow[] }) {
    return (
        <>
            <Head title="Pages" />

            <div className="space-y-6 p-4">
                <Button asChild>
                    <Link href={PageController.create()}>New page</Link>
                </Button>

                {pages.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No pages yet.</p>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="text-left text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">Title</th>
                                <th className="px-3 py-2 font-medium">Slug</th>
                                <th className="px-3 py-2 font-medium">Status</th>
                                <th className="px-3 py-2 font-medium">Updated</th>
                                <th className="px-3 py-2 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            {pages.map((page) => (
                                <tr key={page.id} className="border-t">
                                    <td className="px-3 py-2 font-medium">{page.title}</td>
                                    <td className="px-3 py-2 text-muted-foreground">
                                        {page.slug}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Badge
                                            variant={page.is_published ? 'default' : 'outline'}
                                        >
                                            {page.is_published ? 'Published' : 'Draft'}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-2">{page.updated_at ?? '—'}</td>
                                    <td className="px-3 py-2 text-right">
                                        <Link
                                            href={PageController.edit(page.slug)}
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
            </div>
        </>
    );
}

PagesIndex.layout = {
    breadcrumbs: [{ title: 'Pages', href: index() }],
};
