import { Form, Head, router } from '@inertiajs/react';
import StaffController from '@/actions/App/Http/Controllers/Admin/StaffController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/hooks/use-translation';
import { index } from '@/routes/admin/staff';

type Role = { value: string; label: string; description: string };

type Member = {
    id: number;
    name: string;
    email: string;
    role: string;
    pending: boolean;
    is_self: boolean;
};

const selectClass =
    'h-9 rounded-md border border-input bg-background px-3 text-sm';

/**
 * Backend users: invite by email, give a role, change it, remove access.
 */
export default function Staff({
    staff,
    roles,
    errors,
}: {
    staff: Member[];
    roles: Role[];
    errors: Record<string, string>;
}) {
    const { t } = useTrans();

    return (
        <>
            <Head title={t('Backend users')} />

            <div className="flex flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>{t('Invite someone')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...StaffController.store.form()}
                            resetOnSuccess
                            className="grid gap-4 md:grid-cols-[1fr_1fr_auto_auto] md:items-end"
                        >
                            {({ processing, errors: formErrors }) => (
                                <>
                                    <div className="grid gap-1">
                                        <Label htmlFor="name">
                                            {t('Name')}
                                        </Label>
                                        <Input id="name" name="name" required />
                                        <InputError message={formErrors.name} />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="email">
                                            {t('Email address')}
                                        </Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            required
                                        />
                                        <InputError
                                            message={formErrors.email}
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="role">
                                            {t('Role')}
                                        </Label>
                                        <select
                                            id="role"
                                            name="role"
                                            defaultValue="manager"
                                            className={selectClass}
                                        >
                                            {roles.map((role) => (
                                                <option
                                                    key={role.value}
                                                    value={role.value}
                                                >
                                                    {role.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={formErrors.role} />
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        {t('Send invitation')}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Backend users')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <InputError message={errors.staff} />
                        <InputError message={errors.role} />

                        <table className="w-full text-sm">
                            <thead className="text-left text-muted-foreground">
                                <tr>
                                    <th
                                        scope="col"
                                        className="py-2 font-medium"
                                    >
                                        {t('Name')}
                                    </th>
                                    <th
                                        scope="col"
                                        className="py-2 font-medium"
                                    >
                                        {t('Email address')}
                                    </th>
                                    <th
                                        scope="col"
                                        className="py-2 font-medium"
                                    >
                                        {t('Role')}
                                    </th>
                                    <th scope="col" className="py-2">
                                        <span className="sr-only">
                                            {t('Actions')}
                                        </span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {staff.map((member) => (
                                    <tr key={member.id} className="border-t">
                                        <td className="py-2">
                                            {member.name}
                                            {member.pending && (
                                                <Badge
                                                    variant="outline"
                                                    className="ml-2"
                                                >
                                                    {t('Invitation pending')}
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="py-2">{member.email}</td>
                                        <td className="py-2">
                                            <select
                                                aria-label={t('Role of :name', {
                                                    name: member.name,
                                                })}
                                                value={member.role}
                                                onChange={(event) =>
                                                    router.patch(
                                                        StaffController.update.url(
                                                            { user: member.id },
                                                        ),
                                                        {
                                                            role: event.target
                                                                .value,
                                                        },
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                                className={selectClass}
                                            >
                                                {roles.map((role) => (
                                                    <option
                                                        key={role.value}
                                                        value={role.value}
                                                    >
                                                        {role.label}
                                                    </option>
                                                ))}
                                            </select>
                                        </td>
                                        <td className="py-2">
                                            <div className="flex justify-end gap-2">
                                                {member.pending && (
                                                    <Form
                                                        {...StaffController.invite.form(
                                                            { user: member.id },
                                                        )}
                                                        options={{
                                                            preserveScroll: true,
                                                        }}
                                                    >
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            variant="outline"
                                                        >
                                                            {t('Invite again')}
                                                        </Button>
                                                    </Form>
                                                )}
                                                {!member.is_self && (
                                                    <Form
                                                        {...StaffController.destroy.form(
                                                            { user: member.id },
                                                        )}
                                                        options={{
                                                            preserveScroll: true,
                                                        }}
                                                        onBefore={() =>
                                                            confirm(
                                                                t(
                                                                    'Remove backend access for :name?',
                                                                    {
                                                                        name: member.name,
                                                                    },
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <Button
                                                            type="submit"
                                                            size="sm"
                                                            variant="destructive"
                                                        >
                                                            {t('Remove')}
                                                        </Button>
                                                    </Form>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Roles')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-x-6 gap-y-2 text-sm md:grid-cols-[max-content_1fr]">
                            {roles.map((role) => (
                                <div key={role.value} className="contents">
                                    <dt className="font-medium">
                                        {role.label}
                                    </dt>
                                    <dd className="text-muted-foreground">
                                        {role.description}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Staff.layout = {
    breadcrumbs: [{ title: 'Backend users', href: index() }],
};
