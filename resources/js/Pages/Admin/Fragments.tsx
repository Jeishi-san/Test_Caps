import AdminLayout from '@/Admin/AdminLayout';
import { Head } from '@inertiajs/react';
import { Database, CheckCircle, AlertTriangle, AlertOctagon, RefreshCw, Eye } from 'lucide-react';
import { useState } from 'react';

type Fragment = {
    id: string;
    document: string;
    status: 'verified' | 'warning' | 'critical';
    integrity: number;
    size: string;
    lastVerified: string;
};

const mockStats = [
    { label: 'Total Fragments', value: '1,284', icon: Database, color: 'green' },
    { label: 'Verified', value: '1,156', icon: CheckCircle, color: 'green' },
    { label: 'Warning', value: '98', icon: AlertTriangle, color: 'yellow' },
    { label: 'Critical', value: '30', icon: AlertOctagon, color: 'red' },
];

const mockFragments: Fragment[] = [
    { id: 'FRG-7A2B9C', document: 'Q4_Report.pdf', status: 'verified', integrity: 100, size: '2.4 MB', lastVerified: '2 min ago' },
    { id: 'FRG-3D8E1F', document: 'Budget_2024.xlsx', status: 'verified', integrity: 98, size: '1.8 MB', lastVerified: '5 min ago' },
    { id: 'FRG-9C1A4D', document: 'Contract.pdf', status: 'warning', integrity: 72, size: '3.2 MB', lastVerified: '1 hour ago' },
    { id: 'FRG-5B7E2A', document: 'Presentation.pptx', status: 'critical', integrity: 34, size: '5.1 MB', lastVerified: '3 hours ago' },
    { id: 'FRG-2F4C8E', document: 'Analysis.docx', status: 'verified', integrity: 100, size: '1.2 MB', lastVerified: '30 min ago' },
];

export default function Fragments() {
    const [fragments] = useState<Fragment[]>(mockFragments);

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'verified':
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-md border border-green-600/30 bg-green-600/20 px-2 py-1 text-xs font-medium text-green-400">
                        <CheckCircle className="size-3" /> Verified
                    </span>
                );
            case 'warning':
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-md border border-yellow-600/30 bg-yellow-600/20 px-2 py-1 text-xs font-medium text-yellow-400">
                        <AlertTriangle className="size-3" /> Warning
                    </span>
                );
            case 'critical':
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-md border border-red-600/30 bg-red-600/20 px-2 py-1 text-xs font-medium text-red-400">
                        <AlertOctagon className="size-3" /> Critical
                    </span>
                );
            default:
                return null;
        }
    };

    const getIntegrityBar = (integrity: number) => {
        const color = integrity >= 90 ? 'bg-green-500' : integrity >= 50 ? 'bg-yellow-500' : 'bg-red-500';
        return (
            <div className="flex items-center gap-3">
                <div className="h-2 w-24 rounded-full bg-slate-700">
                    <div className={`h-2 rounded-full ${color}`} style={{ width: `${integrity}%` }} />
                </div>
                <span className="text-xs text-slate-400 font-mono">{integrity}%</span>
            </div>
        );
    };

    return (
        <AdminLayout>
            <Head title="Fragment Monitoring" />
            <div className="space-y-6">
                {/* Page Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-white">Fragment Monitoring</h1>
                        <p className="mt-1 text-sm text-slate-400">Monitor encrypted document fragments and integrity</p>
                    </div>
                    <button className="inline-flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-800 px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-700">
                        <RefreshCw className="size-4" />
                        Refresh
                    </button>
                </div>

                {/* Stats Cards */}
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

                {/* Fragments Table */}
                <div className="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900/50">
                    {/* Header Row */}
                    <div className="grid grid-cols-12 gap-4 bg-slate-800/50 px-6 py-4">
                        <div className="col-span-2 text-xs font-semibold text-slate-400 uppercase">Fragment ID</div>
                        <div className="col-span-3 text-xs font-semibold text-slate-400 uppercase">Document</div>
                        <div className="col-span-2 text-xs font-semibold text-slate-400 uppercase">Status</div>
                        <div className="col-span-2 text-xs font-semibold text-slate-400 uppercase">Integrity</div>
                        <div className="col-span-1 text-xs font-semibold text-slate-400 uppercase">Size</div>
                        <div className="col-span-1 text-xs font-semibold text-slate-400 uppercase">Verified</div>
                        <div className="col-span-1 text-xs font-semibold text-slate-400 uppercase">Actions</div>
                    </div>

                    {/* Data Rows */}
                    <div className="divide-y divide-slate-800">
                        {fragments.map((fragment) => (
                            <div key={fragment.id} className="grid grid-cols-12 items-center gap-4 px-6 py-4 transition hover:bg-slate-800/30">
                                <div className="col-span-2">
                                    <span className="text-sm font-mono text-slate-300">{fragment.id}</span>
                                </div>
                                <div className="col-span-3">
                                    <span className="text-sm text-white">{fragment.document}</span>
                                </div>
                                <div className="col-span-2">{getStatusBadge(fragment.status)}</div>
                                <div className="col-span-2">{getIntegrityBar(fragment.integrity)}</div>
                                <div className="col-span-1">
                                    <span className="text-sm text-slate-400">{fragment.size}</span>
                                </div>
                                <div className="col-span-1">
                                    <span className="text-xs text-slate-500">{fragment.lastVerified}</span>
                                </div>
                                <div className="col-span-1">
                                    <button className="rounded-lg p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white">
                                        <Eye className="size-4" />
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
