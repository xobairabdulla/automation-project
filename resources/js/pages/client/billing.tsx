import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ClientDashboardLayout from '@/layouts/client-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { Brain, ImageIcon, MessageSquare, Package, ShoppingCart, Zap } from 'lucide-react';

interface UsageLimit {
    message_reply_limit: number;
    message_reply_used: number;
    comment_reply_limit: number;
    comment_reply_used: number;
    ai_reply_limit: number;
    ai_reply_used: number;
    image_send_limit: number;
    image_send_used: number;
}

interface UsagePackage {
    id: number;
    name: string;
    description: string | null;
    message_extra: number;
    comment_extra: number;
    ai_extra: number;
    image_send_extra: number;
    price: string;
    currency: string;
}

interface Payment {
    id: number;
    amount: string;
    currency: string;
    status: string;
    type: string;
    paid_at: string | null;
    created_at: string;
}

interface BillingProps {
    usageLimit: UsageLimit;
    packages: UsagePackage[];
    payments: Payment[];
}

interface SharedProps {
    flash?: { success?: string; error?: string };
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Billing', href: '/billing' }];

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'completed') return 'default';
    if (status === 'pending') return 'secondary';
    if (status === 'failed') return 'destructive';
    return 'outline';
}

function paymentTypeLabel(type: string): string {
    const labels: Record<string, string> = {
        limit_extension: 'Credit Purchase',
        subscription: 'Subscription',
        admin_granted: 'Admin Granted',
    };
    return labels[type] ?? type;
}

function CreditBar({ used, limit, label, icon: Icon }: { used: number; limit: number; label: string; icon: React.ElementType }) {
    const remaining = Math.max(0, limit - used);
    const pct = limit > 0 ? Math.min(100, (used / limit) * 100) : 0;
    const isLow = limit > 0 && pct >= 80;
    const isEmpty = limit > 0 && remaining === 0;

    return (
        <div className="rounded-lg border p-4">
            <div className="flex items-center justify-between mb-2">
                <div className="flex items-center gap-2 text-sm font-medium">
                    <Icon size={15} className="text-muted-foreground" />
                    {label}
                </div>
                <span className={`text-sm font-semibold ${isEmpty ? 'text-red-500' : isLow ? 'text-amber-500' : 'text-green-600'}`}>
                    {limit === 0 ? <span className="text-muted-foreground text-xs">No credits</span> : `${remaining.toLocaleString()} left`}
                </span>
            </div>
            {limit > 0 ? (
                <>
                    <div className="h-2 rounded-full bg-muted overflow-hidden">
                        <div
                            className={`h-full rounded-full transition-all ${isEmpty ? 'bg-red-500' : isLow ? 'bg-amber-400' : 'bg-green-500'}`}
                            style={{ width: `${100 - pct}%` }}
                        />
                    </div>
                    <div className="flex justify-between text-xs text-muted-foreground mt-1">
                        <span>{used.toLocaleString()} used</span>
                        <span>{limit.toLocaleString()} total</span>
                    </div>
                </>
            ) : (
                <p className="text-xs text-muted-foreground mt-1">Buy a package below to get credits.</p>
            )}
        </div>
    );
}

export default function Billing({ usageLimit, packages, payments }: BillingProps) {
    const { flash } = usePage<SharedProps>().props;

    function buyPackage(pkg: UsagePackage) {
        router.post(`/billing/extend-checkout/${pkg.id}`);
    }

    return (
        <ClientDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Billing & Credits" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-normal">Billing & Credits</h1>
                    <p className="text-muted-foreground text-sm">Buy usage credits and track your consumption.</p>
                </div>

                {flash?.success && <Alert><AlertDescription>{flash.success}</AlertDescription></Alert>}

                {/* Credit overview */}
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base">Current Credits</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <CreditBar
                                used={usageLimit.message_reply_used}
                                limit={usageLimit.message_reply_limit}
                                label="Message Replies"
                                icon={MessageSquare}
                            />
                            <CreditBar
                                used={usageLimit.comment_reply_used}
                                limit={usageLimit.comment_reply_limit}
                                label="Comment Replies"
                                icon={Zap}
                            />
                            <CreditBar
                                used={usageLimit.ai_reply_used}
                                limit={usageLimit.ai_reply_limit}
                                label="AI Replies"
                                icon={Brain}
                            />
                            <CreditBar
                                used={usageLimit.image_send_used}
                                limit={usageLimit.image_send_limit}
                                label="Image Sends"
                                icon={ImageIcon}
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* Buy credits */}
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <ShoppingCart size={16} />
                            Buy Credits
                        </CardTitle>
                        <p className="text-muted-foreground text-sm">
                            Credits never expire. Buy more anytime when you run out.
                        </p>
                    </CardHeader>
                    <CardContent>
                        {packages.length === 0 ? (
                            <p className="text-muted-foreground text-sm py-4 text-center">
                                No packages available yet. Contact support.
                            </p>
                        ) : (
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {packages.map((pkg) => (
                                    <div
                                        key={pkg.id}
                                        className="rounded-xl border p-5 flex flex-col gap-3 hover:border-violet-400 transition-colors"
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div>
                                                <p className="font-semibold text-sm">{pkg.name}</p>
                                                {pkg.description && (
                                                    <p className="text-xs text-muted-foreground mt-0.5">{pkg.description}</p>
                                                )}
                                            </div>
                                            <Package size={16} className="text-muted-foreground shrink-0 mt-0.5" />
                                        </div>

                                        <div className="space-y-1">
                                            {pkg.message_extra > 0 && (
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-muted-foreground flex items-center gap-1"><MessageSquare size={11} />Messages</span>
                                                    <span className="font-medium text-green-600">+{pkg.message_extra.toLocaleString()}</span>
                                                </div>
                                            )}
                                            {pkg.comment_extra > 0 && (
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-muted-foreground flex items-center gap-1"><Zap size={11} />Comments</span>
                                                    <span className="font-medium text-green-600">+{pkg.comment_extra.toLocaleString()}</span>
                                                </div>
                                            )}
                                            {pkg.ai_extra > 0 && (
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-muted-foreground flex items-center gap-1"><Brain size={11} />AI Replies</span>
                                                    <span className="font-medium text-green-600">+{pkg.ai_extra.toLocaleString()}</span>
                                                </div>
                                            )}
                                            {pkg.image_send_extra > 0 && (
                                                <div className="flex items-center justify-between text-sm">
                                                    <span className="text-muted-foreground flex items-center gap-1"><ImageIcon size={11} />Image Sends</span>
                                                    <span className="font-medium text-green-600">+{pkg.image_send_extra.toLocaleString()}</span>
                                                </div>
                                            )}
                                        </div>

                                        <div className="flex items-center justify-between mt-auto pt-2 border-t">
                                            <div className="text-xl font-bold">
                                                {pkg.currency} {parseFloat(pkg.price).toLocaleString()}
                                            </div>
                                            <Button size="sm" onClick={() => buyPackage(pkg)}>
                                                Buy Now
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Payment history */}
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-base">Payment History</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/50">
                                <tr>
                                    <th className="px-4 py-2 text-left font-medium">Package</th>
                                    <th className="px-4 py-2 text-left font-medium">Amount</th>
                                    <th className="px-4 py-2 text-left font-medium">Status</th>
                                    <th className="px-4 py-2 text-left font-medium">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                {payments.length === 0 ? (
                                    <tr>
                                        <td className="text-muted-foreground px-4 py-6 text-center" colSpan={4}>
                                            No payments yet.
                                        </td>
                                    </tr>
                                ) : (
                                    payments.map((p) => (
                                        <tr key={p.id} className="border-b last:border-0">
                                            <td className="px-4 py-2">{paymentTypeLabel(p.type)}</td>
                                            <td className="px-4 py-2">
                                                {p.type === 'admin_granted'
                                                    ? <span className="text-muted-foreground">Free</span>
                                                    : `${p.currency} ${parseFloat(p.amount).toLocaleString()}`}
                                            </td>
                                            <td className="px-4 py-2">
                                                <Badge variant={statusVariant(p.status)}>{p.status}</Badge>
                                            </td>
                                            <td className="px-4 py-2 text-muted-foreground">
                                                {p.paid_at
                                                    ? new Date(p.paid_at).toLocaleDateString()
                                                    : new Date(p.created_at).toLocaleDateString()}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </ClientDashboardLayout>
    );
}
