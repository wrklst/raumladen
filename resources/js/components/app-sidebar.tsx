import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    FileText,
    LayoutGrid,
    Package,
    Receipt,
    Store,
    Table2,
    Ticket,
    Users,
    UsersRound,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes/admin';
import { index as coupons } from '@/routes/admin/coupons';
import { index as customerGroups } from '@/routes/admin/customer-groups';
import { index as customers } from '@/routes/admin/customers';
import { index as datev } from '@/routes/admin/datev';
import { index as invoices } from '@/routes/admin/invoices';
import { index as orders } from '@/routes/admin/orders';
import { index as pages } from '@/routes/admin/pages';
import { index as products } from '@/routes/admin/products';
import { home } from '@/routes/shop';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'Orders', href: orders(), icon: Package },
    { title: 'Products', href: products(), icon: BookOpen },
    { title: 'Customers', href: customers(), icon: Users },
    { title: 'Customer groups', href: customerGroups(), icon: UsersRound },
    { title: 'Coupons', href: coupons(), icon: Ticket },
    { title: 'Invoices', href: invoices(), icon: Receipt },
    { title: 'DATEV export', href: datev(), icon: Table2 },
    { title: 'Pages', href: pages(), icon: FileText },
];

export function AppSidebar() {
    const { locale } = usePage<{ locale: string }>().props;

    const footerNavItems: NavItem[] = [
        {
            title: 'View shop',
            href: home({ locale }),
            icon: Store,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
