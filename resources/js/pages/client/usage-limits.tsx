import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ClientDashboardLayout from '@/layouts/client-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Brain, ImageIcon, MessageSquare, Zap } from 'lucide-react';

interface Usage {
    message_reply_limit: number;
    message_reply_used: number;
    comment_reply_limit: number;
    comment_reply_used: number;
    ai_reply_limit: number;
    ai_reply_used: number;
    image_send_limit: number;
    image_send_used: number;
    connected_page_limit: number;
    team_member_limit: number;
    knowledge_base_limit: number;
}

interface UsageLog {
    id: number;
    type: string;
    amount: number;
    created_at: string;
}

interface Props {
    usage: Usage;
    history: UsageLog[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Usage & Limits', href: '/usage-limits' }];

function pct(used: number, limit: number): number {
    if (limit === 0) return 0;
    return Math.min(100, Math.round((used / limit) * 100));
}

function barColor(p: number): string {
    if (p >= 100) return 'bg-red-500';
    if (p >= 80) return 'bg-amber-400';
    return 'bg-green-500';
}

function UsageMeter({ label, used, limit, icon: Icon }: { label: string; used: number; limit: number; icon: React.ElementType }) {
    const p = pct(used, limit);
    const remaining = Math.max(0, limit - used);
    const isEmpty = limit > 0 && remaining === 0;
    const isLow = limit > 0 && p >= 80 && !isEmpty;

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="flex items-center gap-1.5 text-sm font-medium">
                    <Icon size={14} className="text-muted-foreground" />
                    {label}
                </CardTitle>
                {isEmpty && <Badge variant="destructive">Depleted</Badge>}
                {isLow && <Badge variant="secondary">Low</Badge>}
            </CardHeader>
            <CardContent className="space-y-2">
                {limit === 0 ? (
                    <p className="text-xs text-muted-foreground">No credits. Buy a package to get started.</p>
                ) : (
                    <>
                        <div className="flex justify-between text-xs text-muted-foreground">
                            <span>{used.toLocaleString()} used</span>
                            <span className="font-medium">{remaining.toLocaleString()} left / {limit.toLocaleString()}</span>
                        </div>
                        <div className="h-2 overflow-hidden rounded-full bg-muted">
                            <div className={`h-full rounded-full ${barColor(p)}`} style={{ width: `${p}%` }} />
                        </div>
                    </>
                )}
            </CardContent>
        </Card>
    );
}

export default function UsageLimits({ usage, history }: Props) {
    return (
        <ClientDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Usage & Limits" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-normal">Usage & Limits</h1>
                        <p className="text-muted-foreground text-sm">Your credit balance and automation usage.</p>
                    </div>
                    <Button variant="outline" onClick={() => router.visit('/billing')}>Buy Credits</Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <UsageMeter label="Message Replies" used={usage.message_reply_used} limit={usage.message_reply_limit} icon={MessageSquare} />
                    <UsageMeter label="Comment Replies" used={usage.comment_reply_used} limit={usage.comment_reply_limit} icon={Zap} />
                    <UsageMeter label="AI Replies" used={usage.ai_reply_used} limit={usage.ai_reply_limit} icon={Brain} />
                    <UsageMeter label="Image Sends" used={usage.image_send_used} limit={usage.image_send_limit} icon={ImageIcon} />
                </div>

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm">Resource Limits</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 text-sm sm:grid-cols-3">
                        <div>
                            <p className="text-muted-foreground">Connected Pages</p>
                            <p className="font-medium">{usage.connected_page_limit}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Team Members</p>
                            <p className="font-medium">{usage.team_member_limit}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Knowledge Bases</p>
                            <p className="font-medium">{usage.knowledge_base_limit}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm">Usage History</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/50">
                                <tr>
                                    <th className="px-4 py-2 text-left font-medium">Type</th>
                                    <th className="px-4 py-2 text-left font-medium">Amount</th>
                                    <th className="px-4 py-2 text-left font-medium">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                {history.length === 0 ? (
                                    <tr>
                                        <td className="px-4 py-6 text-center text-muted-foreground" colSpan={3}>
                                            No usage logged yet.
                                        </td>
                                    </tr>
                                ) : (
                                    history.map((log) => (
                                        <tr key={log.id} className="border-b last:border-0">
                                            <td className="px-4 py-2 capitalize">{log.type.replaceAll('_', ' ')}</td>
                                            <td className="px-4 py-2">{log.amount}</td>
                                            <td className="px-4 py-2 text-muted-foreground">{new Date(log.created_at).toLocaleString()}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </ClientDashboardLayout>
    );
}
