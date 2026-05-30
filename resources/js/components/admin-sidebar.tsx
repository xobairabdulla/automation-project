import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { BarChart3, Brain, CreditCard, DollarSign, FileText, LayoutGrid, MessageSquare, Package, Settings, Users, Zap } from 'lucide-react';
import AppLogo from './app-logo';

const adminNavItems: NavItem[] = [
    { title: 'Dashboard', url: '/admin/dashboard', icon: LayoutGrid },
    { title: 'Users', url: '/admin/users', icon: Users },
    { title: 'Packages', url: '/admin/packages', icon: Package },
    { title: 'Plans', url: '/admin/plans', icon: CreditCard },
    { title: 'Payments', url: '/admin/payments', icon: DollarSign },
    { title: 'AI Logs', url: '/admin/ai-logs', icon: Brain },
    { title: 'Webhooks', url: '/admin/webhook-logs', icon: Zap },
    { title: 'Audit Logs', url: '/admin/audit-logs', icon: FileText },
    { title: 'Email Logs', url: '/admin/email-logs', icon: MessageSquare },
    { title: 'Analytics', url: '/admin/analytics', icon: BarChart3 },
    { title: 'System Settings', url: '/admin/system-settings', icon: Settings },
];

export function AdminSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/admin/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={adminNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={[]} className="mt-auto" />
                <NavUser logoutRoute="admin.logout" />
            </SidebarFooter>
        </Sidebar>
    );
}
