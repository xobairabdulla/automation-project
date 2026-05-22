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
import { useState } from 'react';

interface Template {
    id: number;
    name: string;
    channel: string;
    language: string;
    body: string;
    status: string;
}

interface Page {
    id: number;
    page_name: string;
}

interface Props {
    templates: Template[];
    pages: Page[];
    selectedPageId: number | null;
}

interface SharedProps {
    flash?: { success?: string; error?: string };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reply Templates', href: '/reply-templates' },
];

const emptyTemplate = {
    facebook_page_id: '',
    name: '',
    channel: 'both',
    language: 'en',
    body: '',
    status: 'active',
};

export default function ReplyTemplates({ templates, pages, selectedPageId }: Props) {
    const { flash } = usePage<SharedProps>().props;
    const [createOpen, setCreateOpen] = useState(false);
    const [editTemplate, setEditTemplate] = useState<Template | null>(null);

    const selectedPage = pages.find((p) => p.id === selectedPageId) ?? pages[0] ?? null;

    const createForm = useForm({ ...emptyTemplate, facebook_page_id: String(selectedPage?.id ?? '') });
    const editForm = useForm({ name: '', channel: 'both', language: 'en', body: '', status: 'active' });

    function submitCreate(e: React.FormEvent) {
        e.preventDefault();
        createForm.post('/reply-templates', {
            onSuccess: () => {
                setCreateOpen(false);
                createForm.reset();
            },
        });
    }

    function openEdit(t: Template) {
        editForm.setData({ name: t.name, channel: t.channel, language: t.language, body: t.body, status: t.status });
        setEditTemplate(t);
    }

    function submitEdit(e: React.FormEvent) {
        e.preventDefault();
        if (!editTemplate) return;
        editForm.put(`/reply-templates/${editTemplate.id}`, { onSuccess: () => setEditTemplate(null) });
    }

    function deleteTemplate(t: Template) {
        if (!confirm(`Delete template "${t.name}"?`)) return;
        router.delete(`/reply-templates/${t.id}`);
    }

    function filterByPage(pageId: number | null) {
        router.get('/reply-templates', pageId ? { page_id: pageId } : {}, { preserveState: true });
    }

    return (
        <ClientDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Reply Templates" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-normal">Reply Templates</h1>
                        <p className="text-muted-foreground text-sm">Reusable reply text for automation rules.</p>
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
                                <Button>Create Template</Button>
                            </DialogTrigger>
                            <DialogContent className="max-w-md">
                                <DialogHeader>
                                    <DialogTitle>Create Reply Template</DialogTitle>
                                </DialogHeader>
                                <form onSubmit={submitCreate} className="grid gap-3">
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
                                    <div>
                                        <Label>Template Name</Label>
                                        <Input value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} required />
                                    </div>
                                    <div className="grid grid-cols-3 gap-3">
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
                                            <Label>Language</Label>
                                            <Input value={createForm.data.language} onChange={(e) => createForm.setData('language', e.target.value)} maxLength={10} />
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
                                        <Label>Body</Label>
                                        <textarea
                                            className="w-full rounded border p-2 text-sm"
                                            rows={4}
                                            required
                                            value={createForm.data.body}
                                            onChange={(e) => createForm.setData('body', e.target.value)}
                                            placeholder="Template text..."
                                        />
                                    </div>
                                    <Button type="submit" disabled={createForm.processing}>
                                        Create Template
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

                {templates.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center text-muted-foreground">No templates yet. Create one to use in your automation rules.</CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-3 md:grid-cols-2">
                        {templates.map((t) => (
                            <Card key={t.id}>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-base">{t.name}</CardTitle>
                                    <div className="flex items-center gap-2">
                                        <Badge variant="outline">{t.channel}</Badge>
                                        <Badge variant={t.status === 'active' ? 'default' : 'secondary'}>{t.status}</Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <p className="text-sm text-muted-foreground line-clamp-3">{t.body}</p>
                                    <p className="text-xs text-muted-foreground">Lang: {t.language}</p>
                                    <div className="flex gap-2">
                                        <Button variant="outline" size="sm" onClick={() => openEdit(t)}>
                                            Edit
                                        </Button>
                                        <Button variant="destructive" size="sm" onClick={() => deleteTemplate(t)}>
                                            Delete
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            <Dialog open={editTemplate !== null} onOpenChange={(open) => { if (!open) setEditTemplate(null); }}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Edit Template</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitEdit} className="grid gap-3">
                        <div>
                            <Label>Name</Label>
                            <Input value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} />
                        </div>
                        <div className="grid grid-cols-3 gap-3">
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
                                <Label>Language</Label>
                                <Input value={editForm.data.language} onChange={(e) => editForm.setData('language', e.target.value)} />
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
                        </div>
                        <div>
                            <Label>Body</Label>
                            <textarea
                                className="w-full rounded border p-2 text-sm"
                                rows={4}
                                value={editForm.data.body}
                                onChange={(e) => editForm.setData('body', e.target.value)}
                            />
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
