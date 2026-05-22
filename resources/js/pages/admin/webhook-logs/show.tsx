import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminDashboardLayout from '@/layouts/admin-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';

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

interface EventLog {
    id: number;
    status: string;
    message: string;
    metadata_json: Record<string, unknown> | null;
    created_at: string;
}

interface WebhookEvent {
    id: number;
    event_type: string;
    status: string;
    external_event_id: string | null;
    payload_json: Record<string, unknown>;
    error_message: string | null;
    processed_at: string | null;
    created_at: string;
    tenant: Tenant | null;
    facebook_page: FacebookPage | null;
    logs: EventLog[];
}

interface Props {
    event: WebhookEvent;
}

interface SharedProps {
    flash?: { success?: string; error?: string };
}

const STATUS_COLORS: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    received: 'secondary',
    queued: 'outline',
    processing: 'default',
    completed: 'default',
    failed: 'destructive',
    ignored: 'secondary',
};

export default function WebhookEventShow({ event }: Props) {
    const { flash } = usePage<SharedProps>().props;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Admin Dashboard', href: '/admin/dashboard' },
        { title: 'Webhook Logs', href: '/admin/webhook-logs' },
        { title: `Event #${event.id}`, href: `/admin/webhook-logs/${event.id}` },
    ];

    return (
        <AdminDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title={`Webhook Event #${event.id}`} />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-normal">Event #{event.id}</h1>
                        <p className="text-muted-foreground text-sm">{event.event_type.replaceAll('_', ' ')} · {new Date(event.created_at).toLocaleString()}</p>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild variant="outline" size="sm">
                            <Link href="/admin/webhook-logs">Back</Link>
                        </Button>
                        {event.status === 'failed' && (
                            <Button
                                size="sm"
                                onClick={() => {
                                    if (confirm('Retry this event?')) {
                                        router.post(`/admin/webhook-logs/${event.id}/retry`);
                                    }
                                }}
                            >
                                Retry
                            </Button>
                        )}
                    </div>
                </div>

                {flash?.success && (
                    <Alert>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <Row label="Status">
                                <Badge variant={STATUS_COLORS[event.status] ?? 'secondary'}>{event.status}</Badge>
                            </Row>
                            <Row label="Event Type">{event.event_type}</Row>
                            <Row label="External ID">{event.external_event_id ?? '—'}</Row>
                            <Row label="Tenant">
                                {event.tenant ? (
                                    <Link href={`/admin/users/${event.tenant.id}`} className="hover:underline">
                                        {event.tenant.name} ({event.tenant.email})
                                    </Link>
                                ) : '—'}
                            </Row>
                            <Row label="Page">{event.facebook_page ? `${event.facebook_page.page_name} (${event.facebook_page.page_id})` : '—'}</Row>
                            <Row label="Processed At">{event.processed_at ? new Date(event.processed_at).toLocaleString() : '—'}</Row>
                            {event.error_message && (
                                <Row label="Error">
                                    <span className="text-destructive">{event.error_message}</span>
                                </Row>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Processing Log</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            {event.logs.length === 0 ? (
                                <p className="px-6 py-4 text-sm text-muted-foreground">No log entries.</p>
                            ) : (
                                <ul className="divide-y">
                                    {event.logs.map((log) => (
                                        <li key={log.id} className="px-6 py-3 text-sm">
                                            <div className="flex items-center justify-between gap-2">
                                                <Badge variant={STATUS_COLORS[log.status] ?? 'secondary'} className="shrink-0">
                                                    {log.status}
                                                </Badge>
                                                <span className="text-muted-foreground text-xs">{new Date(log.created_at).toLocaleString()}</span>
                                            </div>
                                            <p className="mt-1 text-muted-foreground">{log.message}</p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Raw Payload</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <pre className="overflow-x-auto rounded-md bg-muted p-4 text-xs">
                            {JSON.stringify(event.payload_json, null, 2)}
                        </pre>
                    </CardContent>
                </Card>
            </div>
        </AdminDashboardLayout>
    );
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex gap-2">
            <span className="w-28 shrink-0 text-muted-foreground">{label}</span>
            <span>{children}</span>
        </div>
    );
}
