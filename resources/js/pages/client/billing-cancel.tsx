import { Button } from '@/components/ui/button';
import ClientDashboardLayout from '@/layouts/client-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { XCircle } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Billing', href: '/billing' },
    { title: 'Payment Cancelled', href: '/billing/cancel' },
];

export default function BillingCancel() {
    return (
        <ClientDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Payment Cancelled" />
            <div className="flex flex-1 flex-col items-center justify-center gap-4 p-8 text-center">
                <XCircle className="text-muted-foreground h-16 w-16" />
                <h1 className="text-2xl font-semibold">Payment Cancelled</h1>
                <p className="text-muted-foreground max-w-sm">Your payment was cancelled. No charges were made.</p>
                <Button asChild>
                    <Link href="/billing">Back to Billing</Link>
                </Button>
            </div>
        </ClientDashboardLayout>
    );
}
