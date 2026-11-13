import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    ChartNoAxesColumn,
    FileText,
    LayoutGrid,
    Package,
    Receipt,
    Settings,
    Store,
    Table2,
    Ticket,
    UserCog,
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
import { dashboard, statistics } from '@/routes/admin';
import { index as coupons } from '@/routes/admin/coupons';
import { index as customerGroups } from '@/routes/admin/customer-groups';
import { index as customers } from '@/routes/admin/customers';
import { index as datev } from '@/routes/admin/datev';
import { index as invoices } from '@/routes/admin/invoices';
import { index as orders } from '@/routes/admin/orders';
import { index as pages } from '@/routes/admin/pages';
import { index as products } from '@/routes/admin/products';
import { edit as shopSettings } from '@/routes/admin/settings';
import { index as staff } from '@/routes/admin/staff';
import { home } from '@/routes/shop';
import type { NavItem } from '@/types';

// The backend by topic. Each entry belongs to an area; a user sees the
// entries of their role's areas (the server enforces the same in
// routes/admin.php), and a topic without visible entries is left out.
const navGroups: {
    label?: string;
    items: (NavItem & { area?: string })[];
}[] = [
    { items: [{ title: 'Dashboard', href: dashboard(), icon: LayoutGrid }] },
    {
        label: 'Sales',
        items: [
            { title: 'Orders', href: orders(), icon: Package, area: 'orders' },
            {
                title: 'Coupons',
                href: coupons(),
                icon: Ticket,
                area: 'coupons',
            },
            {
                title: 'Statistics',
                href: statistics(),
                icon: ChartNoAxesColumn,
                area: 'statistics',
            },
        ],
    },
    {
        label: 'Catalogue',
        items: [
            {
                title: 'Products',
                href: products(),
                icon: BookOpen,
                area: 'catalog',
            },
            { title: 'Pages', href: pages(), icon: FileText, area: 'content' },
        ],
    },
    {
        label: 'Customers',
        items: [
            {
                title: 'Customers',
                href: customers(),
                icon: Users,
                area: 'customers',
            },
            {
                title: 'Customer groups',
                href: customerGroups(),
                icon: UsersRound,
                area: 'customers',
            },
        ],
    },
    {
        label: 'Accounting',
        items: [
            {
                title: 'Invoices',
                href: invoices(),
                icon: Receipt,
                area: 'invoices',
            },
            {
                title: 'DATEV export',
                href: datev(),
                icon: Table2,
                area: 'datev',
            },
        ],
    },
    {
        label: 'Administration',
        items: [
            {
                title: 'Shop settings',
                href: shopSettings(),
                icon: Settings,
                area: 'settings',
            },
            {
                title: 'Backend users',
                href: staff(),
                icon: UserCog,
                area: 'settings',
            },
        ],
    },
];

export function AppSidebar() {
    const { locale, auth } = usePage<{
        locale: string;
        auth: { user: { areas: string[] } };
    }>().props;
    const visibleGroups = navGroups
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) => !item.area || auth.user.areas.includes(item.area),
            ),
        }))
        .filter((group) => group.items.length > 0);

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
                {visibleGroups.map((group) => (
                    <NavMain
                        key={group.label ?? 'top'}
                        label={group.label}
                        items={group.items}
                    />
                ))}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
