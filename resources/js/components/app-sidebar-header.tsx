import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Bell, Search } from 'lucide-react';

export function AppSidebarHeader({ breadcrumbs = [] }: { breadcrumbs?: BreadcrumbItem[] }) {
    const { unreadNotifications, auth } = usePage<SharedData>().props;
    const initials = auth.user.name
        .split(' ')
        .map((w) => w[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();

    return (
        <header
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: 12,
                height: 64,
                padding: '0 20px',
                background: 'rgba(255,255,255,0.82)',
                backdropFilter: 'blur(18px)',
                borderBottom: '1px solid #e9eaf3',
                flexShrink: 0,
                transition: 'height .2s ease',
                position: 'sticky',
                top: 0,
                zIndex: 10,
            }}
            className="group-has-data-[collapsible=icon]/sidebar-wrapper:h-12"
        >
            <SidebarTrigger style={{ color: '#8487a8', flexShrink: 0 }} />

            <div style={{ display: 'flex', alignItems: 'center', gap: 6, minWidth: 0, flex: '0 0 auto' }}>
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <div style={{ flex: 1 }} />

            {/* Search */}
            <div className="bf-searchbar">
                <Search size={15} style={{ color: '#8487a8', flexShrink: 0 }} />
                <input placeholder="Search…" />
            </div>

            {/* Notification bell */}
            <Link
                href="/notifications"
                style={{
                    position: 'relative',
                    width: 38,
                    height: 38,
                    borderRadius: 10,
                    background: '#f3f4fb',
                    display: 'grid',
                    placeItems: 'center',
                    flexShrink: 0,
                    color: '#4b4d6b',
                    transition: 'background .14s',
                }}
                onMouseEnter={(e) => ((e.currentTarget as HTMLElement).style.background = '#e9eaf3')}
                onMouseLeave={(e) => ((e.currentTarget as HTMLElement).style.background = '#f3f4fb')}
            >
                <Bell size={18} />
                {unreadNotifications > 0 && (
                    <span
                        style={{
                            position: 'absolute',
                            top: 6,
                            right: 6,
                            width: 8,
                            height: 8,
                            borderRadius: '50%',
                            background: 'linear-gradient(135deg, #6366f1, #8b5cf6)',
                            border: '1.5px solid #fff',
                        }}
                    />
                )}
            </Link>

            {/* Avatar */}
            <Link
                href="/settings/profile"
                style={{
                    width: 36,
                    height: 36,
                    borderRadius: 10,
                    background: 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 55%, #a855f7 100%)',
                    boxShadow: '0 4px 12px rgba(124,92,246,.28)',
                    display: 'grid',
                    placeItems: 'center',
                    flexShrink: 0,
                    color: '#fff',
                    fontFamily: "'Sora', system-ui, sans-serif",
                    fontWeight: 700,
                    fontSize: 13,
                    textDecoration: 'none',
                }}
            >
                {initials}
            </Link>
        </header>
    );
}
