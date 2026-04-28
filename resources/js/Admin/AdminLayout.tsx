import { PropsWithChildren } from 'react';
import AdminSidebar from '@/Admin/AdminSidebar';
import AdminTopbar from '@/Admin/AdminTopbar';
import { usePage, router } from '@inertiajs/react';
import { ToastContainer } from '@/Components/Toast';
import type { PageProps } from '@/types';

type AdminLayoutProps = PropsWithChildren<{
    onLogout?: () => void;
}>;

export default function AdminLayout({ children, onLogout }: AdminLayoutProps) {
    const { auth } = usePage<PageProps>().props;

    const handleLogout = () => {
        if (onLogout) {
            onLogout();
        } else {
            // Default logout handler
            router.post(route('logout'));
        }
    };

    return (
        <div className="flex min-h-screen bg-slate-950 text-slate-100">
            <AdminSidebar role={auth.user.role} onLogout={handleLogout} />
            <div className="flex min-h-screen flex-1 flex-col overflow-hidden">
                <AdminTopbar email={auth.user.email} role={auth.user.role} />
                <main className="flex-1 overflow-y-auto bg-slate-950 p-6 sm:p-8">
                    {children}
                </main>
            </div>
            <ToastContainer />
        </div>
    );
}