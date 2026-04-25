import AdminLayout from '@/Admin/AdminLayout';
import { Head } from '@inertiajs/react';
import { Search, ChevronDown, Download, CheckCircle, XCircle } from 'lucide-react';
import { useState } from 'react';

type Activity = {
    timestamp: string;
    user: string;
    action: string;
    details: string;
    status: 'success' | 'failed';
    ip: string;
};

const mockActivities: Activity[] = [
    { timestamp: '2024-01-15 14:32:15', user: 'John Doe', action: 'Login', details: 'Successful login via web', status: 'success', ip: '192.168.1.105' },
    { timestamp: '2024-01-15 14:30:22', user: 'Jane Smith', action: 'Create', details: 'Created new user: Bob Wilson', status: 'success', ip: '10.0.0.42' },
    { timestamp: '2024-01-15 14:28:10', user: 'System', action: 'Update', details: 'Updated encryption policy', status: 'success', ip: '127.0.0.1' },
    { timestamp: '2024-01-15 14:25:05', user: 'Charlie Davis', action: 'Login', details: 'Failed login attempt', status: 'failed', ip: '203.0.113.42' },
    { timestamp: '2024-01-15 14:20:33', user: 'Alice Brown', action: 'Delete', details: 'Deleted fragment FRG-123ABC', status: 'success', ip: '192.168.1.110' },
];

const actionFilters = ['All Actions', 'Login', 'Logout', 'Create', 'Update', 'Delete'];
const statusFilters = ['All Status', 'Success', 'Failed'];

export default function Activity() {
    const [search, setSearch] = useState('');
    const [actionFilter, setActionFilter] = useState('All Actions');
    const [statusFilter, setStatusFilter] = useState('All Status');
    const [showActionDropdown, setShowActionDropdown] = useState(false);
    const [showStatusDropdown, setShowStatusDropdown] = useState(false);

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'success':
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-md border border-green-600/30 bg-green-600/20 px-2 py-1 text-xs font-medium text-green-400">
                        <CheckCircle className="size-3" /> Success
                    </span>
                );
            case 'failed':
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-md border border-red-600/30 bg-red-600/20 px-2 py-1 text-xs font-medium text-red-400">
                        <XCircle className="size-3" /> Failed
                    </span>
                );
            default:
                return null;
        }
    };

    const filteredActivities = mockActivities.filter((activity) => {
        const matchesSearch =
            activity.user.toLowerCase().includes(search.toLowerCase()) ||
            activity.action.toLowerCase().includes(search.toLowerCase()) ||
            activity.details.toLowerCase().includes(search.toLowerCase());
        const matchesAction = actionFilter === 'All Actions' || activity.action === actionFilter;
        const matchesStatus = statusFilter === 'All Status' || activity.status === statusFilter.toLowerCase();
        return matchesSearch && matchesAction && matchesStatus;
    });

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
                    <button className="inline-flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-800 px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-700">
                        <Download className="size-4" />
                        Export
                    </button>
                </div>

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
                    <div className="divide-y divide-slate-800">
                        {filteredActivities.map((activity, index) => (
                            <div key={index} className="grid grid-cols-12 items-center gap-4 px-6 py-4 transition hover:bg-slate-800/30">
                                <div className="col-span-2">
                                    <span className="text-xs text-slate-400 font-mono">{activity.timestamp}</span>
                                </div>
                                <div className="col-span-2">
                                    <span className="text-sm text-white">{activity.user}</span>
                                </div>
                                <div className="col-span-2">
                                    <span className="text-sm text-slate-300">{activity.action}</span>
                                </div>
                                <div className="col-span-3">
                                    <span className="text-sm text-slate-400">{activity.details}</span>
                                </div>
                                <div className="col-span-1">{getStatusBadge(activity.status)}</div>
                                <div className="col-span-2">
                                    <span className="text-xs text-slate-500 font-mono">{activity.ip}</span>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
