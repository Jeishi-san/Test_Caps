import AdminLayout from '@/Admin/AdminLayout';
import { Head } from '@inertiajs/react';
import { AlertOctagon, AlertTriangle, AlertCircle, Info, Plus } from 'lucide-react';
import { useState } from 'react';

type Incident = {
    id: string;
    title: string;
    severity: 'critical' | 'high' | 'medium' | 'low';
    status: 'open' | 'investigating' | 'resolved';
    reportedBy: string;
    date: string;
};

const mockStats = [
    { label: 'Critical', value: '3', icon: AlertOctagon, color: 'red' },
    { label: 'High', value: '7', icon: AlertTriangle, color: 'orange' },
    { label: 'Medium', value: '12', icon: AlertCircle, color: 'yellow' },
    { label: 'Low', value: '24', icon: Info, color: 'blue' },
];

const mockIncidents: Incident[] = [
    { id: 'INC-001', title: 'Unauthorized access attempt', severity: 'critical', status: 'open', reportedBy: 'System', date: '2024-01-15' },
    { id: 'INC-002', title: 'Fragment integrity check failed', severity: 'high', status: 'investigating', reportedBy: 'John Doe', date: '2024-01-14' },
    { id: 'INC-003', title: 'Storage quota warning', severity: 'medium', status: 'resolved', reportedBy: 'System', date: '2024-01-13' },
    { id: 'INC-004', title: 'Failed login attempts spike', severity: 'high', status: 'open', reportedBy: 'Jane Smith', date: '2024-01-12' },
    { id: 'INC-005', title: 'SSL certificate expiring', severity: 'low', status: 'resolved', reportedBy: 'System', date: '2024-01-11' },
];

export default function Incidents() {
    const [incidents] = useState<Incident[]>(mockIncidents);

    const getSeverityBadge = (severity: string) => {
        switch (severity) {
            case 'critical':
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-md border border-red-600/30 bg-red-600/20 px-2 py-1 text-xs font-medium text-red-400">
                        <AlertOctagon className="size-3" /> Critical
                    </span>
                );
            case 'high':
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-md border border-orange-600/30 bg-orange-600/20 px-2 py-1 text-xs font-medium text-orange-400">
                        <AlertTriangle className="size-3" /> High
                    </span>
                );
            case 'medium':
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-md border border-yellow-600/30 bg-yellow-600/20 px-2 py-1 text-xs font-medium text-yellow-400">
                        <AlertCircle className="size-3" /> Medium
                    </span>
                );
            case 'low':
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-md border border-blue-600/30 bg-blue-600/20 px-2 py-1 text-xs font-medium text-blue-400">
                        <Info className="size-3" /> Low
                    </span>
                );
            default:
                return null;
        }
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'open':
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-md border border-red-600/30 bg-red-600/20 px-2 py-1 text-xs font-medium text-red-400">
                        <span className="size-1.5 rounded-full bg-red-500" /> Open
                    </span>
                );
            case 'investigating':
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-md border border-yellow-600/30 bg-yellow-600/20 px-2 py-1 text-xs font-medium text-yellow-400">
                        <span className="size-1.5 rounded-full bg-yellow-500" /> Investigating
                    </span>
                );
            case 'resolved':
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-md border border-green-600/30 bg-green-600/20 px-2 py-1 text-xs font-medium text-green-400">
                        <span className="size-1.5 rounded-full bg-green-500" /> Resolved
                    </span>
                );
            default:
                return null;
        }
    };

    return (
        <AdminLayout>
            <Head title="Incidents" />
            <div className="space-y-6">
                {/* Page Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-white">Incidents</h1>
                        <p className="mt-1 text-sm text-slate-400">Track and manage security incidents</p>
                    </div>
                    <button className="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-red-500/30 transition hover:bg-red-700">
                        <Plus className="size-4" />
                        Report Incident
                    </button>
                </div>

                {/* Incident Stats */}
                <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                    {mockStats.map((stat) => {
                        const Icon = stat.icon;
                        return (
                            <div key={stat.label} className="rounded-xl border border-slate-800 bg-slate-900/50 p-6">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <p className="text-sm text-slate-400">{stat.label}</p>
                                        <p className="mt-2 text-3xl font-bold text-white">{stat.value}</p>
                                    </div>
                                    <div className={`rounded-lg p-3 bg-${stat.color}-600/20 text-${stat.color}-400`}>
                                        <Icon className="size-6" />
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>

                {/* Incidents Table */}
                <div className="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900/50">
                    {/* Header Row */}
                    <div className="grid grid-cols-12 gap-4 bg-slate-800/50 px-6 py-4">
                        <div className="col-span-1 text-xs font-semibold text-slate-400 uppercase">ID</div>
                        <div className="col-span-3 text-xs font-semibold text-slate-400 uppercase">Title</div>
                        <div className="col-span-2 text-xs font-semibold text-slate-400 uppercase">Severity</div>
                        <div className="col-span-2 text-xs font-semibold text-slate-400 uppercase">Status</div>
                        <div className="col-span-2 text-xs font-semibold text-slate-400 uppercase">Reported By</div>
                        <div className="col-span-1 text-xs font-semibold text-slate-400 uppercase">Date</div>
                        <div className="col-span-1 text-xs font-semibold text-slate-400 uppercase">Actions</div>
                    </div>

                    {/* Data Rows */}
                    <div className="divide-y divide-slate-800">
                        {incidents.map((incident) => (
                            <div key={incident.id} className="grid grid-cols-12 items-center gap-4 px-6 py-4 transition hover:bg-slate-800/30">
                                <div className="col-span-1">
                                    <span className="text-sm font-mono text-slate-300">{incident.id}</span>
                                </div>
                                <div className="col-span-3">
                                    <span className="text-sm text-white">{incident.title}</span>
                                </div>
                                <div className="col-span-2">{getSeverityBadge(incident.severity)}</div>
                                <div className="col-span-2">{getStatusBadge(incident.status)}</div>
                                <div className="col-span-2">
                                    <span className="text-sm text-slate-400">{incident.reportedBy}</span>
                                </div>
                                <div className="col-span-1">
                                    <span className="text-xs text-slate-500">{incident.date}</span>
                                </div>
                                <div className="col-span-1">
                                    <button className="rounded-lg p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white">
                                        <AlertCircle className="size-4" />
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
