import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface AdminDashboardLayoutProps {
    children: React.ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

export default function AdminDashboardLayout({ children, breadcrumbs = [] }: AdminDashboardLayoutProps) {
    return <AppLayout breadcrumbs={breadcrumbs}>{children}</AppLayout>;
}
