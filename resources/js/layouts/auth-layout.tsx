import LanguageSwitcher from '@/components/language-switcher';
import { useTrans } from '@/hooks/use-translation';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';

export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    const { t } = useTrans();

    return (
        <AuthLayoutTemplate
            title={title && t(title)}
            description={description && t(description)}
        >
            {children}
            <LanguageSwitcher className="justify-center" />
        </AuthLayoutTemplate>
    );
}
