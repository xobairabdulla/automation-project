import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';

interface ClientDashboardLayoutProps {
    children: React.ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

export default function ClientDashboardLayout({ children, breadcrumbs = [] }: ClientDashboardLayoutProps) {
    return <AppLayout breadcrumbs={breadcrumbs}>{children}</AppLayout>;
}
