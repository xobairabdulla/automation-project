import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Bell,
    BookOpen,
    BookTemplate,
    Brain,
    CreditCard,
    DollarSign,
    Facebook,
    FileText,
    Inbox,
    LayoutGrid,
    MessageSquare,
    Settings,
    Shield,
    Users,
    Zap,
} from 'lucide-react';
import AppLogo from './app-logo';

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    const { auth, unreadNotifications } = usePage<SharedData>().props;
    const roleSlugs = auth.user?.roles?.map((role) => role.slug) ?? [];
    const isSuperAdmin = roleSlugs.includes('super-admin');

    const mainNavItems: NavItem[] = [
        { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid },
        { title: 'Usage', url: '/usage-limits', icon: BarChart3 },
        { title: 'Facebook Pages', url: '/facebook/pages', icon: Facebook },
        { title: 'Inbox', url: '/inbox', icon: Inbox },
        { title: 'Comments', url: '/comments', icon: MessageSquare },
        { title: 'Automation Rules', url: '/automation-rules', icon: Zap },
        { title: 'Reply Templates', url: '/reply-templates', icon: BookTemplate },
        { title: 'Knowledge Base', url: '/knowledge-base', icon: BookOpen },
        { title: 'AI Settings', url: '/ai-settings', icon: Brain },
        { title: 'Team', url: '/team', icon: Users },
        { title: 'Analytics', url: '/analytics', icon: BarChart3 },
        {
            title: unreadNotifications > 0 ? `Notifications (${unreadNotifications})` : 'Notifications',
            url: '/notifications',
            icon: Bell,
        },
        { title: 'Billing', url: '/billing', icon: CreditCard },
        ...(isSuperAdmin
            ? [
                  { title: 'Admin Dashboard', url: '/admin/dashboard', icon: Shield },
                  { title: 'Admin: Users', url: '/admin/users', icon: Users },
                  { title: 'Admin: Plans', url: '/admin/plans', icon: CreditCard },
                  { title: 'Admin: Payments', url: '/admin/payments', icon: DollarSign },
                  { title: 'Admin: AI Logs', url: '/admin/ai-logs', icon: Brain },
                  { title: 'Admin: Webhooks', url: '/admin/webhook-logs', icon: Zap },
                  { title: 'Admin: Audit', url: '/admin/audit-logs', icon: FileText },
                  { title: 'Admin: Emails', url: '/admin/email-logs', icon: MessageSquare },
                  { title: 'Admin: Analytics', url: '/admin/analytics', icon: BarChart3 },
                  { title: 'Admin: Settings', url: '/admin/system-settings', icon: Settings },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
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
