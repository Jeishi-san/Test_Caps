import AdminLayout from '@/Admin/AdminLayout';
import { Head } from '@inertiajs/react';
import { Shield, UserCog, Pencil, Trash2, AlertCircle, ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import axios from 'axios';
import DeleteConfirmationModal from '@/Components/DeleteConfirmationModal';
import CreateAdminModal from '@/Admin/components/CreateAdminModal';
import { useToast } from '@/Components/Toast';

type Admin = {
    id: number;
    name: string;
    email: string;
    role: 'superadmin' | 'admin';
    active: boolean;
    created_at: string;
};

type PaginatedResponse<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

export default function AdminManagement() {
    const [admins, setAdmins] = useState<Admin[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [deletingAdmin, setDeletingAdmin] = useState<Admin | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [showCreateModal, setShowCreateModal] = useState(false);

    // Pagination state
    const [currentPage, setCurrentPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [total, setTotal] = useState(0);
    const perPage = 10;

    const { success, error: showError } = useToast();

    const fetchAdmins = async (page = 1) => {
        setLoading(true);
        setError(null);
        try {
            const response = await axios.get('/api/admins', {
                params: { page, per_page: perPage }
            });
            setAdmins(response.data.data || []);
            setCurrentPage(response.data.current_page);
            setLastPage(response.data.last_page);
            setTotal(response.data.total);
        } catch (err: any) {
            const errorMsg = err.response?.data?.message || 'Failed to fetch admin users';
            setError(errorMsg);
            showError(errorMsg);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchAdmins();
    }, []);

    const handleDelete = async () => {
        if (!deletingAdmin) return;

        setDeleting(true);
        try {
            await axios.delete(`/api/users/${deletingAdmin.id}`);
            setShowDeleteModal(false);
            setDeletingAdmin(null);
            success(`Admin "${deletingAdmin.name}" deleted successfully`);
            fetchAdmins(currentPage);
        } catch (err: any) {
            const errorMsg = err.response?.data?.message || 'Failed to delete admin user';
            showError(errorMsg);
        } finally {
            setDeleting(false);
        }
    };

    const openDeleteModal = (admin: Admin) => {
        setDeletingAdmin(admin);
        setShowDeleteModal(true);
    };

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
                    <button
                        onClick={() => setShowCreateModal(true)}
                        className="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-red-500/30 transition hover:bg-red-700"
                    >
                        <Shield className="size-4" />
                        Create Admin
                    </button>
                </div>

                {/* Error State */}
                {error && (
                    <div className="flex items-center gap-3 rounded-xl border border-red-600/30 bg-red-600/10 p-4">
                        <AlertCircle className="size-5 text-red-400" />
                        <p className="text-sm text-red-200">{error}</p>
                        <button onClick={() => fetchAdmins(currentPage)} className="ml-auto text-sm text-red-400 hover:text-red-300">
                            Retry
                        </button>
                    </div>
                )}

                {/* Loading State */}
                {loading ? (
                    <div className="rounded-xl border border-slate-800 bg-slate-900/50 p-8 text-center">
                        <div className="text-slate-400">Loading admins...</div>
                    </div>
                ) : admins.length === 0 ? (
                    <div className="rounded-xl border border-slate-800 bg-slate-900/50 p-8 text-center">
                        <p className="text-slate-400">No admin users found</p>
                    </div>
                ) : (
                    <>
                        {/* Admins Table */}
                        <div className="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900/50">
                            {/* Header Row */}
                            <div className="grid grid-cols-12 gap-4 bg-slate-800/50 px-6 py-4">
                                <div className="col-span-4 text-xs font-semibold text-slate-400 uppercase">Name</div>
                                <div className="col-span-4 text-xs font-semibold text-slate-400 uppercase">Email</div>
                                <div className="col-span-2 text-xs font-semibold text-slate-400 uppercase">Role</div>
                                <div className="col-span-2 text-xs font-semibold text-slate-400 uppercase">Actions</div>
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
                                        <div className="col-span-2 flex items-center gap-2">
                                            <button
                                                onClick={() => openDeleteModal(admin)}
                                                disabled={admin.role === 'superadmin'}
                                                className="rounded-lg p-2 text-slate-400 transition hover:bg-red-500/20 hover:text-red-400 disabled:opacity-50 disabled:cursor-not-allowed"
                                                title={admin.role === 'superadmin' ? 'Cannot delete superadmin' : 'Delete admin'}
                                            >
                                                <Trash2 className="size-4" />
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {/* Pagination */}
                        {lastPage > 1 && (
                            <div className="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-900/50 px-6 py-4">
                                <div className="text-sm text-slate-400">
                                    Showing {((currentPage - 1) * perPage) + 1} to {Math.min(currentPage * perPage, total)} of {total} admins
                                </div>
                                <div className="flex items-center gap-2">
                                    <button
                                        onClick={() => {
                                            const newPage = currentPage - 1;
                                            setCurrentPage(newPage);
                                            fetchAdmins(newPage);
                                        }}
                                        disabled={currentPage === 1}
                                        className="inline-flex items-center gap-1 rounded-lg border border-slate-800 bg-slate-800 px-3 py-2 text-sm text-slate-300 transition hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <ChevronLeft className="size-4" />
                                        Previous
                                    </button>
                                    <span className="text-sm text-slate-300">
                                        Page {currentPage} of {lastPage}
                                    </span>
                                    <button
                                        onClick={() => {
                                            const newPage = currentPage + 1;
                                            setCurrentPage(newPage);
                                            fetchAdmins(newPage);
                                        }}
                                        disabled={currentPage === lastPage}
                                        className="inline-flex items-center gap-1 rounded-lg border border-slate-800 bg-slate-800 px-3 py-2 text-sm text-slate-300 transition hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        Next
                                        <ChevronRight className="size-4" />
                                    </button>
                                </div>
                            </div>
                        )}
                    </>
                )}

                {/* Delete Confirmation Modal */}
                <DeleteConfirmationModal
                    show={showDeleteModal}
                    onClose={() => {
                        setShowDeleteModal(false);
                        setDeletingAdmin(null);
                    }}
                    onConfirm={handleDelete}
                    itemName={deletingAdmin?.name || ''}
                    itemType="admin user"
                    processing={deleting}
                />

                {/* Create Admin Modal */}
                <CreateAdminModal
                    isOpen={showCreateModal}
                    onClose={() => setShowCreateModal(false)}
                    onSubmit={async (data) => {
                        try {
                            await axios.post('/api/admins', {
                                name: data.name,
                                email: data.email,
                                password: data.password,
                                role: data.role,
                                active: true,
                            });
                            success(`Admin "${data.name}" created successfully`);
                            setShowCreateModal(false);
                            fetchAdmins(currentPage);
                        } catch (err: any) {
                            const errorMsg = err.response?.data?.message || 'Failed to create admin user';
                            showError(errorMsg);
                        }
                    }}
                />
            </div>
        </AdminLayout>
    );
}
