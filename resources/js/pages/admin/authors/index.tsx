import { Form, Head } from '@inertiajs/react';
import AuthorController from '@/actions/App/Http/Controllers/Admin/AuthorController';
import InputError from '@/components/input-error';
import { Field } from '@/components/shop/field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/hooks/use-translation';
import { index } from '@/routes/admin/authors';

type Author = {
    id: number;
    name: string;
    bio: Record<string, string | null> | null;
};

function AuthorFields({
    author,
    locales,
    errors,
}: {
    author?: Author;
    locales: string[];
    errors: Record<string, string>;
}) {
    const { t } = useTrans();
    const key = author?.id ?? 'new';

    return (
        <>
            <Field
                label={t('Name')}
                name="name"
                required
                defaultValue={author?.name}
                error={errors.name}
            />
            {locales.map((locale) => (
                <div key={locale} className="grid gap-1">
                    <Label htmlFor={`bio-${key}-${locale}`}>
                        {t('About the author')} ({locale.toUpperCase()})
                    </Label>
                    <textarea
                        id={`bio-${key}-${locale}`}
                        name={`bio[${locale}]`}
                        rows={5}
                        maxLength={5000}
                        defaultValue={author?.bio?.[locale] ?? ''}
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                    <InputError message={errors[`bio.${locale}`]} />
                </div>
            ))}
        </>
    );
}

/**
 * Authors and a few lines about each. A book names its authors as text; an
 * author entered here under the same name is shown above their books.
 */
export default function AuthorsIndex({
    authors,
    locales,
}: {
    authors: Author[];
    locales: string[];
}) {
    const { t } = useTrans();

    return (
        <>
            <Head title={t('Authors')} />

            <div className="max-w-3xl space-y-6 p-4">
                <p className="text-sm text-muted-foreground">
                    {t(
                        'The name must match the name on the book. The text appears in the shop above the books of that author.',
                    )}
                </p>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('New author')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...AuthorController.store.form()}
                            resetOnSuccess
                            className="grid gap-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <AuthorFields
                                        locales={locales}
                                        errors={errors}
                                    />
                                    <div>
                                        <Button disabled={processing}>
                                            {t('Create')}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {authors.map((author) => (
                    <Card key={author.id}>
                        <CardHeader>
                            <CardTitle>{author.name}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Form
                                {...AuthorController.update.form(author.id)}
                                options={{ preserveScroll: true }}
                                className="grid gap-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <AuthorFields
                                            author={author}
                                            locales={locales}
                                            errors={errors}
                                        />
                                        <div>
                                            <Button disabled={processing}>
                                                {t('Save')}
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                            <Form
                                {...AuthorController.destroy.form(author.id)}
                                onBefore={() =>
                                    confirm(t('Really delete this author?'))
                                }
                            >
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    size="sm"
                                >
                                    {t('Delete')}
                                </Button>
                            </Form>
                        </CardContent>
                    </Card>
                ))}
            </div>
        </>
    );
}

AuthorsIndex.layout = {
    breadcrumbs: [{ title: 'Authors', href: index() }],
};
