import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    Archive,
    Database,
    HardDrive,
    Key,
    LayoutDashboard,
    Lock,
    LogOut,
    Settings,
    UserCog,
    Users,
} from 'lucide-react';
import type { PageProps } from '@/types';

type AdminSidebarProps = {
    role?: string;
    onLogout?: () => void;
};

export default function AdminSidebar({ role, onLogout }: AdminSidebarProps) {
    const { url } = usePage<PageProps>();
    const isOwner = role === 'owner' || role === 'superadmin';

    const adminNavItems = [
        { href: route('admin.dashboard', undefined, false), icon: LayoutDashboard, label: 'Dashboard', match: ['/admin', '/admin/dashboard'] },
        { href: route('admin.users', undefined, false), icon: Users, label: 'Users', match: ['/admin/users'] },
        { href: route('admin.fragments', undefined, false), icon: Database, label: 'Fragment Monitoring', match: ['/admin/fragments'] },
        { href: route('admin.activity', undefined, false), icon: Activity, label: 'Activity Logs', match: ['/admin/activity'] },
        { href: route('admin.incidents', undefined, false), icon: AlertTriangle, label: 'Incidents', match: ['/admin/incidents'] },
    ];

    const ownerNavItems = [
        { href: route('admin.management', undefined, false), icon: UserCog, label: 'Admin Management', match: ['/admin/admin-management'] },
        { href: route('admin.encryption-policy', undefined, false), icon: Lock, label: 'Encryption Policy', match: ['/admin/encryption-policy'] },
        { href: route('admin.key-management', undefined, false), icon: Key, label: 'Key Management Policy', match: ['/admin/key-management'] },
        { href: route('admin.storage', undefined, false), icon: HardDrive, label: 'Storage Configuration', match: ['/admin/storage'] },
        { href: route('admin.system', undefined, false), icon: Settings, label: 'System Configuration', match: ['/admin/system'] },
        { href: route('admin.disaster-recovery', undefined, false), icon: Archive, label: 'Disaster Recovery', match: ['/admin/disaster-recovery'] },
    ];

    const isActive = (paths: string[]) => paths.some((path) => url === path || url.startsWith(`${path}/`));

    return (
        <aside className="flex h-full w-64 flex-col border-r border-slate-800 bg-slate-900 text-slate-200">
            <div className="border-b border-slate-800 px-6 py-6">
                <p className="text-xs uppercase tracking-[0.3em] text-slate-400">StegoLock</p>
                <h1 className="mt-2 text-xl font-bold text-white">Administration Panel</h1>
                <p className="mt-1 text-sm text-slate-400">Secure system administration</p>
            </div>

            <nav className="flex-1 space-y-1 overflow-y-auto p-4">
                {adminNavItems.map((item) => {
                    const Icon = item.icon;

                    return (
                        <Link
                            key={item.label}
                            href={item.href}
                            className={`flex items-center gap-3 rounded-xl px-4 py-3 transition ${isActive(item.match) ? 'bg-orange-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white'}`}
                        >
                            <Icon className="size-5" />
                            <span className="text-sm font-medium">{item.label}</span>
                        </Link>
                    );
                })}

                {isOwner && (
                    <>
                        <div className="my-4 border-t border-slate-800" />
                        <p className="px-4 pb-2 text-xs uppercase tracking-[0.3em] text-slate-500">Superadmin only</p>
                        {ownerNavItems.map((item) => {
                            const Icon = item.icon;

                            return (
                                <Link
                                    key={item.label}
                                    href={item.href}
                                    className={`flex items-center gap-3 rounded-xl px-4 py-3 transition ${isActive(item.match) ? 'bg-red-600 text-white' : 'text-slate-400 hover:bg-slate-800 hover:text-white'}`}
                                >
                                    <Icon className="size-5" />
                                    <span className="text-sm font-medium">{item.label}</span>
                                </Link>
                            );
                        })}
                    </>
                )}
            </nav>

            <div className="border-t border-slate-800 p-4">
                <button
                    type="button"
                    onClick={onLogout}
                    className="flex w-full items-center gap-3 rounded-xl px-4 py-3 text-slate-400 transition hover:bg-slate-800 hover:text-white"
                >
                    <LogOut className="size-5" />
                    <span className="text-sm font-medium">Logout</span>
                </button>
            </div>
        </aside>
    );
}