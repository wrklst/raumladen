import { Form } from '@inertiajs/react';
import LoginController from '@/actions/App/Http/Controllers/Account/LoginController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/hooks/use-translation';

/**
 * Passwordless login: request a code by email, then enter it.
 */
export default function Login({ email }: { email: string | null }) {
    const { t } = useTrans();

    return (
        <div className="mx-auto max-w-sm space-y-6">
            <header className="space-y-1">
                <h1 className="text-2xl font-semibold">{t('Sign in')}</h1>
                <p className="text-sm text-muted-foreground">
                    {email
                        ? t('We sent a 6-digit code to :email.', { email })
                        : t('Enter your email address and we will send you a one-time login code. No password needed.')}
                </p>
            </header>

            {email ? (
                <>
                    <Form {...LoginController.verify.form()} className="space-y-4">
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-1">
                                    <Label htmlFor="code">{t('Login code')}</Label>
                                    <Input
                                        id="code"
                                        name="code"
                                        inputMode="numeric"
                                        pattern="[0-9]*"
                                        maxLength={6}
                                        autoComplete="one-time-code"
                                        autoFocus
                                        required
                                        className="text-center text-2xl tracking-[0.5em]"
                                    />
                                    <InputError message={errors.code} />
                                </div>
                                <Button type="submit" className="w-full" disabled={processing}>
                                    {t('Continue')}
                                </Button>
                            </>
                        )}
                    </Form>

                    <Form {...LoginController.sendCode.form()} className="text-center text-sm">
                        {({ processing }) => (
                            <>
                                <input type="hidden" name="email" value={email} />
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="text-muted-foreground underline underline-offset-4 hover:text-foreground"
                                >
                                    {t('Send a new code')}
                                </button>
                            </>
                        )}
                    </Form>
                </>
            ) : (
                <Form {...LoginController.sendCode.form()} className="space-y-4">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-1">
                                <Label htmlFor="email">{t('Email address')}</Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    autoComplete="email"
                                    autoFocus
                                    required
                                />
                                <InputError message={errors.email} />
                            </div>
                            <Button type="submit" className="w-full" disabled={processing}>
                                {t('Send login code')}
                            </Button>
                        </>
                    )}
                </Form>
            )}
        </div>
    );
}
