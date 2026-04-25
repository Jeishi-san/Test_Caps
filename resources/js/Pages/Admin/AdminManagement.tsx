import AdminLayout from '@/Admin/AdminLayout';
import { Head } from '@inertiajs/react';
import { Shield, UserCog, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';

type Admin = {
    id: number;
    name: string;
    email: string;
    role: 'superadmin' | 'admin';
    mfaEnabled: boolean;
    lastActive: string;
};

const mockAdmins: Admin[] = [
    { id: 1, name: 'John Doe', email: 'john@example.com', role: 'superadmin', mfaEnabled: true, lastActive: '2 min ago' },
    { id: 2, name: 'Jane Smith', email: 'jane@example.com', role: 'admin', mfaEnabled: true, lastActive: '1 hour ago' },
    { id: 3, name: 'Bob Wilson', email: 'bob@example.com', role: 'admin', mfaEnabled: false, lastActive: '3 days ago' },
];

export default function AdminManagement() {
    const [admins] = useState<Admin[]>(mockAdmins);

    const getRoleBadge = (role: string) => {
        switch (role) {
            case 'superadmin':
                return (
                    <span className="inline-flex items-center gap-1 rounded-md border border-red-600/30 bg-red-600/20 px-2 py-1 text-xs font-medium text-red-400">
                        <Shield className="size-3" /> SUPERADMIN
                    </span>
                );
            case 'admin':
                return (
                    <span className="inline-flex items-center gap-1 rounded-md border border-blue-600/30 bg-blue-600/20 px-2 py-1 text-xs font-medium text-blue-400">
                        <UserCog className="size-3" /> ADMIN
                    </span>
                );
            default:
                return null;
        }
    };

    return (
        <AdminLayout>
            <Head title="Admin Management" />
            <div className="space-y-6">
                {/* Page Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-white">Admin Management</h1>
                        <p className="mt-1 text-sm text-slate-400">Manage administrator accounts (Superadmin only)</p>
                    </div>
                    <button className="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-red-500/30 transition hover:bg-red-700">
                        <Shield className="size-4" />
                        Create Admin
                    </button>
                </div>

                {/* Admins Table */}
                <div className="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900/50">
                    {/* Header Row */}
                    <div className="grid grid-cols-12 gap-4 bg-slate-800/50 px-6 py-4">
                        <div className="col-span-3 text-xs font-semibold text-slate-400 uppercase">Name</div>
                        <div className="col-span-3 text-xs font-semibold text-slate-400 uppercase">Email</div>
                        <div className="col-span-2 text-xs font-semibold text-slate-400 uppercase">Role</div>
                        <div className="col-span-1 text-xs font-semibold text-slate-400 uppercase">MFA</div>
                        <div className="col-span-2 text-xs font-semibold text-slate-400 uppercase">Last Active</div>
                        <div className="col-span-1 text-xs font-semibold text-slate-400 uppercase">Actions</div>
                    </div>

                    {/* Data Rows */}
                    <div className="divide-y divide-slate-800">
                        {admins.map((admin) => (
                            <div key={admin.id} className="grid grid-cols-12 items-center gap-4 px-6 py-4 transition hover:bg-slate-800/30">
                                <div className="col-span-3">
                                    <span className="text-sm font-medium text-white">{admin.name}</span>
                                </div>
                                <div className="col-span-3">
                                    <span className="text-sm text-slate-400">{admin.email}</span>
                                </div>
                                <div className="col-span-2">{getRoleBadge(admin.role)}</div>
                                <div className="col-span-1">
                                    <button
                                        className={`relative inline-flex h-5 w-9 items-center rounded-full transition ${
                                            admin.mfaEnabled ? 'bg-green-600' : 'bg-slate-600'
                                        }`}
                                    >
                                        <span
                                            className={`inline-block h-3 w-3 rounded-full bg-white transition ${
                                                admin.mfaEnabled ? 'translate-x-5' : 'translate-x-1'
                                            }`}
                                        />
                                    </button>
                                </div>
                                <div className="col-span-2">
                                    <span className="text-sm text-slate-500">{admin.lastActive}</span>
                                </div>
                                <div className="col-span-1 flex items-center gap-2">
                                    <button className="rounded-lg p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white">
                                        <Pencil className="size-4" />
                                    </button>
                                    <button className="rounded-lg p-2 text-slate-400 transition hover:bg-red-500/20 hover:text-red-400">
                                        <Trash2 className="size-4" />
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
