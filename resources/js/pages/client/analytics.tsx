import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ClientDashboardLayout from '@/layouts/client-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Deferred } from '@inertiajs/react';
import { useState } from 'react';

interface DailyRow {
    date: string;
    messages_received: number;
    comments_received: number;
    replies_sent: number;
    ai_replies: number;
    human_handovers: number;
}

interface Totals {
    messages_received: number;
    comments_received: number;
    message_replies_sent: number;
    comment_replies_sent: number;
    ai_replies_sent: number;
    manual_replies_sent: number;
    human_handovers: number;
    failed_replies: number;
}

interface Stats {
    totals: Totals;
    daily: DailyRow[];
}

interface Props {
    from: string;
    to: string;
    stats: Stats | null;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Analytics', href: '/analytics' }];

function StatCard({ title, value, sub }: { title: string; value: number; sub?: string }) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">{title}</CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-3xl font-bold">{value.toLocaleString()}</p>
                {sub && <p className="mt-1 text-xs text-muted-foreground">{sub}</p>}
            </CardContent>
        </Card>
    );
}

function BarChart({ data, valueKey, label }: { data: DailyRow[]; valueKey: keyof DailyRow; label: string }) {
    if (!data.length) {
        return <p className="text-sm text-muted-foreground">No data for this period.</p>;
    }
    const max = Math.max(...data.map((r) => r[valueKey] as number), 1);

    return (
        <div className="space-y-1">
            <p className="text-sm font-medium text-muted-foreground">{label}</p>
            <div className="flex items-end gap-1" style={{ height: '120px' }}>
                {data.map((row) => {
                    const pct = Math.round(((row[valueKey] as number) / max) * 100);
                    return (
                        <div key={row.date} className="group relative flex flex-1 flex-col items-center justify-end">
                            <div className="absolute -top-6 left-1/2 hidden -translate-x-1/2 rounded bg-foreground px-1 py-0.5 text-xs text-background group-hover:block">
                                {row[valueKey]}
                            </div>
                            <div
                                className="w-full rounded-t bg-primary transition-all"
                                style={{ height: `${Math.max(pct, 2)}%` }}
                            />
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

function StatsContent({ stats, from, to }: { stats: Stats; from: string; to: string }) {
    const { totals, daily } = stats;

    return (
        <div className="space-y-6">
            <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                <StatCard title="Messages Received" value={totals.messages_received} />
                <StatCard title="Comments Received" value={totals.comments_received} />
                <StatCard
                    title="Replies Sent"
                    value={totals.message_replies_sent + totals.comment_replies_sent}
                    sub={`${totals.ai_replies_sent} AI · ${totals.manual_replies_sent} manual`}
                />
                <StatCard title="Human Handovers" value={totals.human_handovers} />
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">Daily Messages</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <BarChart data={daily} valueKey="messages_received" label="Messages received per day" />
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">Daily Replies</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <BarChart data={daily} valueKey="replies_sent" label="Replies sent per day" />
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">AI Replies</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <BarChart data={daily} valueKey="ai_replies" label="AI replies per day" />
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">Human Handovers</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <BarChart data={daily} valueKey="human_handovers" label="Handovers per day" />
                    </CardContent>
                </Card>
            </div>

            <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                <StatCard title="AI Replies" value={totals.ai_replies_sent} />
                <StatCard title="Manual Replies" value={totals.manual_replies_sent} />
                <StatCard title="Comment Replies" value={totals.comment_replies_sent} />
                <StatCard title="Failed Replies" value={totals.failed_replies} />
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
                {Array.from({ length: 4 }).map((_, i) => (
                    <Card key={i}>
                        <CardHeader>
                            <div className="h-4 w-24 animate-pulse rounded bg-muted" />
                        </CardHeader>
                        <CardContent>
                            <div className="h-32 animate-pulse rounded bg-muted" />
                        </CardContent>
                    </Card>
                ))}
            </div>
        </div>
    );
}

export default function Analytics({ from, to, stats }: Props) {
    const [dateFrom, setDateFrom] = useState(from);
    const [dateTo, setDateTo] = useState(to);

    function applyRange() {
        router.get('/analytics', { from: dateFrom, to: dateTo }, { preserveState: true });
    }

    return (
        <ClientDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Analytics" />
            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-end gap-3">
                    <div className="flex flex-col gap-1">
                        <label className="text-xs text-muted-foreground">From</label>
                        <input
                            type="date"
                            value={dateFrom}
                            onChange={(e) => setDateFrom(e.target.value)}
                            className="rounded border px-2 py-1 text-sm"
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-xs text-muted-foreground">To</label>
                        <input
                            type="date"
                            value={dateTo}
                            onChange={(e) => setDateTo(e.target.value)}
                            className="rounded border px-2 py-1 text-sm"
                        />
                    </div>
                    <button
                        onClick={applyRange}
                        className="rounded bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                    >
                        Apply
                    </button>
                </div>

                <Deferred data="stats" fallback={<StatsSkeleton />}>
                    {stats && <StatsContent stats={stats} from={from} to={to} />}
                </Deferred>
            </div>
        </ClientDashboardLayout>
    );
}
