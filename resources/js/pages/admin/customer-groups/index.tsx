import { Form, Head } from '@inertiajs/react';
import CustomerGroupController from '@/actions/App/Http/Controllers/Admin/CustomerGroupController';
import { Field } from '@/components/shop/field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { useTrans } from '@/hooks/use-translation';
import { index } from '@/routes/admin/customer-groups';

type Group = {
    id: number;
    code: string;
    name: string;
    prices_include_tax: boolean;
    is_default: boolean;
    users_count: number;
};

function GroupFields({
    group,
    errors,
}: {
    group?: Group;
    errors: Record<string, string>;
}) {
    const { t } = useTrans();

    return (
        <>
            <Field
                label={t('Code')}
                name="code"
                required
                defaultValue={group?.code}
                error={errors.code}
            />
            <Field
                label={t('Name')}
                name="name"
                required
                defaultValue={group?.name}
                error={errors.name}
            />
            <label className="flex items-center gap-2 text-sm">
                <Checkbox
                    name="prices_include_tax"
                    value="1"
                    defaultChecked={group?.prices_include_tax ?? true}
                />
                {t('Show prices including VAT (B2C)')}
            </label>
            <label className="flex items-center gap-2 text-sm">
                <Checkbox
                    name="is_default"
                    value="1"
                    defaultChecked={group?.is_default ?? false}
                    disabled={group?.is_default}
                />
                {t('Default group for new customers and guests')}
            </label>
        </>
    );
}

export default function CustomerGroupsIndex({ groups }: { groups: Group[] }) {
    const { t } = useTrans();

    return (
        <>
            <Head title={t('Customer groups')} />

            <div className="max-w-3xl space-y-6 p-4">
                {groups.map((group) => (
                    <Card key={group.id}>
                        <CardHeader>
                            <CardTitle>
                                {group.name}{' '}
                                <span className="text-sm font-normal text-muted-foreground">
                                    ·{' '}
                                    {t(':count customers', {
                                        count: group.users_count,
                                    })}
                                </span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Form
                                {...CustomerGroupController.update.form(
                                    group.id,
                                )}
                                options={{ preserveScroll: true }}
                                className="grid gap-4 sm:grid-cols-2"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <GroupFields
                                            group={group}
                                            errors={errors}
                                        />
                                        <div className="sm:col-span-2">
                                            <Button disabled={processing}>
                                                {t('Save')}
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                            {!group.is_default && group.users_count === 0 && (
                                <Form
                                    {...CustomerGroupController.destroy.form(
                                        group.id,
                                    )}
                                    onBefore={() =>
                                        confirm(t('Really delete this group?'))
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
                            )}
                        </CardContent>
                    </Card>
                ))}

                <Card>
                    <CardHeader>
                        <CardTitle>{t('New group')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...CustomerGroupController.store.form()}
                            resetOnSuccess
                            className="grid gap-4 sm:grid-cols-2"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <GroupFields errors={errors} />
                                    <div className="sm:col-span-2">
                                        <Button disabled={processing}>
                                            {t('Create')}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

CustomerGroupsIndex.layout = {
    breadcrumbs: [{ title: 'Customer groups', href: index() }],
};
