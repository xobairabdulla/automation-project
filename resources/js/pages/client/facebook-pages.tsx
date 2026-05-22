import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ClientDashboardLayout from '@/layouts/client-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';

interface FacebookPageItem {
    id: number;
    page_id: string;
    page_name: string;
    is_connected: boolean;
    automation_enabled: boolean;
    message_automation_enabled: boolean;
    comment_automation_enabled: boolean;
    token_expired: boolean;
    last_webhook_received_at: string | null;
    facebook_account_name: string;
}

interface PageProps {
    pages: FacebookPageItem[];
    connectUrl: string;
}

interface SharedProps {
    flash?: { success?: string; error?: string };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Facebook Pages', href: '/facebook/pages' },
];

function post(url: string) {
    router.post(url);
}

export default function FacebookPages({ pages, connectUrl }: PageProps) {
    const { flash } = usePage<SharedProps>().props;
    const connected = pages.filter((p) => p.is_connected);
    const available = pages.filter((p) => !p.is_connected);

    return (
        <ClientDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Facebook Pages" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-normal">Facebook Pages</h1>
                        <p className="text-muted-foreground text-sm">Manage connected pages and automation settings.</p>
                    </div>
                    <Button asChild>
                        <a href={connectUrl}>Connect Facebook Account</a>
                    </Button>
                </div>

                {flash?.success && (
                    <Alert>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}
                {flash?.error && (
                    <Alert variant="destructive">
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                )}

                {pages.length === 0 && (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-4 py-12 text-center">
                            <p className="text-muted-foreground">No Facebook pages found.</p>
                            <p className="text-muted-foreground text-sm">Click "Connect Facebook Account" to link your pages.</p>
                            <Button asChild>
                                <a href={connectUrl}>Connect Now</a>
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {connected.length > 0 && (
                    <div className="space-y-3">
                        <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Connected Pages</h2>
                        {connected.map((page) => (
                            <PageCard key={page.id} page={page} />
                        ))}
                    </div>
                )}

                {available.length > 0 && (
                    <div className="space-y-3">
                        <h2 className="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Available Pages</h2>
                        {available.map((page) => (
                            <PageCard key={page.id} page={page} />
                        ))}
                    </div>
                )}
            </div>
        </ClientDashboardLayout>
    );
}

function PageCard({ page }: { page: FacebookPageItem }) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3">
                <div className="flex items-center gap-3">
                    <div>
                        <CardTitle className="text-base">{page.page_name}</CardTitle>
                        <p className="text-muted-foreground text-xs">
                            ID: {page.page_id} · Account: {page.facebook_account_name}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    {page.token_expired && (
                        <Badge variant="destructive">Token Expired</Badge>
                    )}
                    {page.is_connected ? (
                        <Badge>Connected</Badge>
                    ) : (
                        <Badge variant="outline">Not Connected</Badge>
                    )}
                </div>
            </CardHeader>

            <CardContent className="space-y-3">
                {page.is_connected && (
                    <div className="flex flex-wrap gap-2 text-sm">
                        <AutomationBadge
                            label="Automation"
                            active={page.automation_enabled}
                            onEnable={() => post(`/facebook/pages/${page.id}/enable-automation`)}
                            onDisable={() => post(`/facebook/pages/${page.id}/disable-automation`)}
                        />
                        <AutomationBadge
                            label="Messages"
                            active={page.message_automation_enabled}
                            onEnable={() => router.post(`/facebook/pages/${page.id}/enable-automation`, { message_automation: true })}
                            onDisable={() => router.post(`/facebook/pages/${page.id}/enable-automation`, { message_automation: false })}
                        />
                        <AutomationBadge
                            label="Comments"
                            active={page.comment_automation_enabled}
                            onEnable={() => router.post(`/facebook/pages/${page.id}/enable-automation`, { comment_automation: true })}
                            onDisable={() => router.post(`/facebook/pages/${page.id}/enable-automation`, { comment_automation: false })}
                        />
                    </div>
                )}

                {page.last_webhook_received_at && (
                    <p className="text-xs text-muted-foreground">
                        Last webhook: {new Date(page.last_webhook_received_at).toLocaleString()}
                    </p>
                )}

                <div className="flex gap-2 pt-1">
                    {page.is_connected ? (
                        <Button
                            variant="destructive"
                            size="sm"
                            onClick={() => {
                                if (confirm(`Disconnect "${page.page_name}"?`)) {
                                    post(`/facebook/pages/${page.id}/disconnect`);
                                }
                            }}
                        >
                            Disconnect
                        </Button>
                    ) : (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => post(`/facebook/pages/${page.id}/connect`)}
                        >
                            Connect
                        </Button>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function AutomationBadge({
    label,
    active,
    onEnable,
    onDisable,
}: {
    label: string;
    active: boolean;
    onEnable: () => void;
    onDisable: () => void;
}) {
    return (
        <button
            onClick={active ? onDisable : onEnable}
            className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                active
                    ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200 dark:bg-emerald-900 dark:text-emerald-200'
                    : 'bg-muted text-muted-foreground hover:bg-muted/80'
            }`}
        >
            {label}: {active ? 'ON' : 'OFF'}
        </button>
    );
}
