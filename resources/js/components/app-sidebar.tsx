import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Bell,
    BookOpen,
    BookTemplate,
    Brain,
    CreditCard,
    Facebook,
    HelpCircle,
    Inbox,
    LayoutGrid,
    MessageSquare,
    Package,
    Rocket,
    Users,
    Zap,
} from 'lucide-react';
import AppLogo from './app-logo';

const navItems = [
    { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid },
    { title: 'Usage', url: '/usage-limits', icon: BarChart3 },
    { title: 'Facebook Pages', url: '/facebook/pages', icon: Facebook },
    { title: 'Inbox', url: '/inbox', icon: Inbox },
    { title: 'Comments', url: '/comments', icon: MessageSquare },
    { title: 'Automation Rules', url: '/automation-rules', icon: Zap },
    { title: 'Reply Templates', url: '/reply-templates', icon: BookTemplate },
    { title: 'Knowledge Base', url: '/knowledge-base', icon: BookOpen },
    { title: 'Product Sources', url: '/product-sources', icon: Package },
    { title: 'AI Settings', url: '/ai-settings', icon: Brain },
    { title: 'Team', url: '/team', icon: Users },
    { title: 'Analytics', url: '/analytics', icon: BarChart3 },
    { title: 'Billing', url: '/billing', icon: CreditCard },
    { title: 'User Guide', url: '/guide', icon: HelpCircle },
];

function UpgradeCTA() {
    const { state } = useSidebar();
    if (state === 'collapsed') return null;
    return (
        <div
            style={{
                borderRadius: 14,
                background: 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 55%, #a855f7 100%)',
                padding: 16,
                color: '#fff',
                position: 'relative',
                overflow: 'hidden',
                marginTop: 4,
            }}
        >
            <div
                style={{
                    position: 'absolute',
                    right: -20,
                    top: -20,
                    width: 90,
                    height: 90,
                    borderRadius: '50%',
                    background: 'rgba(255,255,255,.16)',
                }}
            />
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontWeight: 700, fontSize: 13.5, position: 'relative' }}>
                <Rocket size={15} />
                Free trial
            </div>
            <div style={{ fontSize: 12, opacity: 0.9, margin: '6px 0 12px', position: 'relative', lineHeight: 1.5 }}>
                Upgrade for unlimited AI replies
            </div>
            <Link
                href="/billing"
                style={{
                    display: 'block',
                    textAlign: 'center',
                    background: '#fff',
                    color: '#8b5cf6',
                    fontWeight: 700,
                    fontSize: 13,
                    padding: '8px',
                    borderRadius: 10,
                    position: 'relative',
                }}
            >
                Upgrade plan
            </Link>
        </div>
    );
}

export function AppSidebar() {
    const { unreadNotifications } = usePage<SharedData>().props;
    const page = usePage();

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
                <div className="px-3 pt-4 pb-1">
                    <p
                        style={{
                            fontSize: 10.5,
                            fontWeight: 700,
                            letterSpacing: '0.1em',
                            textTransform: 'uppercase',
                            color: '#8487a8',
                            paddingLeft: 4,
                            marginBottom: 4,
                        }}
                    >
                        Platform
                    </p>
                    <div className="flex flex-col gap-0.5">
                        {navItems.map((item) => {
                            const isActive = page.url === item.url || page.url.startsWith(item.url + '/');
                            const title =
                                item.title === 'Notifications' && unreadNotifications > 0
                                    ? `Notifications (${unreadNotifications})`
                                    : item.title;
                            return (
                                <Link
                                    key={item.url}
                                    href={item.url}
                                    prefetch
                                    style={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 12,
                                        padding: '9px 12px',
                                        borderRadius: 12,
                                        fontWeight: 600,
                                        fontSize: 13.5,
                                        color: isActive ? '#fff' : '#4b4d6b',
                                        background: isActive
                                            ? 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 55%, #a855f7 100%)'
                                            : 'transparent',
                                        boxShadow: isActive ? '0 6px 18px rgba(124,92,246,.28)' : 'none',
                                        transition: 'background .16s, color .16s, box-shadow .16s',
                                        textDecoration: 'none',
                                        position: 'relative',
                                    }}
                                    onMouseEnter={(e) => {
                                        if (!isActive) {
                                            (e.currentTarget as HTMLElement).style.background = '#f3f4fb';
                                            (e.currentTarget as HTMLElement).style.color = '#1a1b2e';
                                        }
                                    }}
                                    onMouseLeave={(e) => {
                                        if (!isActive) {
                                            (e.currentTarget as HTMLElement).style.background = 'transparent';
                                            (e.currentTarget as HTMLElement).style.color = '#4b4d6b';
                                        }
                                    }}
                                >
                                    <item.icon
                                        size={18}
                                        style={{ flexShrink: 0, color: isActive ? '#fff' : '#8487a8' }}
                                    />
                                    <span className="truncate group-data-[collapsible=icon]/sidebar-wrapper:hidden">
                                        {title}
                                    </span>
                                    {item.title === 'Notifications' && unreadNotifications > 0 && (
                                        <span
                                            style={{
                                                marginLeft: 'auto',
                                                fontSize: 11,
                                                fontWeight: 700,
                                                padding: '2px 8px',
                                                borderRadius: 99,
                                                background: isActive ? 'rgba(255,255,255,.25)' : 'linear-gradient(135deg, #6366f1, #8b5cf6)',
                                                color: '#fff',
                                            }}
                                            className="group-data-[collapsible=icon]/sidebar-wrapper:hidden"
                                        >
                                            {unreadNotifications}
                                        </span>
                                    )}
                                </Link>
                            );
                        })}
                    </div>
                </div>
            </SidebarContent>

            <SidebarFooter>
                <div className="px-3 pb-2 group-data-[collapsible=icon]/sidebar-wrapper:hidden">
                    <UpgradeCTA />
                </div>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
