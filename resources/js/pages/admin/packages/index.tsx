import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminDashboardLayout from '@/layouts/admin-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { MessageSquare, Package, Trash2, Zap } from 'lucide-react';
import { useState } from 'react';

interface UsagePackage {
    id: number;
    name: string;
    description: string | null;
    message_extra: number;
    comment_extra: number;
    ai_extra: number;
    image_send_extra: number;
    price: string;
    currency: string;
    is_active: boolean;
}

interface Props {
    packages: UsagePackage[];
}

interface SharedProps {
    flash?: { success?: string; error?: string };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin Dashboard', href: '/admin/dashboard' },
    { title: 'Usage Packages', href: '/admin/packages' },
];

const emptyForm = {
    name: '',
    description: '',
    message_extra: '0',
    comment_extra: '0',
    ai_extra: '0',
    image_send_extra: '0',
    price: '',
    currency: 'BDT',
    is_active: true,
};

function PackageForm({ form }: { form: ReturnType<typeof useForm> }) {
    return (
        <div className="grid gap-3">
            <div className="grid grid-cols-2 gap-3">
                <div className="col-span-2">
                    <Label>Package Name *</Label>
                    <Input value={form.data.name as string} onChange={(e) => form.setData('name', e.target.value)} required placeholder="e.g. Message Starter Pack" />
                    {form.errors.name && <p className="text-xs text-red-500">{form.errors.name}</p>}
                </div>
                <div className="col-span-2">
                    <Label>Description</Label>
                    <Input value={form.data.description as string} onChange={(e) => form.setData('description', e.target.value)} placeholder="Short description shown to users" />
                </div>
            </div>

            <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Credits Included</p>
            <div className="grid grid-cols-2 gap-3">
                {[
                    { key: 'message_extra', label: 'Message Replies' },
                    { key: 'comment_extra', label: 'Comment Replies' },
                    { key: 'ai_extra', label: 'AI Replies' },
                    { key: 'image_send_extra', label: 'Image Sends' },
                ].map(({ key, label }) => (
                    <div key={key}>
                        <Label>{label}</Label>
                        <Input
                            type="number"
                            min={0}
                            value={(form.data as Record<string, string>)[key]}
                            onChange={(e) => form.setData(key as never, e.target.value)}
                        />
                        {(form.errors as Record<string, string>)[key] && (
                            <p className="text-xs text-red-500">{(form.errors as Record<string, string>)[key]}</p>
                        )}
                    </div>
                ))}
            </div>

            <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Pricing</p>
            <div className="grid grid-cols-2 gap-3">
                <div>
                    <Label>Price *</Label>
                    <Input type="number" min={0} step="0.01" value={form.data.price as string} onChange={(e) => form.setData('price', e.target.value)} required placeholder="200" />
                    {form.errors.price && <p className="text-xs text-red-500">{form.errors.price}</p>}
                </div>
                <div>
                    <Label>Currency</Label>
                    <Input value={form.data.currency as string} onChange={(e) => form.setData('currency', e.target.value)} maxLength={3} placeholder="BDT" />
                </div>
            </div>

            <label className="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" checked={form.data.is_active as boolean} onChange={(e) => form.setData('is_active', e.target.checked as never)} />
                Active (visible to users)
            </label>
        </div>
    );
}

export default function PackagesIndex({ packages }: Props) {
    const { flash } = usePage<SharedProps>().props;
    const [createOpen, setCreateOpen] = useState(false);
    const [editPkg, setEditPkg] = useState<UsagePackage | null>(null);

    const createForm = useForm({ ...emptyForm });
    const editForm = useForm({ ...emptyForm });

    function openEdit(pkg: UsagePackage) {
        editForm.setData({
            name: pkg.name,
            description: pkg.description ?? '',
            message_extra: String(pkg.message_extra),
            comment_extra: String(pkg.comment_extra),
            ai_extra: String(pkg.ai_extra),
            image_send_extra: String(pkg.image_send_extra),
            price: pkg.price,
            currency: pkg.currency,
            is_active: pkg.is_active,
        });
        setEditPkg(pkg);
    }

    function submitCreate(e: React.FormEvent) {
        e.preventDefault();
        createForm.post('/admin/packages', {
            onSuccess: () => { setCreateOpen(false); createForm.reset(); },
        });
    }

    function submitEdit(e: React.FormEvent) {
        e.preventDefault();
        if (!editPkg) return;
        editForm.put(`/admin/packages/${editPkg.id}`, { onSuccess: () => setEditPkg(null) });
    }

    function deletePkg(pkg: UsagePackage) {
        if (!confirm(`Delete "${pkg.name}"?`)) return;
        router.delete(`/admin/packages/${pkg.id}`);
    }

    return (
        <AdminDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Usage Packages" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-normal">Usage Packages</h1>
                        <p className="text-muted-foreground text-sm">
                            Create credit packages users can buy when they run out of usage.
                        </p>
                    </div>
                    <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                        <DialogTrigger asChild>
                            <Button><Package size={15} className="mr-1" />Add Package</Button>
                        </DialogTrigger>
                        <DialogContent className="max-w-lg">
                            <DialogHeader><DialogTitle>Create Usage Package</DialogTitle></DialogHeader>
                            <form onSubmit={submitCreate} className="grid gap-4">
                                <PackageForm form={createForm} />
                                <Button type="submit" disabled={createForm.processing}>Create Package</Button>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>

                {flash?.success && <Alert><AlertDescription>{flash.success}</AlertDescription></Alert>}

                {packages.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center text-muted-foreground">
                            No packages yet. Create one above.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {packages.map((pkg) => (
                            <Card key={pkg.id}>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-base">{pkg.name}</CardTitle>
                                    <Badge variant={pkg.is_active ? 'default' : 'secondary'}>
                                        {pkg.is_active ? 'Active' : 'Inactive'}
                                    </Badge>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {pkg.description && (
                                        <p className="text-sm text-muted-foreground">{pkg.description}</p>
                                    )}

                                    <div className="text-2xl font-bold">
                                        {pkg.currency} {parseFloat(pkg.price).toLocaleString()}
                                    </div>

                                    <div className="grid grid-cols-2 gap-1 text-sm">
                                        {pkg.message_extra > 0 && (
                                            <>
                                                <span className="flex items-center gap-1 text-muted-foreground"><MessageSquare size={12} />Messages</span>
                                                <span className="font-medium">{pkg.message_extra.toLocaleString()}</span>
                                            </>
                                        )}
                                        {pkg.comment_extra > 0 && (
                                            <>
                                                <span className="text-muted-foreground">Comments</span>
                                                <span className="font-medium">{pkg.comment_extra.toLocaleString()}</span>
                                            </>
                                        )}
                                        {pkg.ai_extra > 0 && (
                                            <>
                                                <span className="flex items-center gap-1 text-muted-foreground"><Zap size={12} />AI Replies</span>
                                                <span className="font-medium">{pkg.ai_extra.toLocaleString()}</span>
                                            </>
                                        )}
                                        {pkg.image_send_extra > 0 && (
                                            <>
                                                <span className="text-muted-foreground">Image Sends</span>
                                                <span className="font-medium">{pkg.image_send_extra.toLocaleString()}</span>
                                            </>
                                        )}
                                    </div>

                                    <div className="flex gap-2 pt-1">
                                        <Button variant="outline" size="sm" onClick={() => openEdit(pkg)}>Edit</Button>
                                        <Button variant="destructive" size="sm" onClick={() => deletePkg(pkg)}>
                                            <Trash2 size={13} />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            <Dialog open={editPkg !== null} onOpenChange={(open) => { if (!open) setEditPkg(null); }}>
                <DialogContent className="max-w-lg">
                    <DialogHeader><DialogTitle>Edit {editPkg?.name}</DialogTitle></DialogHeader>
                    <form onSubmit={submitEdit} className="grid gap-4">
                        <PackageForm form={editForm} />
                        <Button type="submit" disabled={editForm.processing}>Save Changes</Button>
                    </form>
                </DialogContent>
            </Dialog>
        </AdminDashboardLayout>
    );
}
