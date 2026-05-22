import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ClientDashboardLayout from '@/layouts/client-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

interface Plan {
    name: string;
    description?: string;
    monthly_price: string;
    yearly_price: string;
}

interface Subscription {
    status: string;
    billing_cycle: string;
    starts_at: string;
    ends_at?: string | null;
    next_billing_at?: string | null;
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
    team_member_limit: number;
    knowledge_base_limit: number;
    reset_at: string;
}

interface UsageLog {
    id: number;
    type: string;
    amount: number;
    created_at: string;
}

interface UsageLimitsProps {
    subscription: Subscription;
    usage: Usage;
    history: UsageLog[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Usage & Limits',
        href: '/usage-limits',
    },
];

function usagePercent(used: number, limit: number): number {
    if (limit === 0) {
        return 100;
    }

    return Math.min(100, Math.round((used / limit) * 100));
}

function stateFor(percent: number): { label: string; className: string } {
    if (percent >= 100) {
        return { label: 'Blocked', className: 'bg-red-600' };
    }

    if (percent >= 80) {
        return { label: 'Warning', className: 'bg-amber-500' };
    }

    return { label: 'Available', className: 'bg-emerald-600' };
}

function UsageMeter({ label, used, limit }: { label: string; used: number; limit: number }) {
    const percent = usagePercent(used, limit);
    const state = stateFor(percent);

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0">
                <CardTitle className="text-sm font-medium">{label}</CardTitle>
                <Badge variant={percent >= 100 ? 'destructive' : percent >= 80 ? 'secondary' : 'outline'}>{state.label}</Badge>
            </CardHeader>
            <CardContent className="space-y-3">
                <div className="flex justify-between text-sm">
                    <span className="text-muted-foreground">
                        {used.toLocaleString()} / {limit.toLocaleString()}
                    </span>
                    <span className="font-medium">{percent}%</span>
                </div>
                <div className="h-2 overflow-hidden rounded-full bg-muted">
                    <div className={`h-full rounded-full ${state.className}`} style={{ width: `${percent}%` }} />
                </div>
            </CardContent>
        </Card>
    );
}

export default function UsageLimits({ subscription, usage, history }: UsageLimitsProps) {
    return (
        <ClientDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Usage & Limits" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-normal">Usage & Limits</h1>
                        <p className="text-muted-foreground text-sm">Current plan limits and automation usage.</p>
                    </div>
                    <Button variant="outline">Upgrade</Button>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0">
                        <div>
                            <CardTitle>{subscription.plan.name}</CardTitle>
                            <p className="text-muted-foreground text-sm">{subscription.plan.description}</p>
                        </div>
                        <Badge variant="secondary">{subscription.status}</Badge>
                    </CardHeader>
                    <CardContent className="grid gap-3 text-sm md:grid-cols-4">
                        <div>
                            <div className="text-muted-foreground">Billing</div>
                            <div className="font-medium">{subscription.billing_cycle}</div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">Monthly</div>
                            <div className="font-medium">${subscription.plan.monthly_price}</div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">Connected pages</div>
                            <div className="font-medium">{usage.connected_page_limit}</div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">Reset</div>
                            <div className="font-medium">{new Date(usage.reset_at).toLocaleDateString()}</div>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-3">
                    <UsageMeter label="Message replies" used={usage.message_reply_used} limit={usage.message_reply_limit} />
                    <UsageMeter label="Comment replies" used={usage.comment_reply_used} limit={usage.comment_reply_limit} />
                    <UsageMeter label="AI replies" used={usage.ai_reply_used} limit={usage.ai_reply_limit} />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Usage History</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left">
                                        <th className="py-2 font-medium">Type</th>
                                        <th className="py-2 font-medium">Amount</th>
                                        <th className="py-2 font-medium">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {history.length === 0 ? (
                                        <tr>
                                            <td className="text-muted-foreground py-6" colSpan={3}>
                                                No usage logged yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        history.map((log) => (
                                            <tr key={log.id} className="border-b last:border-0">
                                                <td className="py-2">{log.type.replaceAll('_', ' ')}</td>
                                                <td className="py-2">{log.amount}</td>
                                                <td className="py-2">{new Date(log.created_at).toLocaleString()}</td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </ClientDashboardLayout>
    );
}
