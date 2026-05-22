import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminDashboardLayout from '@/layouts/admin-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Deferred } from '@inertiajs/react';

interface SignupRow {
    date: string;
    count: number;
}

interface RevenueRow {
    date: string;
    total: number;
}

interface Stats {
    total_users: number;
    active_subscriptions: number;
    total_revenue: number;
    mrr: number;
    total_messages: number;
    total_comments: number;
    total_replies: number;
    daily_signups: SignupRow[];
    daily_revenue: RevenueRow[];
}

interface Props {
    stats: Stats | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Analytics', href: '/admin/analytics' },
];

function StatCard({ title, value, format }: { title: string; value: number; format?: 'currency' | 'number' }) {
    const display =
        format === 'currency'
            ? `$${value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
            : value.toLocaleString();
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">{title}</CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-3xl font-bold">{display}</p>
            </CardContent>
        </Card>
    );
}

function SimpleBarChart({ data, label }: { data: { date: string; value: number }[]; label: string }) {
    if (!data.length) {
        return <p className="text-sm text-muted-foreground">No data yet.</p>;
    }
    const max = Math.max(...data.map((r) => r.value), 1);
    return (
        <div className="space-y-1">
            <p className="text-xs text-muted-foreground">{label}</p>
            <div className="flex items-end gap-0.5" style={{ height: '100px' }}>
                {data.map((row) => {
                    const pct = Math.round((row.value / max) * 100);
                    return (
                        <div key={row.date} className="group relative flex flex-1 flex-col items-center justify-end">
                            <div className="absolute -top-6 left-1/2 hidden -translate-x-1/2 rounded bg-foreground px-1 py-0.5 text-xs text-background group-hover:block">
                                {row.value}
                            </div>
                            <div className="w-full rounded-t bg-primary" style={{ height: `${Math.max(pct, 2)}%` }} />
                        </div>
                    );
                })}
            </div>
            <div className="flex justify-between text-xs text-muted-foreground">
                <span>{data[0]?.date}</span>
                <span>{data[data.length - 1]?.date}</span>
            </div>
        </div>
    );
}

function StatsContent({ stats }: { stats: Stats }) {
    const signupData = stats.daily_signups.map((r) => ({ date: r.date, value: r.count }));
    const revenueData = stats.daily_revenue.map((r) => ({ date: r.date, value: Math.round(r.total) }));

    return (
        <div className="space-y-6">
            <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                <StatCard title="Total Users" value={stats.total_users} />
                <StatCard title="Active Subscriptions" value={stats.active_subscriptions} />
                <StatCard title="Total Revenue" value={stats.total_revenue} format="currency" />
                <StatCard title="MRR (This Month)" value={stats.mrr} format="currency" />
            </div>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                <StatCard title="Total Messages Processed" value={stats.total_messages} />
                <StatCard title="Total Comments Processed" value={stats.total_comments} />
                <StatCard title="Total Replies Sent" value={stats.total_replies} />
            </div>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">New Signups (30 days)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <SimpleBarChart data={signupData} label="Signups per day" />
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">Revenue (30 days)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <SimpleBarChart data={revenueData} label="Revenue per day ($)" />
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

function StatsSkeleton() {
    return (
        <div className="space-y-6">
            <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                {Array.from({ length: 4 }).map((_, i) => (
                    <Card key={i}>
                        <CardHeader className="pb-2">
                            <div className="h-4 w-24 animate-pulse rounded bg-muted" />
                        </CardHeader>
                        <CardContent>
                            <div className="h-8 w-16 animate-pulse rounded bg-muted" />
                        </CardContent>
                    </Card>
                ))}
            </div>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                {Array.from({ length: 2 }).map((_, i) => (
                    <Card key={i}>
                        <CardHeader>
                            <div className="h-4 w-32 animate-pulse rounded bg-muted" />
                        </CardHeader>
                        <CardContent>
                            <div className="h-28 animate-pulse rounded bg-muted" />
                        </CardContent>
                    </Card>
                ))}
            </div>
        </div>
    );
}

export default function AdminAnalytics({ stats }: Props) {
    return (
        <AdminDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Analytics" />
            <div className="space-y-6 p-4">
                <h1 className="text-xl font-semibold">Platform Analytics</h1>
                <Deferred data="stats" fallback={<StatsSkeleton />}>
                    {stats && <StatsContent stats={stats} />}
                </Deferred>
            </div>
        </AdminDashboardLayout>
    );
}
