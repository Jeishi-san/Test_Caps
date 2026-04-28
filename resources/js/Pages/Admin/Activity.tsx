import AdminLayout from '@/Admin/AdminLayout';
import { Head } from '@inertiajs/react';
import { Search, ChevronDown, Download, CheckCircle, XCircle, AlertCircle, ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import axios from 'axios';
import { useToast } from '@/Components/Toast';

type Activity = {
    id: number;
    accessed_at: string;
    user: { name: string; email: string };
    action: string;
    resource: string;
    resource_id: string;
    ip_address: string;
    method: string;
    url: string;
    status_code: number;
};

type PaginatedResponse<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

const actionFilters = ['All Actions', 'Login', 'Logout', 'Create', 'Update', 'Delete', 'Upload', 'Download'];
const statusFilters = ['All Status', 'Success', 'Failed'];

export default function Activity() {
    const [activities, setActivities] = useState<Activity[]>([]);
    const [search, setSearch] = useState('');
    const [actionFilter, setActionFilter] = useState('All Actions');
    const [statusFilter, setStatusFilter] = useState('All Status');
    const [showActionDropdown, setShowActionDropdown] = useState(false);
    const [showStatusDropdown, setShowStatusDropdown] = useState(false);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    // Pagination state
    const [currentPage, setCurrentPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [total, setTotal] = useState(0);
    const perPage = 20;

    const { error: showError } = useToast();

    const fetchActivities = async (page = 1) => {
        setLoading(true);
        setError(null);
        try {
            const params: any = { page, per_page: perPage };
            if (actionFilter !== 'All Actions') params.action = actionFilter;
            if (statusFilter === 'Success') params.status = 200;
            else if (statusFilter === 'Failed') params.status = 400;
            if (search) params.search = search;

            const response = await axios.get<PaginatedResponse<Activity>>('/api/admin/activity-logs', { params });
            setActivities(response.data.data || []);
            setCurrentPage(response.data.current_page);
            setLastPage(response.data.last_page);
            setTotal(response.data.total);
        } catch (err: any) {
            const errorMsg = err.response?.data?.message || 'Failed to fetch activity logs. Please try again.';
            setError(errorMsg);
            showError(errorMsg);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchActivities();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search, actionFilter, statusFilter]);

    const handlePageChange = (newPage: number) => {
        setCurrentPage(newPage);
        fetchActivities(newPage);
    };

    const getStatusBadge = (statusCode: number) => {
        const isSuccess = statusCode >= 200 && statusCode < 300;
        if (isSuccess) {
            return (
                <span className="inline-flex items-center gap-1.5 rounded-md border border-green-600/30 bg-green-600/20 px-2 py-1 text-xs font-medium text-green-400">
                    <CheckCircle className="size-3" /> Success
                </span>
            );
        }
        return (
            <span className="inline-flex items-center gap-1.5 rounded-md border border-red-600/30 bg-red-600/20 px-2 py-1 text-xs font-medium text-red-400">
                <XCircle className="size-3" /> Failed
            </span>
        );
    };

    const formatTimestamp = (dateString: string) => {
        const date = new Date(dateString);
        return date.toLocaleString();
    };

    const getActionFromUrl = (url: string, method: string): string => {
        if (url.includes('/api/stego/encode')) return 'Encode';
        if (url.includes('/api/stego/decode')) return 'Decode';
        if (url.includes('/documents') && method === 'POST') return 'Upload';
        if (url.includes('/documents') && method === 'DELETE') return 'Delete';
        if (url.includes('/users') && method === 'POST') return 'Create User';
        if (url.includes('/users') && method === 'DELETE') return 'Delete User';
        return method; // GET, POST, PUT, DELETE
    };

    return (
        <AdminLayout>
            <Head title="Activity Logs" />
            <div className="space-y-6">
                {/* Page Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-white">Activity Logs</h1>
                        <p className="mt-1 text-sm text-slate-400">Track all system activities and user actions</p>
                    </div>
                    <button
                        onClick={() => window.open('/api/admin/activity-logs/export', '_blank')}
                        className="inline-flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-800 px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-700"
                    >
                        <Download className="size-4" />
                        Export
                    </button>
                </div>

                {/* Error State */}
                {error && (
                    <div className="flex items-center gap-3 rounded-xl border border-red-600/30 bg-red-600/10 p-4">
                        <AlertCircle className="size-5 text-red-400" />
                        <p className="text-sm text-red-200">{error}</p>
                        <button onClick={() => fetchActivities(currentPage)} className="ml-auto text-sm text-red-400 hover:text-red-300">
                            Retry
                        </button>
                    </div>
                )}

                {/* Filter Controls */}
                <div className="flex flex-wrap items-center gap-4">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                        <input
                            type="text"
                            placeholder="Search activities..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full rounded-xl border border-slate-800 bg-slate-800/50 py-2.5 pl-10 pr-4 text-sm text-white placeholder:text-slate-500 focus:border-orange-500/50 focus:ring-2 focus:ring-orange-500/50 focus:outline-none"
                        />
                    </div>
                    <div className="relative">
                        <button
                            onClick={() => setShowActionDropdown(!showActionDropdown)}
                            className="inline-flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-800/50 px-4 py-2.5 text-sm text-slate-300 hover:bg-slate-800"
                        >
                            {actionFilter}
                            <ChevronDown className={`size-4 transition ${showActionDropdown ? 'rotate-180' : ''}`} />
                        </button>
                        {showActionDropdown && (
                            <div className="absolute right-0 z-10 mt-2 w-48 rounded-xl border border-slate-800 bg-slate-900 py-1 shadow-2xl">
                                {actionFilters.map((filter) => (
                                    <button
                                        key={filter}
                                        onClick={() => {
                                            setActionFilter(filter);
                                            setShowActionDropdown(false);
                                        }}
                                        className={`w-full px-4 py-2 text-left text-sm transition ${
                                            actionFilter === filter
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

                {/* Activity Table */}
                <div className="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900/50">
                    {/* Header Row */}
                    <div className="grid grid-cols-12 gap-4 bg-slate-800/50 px-6 py-4">
                        <div className="col-span-2 text-xs font-semibold text-slate-400 uppercase">Timestamp</div>
                        <div className="col-span-2 text-xs font-semibold text-slate-400 uppercase">User</div>
                        <div className="col-span-2 text-xs font-semibold text-slate-400 uppercase">Action</div>
                        <div className="col-span-3 text-xs font-semibold text-slate-400 uppercase">Details</div>
                        <div className="col-span-1 text-xs font-semibold text-slate-400 uppercase">Status</div>
                        <div className="col-span-2 text-xs font-semibold text-slate-400 uppercase">IP Address</div>
                    </div>

                    {/* Data Rows */}
                    {loading ? (
                        <div className="px-6 py-8 text-center text-sm text-slate-400">Loading activity logs...</div>
                    ) : activities.length === 0 ? (
                        <div className="px-6 py-8 text-center text-sm text-slate-400">No activity logs found</div>
                    ) : (
                        <div className="divide-y divide-slate-800">
                            {activities.map((activity) => (
                                <div key={activity.id} className="grid grid-cols-12 items-center gap-4 px-6 py-4 transition hover:bg-slate-800/30">
                                    <div className="col-span-2">
                                        <span className="text-xs text-slate-400 font-mono">{formatTimestamp(activity.accessed_at)}</span>
                                    </div>
                                    <div className="col-span-2">
                                        <span className="text-sm text-white">{activity.user.name}</span>
                                    </div>
                                    <div className="col-span-2">
                                        <span className="text-sm text-slate-300">{getActionFromUrl(activity.url, activity.method)}</span>
                                    </div>
                                    <div className="col-span-3">
                                        <span className="text-sm text-slate-400">{activity.resource} #{activity.resource_id}</span>
                                    </div>
                                    <div className="col-span-1">{getStatusBadge(activity.status_code)}</div>
                                    <div className="col-span-2">
                                        <span className="text-xs text-slate-500 font-mono">{activity.ip_address}</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Pagination */}
                {!loading && lastPage > 1 && (
                    <div className="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-900/50 px-6 py-4">
                        <div className="text-sm text-slate-400">
                            Showing {((currentPage - 1) * perPage) + 1} to {Math.min(currentPage * perPage, total)} of {total} activities
                        </div>
                        <div className="flex items-center gap-2">
                            <button
                                onClick={() => handlePageChange(currentPage - 1)}
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
                                onClick={() => handlePageChange(currentPage + 1)}
                                disabled={currentPage === lastPage}
                                className="inline-flex items-center gap-1 rounded-lg border border-slate-800 bg-slate-800 px-3 py-2 text-sm text-slate-300 transition hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Next
                                <ChevronRight className="size-4" />
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
