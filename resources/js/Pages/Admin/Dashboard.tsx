import AdminLayout from '@/Admin/AdminLayout';
import AdminDashboard from '@/Admin/components/AdminDashboard';
import { Head } from '@inertiajs/react';

export default function Dashboard() {
    return (
        <AdminLayout>
            <Head title="Admin Dashboard" />
            <AdminDashboard />
        </AdminLayout>
    );
}