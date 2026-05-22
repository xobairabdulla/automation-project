import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AdminDashboardLayout from '@/layouts/admin-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Link, Head } from '@inertiajs/react';

interface Stats {
    total_users: number;
    active_users: number;
    suspended_users: number;
    pending_users: number;
    total_plans: number;
    active_subscriptions: number;
}

interface AdminDashboardProps {
    stats: Stats;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Admin Dashboard', href: '/admin/dashboard' }];

function StatCard({ title, value, href }: { title: string; value: number; href?: string }) {
    const content = (
        <Card className={href ? 'transition-shadow hover:shadow-md' : ''}>
            <CardHeader>
                <CardTitle className="text-sm font-medium text-muted-foreground">{title}</CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-3xl font-bold">{value.toLocaleString()}</p>
            </CardContent>
        </Card>
    );

    return href ? <Link href={href}>{content}</Link> : content;
}

export default function AdminDashboard({ stats }: AdminDashboardProps) {
    return (
        <AdminDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Dashboard" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold tracking-normal">Super Admin Dashboard</h1>
                    <p className="text-muted-foreground text-sm">Platform overview.</p>
                </div>
                <div className="grid gap-4 md:grid-cols-3">
                    <StatCard title="Total Users" value={stats.total_users} href="/admin/users" />
                    <StatCard title="Active Users" value={stats.active_users} href="/admin/users?status=active" />
                    <StatCard title="Suspended Users" value={stats.suspended_users} href="/admin/users?status=suspended" />
                    <StatCard title="Pending Users" value={stats.pending_users} href="/admin/users?status=pending" />
                    <StatCard title="Plans" value={stats.total_plans} href="/admin/plans" />
                    <StatCard title="Active Subscriptions" value={stats.active_subscriptions} />
                </div>
            </div>
        </AdminDashboardLayout>
    );
}
