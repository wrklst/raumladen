import { Form, Head } from '@inertiajs/react';
import PageController from '@/actions/App/Http/Controllers/Admin/PageController';
import InputError from '@/components/input-error';
import { Field } from '@/components/shop/field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/hooks/use-translation';
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
    const { t } = useTrans();

    return (
        <>
            <Head title={page?.title[locales[0]] ?? t('New page')} />

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
                                    <CardTitle>{t('Page')}</CardTitle>
                                </CardHeader>
                                <CardContent className="grid gap-4">
                                    <Field
                                        label={t('Slug')}
                                        name="slug"
                                        required
                                        defaultValue={page?.slug}
                                        error={errors.slug}
                                    />
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            name="is_published"
                                            value="1"
                                            defaultChecked={
                                                page?.is_published ?? true
                                            }
                                        />
                                        {t('Published (visible in the shop)')}
                                    </label>
                                </CardContent>
                            </Card>

                            {locales.map((locale) => (
                                <Card key={locale}>
                                    <CardHeader>
                                        <CardTitle>
                                            {locale.toUpperCase()}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="grid gap-4">
                                        <Field
                                            label={t('Title')}
                                            name={`title[${locale}]`}
                                            required={locale === locales[0]}
                                            defaultValue={page?.title[locale]}
                                            error={errors[`title.${locale}`]}
                                        />
                                        <div className="grid gap-1">
                                            <Label htmlFor={`body-${locale}`}>
                                                {t('Body (Markdown)')}
                                            </Label>
                                            <textarea
                                                id={`body-${locale}`}
                                                name={`body[${locale}]`}
                                                rows={16}
                                                defaultValue={
                                                    page?.body[locale] ?? ''
                                                }
                                                className="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm shadow-xs"
                                            />
                                            <InputError
                                                message={
                                                    errors[`body.${locale}`]
                                                }
                                            />
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}

                            <Button disabled={processing}>{t('Save')}</Button>
                        </>
                    )}
                </Form>

                {page && (
                    <Form
                        {...PageController.destroy.form(page.slug)}
                        onBefore={() => confirm(t('Really delete this page?'))}
                    >
                        <Button type="submit" variant="destructive">
                            {t('Delete page')}
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
