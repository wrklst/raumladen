import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/hooks/use-translation';

type Props = {
    name: string;
    email: string;
    role: string;
    /** The signed invitation address the form posts back to. */
    action: string;
};

/**
 * An invited backend user sets their password; that accepts the invitation.
 */
export default function AcceptInvitation({ name, email, role, action }: Props) {
    const { t } = useTrans();

    return (
        <>
            <Head title={t('Accept invitation')} />

            <p className="text-center text-sm">
                {t(
                    ':name, you are invited as :role. You will sign in with :email.',
                    {
                        name,
                        role,
                        email,
                    },
                )}
            </p>

            <Form action={action} method="post">
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">{t('Password')}</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                autoFocus
                                required
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                {t('Confirm password')}
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                required
                            />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {t('Set password and sign in')}
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

AcceptInvitation.layout = {
    title: 'Accept invitation',
    description: 'Choose a password for the backend',
};
