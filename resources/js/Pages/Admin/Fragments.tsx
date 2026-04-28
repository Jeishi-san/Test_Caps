import AdminLayout from '@/Admin/AdminLayout';
import { Head } from '@inertiajs/react';
import { Database, CheckCircle, AlertTriangle, AlertOctagon, RefreshCw, Eye } from 'lucide-react';
import { useState, useEffect } from 'react';
import axios from 'axios';
import { useToast } from '@/Components/Toast';

type Carrier = {
    id: number;
    name: string;
    validation_status: string;
    psnr: number | null;
    size: string;
};

export default function Fragments() {
    const [carriers, setCarriers] = useState<Carrier[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const { error: showError } = useToast();

    const fetchFragments = async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await axios.get('/api/admin/fragments');
            const data = response.data;
            // Map carriers to display format
            const mappedCarriers = (data.carriers?.data || []).map((carrier: any) => ({
                id: `FRG-${carrier.id.toString(16).toUpperCase().padStart(6, '0')}`,
                document: carrier.name,
                status: carrier.validation_status === 'validated' ? 'verified' : 
                        carrier.validation_status === 'pending' ? 'warning' : 'critical',
                integrity: carrier.psnr || 0,
                size: `${(carrier.size / (1024 * 1024)).toFixed(1)} MB`,
                lastVerified: carrier.validated_at ? new Date(carrier.validated_at).toLocaleString() : 'Never',
            }));
            setCarriers(mappedCarriers);
        } catch (err: any) {
            const errorMsg = err.response?.data?.message || 'Failed to fetch fragment data';
            setError(errorMsg);
            showError(errorMsg);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchFragments();
    }, []);

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
                    <button
                        onClick={fetchFragments}
                        disabled={loading}
                        className="inline-flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-800 px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-700 disabled:opacity-50"
                    >
                        <RefreshCw className="size-4" />
                        {loading ? 'Refreshing...' : 'Refresh'}
                    </button>
                </div>

                {/* Error Message */}
                {error && (
                    <div className="flex items-center gap-3 rounded-xl border border-red-600/30 bg-red-600/10 p-4">
                        <AlertTriangle className="size-5 text-red-400" />
                        <p className="text-sm text-red-200">{error}</p>
                        <button onClick={fetchFragments} className="ml-auto text-sm text-red-400 hover:text-red-300">
                            Retry
                        </button>
                    </div>
                )}

                {/* Stats Cards */}
                <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                    {[
                        { label: 'Total Fragments', value: carriers.length.toString(), icon: Database, color: 'green' },
                        { label: 'Verified', value: carriers.filter(c => c.status === 'verified').length.toString(), icon: CheckCircle, color: 'green' },
                        { label: 'Warning', value: carriers.filter(c => c.status === 'warning').length.toString(), icon: AlertTriangle, color: 'yellow' },
                        { label: 'Critical', value: carriers.filter(c => c.status === 'critical').length.toString(), icon: AlertOctagon, color: 'red' },
                    ].map((stat) => {
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
                    {loading ? (
                        <div className="px-6 py-8 text-center text-sm text-slate-400">Loading fragments...</div>
                    ) : carriers.length === 0 ? (
                        <div className="px-6 py-8 text-center text-sm text-slate-400">No fragments found</div>
                    ) : (
                        <div className="divide-y divide-slate-800">
                            {carriers.map((carrier) => (
                                <div key={carrier.id} className="grid grid-cols-12 items-center gap-4 px-6 py-4 transition hover:bg-slate-800/30">
                                    <div className="col-span-2">
                                        <span className="text-sm font-mono text-slate-300">{carrier.id}</span>
                                    </div>
                                    <div className="col-span-3">
                                        <span className="text-sm text-white">{carrier.document}</span>
                                    </div>
                                    <div className="col-span-2">{getStatusBadge(carrier.status)}</div>
                                    <div className="col-span-2">{getIntegrityBar(carrier.integrity)}</div>
                                    <div className="col-span-1">
                                        <span className="text-sm text-slate-400">{carrier.size}</span>
                                    </div>
                                    <div className="col-span-1">
                                        <span className="text-xs text-slate-500">{carrier.lastVerified}</span>
                                    </div>
                                    <div className="col-span-1">
                                        <button 
                                            onClick={() => alert(`Carrier details:\nName: ${carrier.document}\nStatus: ${carrier.status}\nPSNR: ${carrier.integrity}dB`)}
                                            className="rounded-lg p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white"
                                        >
                                            <Eye className="size-4" />
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
