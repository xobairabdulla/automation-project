import { Button } from '@/components/ui/button';
import ClientDashboardLayout from '@/layouts/client-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { CheckCircle } from 'lucide-react';

interface BillingSuccessProps {
    session_id: string | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Billing', href: '/billing' },
    { title: 'Payment Success', href: '/billing/success' },
];

export default function BillingSuccess({ session_id }: BillingSuccessProps) {
    return (
        <ClientDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Payment Successful" />
            <div className="flex flex-1 flex-col items-center justify-center gap-4 p-8 text-center">
                <CheckCircle className="h-16 w-16 text-emerald-500" />
                <h1 className="text-2xl font-semibold">Payment Successful!</h1>
                <p className="text-muted-foreground max-w-sm">
                    Your payment has been processed. Your subscription or limit extension will be activated shortly.
                </p>
                <Button asChild>
                    <Link href="/billing">Back to Billing</Link>
                </Button>
            </div>
        </ClientDashboardLayout>
    );
}
