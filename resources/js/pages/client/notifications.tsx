import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import ClientDashboardLayout from '@/layouts/client-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { Bell, Trash2 } from 'lucide-react';

interface Notification {
    id: string;
    data: {
        type: string;
        title: string;
        message: string;
        [key: string]: unknown;
    };
    read_at: string | null;
    created_at: string;
}

interface Paginated {
    data: Notification[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Props {
    notifications: Paginated;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notifications', href: '/notifications' }];

const typeColors: Record<string, string> = {
    usage_limit_warning: 'bg-yellow-100 text-yellow-800',
    usage_limit_exceeded: 'bg-red-100 text-red-800',
    payment_success: 'bg-green-100 text-green-800',
    payment_failed: 'bg-red-100 text-red-800',
    human_handover: 'bg-blue-100 text-blue-800',
    facebook_token_expired: 'bg-orange-100 text-orange-800',
    webhook_failed: 'bg-red-100 text-red-800',
    team_invitation: 'bg-purple-100 text-purple-800',
};

export default function Notifications({ notifications }: Props) {
    async function markRead(id: string) {
        await axios.post(`/notifications/${id}/read`);
        router.reload({ only: ['notifications'] });
    }

    async function markAllRead() {
        await axios.post('/notifications/read-all');
        router.reload({ only: ['notifications'] });
    }

    async function deleteNotification(id: string) {
        await axios.delete(`/notifications/${id}`);
        router.reload({ only: ['notifications'] });
    }

    return (
        <ClientDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Notifications" />
            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">Notifications</h1>
                    {notifications.data.some((n) => !n.read_at) && (
                        <Button variant="outline" size="sm" onClick={markAllRead}>
                            Mark all as read
                        </Button>
                    )}
                </div>

                {notifications.data.length === 0 && (
                    <div className="flex flex-col items-center justify-center py-16 text-muted-foreground">
                        <Bell className="mb-3 size-10 opacity-30" />
                        <p>No notifications yet.</p>
                    </div>
                )}

                <div className="space-y-2">
                    {notifications.data.map((n) => (
                        <Card key={n.id} className={n.read_at ? 'opacity-60' : 'border-primary/30'}>
                            <CardContent className="flex items-start justify-between gap-3 py-3">
                                <div className="flex-1 space-y-1">
                                    <div className="flex items-center gap-2">
                                        {!n.read_at && <span className="size-2 rounded-full bg-primary" />}
                                        <span className="font-medium">{n.data.title}</span>
                                        <span
                                            className={`rounded px-1.5 py-0.5 text-xs font-medium ${typeColors[n.data.type] ?? 'bg-muted text-muted-foreground'}`}
                                        >
                                            {n.data.type.replace(/_/g, ' ')}
                                        </span>
                                    </div>
                                    <p className="text-sm text-muted-foreground">{n.data.message}</p>
                                    <p className="text-xs text-muted-foreground">{new Date(n.created_at).toLocaleString()}</p>
                                </div>
                                <div className="flex gap-2">
                                    {!n.read_at && (
                                        <Button variant="ghost" size="sm" onClick={() => markRead(n.id)}>
                                            Mark read
                                        </Button>
                                    )}
                                    <Button variant="ghost" size="icon" onClick={() => deleteNotification(n.id)}>
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {notifications.last_page > 1 && (
                    <div className="flex justify-center gap-2">
                        {notifications.current_page > 1 && (
                            <Button variant="outline" size="sm" onClick={() => router.get('/notifications', { page: notifications.current_page - 1 })}>
                                Previous
                            </Button>
                        )}
                        <span className="flex items-center text-sm text-muted-foreground">
                            Page {notifications.current_page} of {notifications.last_page}
                        </span>
                        {notifications.current_page < notifications.last_page && (
                            <Button variant="outline" size="sm" onClick={() => router.get('/notifications', { page: notifications.current_page + 1 })}>
                                Next
                            </Button>
                        )}
                    </div>
                )}
            </div>
        </ClientDashboardLayout>
    );
}
