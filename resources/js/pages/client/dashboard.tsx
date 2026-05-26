import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ClientDashboardLayout from '@/layouts/client-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { BarChart3, BookOpen, Bot, Facebook, Inbox, MessageCircle, ThumbsUp, UserCheck, XCircle, Zap } from 'lucide-react';

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

function UsageBar({ label, used, limit, color }: { label: string; used: number; limit: number; color: string }) {
    const pct = usagePct(used, limit);
    const barColor = pct >= 100 ? 'bg-red-500' : pct >= 80 ? 'bg-amber-500' : color;
    return (
        <div className="space-y-1.5">
            <div className="flex justify-between text-xs">
                <span className="text-muted-foreground font-medium">{label}</span>
                <span className="font-semibold">
                    {used.toLocaleString()}{' '}
                    <span className="text-muted-foreground font-normal">/ {limit.toLocaleString()}</span>
                </span>
            </div>
            <div className="h-2 overflow-hidden rounded-full bg-muted">
                <div className={`h-full rounded-full transition-all duration-500 ${barColor}`} style={{ width: `${pct}%` }} />
            </div>
        </div>
    );
}

interface StatCardProps {
    title: string;
    value: number | string;
    sub?: string;
    icon: React.ReactNode;
    gradient: string;
}

function StatCard({ title, value, sub, icon, gradient }: StatCardProps) {
    return (
        <Card className="overflow-hidden border-0 shadow-md">
            <CardContent className="p-0">
                <div className={`p-4 ${gradient}`}>
                    <div className="flex items-center justify-between">
                        <div className="rounded-xl bg-white/20 p-2.5 backdrop-blur-sm">{icon}</div>
                        <p className="text-3xl font-bold text-white">{typeof value === 'number' ? value.toLocaleString() : value}</p>
                    </div>
                    <div className="mt-3">
                        <p className="text-sm font-semibold text-white/90">{title}</p>
                        {sub && <p className="text-xs text-white/70 mt-0.5">{sub}</p>}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function alertVariant(type: AlertItem['type']): 'default' | 'destructive' {
    return type === 'error' ? 'destructive' : 'default';
}

const quickActions = [
    { label: 'Facebook Pages', href: '/facebook/pages', icon: Facebook, color: 'bg-blue-500 hover:bg-blue-600' },
    { label: 'Inbox', href: '/inbox', icon: Inbox, color: 'bg-indigo-500 hover:bg-indigo-600' },
    { label: 'Automation Rules', href: '/automation-rules', icon: Zap, color: 'bg-amber-500 hover:bg-amber-600' },
    { label: 'Knowledge Base', href: '/knowledge-base', icon: BookOpen, color: 'bg-emerald-500 hover:bg-emerald-600' },
    { label: 'Analytics', href: '/analytics', icon: BarChart3, color: 'bg-purple-500 hover:bg-purple-600' },
    { label: 'User Guide', href: '/guide', icon: UserCheck, color: 'bg-rose-500 hover:bg-rose-600' },
];

export default function ClientDashboard({ subscription, usage, stats, alerts, recentActivity }: DashboardProps) {
    return (
        <ClientDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">

                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Welcome back! 👋</h1>
                        <p className="text-muted-foreground text-sm mt-0.5">Here's your Facebook automation overview for today.</p>
                    </div>
                    <Button asChild size="sm" className="bg-blue-600 hover:bg-blue-700">
                        <Link href="/usage-limits">View Usage Limits</Link>
                    </Button>
                </div>

                {/* Alerts */}
                {alerts.length > 0 && (
                    <div className="space-y-2">
                        {alerts.map((alert, i) => (
                            <Alert key={i} variant={alertVariant(alert.type)}>
                                <AlertDescription>{alert.message}</AlertDescription>
                            </Alert>
                        ))}
                    </div>
                )}

                {/* Stat Cards */}
                <div className="grid gap-4 grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    <StatCard
                        title="Connected Pages"
                        value={stats.connected_pages}
                        sub={`of ${usage.connected_page_limit} allowed`}
                        icon={<Facebook className="h-5 w-5 text-white" />}
                        gradient="bg-gradient-to-br from-blue-500 to-blue-700"
                    />
                    <StatCard
                        title="Messages Today"
                        value={stats.messages_today}
                        icon={<MessageCircle className="h-5 w-5 text-white" />}
                        gradient="bg-gradient-to-br from-violet-500 to-violet-700"
                    />
                    <StatCard
                        title="Comments Today"
                        value={stats.comments_today}
                        icon={<ThumbsUp className="h-5 w-5 text-white" />}
                        gradient="bg-gradient-to-br from-emerald-500 to-emerald-700"
                    />
                    <StatCard
                        title="AI Replies Today"
                        value={stats.ai_replies_today}
                        icon={<Bot className="h-5 w-5 text-white" />}
                        gradient="bg-gradient-to-br from-amber-500 to-orange-600"
                    />
                    <StatCard
                        title="Failed Replies"
                        value={stats.failed_replies_today}
                        icon={<XCircle className="h-5 w-5 text-white" />}
                        gradient="bg-gradient-to-br from-red-500 to-rose-700"
                    />
                    <StatCard
                        title="Human Handovers"
                        value={stats.human_handovers_today}
                        icon={<UserCheck className="h-5 w-5 text-white" />}
                        gradient="bg-gradient-to-br from-pink-500 to-pink-700"
                    />
                </div>

                {/* Plan + Activity */}
                <div className="grid gap-4 md:grid-cols-2">
                    <Card className="shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3">
                            <CardTitle className="text-base font-semibold">Current Plan</CardTitle>
                            <Badge
                                className={
                                    subscription.status === 'active'
                                        ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-100'
                                        : 'bg-red-100 text-red-700 hover:bg-red-100'
                                }
                            >
                                {subscription.status}
                            </Badge>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-baseline gap-2">
                                <span className="text-2xl font-bold">{subscription.plan.name}</span>
                                <span className="text-muted-foreground text-sm">${subscription.plan.monthly_price}/mo</span>
                            </div>
                            <p className="text-xs text-muted-foreground capitalize">
                                {subscription.billing_cycle} billing
                                {subscription.ends_at && (
                                    <> · expires {new Date(subscription.ends_at).toLocaleDateString()}</>
                                )}
                                {' '}· resets {new Date(usage.reset_at).toLocaleDateString()}
                            </p>
                            <div className="space-y-3 pt-1">
                                <UsageBar label="Message Replies" used={usage.message_reply_used} limit={usage.message_reply_limit} color="bg-violet-500" />
                                <UsageBar label="Comment Replies" used={usage.comment_reply_used} limit={usage.comment_reply_limit} color="bg-emerald-500" />
                                <UsageBar label="AI Replies" used={usage.ai_reply_used} limit={usage.ai_reply_limit} color="bg-amber-500" />
                            </div>
                            <Button asChild variant="outline" size="sm" className="mt-1 w-full">
                                <Link href="/billing">Manage Billing</Link>
                            </Button>
                        </CardContent>
                    </Card>

                    <Card className="shadow-sm">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base font-semibold">Recent Activity</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            {recentActivity.length === 0 ? (
                                <div className="flex flex-col items-center justify-center py-10 text-center">
                                    <MessageCircle className="h-10 w-10 text-muted-foreground/30 mb-3" />
                                    <p className="text-sm text-muted-foreground">No activity yet.</p>
                                    <p className="text-xs text-muted-foreground mt-1">Connect a page and send a message to get started.</p>
                                </div>
                            ) : (
                                <ul className="divide-y">
                                    {recentActivity.map((log) => (
                                        <li key={log.id} className="flex items-center justify-between px-6 py-2.5 text-sm">
                                            <div className="flex items-center gap-2">
                                                <span className="h-2 w-2 rounded-full bg-emerald-500 flex-shrink-0" />
                                                <span className="capitalize">{log.type.replaceAll('_', ' ')}</span>
                                            </div>
                                            <span className="text-muted-foreground text-xs">
                                                {new Date(log.created_at).toLocaleString()}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Quick Actions */}
                <Card className="shadow-sm">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base font-semibold">Quick Actions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-6">
                            {quickActions.map(({ label, href, icon: Icon, color }) => (
                                <Link
                                    key={href}
                                    href={href}
                                    className={`flex flex-col items-center gap-2 rounded-xl p-4 text-white transition-all hover:scale-105 hover:shadow-md ${color}`}
                                >
                                    <Icon className="h-6 w-6" />
                                    <span className="text-xs font-medium text-center leading-tight">{label}</span>
                                </Link>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </ClientDashboardLayout>
    );
}
