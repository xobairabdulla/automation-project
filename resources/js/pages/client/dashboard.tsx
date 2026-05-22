import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ClientDashboardLayout from '@/layouts/client-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

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

function MiniUsageBar({ label, used, limit }: { label: string; used: number; limit: number }) {
    const pct = usagePct(used, limit);
    const color = pct >= 100 ? 'bg-red-600' : pct >= 80 ? 'bg-amber-500' : 'bg-emerald-600';
    return (
        <div className="space-y-1">
            <div className="flex justify-between text-xs">
                <span className="text-muted-foreground">{label}</span>
                <span className="font-medium">{used.toLocaleString()} / {limit.toLocaleString()}</span>
            </div>
            <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                <div className={`h-full rounded-full ${color}`} style={{ width: `${pct}%` }} />
            </div>
        </div>
    );
}

function StatCard({ title, value, sub }: { title: string; value: number | string; sub?: string }) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">{title}</CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-2xl font-bold">{typeof value === 'number' ? value.toLocaleString() : value}</p>
                {sub && <p className="text-muted-foreground mt-1 text-xs">{sub}</p>}
            </CardContent>
        </Card>
    );
}

function alertVariant(type: AlertItem['type']): 'default' | 'destructive' {
    return type === 'error' ? 'destructive' : 'default';
}

export default function ClientDashboard({ subscription, usage, stats, alerts, recentActivity }: DashboardProps) {
    return (
        <ClientDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-4 p-4">

                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-normal">Dashboard</h1>
                        <p className="text-muted-foreground text-sm">Your Facebook automation overview.</p>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild variant="outline" size="sm">
                            <Link href="/usage-limits">View Limits</Link>
                        </Button>
                    </div>
                </div>

                {alerts.length > 0 && (
                    <div className="space-y-2">
                        {alerts.map((alert, i) => (
                            <Alert key={i} variant={alertVariant(alert.type)}>
                                <AlertDescription>{alert.message}</AlertDescription>
                            </Alert>
                        ))}
                    </div>
                )}

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <StatCard title="Connected Pages" value={stats.connected_pages} sub={`of ${usage.connected_page_limit} allowed`} />
                    <StatCard title="Messages Today" value={stats.messages_today} />
                    <StatCard title="Comments Today" value={stats.comments_today} />
                    <StatCard title="AI Replies Today" value={stats.ai_replies_today} />
                    <StatCard title="Failed Replies Today" value={stats.failed_replies_today} />
                    <StatCard title="Human Handovers Today" value={stats.human_handovers_today} />
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0">
                            <CardTitle>Current Plan</CardTitle>
                            <Badge variant="secondary">{subscription.status}</Badge>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="flex items-baseline gap-2">
                                <span className="text-xl font-bold">{subscription.plan.name}</span>
                                <span className="text-muted-foreground text-sm">${subscription.plan.monthly_price}/mo</span>
                            </div>
                            <div className="text-sm text-muted-foreground">
                                {subscription.billing_cycle} billing
                                {subscription.ends_at && (
                                    <> · expires {new Date(subscription.ends_at).toLocaleDateString()}</>
                                )}
                            </div>
                            <div className="space-y-2 pt-1">
                                <MiniUsageBar label="Message Replies" used={usage.message_reply_used} limit={usage.message_reply_limit} />
                                <MiniUsageBar label="Comment Replies" used={usage.comment_reply_used} limit={usage.comment_reply_limit} />
                                <MiniUsageBar label="AI Replies" used={usage.ai_reply_used} limit={usage.ai_reply_limit} />
                            </div>
                            <div className="flex gap-2 pt-1">
                                <Button asChild variant="outline" size="sm">
                                    <Link href="/usage-limits">Usage Details</Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Recent Activity</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            {recentActivity.length === 0 ? (
                                <p className="px-6 py-4 text-sm text-muted-foreground">No activity yet.</p>
                            ) : (
                                <ul className="divide-y">
                                    {recentActivity.map((log) => (
                                        <li key={log.id} className="flex items-center justify-between px-6 py-2 text-sm">
                                            <span>{log.type.replaceAll('_', ' ')}</span>
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

                <Card>
                    <CardHeader>
                        <CardTitle>Quick Actions</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href="/usage-limits">Usage & Limits</Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href="/facebook/pages">Facebook Pages</Link>
                        </Button>
                        <Button variant="outline" disabled>Automation Rules</Button>
                        <Button variant="outline" disabled>AI Knowledge Base</Button>
                        <Button variant="outline" disabled>Inbox</Button>
                    </CardContent>
                </Card>
            </div>
        </ClientDashboardLayout>
    );
}
