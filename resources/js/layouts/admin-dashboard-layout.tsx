import AdminSidebarLayout from '@/layouts/app/admin-sidebar-layout';
import { type BreadcrumbItem } from '@/types';

interface AdminDashboardLayoutProps {
    children: React.ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

export default function AdminDashboardLayout({ children, breadcrumbs = [] }: AdminDashboardLayoutProps) {
    return <AdminSidebarLayout breadcrumbs={breadcrumbs}>{children}</AdminSidebarLayout>;
}
