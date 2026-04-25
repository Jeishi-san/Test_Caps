import AdminLayout from '@/Admin/AdminLayout';
import { Head } from '@inertiajs/react';
import { UserPlus, Search, ChevronDown, Pencil, Trash2, Shield, UserCog } from 'lucide-react';
import { useState } from 'react';
import CreateUserModal from '@/Admin/components/CreateUserModal';

type User = {
    id: number;
    name: string;
    email: string;
    role: 'user' | 'admin' | 'superadmin';
    status: 'active' | 'inactive' | 'suspended';
    lastActive: string;
    avatar?: string;
};

const mockUsers: User[] = [
    { id: 1, name: 'John Doe', email: 'john@example.com', role: 'superadmin', status: 'active', lastActive: '2 minutes ago' },
    { id: 2, name: 'Jane Smith', email: 'jane@example.com', role: 'admin', status: 'active', lastActive: '1 hour ago' },
    { id: 3, name: 'Bob Wilson', email: 'bob@example.com', role: 'user', status: 'inactive', lastActive: '3 days ago' },
    { id: 4, name: 'Alice Brown', email: 'alice@example.com', role: 'user', status: 'active', lastActive: '5 minutes ago' },
    { id: 5, name: 'Charlie Davis', email: 'charlie@example.com', role: 'user', status: 'suspended', lastActive: '2 weeks ago' },
];

const statusFilters = ['All Statuses', 'Active', 'Inactive', 'Suspended'];

export default function Users() {
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('All Statuses');
    const [showStatusDropdown, setShowStatusDropdown] = useState(false);
    const [showCreateModal, setShowCreateModal] = useState(false);

    const getRoleBadge = (role: string) => {
        switch (role) {
            case 'superadmin':
                return <span className="inline-flex items-center gap-1 rounded-md border border-red-600/30 bg-red-600/20 px-2 py-1 text-xs font-medium text-red-400"><Shield className="size-3" /> SUPERADMIN</span>;
            case 'admin':
                return <span className="inline-flex items-center gap-1 rounded-md border border-blue-600/30 bg-blue-600/20 px-2 py-1 text-xs font-medium text-blue-400"><UserCog className="size-3" /> ADMIN</span>;
            default:
                return <span className="inline-flex rounded-md border border-slate-600/30 bg-slate-600/20 px-2 py-1 text-xs font-medium text-slate-400">USER</span>;
        }
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'active':
                return <span className="inline-flex items-center gap-2 rounded-md border border-green-600/30 bg-green-600/20 px-2 py-1 text-xs font-medium text-green-400"><span className="size-1.5 rounded-full bg-green-500" /> Active</span>;
            case 'inactive':
                return <span className="inline-flex items-center gap-2 rounded-md border border-slate-600/30 bg-slate-600/20 px-2 py-1 text-xs font-medium text-slate-400"><span className="size-1.5 rounded-full bg-slate-500" /> Inactive</span>;
            case 'suspended':
                return <span className="inline-flex items-center gap-2 rounded-md border border-red-600/30 bg-red-600/20 px-2 py-1 text-xs font-medium text-red-400"><span className="size-1.5 rounded-full bg-red-500" /> Suspended</span>;
            default:
                return null;
        }
    };

    const filteredUsers = mockUsers.filter((user) => {
        const matchesSearch = user.name.toLowerCase().includes(search.toLowerCase()) || user.email.toLowerCase().includes(search.toLowerCase());
        const matchesStatus = statusFilter === 'All Statuses' || user.status.toLowerCase() === statusFilter.toLowerCase();
        return matchesSearch && matchesStatus;
    });

    return (
        <AdminLayout>
            <Head title="Users" />
            <div className="space-y-6">
                {/* Page Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-white">Users</h1>
                        <p className="mt-1 text-sm text-slate-400">Manage user accounts and permissions</p>
                    </div>
                    <button
                        onClick={() => setShowCreateModal(true)}
                        className="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-orange-600 to-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/30 transition hover:from-orange-700 hover:to-red-700"
                    >
                        <UserPlus className="size-4" />
                        Create User
                    </button>
                </div>

                {/* Filter Section */}
                <div className="flex items-center gap-4">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                        <input
                            type="text"
                            placeholder="Search users..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full rounded-xl border border-slate-800 bg-slate-800/50 py-2.5 pl-10 pr-4 text-sm text-white placeholder:text-slate-500 focus:border-orange-500/50 focus:ring-2 focus:ring-orange-500/50 focus:outline-none"
                        />
                    </div>
                    <div className="relative">
                        <button
                            onClick={() => setShowStatusDropdown(!showStatusDropdown)}
                            className="inline-flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-800/50 px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-800"
                        >
                            {statusFilter}
                            <ChevronDown className={`size-4 transition ${showStatusDropdown ? 'rotate-180' : ''}`} />
                        </button>
                        {showStatusDropdown && (
                            <div className="absolute right-0 z-10 mt-2 w-48 rounded-xl border border-slate-800 bg-slate-900 py-1 shadow-2xl">
                                {statusFilters.map((filter) => (
                                    <button
                                        key={filter}
                                        onClick={() => {
                                            setStatusFilter(filter);
                                            setShowStatusDropdown(false);
                                        }}
                                        className={`w-full px-4 py-2 text-left text-sm transition ${
                                            statusFilter === filter
                                                ? 'bg-orange-600/20 text-orange-400 font-medium'
                                                : 'text-slate-300 hover:bg-slate-800'
                                        }`}
                                    >
                                        {filter}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Users Table */}
                <div className="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900/50">
                    {/* Header Row */}
                    <div className="grid grid-cols-12 gap-4 bg-slate-800/50 px-6 py-4">
                        <div className="col-span-4 text-xs font-semibold text-slate-400 uppercase">User</div>
                        <div className="col-span-2 text-xs font-semibold text-slate-400 uppercase">Role</div>
                        <div className="col-span-2 text-xs font-semibold text-slate-400 uppercase">Status</div>
                        <div className="col-span-3 text-xs font-semibold text-slate-400 uppercase">Last Active</div>
                        <div className="col-span-1 text-xs font-semibold text-slate-400 uppercase">Actions</div>
                    </div>

                    {/* Data Rows */}
                    <div className="divide-y divide-slate-800">
                        {filteredUsers.map((user) => (
                            <div key={user.id} className="grid grid-cols-12 items-center gap-4 px-6 py-4 transition hover:bg-slate-800/30">
                                <div className="col-span-4 flex items-center gap-3">
                                    <div className="flex size-10 items-center justify-center rounded-lg bg-slate-800 text-sm font-medium text-slate-300">
                                        {user.avatar ? (
                                            <img src={user.avatar} alt={user.name} className="size-10 rounded-lg" />
                                        ) : (
                                            user.name.charAt(0).toUpperCase()
                                        )}
                                    </div>
                                    <div>
                                        <p className="text-sm font-medium text-white">{user.name}</p>
                                        <p className="text-xs text-slate-500">{user.email}</p>
                                    </div>
                                </div>
                                <div className="col-span-2">{getRoleBadge(user.role)}</div>
                                <div className="col-span-2">{getStatusBadge(user.status)}</div>
                                <div className="col-span-3 text-sm text-slate-500">{user.lastActive}</div>
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

                {/* Create User Modal */}
                <CreateUserModal isOpen={showCreateModal} onClose={() => setShowCreateModal(false)} />
            </div>
        </AdminLayout>
    );
}
