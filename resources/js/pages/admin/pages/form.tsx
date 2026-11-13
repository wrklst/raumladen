import { Form, Head } from '@inertiajs/react';
import PageController from '@/actions/App/Http/Controllers/Admin/PageController';
import InputError from '@/components/input-error';
import { Field } from '@/components/shop/field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/admin/pages';

type Props = {
    page: {
        id: number;
        slug: string;
        title: Record<string, string>;
        body: Record<string, string>;
        is_published: boolean;
    } | null;
    locales: string[];
};

export default function PageForm({ page, locales }: Props) {
    return (
        <>
            <Head title={page?.title[locales[0]] ?? 'New page'} />

            <div className="max-w-3xl space-y-6 p-4">
                <Form
                    {...(page
                        ? PageController.update.form(page.slug)
                        : PageController.store.form())}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Page</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <Field
                                        label="Slug"
                                        name="slug"
                                        required
                                        defaultValue={page?.slug}
                                        error={errors.slug}
                                    />
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            name="is_published"
                                            value="1"
                                            defaultChecked={page?.is_published ?? true}
                                        />
                                        Published
                                    </label>
                                </CardContent>
                            </Card>

                            {locales.map((locale) => (
                                <Card key={locale}>
                                    <CardHeader>
                                        <CardTitle>{locale.toUpperCase()}</CardTitle>
                                    </CardHeader>
                                    <CardContent className="grid gap-4">
                                        <Field
                                            label="Title"
                                            name={`title[${locale}]`}
                                            required={locale === locales[0]}
                                            defaultValue={page?.title[locale]}
                                            error={errors[`title.${locale}`]}
                                        />
                                        <div className="grid gap-1">
                                            <Label htmlFor={`body-${locale}`}>
                                                Body (Markdown)
                                            </Label>
                                            <textarea
                                                id={`body-${locale}`}
                                                name={`body[${locale}]`}
                                                rows={16}
                                                defaultValue={page?.body[locale] ?? ''}
                                                className="border-input bg-background w-full rounded-md border px-3 py-2 font-mono text-sm shadow-xs"
                                            />
                                            <InputError message={errors[`body.${locale}`]} />
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}

                            <Button disabled={processing}>Save</Button>
                        </>
                    )}
                </Form>

                {page && (
                    <Form
                        {...PageController.destroy.form(page.slug)}
                        onBefore={() => confirm('Really delete this page?')}
                    >
                        <Button type="submit" variant="destructive">
                            Delete page
                        </Button>
                    </Form>
                )}
            </div>
        </>
    );
}

PageForm.layout = ({ page, locales }: Props) => ({
    breadcrumbs: [
        { title: 'Pages', href: index() },
        page
            ? {
                  title: page.title[locales[0]] ?? page.slug,
                  href: PageController.edit(page.slug),
              }
            : { title: 'New page', href: PageController.create() },
    ],
});
