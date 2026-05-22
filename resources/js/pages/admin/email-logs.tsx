import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AdminDashboardLayout from '@/layouts/admin-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface EmailLog {
    id: number;
    tenant_id: number | null;
    user_id: number | null;
    to_email: string;
    subject: string;
    status: string;
    error_message: string | null;
    created_at: string;
}

interface Paginated {
    data: EmailLog[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Filters {
    status?: string;
    search?: string;
}

interface Props {
    logs: Paginated;
    filters: Filters;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Email Logs', href: '/admin/email-logs' },
];

export default function EmailLogs({ logs, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    function applyFilters() {
        router.get('/admin/email-logs', { search: search || undefined, status: status || undefined });
    }

    return (
        <AdminDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Email Logs" />
            <div className="space-y-4 p-4">
                <div className="flex flex-wrap items-end gap-3">
                    <div className="flex flex-col gap-1">
                        <label className="text-xs text-muted-foreground">Search</label>
                        <Input
                            placeholder="Email or subject..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-56"
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-xs text-muted-foreground">Status</label>
                        <select
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                            className="rounded border px-2 py-1.5 text-sm"
                        >
                            <option value="">All</option>
                            <option value="sent">Sent</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                    <Button size="sm" onClick={applyFilters}>
                        Filter
                    </Button>
                </div>

                <p className="text-sm text-muted-foreground">{logs.total} total emails</p>

                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="pb-2 pr-4">To</th>
                                <th className="pb-2 pr-4">Subject</th>
                                <th className="pb-2 pr-4">Status</th>
                                <th className="pb-2 pr-4">Sent at</th>
                                <th className="pb-2">Error</th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.map((log) => (
                                <tr key={log.id} className="border-b last:border-0">
                                    <td className="py-2 pr-4 font-mono text-xs">{log.to_email}</td>
                                    <td className="py-2 pr-4">{log.subject}</td>
                                    <td className="py-2 pr-4">
                                        <Badge variant={log.status === 'sent' ? 'default' : 'destructive'}>{log.status}</Badge>
                                    </td>
                                    <td className="py-2 pr-4 text-xs text-muted-foreground">{new Date(log.created_at).toLocaleString()}</td>
                                    <td className="py-2 text-xs text-red-500">{log.error_message ?? '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {logs.data.length === 0 && <p className="py-8 text-center text-muted-foreground">No email logs found.</p>}
                </div>

                {logs.last_page > 1 && (
                    <div className="flex justify-center gap-2">
                        {logs.current_page > 1 && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => router.get('/admin/email-logs', { ...filters, page: logs.current_page - 1 })}
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
                                onClick={() => router.get('/admin/email-logs', { ...filters, page: logs.current_page + 1 })}
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
