import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AdminDashboardLayout from '@/layouts/admin-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

interface Tenant {
    id: number;
    name: string;
    email: string;
}

interface FacebookPage {
    id: number;
    page_name: string;
    page_id: string;
}

interface WebhookEvent {
    id: number;
    event_type: string;
    status: string;
    external_event_id: string | null;
    error_message: string | null;
    processed_at: string | null;
    created_at: string;
    tenant: Tenant | null;
    facebook_page: FacebookPage | null;
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Props {
    events: Paginator<WebhookEvent>;
    filters: { status?: string; event_type?: string; tenant_id?: string };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin Dashboard', href: '/admin/dashboard' },
    { title: 'Webhook Logs', href: '/admin/webhook-logs' },
];

const STATUS_COLORS: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    received: 'secondary',
    queued: 'outline',
    processing: 'default',
    completed: 'default',
    failed: 'destructive',
    ignored: 'secondary',
};

function filter(key: string, value: string) {
    router.get('/admin/webhook-logs', { [key]: value === 'all' ? undefined : value || undefined }, { preserveState: true, replace: true });
}

export default function WebhookLogsIndex({ events, filters }: Props) {
    return (
        <AdminDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Webhook Logs" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-normal">Webhook Logs</h1>
                        <p className="text-muted-foreground text-sm">Incoming Facebook webhook events.</p>
                    </div>
                </div>

                <div className="flex flex-wrap gap-2">
                    <Select value={filters.status ?? 'all'} onValueChange={(v) => filter('status', v)}>
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="All Statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Statuses</SelectItem>
                            <SelectItem value="received">Received</SelectItem>
                            <SelectItem value="queued">Queued</SelectItem>
                            <SelectItem value="processing">Processing</SelectItem>
                            <SelectItem value="completed">Completed</SelectItem>
                            <SelectItem value="failed">Failed</SelectItem>
                            <SelectItem value="ignored">Ignored</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select value={filters.event_type ?? 'all'} onValueChange={(v) => filter('event_type', v)}>
                        <SelectTrigger className="w-44">
                            <SelectValue placeholder="All Event Types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Event Types</SelectItem>
                            <SelectItem value="message">Message</SelectItem>
                            <SelectItem value="comment_feed">Comment</SelectItem>
                            <SelectItem value="unknown">Unknown</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Events ({events.total})</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        {events.data.length === 0 ? (
                            <p className="px-6 py-8 text-sm text-muted-foreground">No webhook events found.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b bg-muted/40">
                                        <tr>
                                            <th className="px-4 py-3 text-left font-medium">ID</th>
                                            <th className="px-4 py-3 text-left font-medium">Type</th>
                                            <th className="px-4 py-3 text-left font-medium">Status</th>
                                            <th className="px-4 py-3 text-left font-medium">Tenant</th>
                                            <th className="px-4 py-3 text-left font-medium">Page</th>
                                            <th className="px-4 py-3 text-left font-medium">Received</th>
                                            <th className="px-4 py-3 text-left font-medium"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {events.data.map((event) => (
                                            <tr key={event.id} className="hover:bg-muted/20">
                                                <td className="px-4 py-3 font-mono text-xs">{event.id}</td>
                                                <td className="px-4 py-3">{event.event_type.replaceAll('_', ' ')}</td>
                                                <td className="px-4 py-3">
                                                    <Badge variant={STATUS_COLORS[event.status] ?? 'secondary'}>
                                                        {event.status}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3">
                                                    {event.tenant ? (
                                                        <Link href={`/admin/users/${event.tenant.id}`} className="hover:underline">
                                                            {event.tenant.name}
                                                        </Link>
                                                    ) : (
                                                        <span className="text-muted-foreground">—</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground text-xs">
                                                    {event.facebook_page?.page_name ?? '—'}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground text-xs">
                                                    {new Date(event.created_at).toLocaleString()}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Button asChild variant="outline" size="sm">
                                                        <Link href={`/admin/webhook-logs/${event.id}`}>View</Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {events.last_page > 1 && (
                    <div className="flex justify-end gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={events.current_page === 1}
                            onClick={() => router.get('/admin/webhook-logs', { ...filters, page: events.current_page - 1 })}
                        >
                            Previous
                        </Button>
                        <span className="flex items-center text-sm text-muted-foreground">
                            {events.current_page} / {events.last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={events.current_page === events.last_page}
                            onClick={() => router.get('/admin/webhook-logs', { ...filters, page: events.current_page + 1 })}
                        >
                            Next
                        </Button>
                    </div>
                )}
            </div>
        </AdminDashboardLayout>
    );
}
