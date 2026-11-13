import { Form, Head } from '@inertiajs/react';
import SettingsController from '@/actions/App/Http/Controllers/Admin/SettingsController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/hooks/use-translation';
import { edit } from '@/routes/admin/settings';

type Field = {
    name: string;
    type:
        | 'text'
        | 'email'
        | 'number'
        | 'money'
        | 'bool'
        | 'secret'
        | 'select'
        | 'lines';
    label: string;
    help: string | null;
    options: Record<string, string> | null;
    required: boolean;
};

type Group = {
    key: string;
    label: string;
    description: string;
    fields: Field[];
    values: Record<string, string | number | boolean | null>;
};

function SettingField({
    field,
    value,
    error,
}: {
    field: Field;
    value: string | number | boolean | null;
    error?: string;
}) {
    const { t } = useTrans();
    const describedBy = field.help ? `${field.name}-help` : undefined;

    if (field.type === 'bool') {
        return (
            <div className="md:col-span-2">
                {/* An unchecked box sends nothing, the hidden field sends the "off". */}
                <input type="hidden" name={field.name} value="0" />
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        name={field.name}
                        value="1"
                        defaultChecked={value === true}
                        className="size-4"
                    />
                    {field.label}
                </label>
                {field.help && (
                    <p className="mt-1 text-xs break-all text-muted-foreground">
                        {field.help}
                    </p>
                )}
                <InputError message={error} />
            </div>
        );
    }

    return (
        <div className="grid gap-1">
            <Label htmlFor={field.name}>
                {field.label}
                {field.type === 'money' && ' (EUR)'}
            </Label>
            {field.type === 'lines' ? (
                <textarea
                    id={field.name}
                    name={field.name}
                    rows={3}
                    defaultValue={String(value ?? '')}
                    aria-describedby={describedBy}
                    className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
            ) : field.type === 'select' ? (
                <select
                    id={field.name}
                    name={field.name}
                    defaultValue={String(value ?? '')}
                    className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                >
                    {Object.entries(field.options ?? {}).map(([key, label]) => (
                        <option key={key} value={key}>
                            {label}
                        </option>
                    ))}
                </select>
            ) : (
                <Input
                    id={field.name}
                    name={field.name}
                    type={
                        field.type === 'secret'
                            ? 'password'
                            : field.type === 'money'
                              ? 'number'
                              : field.type
                    }
                    step={field.type === 'money' ? '0.01' : undefined}
                    min={
                        field.type === 'money' || field.type === 'number'
                            ? 0
                            : undefined
                    }
                    // A secret is never sent to the browser: the field only says whether one is saved.
                    defaultValue={
                        field.type === 'secret' ? '' : String(value ?? '')
                    }
                    placeholder={
                        field.type === 'secret'
                            ? value
                                ? t('Saved')
                                : t('Not set')
                            : undefined
                    }
                    autoComplete={field.type === 'secret' ? 'off' : undefined}
                    required={field.required}
                    aria-describedby={describedBy}
                />
            )}
            {field.help && (
                <p id={describedBy} className="text-xs text-muted-foreground">
                    {field.help}
                </p>
            )}
            <InputError message={error} />
        </div>
    );
}

/**
 * Shop settings, one form per group, and what still blocks going live.
 */
export default function Settings({
    groups,
    problems,
    errors,
}: {
    groups: Group[];
    problems: string[];
    errors: Record<string, string>;
}) {
    const { t } = useTrans();

    return (
        <>
            <Head title={t('Shop settings')} />

            <div className="flex flex-col gap-6 p-4">
                <nav
                    aria-label={t('Sections')}
                    className="flex flex-wrap gap-x-4 gap-y-1 text-sm"
                >
                    {groups.map((group) => (
                        <a
                            key={group.key}
                            href={`#${group.key}`}
                            className="underline underline-offset-4"
                        >
                            {group.label}
                        </a>
                    ))}
                </nav>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Before going live')}</CardTitle>
                        <CardDescription>
                            {problems.length === 0
                                ? t('Nothing is missing.')
                                : t('These points are still open:')}
                        </CardDescription>
                    </CardHeader>
                    {problems.length > 0 && (
                        <CardContent>
                            <ul className="list-disc space-y-1 pl-5 text-sm">
                                {problems.map((problem) => (
                                    <li key={problem}>{problem}</li>
                                ))}
                            </ul>
                        </CardContent>
                    )}
                </Card>

                {groups.map((group) => (
                    <Card
                        key={group.key}
                        id={group.key}
                        className="scroll-mt-20"
                    >
                        <CardHeader>
                            <CardTitle>{group.label}</CardTitle>
                            <CardDescription>
                                {group.description}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <Form
                                {...SettingsController.update.form({
                                    group: group.key,
                                })}
                                options={{ preserveScroll: true }}
                                className="space-y-4"
                            >
                                {({ processing, errors: formErrors }) => (
                                    <>
                                        <div className="grid gap-4 md:grid-cols-2">
                                            {group.fields.map((field) => (
                                                <SettingField
                                                    key={field.name}
                                                    field={field}
                                                    value={
                                                        group.values[field.name]
                                                    }
                                                    error={
                                                        formErrors[field.name]
                                                    }
                                                />
                                            ))}
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {t('Save')}
                                        </Button>
                                    </>
                                )}
                            </Form>

                            {group.key === 'mail' && (
                                <Form
                                    {...SettingsController.testMail.form()}
                                    options={{ preserveScroll: true }}
                                    className="space-y-2 border-t pt-4"
                                >
                                    <Button type="submit" variant="outline">
                                        {t('Send a test message to me')}
                                    </Button>
                                    <InputError message={errors.mail_test} />
                                </Form>
                            )}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </>
    );
}

Settings.layout = {
    breadcrumbs: [{ title: 'Shop settings', href: edit() }],
};
