import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import ClientDashboardLayout from '@/layouts/client-dashboard-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    BookOpen,
    Bot,
    Facebook,
    Inbox,
    MessageCircle,
    Rocket,
    ThumbsUp,
    UserCheck,
    XCircle,
    Zap,
} from 'lucide-react';

interface Plan {
    name: string;
    monthly_price: string;
}

interface Subscription {
    status: string;
    billing_cycle: string;
    ends_at: string | null;
    plan: Plan;
}

interface Usage {
    message_reply_limit: number;
    message_reply_used: number;
    comment_reply_limit: number;
    comment_reply_used: number;
    ai_reply_limit: number;
    ai_reply_used: number;
    connected_page_limit: number;
    reset_at: string;
}

interface Stats {
    messages_today: number;
    comments_today: number;
    ai_replies_today: number;
    failed_replies_today: number;
    human_handovers_today: number;
    connected_pages: number;
}

interface AlertItem {
    type: 'error' | 'warning' | 'info';
    message: string;
}

interface ActivityLog {
    id: number;
    type: string;
    amount: number;
    created_at: string;
}

interface DashboardProps {
    subscription: Subscription;
    usage: Usage;
    stats: Stats;
    alerts: AlertItem[];
    recentActivity: ActivityLog[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

function usagePct(used: number, limit: number) {
    if (limit === 0) return 0;
    return Math.min(100, Math.round((used / limit) * 100));
}

function UsageBar({ label, used, limit }: { label: string; used: number; limit: number }) {
    const pct = usagePct(used, limit);
    const danger = pct >= 100;
    const warn = !danger && pct >= 80;
    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12.5, fontWeight: 600 }}>
                <span style={{ color: '#8487a8' }}>{label}</span>
                <span style={{ color: danger ? '#ef4444' : '#4b4d6b' }}>
                    {used.toLocaleString()} <span style={{ color: '#8487a8', fontWeight: 400 }}>/ {limit.toLocaleString()}</span>
                </span>
            </div>
            <div style={{ height: 6, borderRadius: 99, background: '#f3f4fb', overflow: 'hidden' }}>
                <div
                    className="bf-progress-fill"
                    style={{
                        width: `${pct}%`,
                        height: '100%',
                        borderRadius: 99,
                        background: danger
                            ? '#ef4444'
                            : warn
                              ? '#f59e0b'
                              : 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 55%, #a855f7 100%)',
                        boxShadow: danger ? 'none' : warn ? 'none' : '0 2px 8px rgba(124,92,246,.28)',
                        transition: 'width 0.6s cubic-bezier(.2,.8,.2,1)',
                    }}
                />
            </div>
        </div>
    );
}

interface StatCardBefitProps {
    label: string;
    value: number | string;
    sub?: string;
    icon: React.ReactNode;
    tone: 'brand' | 'cyan' | 'violet' | 'green' | 'amber' | 'red' | 'pink';
    delta?: number;
}

const toneStyles: Record<StatCardBefitProps['tone'], { bg: string; shadow: string }> = {
    brand: { bg: 'linear-gradient(135deg, #6366f1, #8b5cf6)', shadow: '0 8px 24px rgba(99,102,241,.28)' },
    cyan: { bg: 'linear-gradient(135deg, #06b6d4, #22d3ee)', shadow: '0 8px 24px rgba(6,182,212,.22)' },
    violet: { bg: 'linear-gradient(135deg, #7c3aed, #8b5cf6)', shadow: '0 8px 24px rgba(124,58,237,.22)' },
    green: { bg: 'linear-gradient(135deg, #059669, #10b981)', shadow: '0 8px 24px rgba(5,150,105,.22)' },
    amber: { bg: 'linear-gradient(135deg, #d97706, #f59e0b)', shadow: '0 8px 24px rgba(217,119,6,.22)' },
    red: { bg: 'linear-gradient(135deg, #dc2626, #ef4444)', shadow: '0 8px 24px rgba(220,38,38,.22)' },
    pink: { bg: 'linear-gradient(135deg, #db2777, #ec4899)', shadow: '0 8px 24px rgba(219,39,119,.22)' },
};

function StatCardBefit({ label, value, sub, icon, tone, delta }: StatCardBefitProps) {
    const { bg, shadow } = toneStyles[tone];
    return (
        <div
            style={{
                borderRadius: 16,
                background: bg,
                boxShadow: shadow,
                padding: '18px 20px',
                color: '#fff',
                position: 'relative',
                overflow: 'hidden',
            }}
        >
            <div
                style={{
                    position: 'absolute',
                    right: -18,
                    top: -18,
                    width: 80,
                    height: 80,
                    borderRadius: '50%',
                    background: 'rgba(255,255,255,.14)',
                }}
            />
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', position: 'relative' }}>
                <div style={{ background: 'rgba(255,255,255,.2)', borderRadius: 10, padding: 8, display: 'grid', placeItems: 'center' }}>
                    {icon}
                </div>
                {delta != null && (
                    <span
                        style={{
                            fontSize: 11,
                            fontWeight: 700,
                            padding: '3px 8px',
                            borderRadius: 99,
                            background: 'rgba(255,255,255,.22)',
                        }}
                    >
                        {delta >= 0 ? '+' : ''}
                        {delta}%
                    </span>
                )}
            </div>
            <div
                style={{
                    fontFamily: "'Sora', system-ui, sans-serif",
                    fontWeight: 700,
                    fontSize: 28,
                    lineHeight: 1,
                    marginTop: 14,
                    position: 'relative',
                }}
            >
                {typeof value === 'number' ? value.toLocaleString() : value}
            </div>
            <div style={{ fontSize: 13, fontWeight: 600, opacity: 0.9, marginTop: 5, position: 'relative' }}>{label}</div>
            {sub && <div style={{ fontSize: 11.5, opacity: 0.72, marginTop: 2 }}>{sub}</div>}
        </div>
    );
}

function alertVariant(type: AlertItem['type']): 'default' | 'destructive' {
    return type === 'error' ? 'destructive' : 'default';
}

const quickActions = [
    { label: 'Facebook Pages', href: '/facebook/pages', icon: Facebook },
    { label: 'Inbox', href: '/inbox', icon: Inbox },
    { label: 'Automation Rules', href: '/automation-rules', icon: Zap },
    { label: 'Knowledge Base', href: '/knowledge-base', icon: BookOpen },
    { label: 'Analytics', href: '/analytics', icon: BarChart3 },
    { label: 'User Guide', href: '/guide', icon: UserCheck },
];

export default function ClientDashboard({ subscription, usage, stats, alerts, recentActivity }: DashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const firstName = auth.user.name.split(' ')[0];

    return (
        <ClientDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div style={{ display: 'flex', flexDirection: 'column', gap: 24, padding: '24px 24px 32px' }}>

                {/* Alerts */}
                {alerts.length > 0 && (
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                        {alerts.map((alert, i) => (
                            <Alert key={i} variant={alertVariant(alert.type)}>
                                <AlertDescription>{alert.message}</AlertDescription>
                            </Alert>
                        ))}
                    </div>
                )}

                {/* Welcome Banner */}
                <div
                    className="bf-fade-up"
                    style={{
                        borderRadius: 20,
                        background: 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 55%, #a855f7 100%)',
                        boxShadow: '0 12px 40px rgba(124,92,246,.32)',
                        padding: '28px 32px',
                        color: '#fff',
                        position: 'relative',
                        overflow: 'hidden',
                    }}
                >
                    <div
                        style={{
                            position: 'absolute',
                            right: -50,
                            top: -60,
                            width: 260,
                            height: 260,
                            borderRadius: '50%',
                            background: 'radial-gradient(circle, rgba(255,255,255,.22), transparent 70%)',
                        }}
                    />
                    <div
                        style={{
                            position: 'absolute',
                            right: 140,
                            bottom: -80,
                            width: 200,
                            height: 200,
                            borderRadius: '50%',
                            background: 'radial-gradient(circle, rgba(34,211,238,.28), transparent 70%)',
                        }}
                    />
                    <div style={{ position: 'relative', display: 'flex', flexWrap: 'wrap', gap: 20, alignItems: 'center', justifyContent: 'space-between' }}>
                        <div style={{ maxWidth: 520 }}>
                            <div
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    gap: 6,
                                    background: 'rgba(255,255,255,.18)',
                                    padding: '4px 12px',
                                    borderRadius: 99,
                                    fontSize: 12,
                                    fontWeight: 700,
                                    marginBottom: 12,
                                }}
                            >
                                <span
                                    style={{
                                        width: 7,
                                        height: 7,
                                        borderRadius: '50%',
                                        background: '#10b981',
                                        display: 'inline-block',
                                    }}
                                />
                                Automation active
                            </div>
                            <h1
                                style={{
                                    fontFamily: "'Sora', system-ui, sans-serif",
                                    fontSize: 28,
                                    fontWeight: 700,
                                    lineHeight: 1.15,
                                    marginBottom: 10,
                                }}
                            >
                                Good to see you, {firstName}!
                            </h1>
                            <p style={{ fontSize: 14.5, opacity: 0.92, lineHeight: 1.55, marginBottom: 20 }}>
                                Your AI assistant has replied to {stats.messages_today + stats.comments_today} conversations today.
                            </p>
                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 10 }}>
                                <Link
                                    href="/facebook/pages"
                                    style={{
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                        gap: 7,
                                        background: '#fff',
                                        color: '#8b5cf6',
                                        fontWeight: 700,
                                        fontSize: 13.5,
                                        padding: '9px 18px',
                                        borderRadius: 10,
                                        textDecoration: 'none',
                                    }}
                                >
                                    <Facebook size={15} />
                                    Manage Pages
                                </Link>
                                <Link
                                    href="/billing"
                                    style={{
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                        gap: 7,
                                        background: 'rgba(255,255,255,.18)',
                                        color: '#fff',
                                        fontWeight: 700,
                                        fontSize: 13.5,
                                        padding: '9px 18px',
                                        borderRadius: 10,
                                        border: '1px solid rgba(255,255,255,.3)',
                                        textDecoration: 'none',
                                    }}
                                >
                                    <Rocket size={14} />
                                    Upgrade Plan
                                </Link>
                            </div>
                        </div>

                        {/* Usage mini-ring */}
                        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 6, opacity: 0.92 }}>
                            <div
                                style={{
                                    fontFamily: "'Sora', system-ui, sans-serif",
                                    fontWeight: 700,
                                    fontSize: 38,
                                    lineHeight: 1,
                                }}
                            >
                                {subscription.plan.name}
                            </div>
                            <div style={{ fontSize: 12, fontWeight: 700, letterSpacing: '0.06em', textTransform: 'uppercase', opacity: 0.75 }}>
                                {subscription.status === 'active' ? 'Active plan' : subscription.status}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Stat Cards */}
                <div
                    className="bf-stagger"
                    style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))', gap: 16 }}
                >
                    <StatCardBefit
                        label="Connected Pages"
                        value={stats.connected_pages}
                        sub={`of ${usage.connected_page_limit} allowed`}
                        icon={<Facebook size={18} color="#fff" />}
                        tone="brand"
                    />
                    <StatCardBefit
                        label="Messages Today"
                        value={stats.messages_today}
                        icon={<MessageCircle size={18} color="#fff" />}
                        tone="violet"
                    />
                    <StatCardBefit
                        label="Comments Today"
                        value={stats.comments_today}
                        icon={<ThumbsUp size={18} color="#fff" />}
                        tone="cyan"
                    />
                    <StatCardBefit
                        label="AI Replies Today"
                        value={stats.ai_replies_today}
                        icon={<Bot size={18} color="#fff" />}
                        tone="green"
                    />
                    <StatCardBefit
                        label="Failed Replies"
                        value={stats.failed_replies_today}
                        icon={<XCircle size={18} color="#fff" />}
                        tone="red"
                    />
                    <StatCardBefit
                        label="Human Handovers"
                        value={stats.human_handovers_today}
                        icon={<UserCheck size={18} color="#fff" />}
                        tone="amber"
                    />
                </div>

                {/* Plan + Activity */}
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }}>
                    {/* Plan & Usage */}
                    <div
                        style={{
                            background: '#fff',
                            borderRadius: 18,
                            boxShadow: '0 2px 16px rgba(99,102,241,.08)',
                            border: '1px solid #e9eaf3',
                            padding: 24,
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 20,
                        }}
                    >
                        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                            <div>
                                <div style={{ fontFamily: "'Sora', system-ui, sans-serif", fontWeight: 700, fontSize: 15, color: '#1a1b2e' }}>
                                    Current Plan
                                </div>
                                <div style={{ fontSize: 12.5, color: '#8487a8', marginTop: 2 }}>
                                    {subscription.billing_cycle} billing
                                    {subscription.ends_at && <> · expires {new Date(subscription.ends_at).toLocaleDateString()}</>}
                                </div>
                            </div>
                            <span
                                style={{
                                    fontSize: 11.5,
                                    fontWeight: 700,
                                    padding: '4px 12px',
                                    borderRadius: 99,
                                    background:
                                        subscription.status === 'active'
                                            ? 'linear-gradient(135deg, rgba(16,185,129,.15), rgba(5,150,105,.15))'
                                            : '#fef2f2',
                                    color: subscription.status === 'active' ? '#059669' : '#dc2626',
                                }}
                            >
                                {subscription.status}
                            </span>
                        </div>

                        <div style={{ display: 'flex', alignItems: 'baseline', gap: 8 }}>
                            <span style={{ fontFamily: "'Sora', system-ui, sans-serif", fontWeight: 700, fontSize: 26, color: '#1a1b2e' }}>
                                {subscription.plan.name}
                            </span>
                            <span style={{ color: '#8487a8', fontSize: 13 }}>${subscription.plan.monthly_price}/mo</span>
                        </div>

                        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                            <UsageBar label="Message Replies" used={usage.message_reply_used} limit={usage.message_reply_limit} />
                            <UsageBar label="Comment Replies" used={usage.comment_reply_used} limit={usage.comment_reply_limit} />
                            <UsageBar label="AI Replies" used={usage.ai_reply_used} limit={usage.ai_reply_limit} />
                        </div>

                        <div style={{ fontSize: 11.5, color: '#8487a8' }}>
                            Resets {new Date(usage.reset_at).toLocaleDateString()}
                        </div>

                        <Button asChild variant="outline" size="sm" style={{ borderRadius: 10, fontWeight: 600 }}>
                            <Link href="/billing">Manage Billing</Link>
                        </Button>
                    </div>

                    {/* Recent Activity */}
                    <div
                        style={{
                            background: '#fff',
                            borderRadius: 18,
                            boxShadow: '0 2px 16px rgba(99,102,241,.08)',
                            border: '1px solid #e9eaf3',
                            overflow: 'hidden',
                            display: 'flex',
                            flexDirection: 'column',
                        }}
                    >
                        <div
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 8,
                                padding: '18px 22px 14px',
                                borderBottom: '1px solid #e9eaf3',
                            }}
                        >
                            <span style={{ fontFamily: "'Sora', system-ui, sans-serif", fontWeight: 700, fontSize: 15, color: '#1a1b2e' }}>
                                Recent Activity
                            </span>
                            <span className="bf-live-dot" style={{ marginLeft: 4 }} />
                            <span style={{ fontSize: 11.5, color: '#8487a8', fontWeight: 600, marginLeft: 'auto' }}>Live</span>
                        </div>

                        {recentActivity.length === 0 ? (
                            <div
                                style={{
                                    flex: 1,
                                    display: 'flex',
                                    flexDirection: 'column',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    padding: 40,
                                    gap: 10,
                                    textAlign: 'center',
                                }}
                            >
                                <MessageCircle size={36} style={{ color: '#e9eaf3' }} />
                                <div style={{ fontWeight: 600, color: '#4b4d6b', fontSize: 14 }}>No activity yet</div>
                                <div style={{ fontSize: 12.5, color: '#8487a8', lineHeight: 1.5 }}>
                                    Connect a page and send a message to get started.
                                </div>
                            </div>
                        ) : (
                            <ul style={{ flex: 1, overflowY: 'auto' }}>
                                {recentActivity.map((log) => (
                                    <li
                                        key={log.id}
                                        style={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'space-between',
                                            padding: '10px 22px',
                                            borderBottom: '1px solid #f3f4fb',
                                            fontSize: 13.5,
                                        }}
                                    >
                                        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                                            <span
                                                style={{
                                                    width: 8,
                                                    height: 8,
                                                    borderRadius: '50%',
                                                    background: 'linear-gradient(135deg, #6366f1, #8b5cf6)',
                                                    flexShrink: 0,
                                                }}
                                            />
                                            <span style={{ fontWeight: 600, color: '#1a1b2e' }}>
                                                {log.type.replaceAll('_', ' ')}
                                            </span>
                                        </div>
                                        <span style={{ fontSize: 12, color: '#8487a8' }}>
                                            {new Date(log.created_at).toLocaleString()}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>

                {/* Quick Actions */}
                <div
                    style={{
                        background: '#fff',
                        borderRadius: 18,
                        boxShadow: '0 2px 16px rgba(99,102,241,.08)',
                        border: '1px solid #e9eaf3',
                        padding: 24,
                    }}
                >
                    <div style={{ fontFamily: "'Sora', system-ui, sans-serif", fontWeight: 700, fontSize: 15, color: '#1a1b2e', marginBottom: 16 }}>
                        Quick Actions
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(130px, 1fr))', gap: 12 }}>
                        {quickActions.map(({ label, href, icon: Icon }) => (
                            <Link
                                key={href}
                                href={href}
                                style={{
                                    display: 'flex',
                                    flexDirection: 'column',
                                    alignItems: 'center',
                                    gap: 10,
                                    padding: '18px 12px',
                                    borderRadius: 14,
                                    background: 'linear-gradient(135deg, rgba(99,102,241,.07), rgba(139,92,246,.07))',
                                    border: '1px solid rgba(99,102,241,.12)',
                                    color: '#6366f1',
                                    textDecoration: 'none',
                                    fontWeight: 600,
                                    fontSize: 12.5,
                                    textAlign: 'center',
                                    transition: 'background .15s, box-shadow .15s',
                                }}
                                onMouseEnter={(e) => {
                                    (e.currentTarget as HTMLElement).style.background =
                                        'linear-gradient(135deg, #6366f1, #8b5cf6)';
                                    (e.currentTarget as HTMLElement).style.color = '#fff';
                                    (e.currentTarget as HTMLElement).style.boxShadow = '0 6px 18px rgba(124,92,246,.28)';
                                }}
                                onMouseLeave={(e) => {
                                    (e.currentTarget as HTMLElement).style.background =
                                        'linear-gradient(135deg, rgba(99,102,241,.07), rgba(139,92,246,.07))';
                                    (e.currentTarget as HTMLElement).style.color = '#6366f1';
                                    (e.currentTarget as HTMLElement).style.boxShadow = 'none';
                                }}
                            >
                                <Icon size={22} />
                                <span style={{ lineHeight: 1.3 }}>{label}</span>
                            </Link>
                        ))}
                    </div>
                </div>
            </div>
        </ClientDashboardLayout>
    );
}
