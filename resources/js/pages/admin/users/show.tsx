import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AdminDashboardLayout from '@/layouts/admin-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface Role {
    slug: string;
    name: string;
}

interface Plan {
    id: number;
    name: string;
}

interface Subscription {
    status: string;
    billing_cycle: string;
    starts_at: string;
    ends_at: string | null;
    plan: Plan;
}

interface UsageLimit {
    message_reply_limit: number;
    message_reply_used: number;
    comment_reply_limit: number;
    comment_reply_used: number;
    ai_reply_limit: number;
    ai_reply_used: number;
    reset_at: string;
}

interface UsageLog {
    id: number;
    type: string;
    amount: number;
    created_at: string;
}

interface User {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    status: string;
    created_at: string;
    last_login_at: string | null;
    roles: Role[];
    subscriptions: Subscription[];
}

interface ShowProps {
    user: User;
    usageLimit: UsageLimit | null;
    history: UsageLog[];
    plans: Plan[];
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'active') return 'default';
    if (status === 'suspended') return 'destructive';
    return 'secondary';
}

function usagePct(used: number, limit: number) {
    if (limit === 0) return 100;
    return Math.min(100, Math.round((used / limit) * 100));
}

export default function UserShow({ user, usageLimit, history, plans }: ShowProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin Dashboard', href: '/admin/dashboard' },
        { title: 'Users', href: '/admin/users' },
        { title: user.name, href: `/admin/users/${user.id}` },
    ];

    const [assignOpen, setAssignOpen] = useState(false);
    const [extendOpen, setExtendOpen] = useState(false);

    const assignForm = useForm({ plan_id: '', billing_cycle: 'monthly' });
    const extendForm = useForm({ message_reply_extra: 0, comment_reply_extra: 0, ai_reply_extra: 0 });

    function toggleStatus() {
        const action = user.status === 'suspended' ? 'activate' : 'suspend';
        router.post(`/admin/users/${user.id}/${action}`);
    }

    function submitAssign(e: React.FormEvent) {
        e.preventDefault();
        assignForm.post(`/admin/users/${user.id}/assign-plan`, { onSuccess: () => setAssignOpen(false) });
    }

    function submitExtend(e: React.FormEvent) {
        e.preventDefault();
        extendForm.post(`/admin/users/${user.id}/extend-limit`, { onSuccess: () => setExtendOpen(false) });
    }

    return (
        <AdminDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title={`User: ${user.name}`} />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-normal">{user.name}</h1>
                        <p className="text-muted-foreground text-sm">{user.email}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant={user.status === 'suspended' ? 'default' : 'destructive'} size="sm" onClick={toggleStatus}>
                            {user.status === 'suspended' ? 'Activate' : 'Suspend'}
                        </Button>

                        <Dialog open={assignOpen} onOpenChange={setAssignOpen}>
                            <DialogTrigger asChild>
                                <Button variant="outline" size="sm">Assign Plan</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Assign Plan to {user.name}</DialogTitle>
                                </DialogHeader>
                                <form onSubmit={submitAssign} className="space-y-4">
                                    <div>
                                        <Label>Plan</Label>
                                        <Select value={assignForm.data.plan_id} onValueChange={(v) => assignForm.setData('plan_id', v)}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select plan" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {plans.map((p) => (
                                                    <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div>
                                        <Label>Billing Cycle</Label>
                                        <Select value={assignForm.data.billing_cycle} onValueChange={(v) => assignForm.setData('billing_cycle', v)}>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="monthly">Monthly</SelectItem>
                                                <SelectItem value="yearly">Yearly</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <Button type="submit" disabled={assignForm.processing} className="w-full">
                                        Assign Plan
                                    </Button>
                                </form>
                            </DialogContent>
                        </Dialog>

                        <Dialog open={extendOpen} onOpenChange={setExtendOpen}>
                            <DialogTrigger asChild>
                                <Button variant="outline" size="sm">Extend Limits</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Extend Limits for {user.name}</DialogTitle>
                                </DialogHeader>
                                <form onSubmit={submitExtend} className="space-y-4">
                                    <div>
                                        <Label>Extra Message Replies</Label>
                                        <Input
                                            type="number"
                                            min={0}
                                            value={extendForm.data.message_reply_extra}
                                            onChange={(e) => extendForm.setData('message_reply_extra', Number(e.target.value))}
                                        />
                                    </div>
                                    <div>
                                        <Label>Extra Comment Replies</Label>
                                        <Input
                                            type="number"
                                            min={0}
                                            value={extendForm.data.comment_reply_extra}
                                            onChange={(e) => extendForm.setData('comment_reply_extra', Number(e.target.value))}
                                        />
                                    </div>
                                    <div>
                                        <Label>Extra AI Replies</Label>
                                        <Input
                                            type="number"
                                            min={0}
                                            value={extendForm.data.ai_reply_extra}
                                            onChange={(e) => extendForm.setData('ai_reply_extra', Number(e.target.value))}
                                        />
                                    </div>
                                    <Button type="submit" disabled={extendForm.processing} className="w-full">
                                        Extend Limits
                                    </Button>
                                </form>
                            </DialogContent>
                        </Dialog>

                        <Button asChild variant="outline" size="sm">
                            <Link href="/admin/users">Back</Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Account Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Status</span>
                                <Badge variant={statusVariant(user.status)}>{user.status}</Badge>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Phone</span>
                                <span>{user.phone ?? '—'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Roles</span>
                                <span>{user.roles.map((r) => r.name).join(', ') || '—'}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Joined</span>
                                <span>{new Date(user.created_at).toLocaleDateString()}</span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">Last Login</span>
                                <span>{user.last_login_at ? new Date(user.last_login_at).toLocaleString() : '—'}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Current Subscription</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {user.subscriptions[0] ? (
                                <>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Plan</span>
                                        <span>{user.subscriptions[0].plan.name}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Status</span>
                                        <Badge variant="secondary">{user.subscriptions[0].status}</Badge>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Billing</span>
                                        <span>{user.subscriptions[0].billing_cycle}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Expires</span>
                                        <span>{user.subscriptions[0].ends_at ? new Date(user.subscriptions[0].ends_at).toLocaleDateString() : '—'}</span>
                                    </div>
                                </>
                            ) : (
                                <p className="text-muted-foreground">No active subscription.</p>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {usageLimit && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Usage Limits</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-3">
                            {[
                                { label: 'Message Replies', used: usageLimit.message_reply_used, limit: usageLimit.message_reply_limit },
                                { label: 'Comment Replies', used: usageLimit.comment_reply_used, limit: usageLimit.comment_reply_limit },
                                { label: 'AI Replies', used: usageLimit.ai_reply_used, limit: usageLimit.ai_reply_limit },
                            ].map((m) => {
                                const pct = usagePct(m.used, m.limit);
                                return (
                                    <div key={m.label} className="space-y-1">
                                        <div className="flex justify-between text-sm">
                                            <span>{m.label}</span>
                                            <span className="text-muted-foreground">{m.used}/{m.limit}</span>
                                        </div>
                                        <div className="h-2 overflow-hidden rounded-full bg-muted">
                                            <div
                                                className={`h-full rounded-full ${pct >= 100 ? 'bg-red-600' : pct >= 80 ? 'bg-amber-500' : 'bg-emerald-600'}`}
                                                style={{ width: `${pct}%` }}
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Recent Usage History</CardTitle>
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
                                        <td className="px-4 py-6 text-center text-muted-foreground" colSpan={3}>No usage logged.</td>
                                    </tr>
                                ) : (
                                    history.map((log) => (
                                        <tr key={log.id} className="border-b last:border-0">
                                            <td className="px-4 py-2">{log.type.replaceAll('_', ' ')}</td>
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
        </AdminDashboardLayout>
    );
}
