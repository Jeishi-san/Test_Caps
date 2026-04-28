import AdminLayout from '@/Admin/AdminLayout';
import { Head } from '@inertiajs/react';
import { UserPlus, Search, ChevronDown, Pencil, Trash2, Shield, UserCog } from 'lucide-react';
import { useState, useEffect } from 'react';
import CreateUserModal from '@/Admin/components/CreateUserModal';
import axios from 'axios';

type User = {
    id: number;
    name: string;
    email: string;
    role: 'user' | 'admin' | 'owner';
    active: boolean;
    created_at: string;
};

const statusFilters = ['All', 'Active', 'Inactive'];

export default function Users() {
    const [users, setUsers] = useState<User[]>([]);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('All');
    const [showStatusDropdown, setShowStatusDropdown] = useState(false);
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editingUser, setEditingUser] = useState<User | null>(null);
    const [loading, setLoading] = useState(true);

    // Fetch users from API
    const fetchUsers = async () => {
        setLoading(true);
        try {
            const params: any = {};
            if (search) params.search = search;
            if (statusFilter !== 'All') params.status = statusFilter.toLowerCase();
            
            const response = await axios.get('/api/users', { params });
            setUsers(response.data);
        } catch (error) {
            console.error('Failed to fetch users:', error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchUsers();
    }, [search, statusFilter]);

    const handleCreateUser = async (userData: {
        name: string;
        email: string;
        password: string;
        role?: string;
        status?: string;
        userId?: number;
    }) => {
        try {
            const payload: any = {
                name: userData.name,
                email: userData.email,
                role: userData.role || 'user',
                active: userData.status !== 'inactive',
            };

            if (userData.userId) {
                // Update existing user: only include password if provided
                if (userData.password && userData.password.trim() !== '') {
                    payload.password = userData.password;
                }
                await axios.put(`/api/users/${userData.userId}`, payload);
            } else {
                // Create new user: password is required
                payload.password = userData.password;
                await axios.post('/api/users', payload);
            }

            setShowCreateModal(false);
            setEditingUser(null);
            fetchUsers(); // Refresh list
        } catch (error: any) {
            console.error('Failed to save user:', error.response?.data);
            alert(error.response?.data?.message || 'Failed to save user');
        }
    };

    const handleEditUser = (user: User) => {
        setEditingUser(user);
        setShowCreateModal(true);
    };

    const handleDeleteUser = async (userId: number) => {
        if (!confirm('Are you sure you want to delete this user?')) return;
        
        try {
            await axios.delete(`/api/users/${userId}`);
            fetchUsers(); // Refresh list
        } catch (error: any) {
            console.error('Failed to delete user:', error.response?.data);
            alert(error.response?.data?.message || 'Failed to delete user');
        }
    };

    const getRoleBadge = (role: string) => {
        switch (role) {
            case 'owner':
                return <span className="inline-flex items-center gap-1 rounded-md border border-red-600/30 bg-red-600/20 px-2 py-1 text-xs font-medium text-red-400"><Shield className="size-3" /> OWNER</span>;
            case 'admin':
                return <span className="inline-flex items-center gap-1 rounded-md border border-blue-600/30 bg-blue-600/20 px-2 py-1 text-xs font-medium text-blue-400"><UserCog className="size-3" /> ADMIN</span>;
            default:
                return <span className="inline-flex rounded-md border border-slate-600/30 bg-slate-600/20 px-2 py-1 text-xs font-medium text-slate-400">USER</span>;
        }
    };

    const getStatusBadge = (active: boolean) => {
        if (active) {
            return <span className="inline-flex items-center gap-2 rounded-md border border-green-600/30 bg-green-600/20 px-2 py-1 text-xs font-medium text-green-400"><span className="size-1.5 rounded-full bg-green-500" /> Active</span>;
        } else {
            return <span className="inline-flex items-center gap-2 rounded-md border border-slate-600/30 bg-slate-600/20 px-2 py-1 text-xs font-medium text-slate-400"><span className="size-1.5 rounded-full bg-slate-500" /> Inactive</span>;
        }
    };

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
                        <div className="col-span-3 text-xs font-semibold text-slate-400 uppercase">Created At</div>
                        <div className="col-span-1 text-xs font-semibold text-slate-400 uppercase">Actions</div>
                    </div>

                    {/* Data Rows */}
                    {loading ? (
                        <div className="px-6 py-8 text-center text-sm text-slate-400">Loading users...</div>
                    ) : (
                        <div className="divide-y divide-slate-800">
                            {users.map((user) => (
                                <div key={user.id} className="grid grid-cols-12 items-center gap-4 px-6 py-4 transition hover:bg-slate-800/30">
                                    <div className="col-span-4 flex items-center gap-3">
                                        <div className="flex size-10 items-center justify-center rounded-lg bg-slate-800 text-sm font-medium text-slate-300">
                                            {user.name.charAt(0).toUpperCase()}
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-white">{user.name}</p>
                                            <p className="text-xs text-slate-500">{user.email}</p>
                                        </div>
                                    </div>
                                    <div className="col-span-2">{getRoleBadge(user.role)}</div>
                                    <div className="col-span-2">{getStatusBadge(user.active)}</div>
                                    <div className="col-span-3 text-sm text-slate-500">
                                        {new Date(user.created_at).toLocaleDateString()}
                                    </div>
                                    <div className="col-span-1 flex items-center gap-2">
                                        <button
                                            onClick={() => handleEditUser(user)}
                                            className="rounded-lg p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white"
                                        >
                                            <Pencil className="size-4" />
                                        </button>
                                        <button
                                            onClick={() => handleDeleteUser(user.id)}
                                            className="rounded-lg p-2 text-slate-400 transition hover:bg-red-500/20 hover:text-red-400"
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    </div>
                                </div>
                            ))}
                            {users.length === 0 && (
                                <div className="px-6 py-8 text-center text-sm text-slate-400">No users found</div>
                            )}
                        </div>
                    )}
                </div>

                {/* Create/Edit User Modal */}
                <CreateUserModal
                    isOpen={showCreateModal}
                    onClose={() => {
                        setShowCreateModal(false);
                        setEditingUser(null);
                    }}
                    onSubmit={handleCreateUser}
                    editUser={editingUser}
                />
            </div>
        </AdminLayout>
    );
}
