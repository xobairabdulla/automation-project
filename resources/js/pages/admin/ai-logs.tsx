import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AdminDashboardLayout from '@/layouts/admin-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface AiLog {
    id: number;
    tenant_id: number;
    model: string;
    status: string;
    tokens_used: number | null;
    error_message: string | null;
    created_at: string;
    facebook_page: { id: number; page_name: string } | null;
}

interface Paginated {
    data: AiLog[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Stats {
    total: number;
    success: number;
    failed: number;
    total_tokens: number;
}

interface Filters {
    status?: string;
    model?: string;
    tenant_id?: string;
}

interface Props {
    logs: Paginated;
    stats: Stats;
    filters: Filters;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'AI Logs', href: '/admin/ai-logs' },
];

export default function AdminAiLogs({ logs, stats, filters }: Props) {
    const [status, setStatus] = useState(filters.status ?? '');
    const [model, setModel] = useState(filters.model ?? '');
    const [tenantId, setTenantId] = useState(filters.tenant_id ?? '');

    function applyFilters() {
        router.get('/admin/ai-logs', {
            status: status || undefined,
            model: model || undefined,
            tenant_id: tenantId || undefined,
        });
    }

    return (
        <AdminDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin: AI Logs" />
            <div className="space-y-4 p-4">
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Card>
                        <CardContent className="py-3">
                            <p className="text-xs text-muted-foreground">Total Requests</p>
                            <p className="text-xl font-bold">{stats.total}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="py-3">
                            <p className="text-xs text-muted-foreground">Successful</p>
                            <p className="text-xl font-bold text-green-600">{stats.success}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="py-3">
                            <p className="text-xs text-muted-foreground">Failed</p>
                            <p className="text-xl font-bold text-red-600">{stats.failed}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="py-3">
                            <p className="text-xs text-muted-foreground">Total Tokens</p>
                            <p className="text-xl font-bold">{stats.total_tokens.toLocaleString()}</p>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <div className="flex flex-col gap-1">
                        <label className="text-xs text-muted-foreground">Status</label>
                        <select value={status} onChange={(e) => setStatus(e.target.value)} className="rounded border px-2 py-1.5 text-sm">
                            <option value="">All</option>
                            <option value="success">Success</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-xs text-muted-foreground">Model</label>
                        <Input
                            placeholder="gpt-4..."
                            value={model}
                            onChange={(e) => setModel(e.target.value)}
                            className="w-40"
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-xs text-muted-foreground">Tenant ID</label>
                        <Input
                            placeholder="123"
                            value={tenantId}
                            onChange={(e) => setTenantId(e.target.value)}
                            className="w-28"
                        />
                    </div>
                    <Button size="sm" onClick={applyFilters}>
                        Filter
                    </Button>
                </div>

                <p className="text-sm text-muted-foreground">{logs.total} total AI requests</p>

                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="pb-2 pr-4">Tenant</th>
                                <th className="pb-2 pr-4">Page</th>
                                <th className="pb-2 pr-4">Model</th>
                                <th className="pb-2 pr-4">Status</th>
                                <th className="pb-2 pr-4">Tokens</th>
                                <th className="pb-2 pr-4">Created</th>
                                <th className="pb-2">Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.map((log) => (
                                <tr key={log.id} className="border-b last:border-0">
                                    <td className="py-2 pr-4 font-mono text-xs">{log.tenant_id}</td>
                                    <td className="py-2 pr-4">{log.facebook_page?.page_name ?? '—'}</td>
                                    <td className="py-2 pr-4">
                                        <Badge variant="outline" className="font-mono text-xs">
                                            {log.model}
                                        </Badge>
                                    </td>
                                    <td className="py-2 pr-4">
                                        <Badge variant={log.status === 'success' ? 'default' : 'destructive'}>{log.status}</Badge>
                                    </td>
                                    <td className="py-2 pr-4 font-mono text-xs">{log.tokens_used ?? '—'}</td>
                                    <td className="py-2 pr-4 text-xs text-muted-foreground">{new Date(log.created_at).toLocaleString()}</td>
                                    <td className="max-w-xs py-2 truncate text-xs text-red-500">{log.error_message ?? '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {logs.data.length === 0 && <p className="py-8 text-center text-muted-foreground">No AI logs found.</p>}
                </div>

                {logs.last_page > 1 && (
                    <div className="flex justify-center gap-2">
                        {logs.current_page > 1 && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => router.get('/admin/ai-logs', { ...filters, page: logs.current_page - 1 })}
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
                                onClick={() => router.get('/admin/ai-logs', { ...filters, page: logs.current_page + 1 })}
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
