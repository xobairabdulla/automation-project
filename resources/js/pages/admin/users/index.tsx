import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AdminDashboardLayout from '@/layouts/admin-dashboard-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface Role {
    slug: string;
    name: string;
}

interface Plan {
    name: string;
}

interface Subscription {
    status: string;
    plan: Plan;
}

interface User {
    id: number;
    name: string;
    email: string;
    status: string;
    created_at: string;
    roles: Role[];
    subscriptions: Subscription[];
}

interface Paginator<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
}

interface UsersIndexProps {
    users: Paginator<User>;
    filters: { search?: string; status?: string };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin Dashboard', href: '/admin/dashboard' },
    { title: 'Users', href: '/admin/users' },
];

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'active') return 'default';
    if (status === 'suspended') return 'destructive';
    return 'secondary';
}

export default function UsersIndex({ users, filters }: UsersIndexProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    useEffect(() => {
        const timeout = setTimeout(() => {
            router.get('/admin/users', { search, status: status || undefined }, { preserveState: true, replace: true });
        }, 350);
        return () => clearTimeout(timeout);
    }, [search, status]);

    return (
        <AdminDashboardLayout breadcrumbs={breadcrumbs}>
            <Head title="User Management" />
            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-normal">User Management</h1>
                        <p className="text-muted-foreground text-sm">{users.total} total users</p>
                    </div>
                </div>

                <div className="flex flex-wrap gap-3">
                    <Input
                        placeholder="Search name or email..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="max-w-xs"
                    />
                    <Select value={status || 'all'} onValueChange={(v) => setStatus(v === 'all' ? '' : v)}>
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="suspended">Suspended</SelectItem>
                            <SelectItem value="pending">Pending</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Users</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="border-b bg-muted/50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-medium">Name</th>
                                        <th className="px-4 py-3 text-left font-medium">Email</th>
                                        <th className="px-4 py-3 text-left font-medium">Status</th>
                                        <th className="px-4 py-3 text-left font-medium">Role</th>
                                        <th className="px-4 py-3 text-left font-medium">Plan</th>
                                        <th className="px-4 py-3 text-left font-medium">Joined</th>
                                        <th className="px-4 py-3 text-left font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.data.length === 0 ? (
                                        <tr>
                                            <td className="px-4 py-8 text-center text-muted-foreground" colSpan={7}>
                                                No users found.
                                            </td>
                                        </tr>
                                    ) : (
                                        users.data.map((user) => (
                                            <tr key={user.id} className="border-b last:border-0 hover:bg-muted/30">
                                                <td className="px-4 py-3 font-medium">{user.name}</td>
                                                <td className="px-4 py-3 text-muted-foreground">{user.email}</td>
                                                <td className="px-4 py-3">
                                                    <Badge variant={statusVariant(user.status)}>{user.status}</Badge>
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {user.roles.map((r) => r.name).join(', ') || '—'}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {user.subscriptions[0]?.plan?.name ?? '—'}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {new Date(user.created_at).toLocaleDateString()}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Button asChild variant="outline" size="sm">
                                                        <Link href={`/admin/users/${user.id}`}>View</Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                        {users.last_page > 1 && (
                            <div className="flex items-center justify-between border-t px-4 py-3">
                                <span className="text-sm text-muted-foreground">
                                    Page {users.current_page} of {users.last_page}
                                </span>
                                <div className="flex gap-2">
                                    {users.current_page > 1 && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                router.get('/admin/users', { ...filters, page: users.current_page - 1 })
                                            }
                                        >
                                            Previous
                                        </Button>
                                    )}
                                    {users.current_page < users.last_page && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                router.get('/admin/users', { ...filters, page: users.current_page + 1 })
                                            }
                                        >
                                            Next
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminDashboardLayout>
    );
}
