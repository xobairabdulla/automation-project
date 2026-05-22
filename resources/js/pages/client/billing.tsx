import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import ClientDashboardLayout from '@/layouts/client-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { CreditCard, Package, Zap } from 'lucide-react';
import { useState } from 'react';

interface Plan {
    id: number;
    name: string;
    description: string | null;
    monthly_price: string;
    yearly_price: string;
    message_reply_limit: number;
    comment_reply_limit: number;
    ai_reply_limit: number;
    stripe_monthly_price_id: string | null;
    stripe_yearly_price_id: string | null;
}

interface Subscription {
    id: number;
    status: string;
    billing_cycle: string;
    starts_at: string;
    ends_at: string | null;
    plan: Plan;
}

interface UsageLimit {
    message_reply_limit: number;
    message_reply_used: number;
    comment_reply_limit: number;
    comment_reply_used: number;
    ai_reply_limit: number;
    ai_reply_used: number;
    reset_at: string;
}

interface ExtensionPackage {
    id: number;
    name: string;
    message_extra: number;
    comment_extra: number;
    ai_extra: number;
    price: string;
    currency: string;
    stripe_price_id: string | null;
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
    subscription: Subscription;
    usageLimit: UsageLimit;
    plans: Plan[];
    packages: ExtensionPackage[];
    payments: Payment[];
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
        subscription: 'Subscription',
        limit_extension: 'Limit Extension',
        admin_granted: 'Admin Granted',
    };
    return labels[type] ?? type;
}

export default function Billing({ subscription, usageLimit, plans, packages, payments }: BillingProps) {
    const [upgradeOpen, setUpgradeOpen] = useState(false);
    const [selectedPlan, setSelectedPlan] = useState<Plan | null>(null);

    const upgradeForm = useForm({ billing_cycle: 'monthly' });

    function openUpgrade(plan: Plan) {
        setSelectedPlan(plan);
        setUpgradeOpen(true);
    }

    function submitUpgrade(e: React.FormEvent) {
        e.preventDefault();
        if (!selectedPlan) return;
        upgradeForm.post(`/billing/checkout/${selectedPlan.id}`, {
            onSuccess: () => setUpgradeOpen(false),
        });
    }

    function buyExtension(pkg: ExtensionPackage) {
        router.post(`/billing/extend-checkout/${pkg.id}`);
    }

    const currentPlanId = subscription.plan.id;

    return (
        <ClientDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Billing" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-normal">Billing</h1>
                    <p className="text-muted-foreground text-sm">Manage your subscription and payment history.</p>
                </div>

                {/* Current subscription */}
                <Card>
                    <CardHeader className="flex flex-row items-center gap-3 space-y-0">
                        <CreditCard className="text-muted-foreground h-5 w-5" />
                        <div className="flex-1">
                            <CardTitle>{subscription.plan.name}</CardTitle>
                            <p className="text-muted-foreground text-sm">{subscription.plan.description}</p>
                        </div>
                        <Badge variant={subscription.status === 'active' ? 'default' : 'secondary'}>{subscription.status}</Badge>
                    </CardHeader>
                    <CardContent className="grid gap-3 text-sm md:grid-cols-3">
                        <div>
                            <div className="text-muted-foreground">Billing cycle</div>
                            <div className="font-medium capitalize">{subscription.billing_cycle}</div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">Price</div>
                            <div className="font-medium">
                                ${subscription.billing_cycle === 'yearly' ? subscription.plan.yearly_price : subscription.plan.monthly_price} /{' '}
                                {subscription.billing_cycle === 'yearly' ? 'year' : 'month'}
                            </div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">Renews</div>
                            <div className="font-medium">{subscription.ends_at ? new Date(subscription.ends_at).toLocaleDateString() : '—'}</div>
                        </div>
                    </CardContent>
                </Card>

                {/* Plan upgrade */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Zap className="h-5 w-5" />
                            Available Plans
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-3">
                            {plans.map((plan) => (
                                <div
                                    key={plan.id}
                                    className={`rounded-lg border p-4 ${plan.id === currentPlanId ? 'border-primary bg-primary/5' : ''}`}
                                >
                                    <div className="mb-3 flex items-center justify-between">
                                        <h3 className="font-semibold">{plan.name}</h3>
                                        {plan.id === currentPlanId && (
                                            <Badge variant="default" className="text-xs">
                                                Current
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="mb-3 space-y-1 text-sm">
                                        <div className="text-2xl font-bold">${plan.monthly_price}<span className="text-muted-foreground text-sm font-normal">/mo</span></div>
                                        <div className="text-muted-foreground text-xs">${plan.yearly_price}/yr</div>
                                    </div>
                                    <ul className="mb-4 space-y-1 text-xs text-muted-foreground">
                                        <li>{plan.message_reply_limit.toLocaleString()} messages/mo</li>
                                        <li>{plan.comment_reply_limit.toLocaleString()} comments/mo</li>
                                        <li>{plan.ai_reply_limit.toLocaleString()} AI replies/mo</li>
                                    </ul>
                                    <Button
                                        size="sm"
                                        variant={plan.id === currentPlanId ? 'outline' : 'default'}
                                        className="w-full"
                                        disabled={plan.id === currentPlanId || !plan.stripe_monthly_price_id}
                                        onClick={() => openUpgrade(plan)}
                                    >
                                        {plan.id === currentPlanId ? 'Current Plan' : 'Upgrade'}
                                    </Button>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Limit extension packages */}
                {packages.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Package className="h-5 w-5" />
                                Limit Extension Packages
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 md:grid-cols-3">
                                {packages.map((pkg) => (
                                    <div key={pkg.id} className="rounded-lg border p-4">
                                        <h3 className="mb-2 font-semibold">{pkg.name}</h3>
                                        <ul className="mb-3 space-y-1 text-xs text-muted-foreground">
                                            {pkg.message_extra > 0 && <li>+{pkg.message_extra.toLocaleString()} message replies</li>}
                                            {pkg.comment_extra > 0 && <li>+{pkg.comment_extra.toLocaleString()} comment replies</li>}
                                            {pkg.ai_extra > 0 && <li>+{pkg.ai_extra.toLocaleString()} AI replies</li>}
                                        </ul>
                                        <div className="mb-3 text-lg font-bold">
                                            ${pkg.price}{' '}
                                            <span className="text-muted-foreground text-sm font-normal">{pkg.currency}</span>
                                        </div>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            className="w-full"
                                            disabled={!pkg.stripe_price_id}
                                            onClick={() => buyExtension(pkg)}
                                        >
                                            Buy
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Payment history */}
                <Card>
                    <CardHeader>
                        <CardTitle>Payment History</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/50">
                                <tr>
                                    <th className="px-4 py-2 text-left font-medium">Type</th>
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
                                                {p.type === 'admin_granted' ? (
                                                    <span className="text-muted-foreground">Free</span>
                                                ) : (
                                                    `$${p.amount} ${p.currency}`
                                                )}
                                            </td>
                                            <td className="px-4 py-2">
                                                <Badge variant={statusVariant(p.status)}>{p.status}</Badge>
                                            </td>
                                            <td className="px-4 py-2 text-muted-foreground">
                                                {p.paid_at ? new Date(p.paid_at).toLocaleDateString() : new Date(p.created_at).toLocaleDateString()}
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>

            {/* Upgrade dialog */}
            <Dialog open={upgradeOpen} onOpenChange={setUpgradeOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Upgrade to {selectedPlan?.name}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitUpgrade} className="space-y-4">
                        <div>
                            <label className="mb-1 block text-sm font-medium">Billing Cycle</label>
                            <Select value={upgradeForm.data.billing_cycle} onValueChange={(v) => upgradeForm.setData('billing_cycle', v)}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="monthly">
                                        Monthly — ${selectedPlan?.monthly_price}/mo
                                    </SelectItem>
                                    <SelectItem value="yearly">
                                        Yearly — ${selectedPlan?.yearly_price}/yr
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <p className="text-muted-foreground text-xs">
                            You will be redirected to Stripe to complete payment securely.
                        </p>
                        <Button type="submit" disabled={upgradeForm.processing} className="w-full">
                            Continue to Payment
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </ClientDashboardLayout>
    );
}
