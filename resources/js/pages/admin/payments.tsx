import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AdminDashboardLayout from '@/layouts/admin-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface Payment {
    id: number;
    amount: number;
    currency: string;
    status: string;
    type: string;
    paid_at: string | null;
    created_at: string;
    user: { id: number; name: string; email: string } | null;
}

interface Paginated {
    data: Payment[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Stats {
    total_revenue: number;
    total_payments: number;
    completed: number;
    failed: number;
}

interface Filters {
    status?: string;
    type?: string;
    search?: string;
}

interface Props {
    payments: Paginated;
    stats: Stats;
    filters: Filters;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Payments', href: '/admin/payments' },
];

const statusColors: Record<string, string> = {
    completed: 'bg-green-100 text-green-800',
    failed: 'bg-red-100 text-red-800',
    pending: 'bg-yellow-100 text-yellow-800',
};

export default function AdminPayments({ payments, stats, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [type, setType] = useState(filters.type ?? '');

    function applyFilters() {
        router.get('/admin/payments', {
            search: search || undefined,
            status: status || undefined,
            type: type || undefined,
        });
    }

    return (
        <AdminDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin: Payments" />
            <div className="space-y-4 p-4">
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Card>
                        <CardContent className="py-3">
                            <p className="text-xs text-muted-foreground">Total Revenue</p>
                            <p className="text-xl font-bold">${stats.total_revenue.toFixed(2)}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="py-3">
                            <p className="text-xs text-muted-foreground">Total Payments</p>
                            <p className="text-xl font-bold">{stats.total_payments}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="py-3">
                            <p className="text-xs text-muted-foreground">Completed</p>
                            <p className="text-xl font-bold text-green-600">{stats.completed}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="py-3">
                            <p className="text-xs text-muted-foreground">Failed</p>
                            <p className="text-xl font-bold text-red-600">{stats.failed}</p>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-wrap items-end gap-3">
                    <div className="flex flex-col gap-1">
                        <label className="text-xs text-muted-foreground">Search user email</label>
                        <Input
                            placeholder="user@example.com"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-56"
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-xs text-muted-foreground">Status</label>
                        <select value={status} onChange={(e) => setStatus(e.target.value)} className="rounded border px-2 py-1.5 text-sm">
                            <option value="">All</option>
                            <option value="completed">Completed</option>
                            <option value="pending">Pending</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                    <div className="flex flex-col gap-1">
                        <label className="text-xs text-muted-foreground">Type</label>
                        <select value={type} onChange={(e) => setType(e.target.value)} className="rounded border px-2 py-1.5 text-sm">
                            <option value="">All</option>
                            <option value="subscription">Subscription</option>
                            <option value="one_time">One-time</option>
                            <option value="admin_granted">Admin Granted</option>
                        </select>
                    </div>
                    <Button size="sm" onClick={applyFilters}>
                        Filter
                    </Button>
                </div>

                <p className="text-sm text-muted-foreground">{payments.total} total payments</p>

                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b text-left text-muted-foreground">
                                <th className="pb-2 pr-4">User</th>
                                <th className="pb-2 pr-4">Amount</th>
                                <th className="pb-2 pr-4">Type</th>
                                <th className="pb-2 pr-4">Status</th>
                                <th className="pb-2 pr-4">Paid at</th>
                                <th className="pb-2">Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            {payments.data.map((p) => (
                                <tr key={p.id} className="border-b last:border-0">
                                    <td className="py-2 pr-4">
                                        {p.user ? (
                                            <div>
                                                <p className="font-medium">{p.user.name}</p>
                                                <p className="text-xs text-muted-foreground">{p.user.email}</p>
                                            </div>
                                        ) : (
                                            <span className="text-muted-foreground">—</span>
                                        )}
                                    </td>
                                    <td className="py-2 pr-4 font-mono">
                                        {p.amount.toFixed(2)} {p.currency.toUpperCase()}
                                    </td>
                                    <td className="py-2 pr-4">
                                        <Badge variant="outline">{p.type}</Badge>
                                    </td>
                                    <td className="py-2 pr-4">
                                        <span
                                            className={`rounded px-1.5 py-0.5 text-xs font-medium ${statusColors[p.status] ?? 'bg-muted text-muted-foreground'}`}
                                        >
                                            {p.status}
                                        </span>
                                    </td>
                                    <td className="py-2 pr-4 text-xs text-muted-foreground">
                                        {p.paid_at ? new Date(p.paid_at).toLocaleString() : '—'}
                                    </td>
                                    <td className="py-2 text-xs text-muted-foreground">{new Date(p.created_at).toLocaleString()}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {payments.data.length === 0 && <p className="py-8 text-center text-muted-foreground">No payments found.</p>}
                </div>

                {payments.last_page > 1 && (
                    <div className="flex justify-center gap-2">
                        {payments.current_page > 1 && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => router.get('/admin/payments', { ...filters, page: payments.current_page - 1 })}
                            >
                                Previous
                            </Button>
                        )}
                        <span className="flex items-center text-sm">
                            {payments.current_page} / {payments.last_page}
                        </span>
                        {payments.current_page < payments.last_page && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => router.get('/admin/payments', { ...filters, page: payments.current_page + 1 })}
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
