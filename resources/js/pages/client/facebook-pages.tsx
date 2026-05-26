import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import ClientDashboardLayout from '@/layouts/client-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { AlertTriangle, Bot, CheckCircle2, Facebook, MessageCircle, ThumbsUp, XCircle, Zap } from 'lucide-react';

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
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">

                {/* Header */}
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Facebook Pages</h1>
                        <p className="text-muted-foreground text-sm mt-0.5">Manage your connected pages and automation settings.</p>
                    </div>
                    <Button asChild className="bg-[#1877F2] hover:bg-[#1464d0] text-white shadow-md">
                        <a href={connectUrl}>
                            <Facebook className="mr-2 h-4 w-4" />
                            Connect Facebook Account
                        </a>
                    </Button>
                </div>

                {/* Flash messages */}
                {flash?.success && (
                    <Alert className="border-emerald-200 bg-emerald-50 text-emerald-800">
                        <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}
                {flash?.error && (
                    <Alert variant="destructive">
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                )}

                {/* Empty state */}
                {pages.length === 0 && (
                    <Card className="border-dashed border-2">
                        <CardContent className="flex flex-col items-center gap-4 py-16 text-center">
                            <div className="rounded-full bg-blue-100 p-5">
                                <Facebook className="h-10 w-10 text-[#1877F2]" />
                            </div>
                            <div>
                                <p className="font-semibold text-lg">No Facebook pages yet</p>
                                <p className="text-muted-foreground text-sm mt-1">Connect your Facebook account to start automating replies.</p>
                            </div>
                            <Button asChild className="bg-[#1877F2] hover:bg-[#1464d0] text-white">
                                <a href={connectUrl}>Connect Now</a>
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {/* Connected Pages */}
                {connected.length > 0 && (
                    <div className="space-y-4">
                        <div className="flex items-center gap-2">
                            <div className="h-px flex-1 bg-border" />
                            <span className="text-xs font-semibold uppercase tracking-widest text-emerald-600 px-2">
                                ✓ Connected ({connected.length})
                            </span>
                            <div className="h-px flex-1 bg-border" />
                        </div>
                        {connected.map((page) => (
                            <PageCard key={page.id} page={page} />
                        ))}
                    </div>
                )}

                {/* Available Pages */}
                {available.length > 0 && (
                    <div className="space-y-4">
                        <div className="flex items-center gap-2">
                            <div className="h-px flex-1 bg-border" />
                            <span className="text-xs font-semibold uppercase tracking-widest text-muted-foreground px-2">
                                Available ({available.length})
                            </span>
                            <div className="h-px flex-1 bg-border" />
                        </div>
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
    const initials = page.page_name
        .split(' ')
        .slice(0, 2)
        .map((w) => w[0])
        .join('')
        .toUpperCase();

    return (
        <Card className={`overflow-hidden shadow-sm transition-shadow hover:shadow-md ${page.is_connected ? 'border-emerald-200' : 'border-dashed'}`}>
            {/* Colored top bar */}
            <div className={`h-1 w-full ${page.is_connected ? 'bg-gradient-to-r from-emerald-400 to-blue-500' : 'bg-muted'}`} />

            <CardHeader className="pb-3">
                <div className="flex items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        {/* Avatar */}
                        <div className={`flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl text-white font-bold text-sm shadow-sm ${page.is_connected ? 'bg-[#1877F2]' : 'bg-muted text-muted-foreground'}`}>
                            {initials || <Facebook className="h-5 w-5" />}
                        </div>
                        <div>
                            <p className="font-semibold text-base leading-tight">{page.page_name}</p>
                            <p className="text-xs text-muted-foreground mt-0.5">
                                ID: {page.page_id}
                                {page.facebook_account_name && <> · {page.facebook_account_name}</>}
                            </p>
                        </div>
                    </div>

                    {/* Status badges */}
                    <div className="flex items-center gap-2 flex-shrink-0">
                        {page.token_expired && (
                            <Badge variant="destructive" className="gap-1">
                                <AlertTriangle className="h-3 w-3" />
                                Token Expired
                            </Badge>
                        )}
                        {page.is_connected ? (
                            <Badge className="bg-emerald-100 text-emerald-700 hover:bg-emerald-100 gap-1">
                                <CheckCircle2 className="h-3 w-3" />
                                Connected
                            </Badge>
                        ) : (
                            <Badge variant="outline" className="gap-1 text-muted-foreground">
                                <XCircle className="h-3 w-3" />
                                Not Connected
                            </Badge>
                        )}
                    </div>
                </div>
            </CardHeader>

            <CardContent className="space-y-4">
                {page.is_connected && (
                    <>
                        {/* Automation toggles */}
                        <div>
                            <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-2">Automation</p>
                            <div className="flex flex-wrap gap-2">
                                <AutomationToggle
                                    label="All Automation"
                                    icon={<Zap className="h-3 w-3" />}
                                    active={page.automation_enabled}
                                    onEnable={() => post(`/facebook/pages/${page.id}/enable-automation`)}
                                    onDisable={() => post(`/facebook/pages/${page.id}/disable-automation`)}
                                    activeColor="bg-amber-100 text-amber-800 border-amber-300 hover:bg-amber-200"
                                />
                                <AutomationToggle
                                    label="Messages"
                                    icon={<MessageCircle className="h-3 w-3" />}
                                    active={page.message_automation_enabled}
                                    onEnable={() => router.post(`/facebook/pages/${page.id}/enable-automation`, { message_automation: true })}
                                    onDisable={() => router.post(`/facebook/pages/${page.id}/enable-automation`, { message_automation: false })}
                                    activeColor="bg-blue-100 text-blue-800 border-blue-300 hover:bg-blue-200"
                                />
                                <AutomationToggle
                                    label="Comments"
                                    icon={<ThumbsUp className="h-3 w-3" />}
                                    active={page.comment_automation_enabled}
                                    onEnable={() => router.post(`/facebook/pages/${page.id}/enable-automation`, { comment_automation: true })}
                                    onDisable={() => router.post(`/facebook/pages/${page.id}/enable-automation`, { comment_automation: false })}
                                    activeColor="bg-violet-100 text-violet-800 border-violet-300 hover:bg-violet-200"
                                />
                                {(page.message_automation_enabled || page.comment_automation_enabled) && (
                                    <span className="inline-flex items-center gap-1 rounded-full border border-emerald-300 bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">
                                        <Bot className="h-3 w-3" />
                                        Auto-replies active
                                    </span>
                                )}
                            </div>
                        </div>

                        {page.last_webhook_received_at && (
                            <p className="text-xs text-muted-foreground">
                                Last webhook received: {new Date(page.last_webhook_received_at).toLocaleString()}
                            </p>
                        )}
                    </>
                )}

                {/* Actions */}
                <div className="flex gap-2 pt-1">
                    {page.is_connected ? (
                        <Button
                            variant="destructive"
                            size="sm"
                            className="gap-1"
                            onClick={() => {
                                if (confirm(`Disconnect "${page.page_name}"? Automation will stop.`)) {
                                    post(`/facebook/pages/${page.id}/disconnect`);
                                }
                            }}
                        >
                            <XCircle className="h-3.5 w-3.5" />
                            Disconnect
                        </Button>
                    ) : (
                        <Button
                            size="sm"
                            className="bg-[#1877F2] hover:bg-[#1464d0] text-white gap-1"
                            onClick={() => post(`/facebook/pages/${page.id}/connect`)}
                        >
                            <CheckCircle2 className="h-3.5 w-3.5" />
                            Connect
                        </Button>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function AutomationToggle({
    label,
    icon,
    active,
    onEnable,
    onDisable,
    activeColor,
}: {
    label: string;
    icon: React.ReactNode;
    active: boolean;
    onEnable: () => void;
    onDisable: () => void;
    activeColor: string;
}) {
    return (
        <button
            onClick={active ? onDisable : onEnable}
            className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition-all ${
                active
                    ? activeColor
                    : 'border-border bg-muted/50 text-muted-foreground hover:bg-muted'
            }`}
        >
            {icon}
            {label}: <span className="font-bold">{active ? 'ON' : 'OFF'}</span>
        </button>
    );
}
