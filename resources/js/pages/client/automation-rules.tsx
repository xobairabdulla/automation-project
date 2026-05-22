import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import ClientDashboardLayout from '@/layouts/client-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';

interface Condition {
    condition_type: string;
    keywords_json: string[];
    case_sensitive: boolean;
}

interface Action {
    action_type: string;
    reply_text: string | null;
    reply_template_id: number | null;
}

interface Rule {
    id: number;
    name: string;
    channel: string;
    priority: number;
    status: string;
    conditions: { id: number; condition_type: string; keywords_json: string[] }[];
    actions: { id: number; action_type: string; reply_text: string | null }[];
}

interface Page {
    id: number;
    page_name: string;
}

interface Props {
    rules: Rule[];
    pages: Page[];
    selectedPageId: number | null;
}

interface SharedProps {
    flash?: { success?: string; error?: string };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Automation Rules', href: '/automation-rules' },
];

export default function AutomationRules({ rules, pages, selectedPageId }: Props) {
    const { flash } = usePage<SharedProps>().props;
    const [createOpen, setCreateOpen] = useState(false);
    const [editRule, setEditRule] = useState<Rule | null>(null);

    const selectedPage = pages.find((p) => p.id === selectedPageId) ?? pages[0] ?? null;

    const createForm = useForm({
        facebook_page_id: String(selectedPage?.id ?? ''),
        name: '',
        channel: 'both',
        priority: '10',
        status: 'active',
        conditions: [{ condition_type: 'keyword_contains', keywords_json: [] as string[], case_sensitive: false }] as Condition[],
        action: { action_type: 'send_text', reply_text: '', reply_template_id: null } as Action,
    });

    const editForm = useForm({ name: '', channel: 'both', priority: '10', status: 'active' });

    function submitCreate(e: React.FormEvent) {
        e.preventDefault();
        createForm.post('/automation-rules', {
            onSuccess: () => {
                setCreateOpen(false);
                createForm.reset();
            },
        });
    }

    function openEdit(rule: Rule) {
        editForm.setData({
            name: rule.name,
            channel: rule.channel,
            priority: String(rule.priority),
            status: rule.status,
        });
        setEditRule(rule);
    }

    function submitEdit(e: React.FormEvent) {
        e.preventDefault();
        if (!editRule) return;
        editForm.put(`/automation-rules/${editRule.id}`, { onSuccess: () => setEditRule(null) });
    }

    function deleteRule(rule: Rule) {
        if (!confirm(`Delete rule "${rule.name}"?`)) return;
        router.delete(`/automation-rules/${rule.id}`);
    }

    function addCondition() {
        createForm.setData('conditions', [
            ...createForm.data.conditions,
            { condition_type: 'keyword_contains', keywords_json: [], case_sensitive: false },
        ]);
    }

    function removeCondition(idx: number) {
        createForm.setData(
            'conditions',
            createForm.data.conditions.filter((_, i) => i !== idx),
        );
    }

    function updateCondition(idx: number, field: keyof Condition, value: unknown) {
        const updated = createForm.data.conditions.map((c, i) => (i === idx ? { ...c, [field]: value } : c));
        createForm.setData('conditions', updated);
    }

    function filterByPage(pageId: number | null) {
        router.get('/automation-rules', pageId ? { page_id: pageId } : {}, { preserveState: true });
    }

    return (
        <ClientDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Automation Rules" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-normal">Automation Rules</h1>
                        <p className="text-muted-foreground text-sm">Keyword-based rules to trigger automatic replies.</p>
                    </div>
                    <div className="flex gap-2">
                        <Select value={String(selectedPageId ?? '')} onValueChange={(v) => filterByPage(v ? Number(v) : null)}>
                            <SelectTrigger className="w-44">
                                <SelectValue placeholder="All pages" />
                            </SelectTrigger>
                            <SelectContent>
                                {pages.map((p) => (
                                    <SelectItem key={p.id} value={String(p.id)}>
                                        {p.page_name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                            <DialogTrigger asChild>
                                <Button>Create Rule</Button>
                            </DialogTrigger>
                            <DialogContent className="max-w-lg max-h-[80vh] overflow-y-auto">
                                <DialogHeader>
                                    <DialogTitle>Create Automation Rule</DialogTitle>
                                </DialogHeader>
                                <form onSubmit={submitCreate} className="grid gap-4">
                                    <div>
                                        <Label>Page</Label>
                                        <Select value={createForm.data.facebook_page_id} onValueChange={(v) => createForm.setData('facebook_page_id', v)}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select page" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {pages.map((p) => (
                                                    <SelectItem key={p.id} value={String(p.id)}>
                                                        {p.page_name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <Label>Rule Name</Label>
                                            <Input value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} required />
                                        </div>
                                        <div>
                                            <Label>Priority (1=highest)</Label>
                                            <Input
                                                type="number"
                                                min={1}
                                                max={100}
                                                value={createForm.data.priority}
                                                onChange={(e) => createForm.setData('priority', e.target.value)}
                                            />
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div>
                                            <Label>Channel</Label>
                                            <Select value={createForm.data.channel} onValueChange={(v) => createForm.setData('channel', v)}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="message">Message</SelectItem>
                                                    <SelectItem value="comment">Comment</SelectItem>
                                                    <SelectItem value="both">Both</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div>
                                            <Label>Status</Label>
                                            <Select value={createForm.data.status} onValueChange={(v) => createForm.setData('status', v)}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="active">Active</SelectItem>
                                                    <SelectItem value="inactive">Inactive</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>

                                    <div>
                                        <div className="mb-2 flex items-center justify-between">
                                            <Label>Conditions (all must match)</Label>
                                            <Button type="button" variant="outline" size="sm" onClick={addCondition}>
                                                + Add
                                            </Button>
                                        </div>
                                        {createForm.data.conditions.map((cond, idx) => (
                                            <div key={idx} className="mb-3 rounded border p-3 space-y-2">
                                                <div className="flex gap-2">
                                                    <Select value={cond.condition_type} onValueChange={(v) => updateCondition(idx, 'condition_type', v)}>
                                                        <SelectTrigger className="flex-1">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="keyword_contains">Contains</SelectItem>
                                                            <SelectItem value="exact_match">Exact match</SelectItem>
                                                            <SelectItem value="starts_with">Starts with</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                    {createForm.data.conditions.length > 1 && (
                                                        <Button type="button" variant="ghost" size="sm" onClick={() => removeCondition(idx)}>
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                                <Input
                                                    placeholder="Keywords, comma-separated"
                                                    value={cond.keywords_json.join(', ')}
                                                    onChange={(e) =>
                                                        updateCondition(
                                                            idx,
                                                            'keywords_json',
                                                            e.target.value
                                                                .split(',')
                                                                .map((k) => k.trim())
                                                                .filter(Boolean),
                                                        )
                                                    }
                                                />
                                            </div>
                                        ))}
                                    </div>

                                    <div>
                                        <Label>Action</Label>
                                        <Select
                                            value={createForm.data.action.action_type}
                                            onValueChange={(v) => createForm.setData('action', { ...createForm.data.action, action_type: v })}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="send_text">Send Text</SelectItem>
                                                <SelectItem value="human_handover">Human Handover</SelectItem>
                                                <SelectItem value="do_nothing">Do Nothing</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {createForm.data.action.action_type === 'send_text' && (
                                            <textarea
                                                className="mt-2 w-full rounded border p-2 text-sm"
                                                rows={3}
                                                placeholder="Reply text..."
                                                value={createForm.data.action.reply_text ?? ''}
                                                onChange={(e) => createForm.setData('action', { ...createForm.data.action, reply_text: e.target.value })}
                                            />
                                        )}
                                    </div>

                                    <Button type="submit" disabled={createForm.processing}>
                                        Create Rule
                                    </Button>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>

                {flash?.success && (
                    <Alert>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                {rules.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center text-muted-foreground">No rules yet. Create one to get started.</CardContent>
                    </Card>
                ) : (
                    <div className="space-y-3">
                        {rules.map((rule) => (
                            <Card key={rule.id}>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <div className="flex items-center gap-2">
                                        <CardTitle className="text-base">{rule.name}</CardTitle>
                                        <Badge variant="outline">Priority {rule.priority}</Badge>
                                        <Badge variant="outline">{rule.channel}</Badge>
                                        <Badge variant={rule.status === 'active' ? 'default' : 'secondary'}>{rule.status}</Badge>
                                    </div>
                                    <div className="flex gap-2">
                                        <Button variant="outline" size="sm" onClick={() => openEdit(rule)}>
                                            Edit
                                        </Button>
                                        <Button variant="destructive" size="sm" onClick={() => deleteRule(rule)}>
                                            Delete
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent className="text-sm text-muted-foreground space-y-1">
                                    {rule.conditions.map((c, i) => (
                                        <p key={i}>
                                            <span className="font-medium">{c.condition_type}</span>: {c.keywords_json?.join(', ')}
                                        </p>
                                    ))}
                                    {rule.actions[0] && (
                                        <p>
                                            Action: <span className="font-medium">{rule.actions[0].action_type}</span>
                                            {rule.actions[0].reply_text && ` — "${rule.actions[0].reply_text}"`}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            <Dialog open={editRule !== null} onOpenChange={(open) => { if (!open) setEditRule(null); }}>
                <DialogContent className="max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Edit Rule</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitEdit} className="grid gap-3">
                        <div>
                            <Label>Name</Label>
                            <Input value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label>Channel</Label>
                                <Select value={editForm.data.channel} onValueChange={(v) => editForm.setData('channel', v)}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="message">Message</SelectItem>
                                        <SelectItem value="comment">Comment</SelectItem>
                                        <SelectItem value="both">Both</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label>Priority</Label>
                                <Input
                                    type="number"
                                    min={1}
                                    max={100}
                                    value={editForm.data.priority}
                                    onChange={(e) => editForm.setData('priority', e.target.value)}
                                />
                            </div>
                        </div>
                        <div>
                            <Label>Status</Label>
                            <Select value={editForm.data.status} onValueChange={(v) => editForm.setData('status', v)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="active">Active</SelectItem>
                                    <SelectItem value="inactive">Inactive</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <Button type="submit" disabled={editForm.processing}>
                            Save
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </ClientDashboardLayout>
    );
}
