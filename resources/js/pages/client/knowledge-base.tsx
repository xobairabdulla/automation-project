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

interface KbItem {
    id: number;
    title: string;
    category: string;
    content: string;
    status: string;
}

interface KnowledgeBase {
    id: number;
    name: string;
    status: string;
    items: KbItem[];
}

interface Page {
    id: number;
    page_name: string;
}

interface Props {
    knowledgeBases: KnowledgeBase[];
    pages: Page[];
    selectedPageId: number | null;
}

interface SharedProps {
    flash?: { success?: string; error?: string };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Knowledge Base', href: '/knowledge-base' },
];

const CATEGORIES = ['faq', 'product', 'pricing', 'delivery', 'refund', 'contact', 'business_hours', 'restricted_topic', 'other'];

export default function KnowledgeBasePage({ knowledgeBases, pages, selectedPageId }: Props) {
    const { flash } = usePage<SharedProps>().props;
    const [addItemOpen, setAddItemOpen] = useState<number | null>(null);
    const [editItem, setEditItem] = useState<KbItem | null>(null);
    const [createBaseOpen, setCreateBaseOpen] = useState(false);

    const selectedPage = pages.find((p) => p.id === selectedPageId) ?? pages[0] ?? null;

    const baseForm = useForm({
        facebook_page_id: String(selectedPage?.id ?? ''),
        name: 'Main Knowledge Base',
    });

    const itemForm = useForm({
        knowledge_base_id: '',
        title: '',
        category: 'faq',
        content: '',
        status: 'active',
    });

    const editForm = useForm({ title: '', category: 'faq', content: '', status: 'active' });

    function submitBase(e: React.FormEvent) {
        e.preventDefault();
        baseForm.post('/knowledge-base/bases', { onSuccess: () => setCreateBaseOpen(false) });
    }

    function openAddItem(kbId: number) {
        itemForm.setData('knowledge_base_id', String(kbId));
        setAddItemOpen(kbId);
    }

    function submitItem(e: React.FormEvent) {
        e.preventDefault();
        itemForm.post('/knowledge-base/items', { onSuccess: () => { setAddItemOpen(null); itemForm.reset(); } });
    }

    function openEdit(item: KbItem) {
        editForm.setData({ title: item.title, category: item.category, content: item.content, status: item.status });
        setEditItem(item);
    }

    function submitEdit(e: React.FormEvent) {
        e.preventDefault();
        if (!editItem) return;
        editForm.put(`/knowledge-base/items/${editItem.id}`, { onSuccess: () => setEditItem(null) });
    }

    function deleteItem(item: KbItem) {
        if (!confirm(`Delete "${item.title}"?`)) return;
        router.delete(`/knowledge-base/items/${item.id}`);
    }

    function filterByPage(pageId: number | null) {
        router.get('/knowledge-base', pageId ? { page_id: pageId } : {}, { preserveState: true });
    }

    return (
        <ClientDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Knowledge Base" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-normal">Knowledge Base</h1>
                        <p className="text-muted-foreground text-sm">Teach the AI about your business.</p>
                    </div>
                    <div className="flex gap-2">
                        <Select value={String(selectedPageId ?? '')} onValueChange={(v) => filterByPage(v ? Number(v) : null)}>
                            <SelectTrigger className="w-44">
                                <SelectValue placeholder="All pages" />
                            </SelectTrigger>
                            <SelectContent>
                                {pages.map((p) => (
                                    <SelectItem key={p.id} value={String(p.id)}>{p.page_name}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Dialog open={createBaseOpen} onOpenChange={setCreateBaseOpen}>
                            <DialogTrigger asChild>
                                <Button variant="outline">New Knowledge Base</Button>
                            </DialogTrigger>
                            <DialogContent className="max-w-sm">
                                <DialogHeader><DialogTitle>Create Knowledge Base</DialogTitle></DialogHeader>
                                <form onSubmit={submitBase} className="grid gap-3">
                                    <div>
                                        <Label>Page</Label>
                                        <Select value={baseForm.data.facebook_page_id} onValueChange={(v) => baseForm.setData('facebook_page_id', v)}>
                                            <SelectTrigger><SelectValue placeholder="Select page" /></SelectTrigger>
                                            <SelectContent>
                                                {pages.map((p) => (
                                                    <SelectItem key={p.id} value={String(p.id)}>{p.page_name}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div>
                                        <Label>Name</Label>
                                        <Input value={baseForm.data.name} onChange={(e) => baseForm.setData('name', e.target.value)} required />
                                    </div>
                                    <Button type="submit" disabled={baseForm.processing}>Create</Button>
                                </form>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>

                {flash?.success && <Alert><AlertDescription>{flash.success}</AlertDescription></Alert>}

                {knowledgeBases.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center text-muted-foreground">
                            No knowledge base yet. Create one to enable AI replies.
                        </CardContent>
                    </Card>
                ) : (
                    knowledgeBases.map((kb) => (
                        <Card key={kb.id}>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3">
                                <CardTitle className="text-base">{kb.name}</CardTitle>
                                <div className="flex items-center gap-2">
                                    <Badge variant={kb.status === 'active' ? 'default' : 'secondary'}>{kb.status}</Badge>
                                    <Button size="sm" onClick={() => openAddItem(kb.id)}>+ Add Item</Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {kb.items.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">No items yet.</p>
                                ) : (
                                    <div className="space-y-2">
                                        {kb.items.map((item) => (
                                            <div key={item.id} className="flex items-start justify-between rounded border p-3 gap-3">
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-center gap-2 mb-1">
                                                        <Badge variant="outline" className="text-xs">{item.category}</Badge>
                                                        <Badge variant={item.status === 'active' ? 'default' : 'secondary'} className="text-xs">{item.status}</Badge>
                                                        <span className="text-sm font-medium truncate">{item.title}</span>
                                                    </div>
                                                    <p className="text-xs text-muted-foreground line-clamp-2">{item.content}</p>
                                                </div>
                                                <div className="flex gap-1 shrink-0">
                                                    <Button variant="outline" size="sm" onClick={() => openEdit(item)}>Edit</Button>
                                                    <Button variant="destructive" size="sm" onClick={() => deleteItem(item)}>Del</Button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    ))
                )}
            </div>

            {/* Add Item Dialog */}
            <Dialog open={addItemOpen !== null} onOpenChange={(open) => { if (!open) setAddItemOpen(null); }}>
                <DialogContent className="max-w-md">
                    <DialogHeader><DialogTitle>Add Knowledge Item</DialogTitle></DialogHeader>
                    <form onSubmit={submitItem} className="grid gap-3">
                        <div>
                            <Label>Title</Label>
                            <Input value={itemForm.data.title} onChange={(e) => itemForm.setData('title', e.target.value)} required />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label>Category</Label>
                                <Select value={itemForm.data.category} onValueChange={(v) => itemForm.setData('category', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        {CATEGORIES.map((c) => (
                                            <SelectItem key={c} value={c}>{c}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label>Status</Label>
                                <Select value={itemForm.data.status} onValueChange={(v) => itemForm.setData('status', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="active">Active</SelectItem>
                                        <SelectItem value="inactive">Inactive</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div>
                            <Label>Content</Label>
                            <textarea
                                className="w-full rounded border p-2 text-sm"
                                rows={4}
                                required
                                value={itemForm.data.content}
                                onChange={(e) => itemForm.setData('content', e.target.value)}
                                placeholder="Write the answer or information..."
                            />
                        </div>
                        <Button type="submit" disabled={itemForm.processing}>Add Item</Button>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit Item Dialog */}
            <Dialog open={editItem !== null} onOpenChange={(open) => { if (!open) setEditItem(null); }}>
                <DialogContent className="max-w-md">
                    <DialogHeader><DialogTitle>Edit Item</DialogTitle></DialogHeader>
                    <form onSubmit={submitEdit} className="grid gap-3">
                        <div>
                            <Label>Title</Label>
                            <Input value={editForm.data.title} onChange={(e) => editForm.setData('title', e.target.value)} />
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label>Category</Label>
                                <Select value={editForm.data.category} onValueChange={(v) => editForm.setData('category', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        {CATEGORIES.map((c) => (
                                            <SelectItem key={c} value={c}>{c}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label>Status</Label>
                                <Select value={editForm.data.status} onValueChange={(v) => editForm.setData('status', v)}>
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="active">Active</SelectItem>
                                        <SelectItem value="inactive">Inactive</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div>
                            <Label>Content</Label>
                            <textarea
                                className="w-full rounded border p-2 text-sm"
                                rows={4}
                                value={editForm.data.content}
                                onChange={(e) => editForm.setData('content', e.target.value)}
                            />
                        </div>
                        <Button type="submit" disabled={editForm.processing}>Save</Button>
                    </form>
                </DialogContent>
            </Dialog>
        </ClientDashboardLayout>
    );
}
