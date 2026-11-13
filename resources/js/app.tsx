import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import ShopLayout from '@/layouts/shop-layout';
import { startSiteBeacon } from '@/lib/site-beacon';
import { pageTitle } from '@/lib/site';

void createInertiaApp({
    title: pageTitle,
    layout: (name) => {
        switch (true) {
            case name.startsWith('shop/') || name.startsWith('account/'):
                return ShopLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

// Anonymous, cookieless visitor statistics (off in the backend and under Do Not Track).
// Browser only: this file is also the entry of the server-side renderer.
if (typeof window !== 'undefined') {
    startSiteBeacon();
}
