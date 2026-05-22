import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AdminDashboardLayout from '@/layouts/admin-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface SystemSetting {
    id: number;
    key: string;
    value_encrypted_or_json: string;
    type: string;
    is_sensitive: boolean;
    updated_at: string;
}

interface Props {
    settings: SystemSetting[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'System Settings', href: '/admin/system-settings' },
];

interface SettingForm {
    key: string;
    value: string;
    type: string;
}

export default function SystemSettings({ settings }: Props) {
    const [editing, setEditing] = useState<SystemSetting | null>(null);
    const [showForm, setShowForm] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm<SettingForm>({
        key: '',
        value: '',
        type: 'string',
    });

    function openNew() {
        reset();
        setEditing(null);
        setShowForm(true);
    }

    function openEdit(setting: SystemSetting) {
        setData({
            key: setting.key,
            value: setting.value_encrypted_or_json,
            type: setting.type,
        });
        setEditing(setting);
        setShowForm(true);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/admin/system-settings', {
            onSuccess: () => {
                setShowForm(false);
                reset();
                setEditing(null);
            },
        });
    }

    function deleteSetting(setting: SystemSetting) {
        if (!confirm(`Delete setting "${setting.key}"?`)) return;
        router.delete(`/admin/system-settings/${setting.id}`);
    }

    return (
        <AdminDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin: System Settings" />
            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between">
                    <p className="text-sm text-muted-foreground">{settings.length} settings</p>
                    <Button size="sm" onClick={openNew}>
                        <Plus className="mr-1 size-4" />
                        Add Setting
                    </Button>
                </div>

                {showForm && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">{editing ? `Edit: ${editing.key}` : 'New Setting'}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-3">
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <div className="flex flex-col gap-1">
                                        <label className="text-xs text-muted-foreground">Key</label>
                                        <Input
                                            value={data.key}
                                            onChange={(e) => setData('key', e.target.value)}
                                            placeholder="setting_key"
                                            disabled={!!editing}
                                        />
                                        {errors.key && <p className="text-xs text-red-500">{errors.key}</p>}
                                    </div>
                                    <div className="flex flex-col gap-1">
                                        <label className="text-xs text-muted-foreground">Type</label>
                                        <select
                                            value={data.type}
                                            onChange={(e) => setData('type', e.target.value)}
                                            className="rounded border px-2 py-1.5 text-sm"
                                        >
                                            <option value="string">String</option>
                                            <option value="integer">Integer</option>
                                            <option value="boolean">Boolean</option>
                                            <option value="json">JSON</option>
                                        </select>
                                        {errors.type && <p className="text-xs text-red-500">{errors.type}</p>}
                                    </div>
                                    <div className="flex flex-col gap-1">
                                        <label className="text-xs text-muted-foreground">Value</label>
                                        <Input
                                            value={data.value}
                                            onChange={(e) => setData('value', e.target.value)}
                                            placeholder="value"
                                        />
                                        {errors.value && <p className="text-xs text-red-500">{errors.value}</p>}
                                    </div>
                                </div>
                                <div className="flex gap-2">
                                    <Button type="submit" size="sm" disabled={processing}>
                                        Save
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => {
                                            setShowForm(false);
                                            reset();
                                            setEditing(null);
                                        }}
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="pb-2 pr-4">Key</th>
                                <th className="pb-2 pr-4">Type</th>
                                <th className="pb-2 pr-4">Value</th>
                                <th className="pb-2 pr-4">Updated</th>
                                <th className="pb-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {settings.map((s) => (
                                <tr key={s.id} className="border-b last:border-0">
                                    <td className="py-2 pr-4 font-mono font-medium">{s.key}</td>
                                    <td className="py-2 pr-4">
                                        <span className="rounded bg-muted px-1.5 py-0.5 text-xs">{s.type}</span>
                                    </td>
                                    <td className="max-w-xs py-2 pr-4 truncate font-mono text-xs text-muted-foreground">
                                        {s.is_sensitive ? '••••••••' : s.value_encrypted_or_json}
                                    </td>
                                    <td className="py-2 pr-4 text-xs text-muted-foreground">{new Date(s.updated_at).toLocaleString()}</td>
                                    <td className="py-2">
                                        <div className="flex gap-1">
                                            <Button variant="ghost" size="sm" onClick={() => openEdit(s)}>
                                                <Pencil className="size-3.5" />
                                            </Button>
                                            <Button variant="ghost" size="sm" onClick={() => deleteSetting(s)}>
                                                <Trash2 className="size-3.5 text-red-500" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {settings.length === 0 && <p className="py-8 text-center text-muted-foreground">No settings configured.</p>}
                </div>
            </div>
        </AdminDashboardLayout>
    );
}
