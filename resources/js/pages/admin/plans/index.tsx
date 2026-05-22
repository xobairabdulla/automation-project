import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AdminDashboardLayout from '@/layouts/admin-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface Plan {
    id: number;
    name: string;
    description: string | null;
    monthly_price: string;
    yearly_price: string;
    message_reply_limit: number;
    comment_reply_limit: number;
    ai_reply_limit: number;
    connected_page_limit: number;
    team_member_limit: number;
    knowledge_base_limit: number;
    status: string;
}

interface PlansIndexProps {
    plans: Plan[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin Dashboard', href: '/admin/dashboard' },
    { title: 'Plans', href: '/admin/plans' },
];

const emptyPlanData = {
    name: '',
    description: '',
    monthly_price: '0',
    yearly_price: '0',
    message_reply_limit: '0',
    comment_reply_limit: '0',
    ai_reply_limit: '0',
    connected_page_limit: '0',
    team_member_limit: '0',
    knowledge_base_limit: '0',
    status: 'active',
};

function PlanForm({ onSubmit, form }: { onSubmit: (e: React.FormEvent) => void; form: ReturnType<typeof useForm> }) {
    return (
        <form onSubmit={onSubmit} className="grid gap-3">
            <div className="grid grid-cols-2 gap-3">
                <div>
                    <Label>Name</Label>
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                    {form.errors.name && <p className="text-xs text-red-500">{form.errors.name}</p>}
                </div>
                <div>
                    <Label>Status</Label>
                    <Select value={form.data.status} onValueChange={(v) => form.setData('status', v)}>
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="inactive">Inactive</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <div>
                <Label>Description</Label>
                <Input value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
            </div>
            <div className="grid grid-cols-2 gap-3">
                <div>
                    <Label>Monthly Price ($)</Label>
                    <Input type="number" min={0} step="0.01" value={form.data.monthly_price} onChange={(e) => form.setData('monthly_price', e.target.value)} />
                </div>
                <div>
                    <Label>Yearly Price ($)</Label>
                    <Input type="number" min={0} step="0.01" value={form.data.yearly_price} onChange={(e) => form.setData('yearly_price', e.target.value)} />
                </div>
            </div>
            <div className="grid grid-cols-3 gap-3">
                {[
                    { key: 'message_reply_limit', label: 'Msg Replies' },
                    { key: 'comment_reply_limit', label: 'Comment Replies' },
                    { key: 'ai_reply_limit', label: 'AI Replies' },
                    { key: 'connected_page_limit', label: 'Pages' },
                    { key: 'team_member_limit', label: 'Team Members' },
                    { key: 'knowledge_base_limit', label: 'KB Items' },
                ].map(({ key, label }) => (
                    <div key={key}>
                        <Label>{label}</Label>
                        <Input
                            type="number"
                            min={0}
                            value={(form.data as Record<string, string>)[key]}
                            onChange={(e) => form.setData(key as never, e.target.value)}
                        />
                    </div>
                ))}
            </div>
            <Button type="submit" disabled={form.processing} className="w-full">
                Save Plan
            </Button>
        </form>
    );
}

export default function PlansIndex({ plans }: PlansIndexProps) {
    const [createOpen, setCreateOpen] = useState(false);
    const [editPlan, setEditPlan] = useState<Plan | null>(null);

    const createForm = useForm(emptyPlanData);
    const editForm = useForm(emptyPlanData);

    function openEdit(plan: Plan) {
        editForm.setData({
            name: plan.name,
            description: plan.description ?? '',
            monthly_price: plan.monthly_price,
            yearly_price: plan.yearly_price,
            message_reply_limit: String(plan.message_reply_limit),
            comment_reply_limit: String(plan.comment_reply_limit),
            ai_reply_limit: String(plan.ai_reply_limit),
            connected_page_limit: String(plan.connected_page_limit),
            team_member_limit: String(plan.team_member_limit),
            knowledge_base_limit: String(plan.knowledge_base_limit),
            status: plan.status,
        });
        setEditPlan(plan);
    }

    function submitCreate(e: React.FormEvent) {
        e.preventDefault();
        createForm.post('/admin/plans', { onSuccess: () => { setCreateOpen(false); createForm.reset(); } });
    }

    function submitEdit(e: React.FormEvent) {
        e.preventDefault();
        if (!editPlan) return;
        editForm.put(`/admin/plans/${editPlan.id}`, { onSuccess: () => setEditPlan(null) });
    }

    function deactivate(plan: Plan) {
        if (!confirm(`Deactivate "${plan.name}"?`)) return;
        router.post(`/admin/plans/${plan.id}/deactivate`);
    }

    return (
        <AdminDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Plan Management" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-normal">Plan Management</h1>
                        <p className="text-muted-foreground text-sm">{plans.length} plans</p>
                    </div>
                    <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                        <DialogTrigger asChild>
                            <Button>Create Plan</Button>
                        </DialogTrigger>
                        <DialogContent className="max-w-lg">
                            <DialogHeader><DialogTitle>Create Plan</DialogTitle></DialogHeader>
                            <PlanForm onSubmit={submitCreate} form={createForm} />
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {plans.length === 0 ? (
                        <Card className="col-span-full">
                            <CardContent className="py-8 text-center text-muted-foreground">No plans yet.</CardContent>
                        </Card>
                    ) : (
                        plans.map((plan) => (
                            <Card key={plan.id}>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0">
                                    <CardTitle className="text-base">{plan.name}</CardTitle>
                                    <Badge variant={plan.status === 'active' ? 'default' : 'secondary'}>{plan.status}</Badge>
                                </CardHeader>
                                <CardContent className="space-y-3 text-sm">
                                    <p className="text-muted-foreground">{plan.description}</p>
                                    <div className="grid grid-cols-2 gap-1">
                                        <span className="text-muted-foreground">Monthly</span>
                                        <span>${plan.monthly_price}</span>
                                        <span className="text-muted-foreground">Msg replies</span>
                                        <span>{plan.message_reply_limit.toLocaleString()}</span>
                                        <span className="text-muted-foreground">Comment replies</span>
                                        <span>{plan.comment_reply_limit.toLocaleString()}</span>
                                        <span className="text-muted-foreground">AI replies</span>
                                        <span>{plan.ai_reply_limit.toLocaleString()}</span>
                                        <span className="text-muted-foreground">Pages</span>
                                        <span>{plan.connected_page_limit}</span>
                                    </div>
                                    <div className="flex gap-2 pt-1">
                                        <Button variant="outline" size="sm" onClick={() => openEdit(plan)}>Edit</Button>
                                        {plan.status === 'active' && (
                                            <Button variant="destructive" size="sm" onClick={() => deactivate(plan)}>Deactivate</Button>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ))
                    )}
                </div>
            </div>

            <Dialog open={editPlan !== null} onOpenChange={(open) => { if (!open) setEditPlan(null); }}>
                <DialogContent className="max-w-lg">
                    <DialogHeader><DialogTitle>Edit {editPlan?.name}</DialogTitle></DialogHeader>
                    <PlanForm onSubmit={submitEdit} form={editForm} />
                </DialogContent>
            </Dialog>
        </AdminDashboardLayout>
    );
}
