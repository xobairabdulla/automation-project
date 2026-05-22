import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AdminDashboardLayout from '@/layouts/admin-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface AuditLogUser {
    id: number;
    name: string;
    email: string;
}

interface AuditLog {
    id: number;
    user_id: number | null;
    user: AuditLogUser | null;
    action: string;
    entity_type: string | null;
    entity_id: number | null;
    old_values_json: Record<string, unknown> | null;
    new_values_json: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string;
}

interface Paginated {
    data: AuditLog[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Filters {
    action?: string;
    user_id?: string;
    entity_type?: string;
}

interface Props {
    logs: Paginated;
    filters: Filters;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Audit Logs', href: '/admin/audit-logs' },
];

export default function AuditLogs({ logs, filters }: Props) {
    const [action, setAction] = useState(filters.action ?? '');
    const [entityType, setEntityType] = useState(filters.entity_type ?? '');

    function applyFilters() {
        router.get('/admin/audit-logs', { action: action || undefined, entity_type: entityType || undefined });
    }

    return (
        <AdminDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Audit Logs" />
            <div className="space-y-4 p-4">
                <div className="flex flex-wrap items-end gap-3">
                    <div className="flex flex-col gap-1">
                        <label className="text-xs text-muted-foreground">Action</label>
                        <Input
                            placeholder="e.g. user.suspended"
                            value={action}
                            onChange={(e) => setAction(e.target.value)}
                            className="w-48"
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-xs text-muted-foreground">Entity Type</label>
                        <Input
                            placeholder="e.g. User"
                            value={entityType}
                            onChange={(e) => setEntityType(e.target.value)}
                            className="w-36"
                        />
                    </div>
                    <Button size="sm" onClick={applyFilters}>
                        Filter
                    </Button>
                </div>

                <p className="text-sm text-muted-foreground">{logs.total} total entries</p>

                <div className="space-y-2">
                    {logs.data.map((log) => (
                        <Card key={log.id}>
                            <CardContent className="py-3">
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div className="space-y-0.5">
                                        <div className="flex items-center gap-2">
                                            <Badge variant="outline">{log.action}</Badge>
                                            {log.entity_type && (
                                                <span className="text-sm text-muted-foreground">
                                                    {log.entity_type} #{log.entity_id}
                                                </span>
                                            )}
                                        </div>
                                        {log.user && (
                                            <p className="text-sm">
                                                By: {log.user.name} ({log.user.email})
                                            </p>
                                        )}
                                        {log.ip_address && <p className="text-xs text-muted-foreground">IP: {log.ip_address}</p>}
                                    </div>
                                    <p className="text-xs text-muted-foreground">{new Date(log.created_at).toLocaleString()}</p>
                                </div>
                                {(log.old_values_json || log.new_values_json) && (
                                    <div className="mt-2 grid grid-cols-1 gap-2 text-xs md:grid-cols-2">
                                        {log.old_values_json && (
                                            <div className="rounded bg-red-50 p-2 dark:bg-red-950">
                                                <p className="font-medium text-red-600">Before</p>
                                                <pre className="whitespace-pre-wrap text-red-700">{JSON.stringify(log.old_values_json, null, 2)}</pre>
                                            </div>
                                        )}
                                        {log.new_values_json && (
                                            <div className="rounded bg-green-50 p-2 dark:bg-green-950">
                                                <p className="font-medium text-green-600">After</p>
                                                <pre className="whitespace-pre-wrap text-green-700">{JSON.stringify(log.new_values_json, null, 2)}</pre>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {logs.last_page > 1 && (
                    <div className="flex justify-center gap-2">
                        {logs.current_page > 1 && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => router.get('/admin/audit-logs', { ...filters, page: logs.current_page - 1 })}
                            >
                                Previous
                            </Button>
                        )}
                        <span className="flex items-center text-sm">
                            {logs.current_page} / {logs.last_page}
                        </span>
                        {logs.current_page < logs.last_page && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => router.get('/admin/audit-logs', { ...filters, page: logs.current_page + 1 })}
                            >
                                Next
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </AdminDashboardLayout>
    );
}
