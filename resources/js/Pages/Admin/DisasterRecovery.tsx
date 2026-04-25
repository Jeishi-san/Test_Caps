import AdminLayout from '@/Admin/AdminLayout';
import { Head } from '@inertiajs/react';
import { AlertTriangle, Plus, RefreshCw, CheckCircle, XCircle, Download, Trash2 } from 'lucide-react';
import { useState } from 'react';

type Backup = {
    id: string;
    date: string;
    size: string;
    status: 'complete' | 'in_progress' | 'failed';
};

type AuditLog = {
    timestamp: string;
    user: string;
    action: string;
    details: string;
    ip: string;
};

const mockBackups: Backup[] = [
    { id: 'BKP-001', date: '2024-01-15', size: '2.4 GB', status: 'complete' },
    { id: 'BKP-002', date: '2024-01-14', size: '2.3 GB', status: 'complete' },
    { id: 'BKP-003', date: '2024-01-13', size: '2.2 GB', status: 'failed' },
    { id: 'BKP-004', date: '2024-01-12', size: '2.1 GB', status: 'in_progress' },
];

const mockAuditLogs: AuditLog[] = [
    { timestamp: '2024-01-15 14:32:15', user: 'John Doe', action: 'Backup Created', details: 'Created backup BKP-001', ip: '192.168.1.105' },
    { timestamp: '2024-01-14 10:15:22', user: 'System', action: 'Backup Restore', details: 'Restored from backup BKP-002', ip: '127.0.0.1' },
    { timestamp: '2024-01-13 08:45:10', user: 'Jane Smith', action: 'Backup Failed', details: 'Backup BKP-003 failed - insufficient storage', ip: '10.0.0.42' },
];

export default function DisasterRecovery() {
    const [backups] = useState<Backup[]>(mockBackups);
    const [auditLogs] = useState<AuditLog[]>(mockAuditLogs);

    const getBackupStatusBadge = (status: string) => {
        switch (status) {
            case 'complete':
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-md border border-green-600/30 bg-green-600/20 px-2 py-1 text-xs font-medium text-green-400">
                        <CheckCircle className="size-3" /> Complete
                    </span>
                );
            case 'in_progress':
                return (
                    <span className="inline-flex items-center gap-1.5 rounded-md border border-yellow-600/30 bg-yellow-600/20 px-2 py-1 text-xs font-medium text-yellow-400">
                        <RefreshCw className="size-3" /> In Progress
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

    return (
        <AdminLayout>
            <Head title="Disaster Recovery" />
            <div className="space-y-6">
                {/* Page Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-white">Disaster Recovery</h1>
                        <p className="mt-1 text-sm text-slate-400">Manage system backups and audit logs (Superadmin only)</p>
                    </div>
                    <button className="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-red-500/30 transition hover:bg-red-700">
                        <Plus className="size-4" />
                        Create Backup
                    </button>
                </div>

                {/* Warning Banner */}
                <div className="flex items-start gap-3 rounded-xl border border-red-600/30 bg-red-600/10 p-4">
                    <AlertTriangle className="mt-0.5 size-5 shrink-0 text-red-400" />
                    <p className="text-sm text-red-200">
                        StegoLock uses zero-knowledge encryption. Backup keys cannot be used to decrypt data without user passwords.
                    </p>
                </div>

                {/* Backup Management */}
                <div className="flex gap-3">
                    <button className="inline-flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-800 px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-700">
                        <Download className="size-4" />
                        Restore from Backup
                    </button>
                    <button className="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-red-500/30 transition hover:bg-red-700">
                        <Plus className="size-4" />
                        Create New Backup
                    </button>
                </div>

                {/* Backup History Table */}
                <div className="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900/50">
                    <div className="px-6 py-4">
                        <h2 className="text-lg font-semibold text-white">Backup History</h2>
                    </div>
                    {/* Header Row */}
                    <div className="grid grid-cols-12 gap-4 bg-slate-800/50 px-6 py-4">
                        <div className="col-span-2 text-xs font-semibold text-slate-400 uppercase">Backup ID</div>
                        <div className="col-span-2 text-xs font-semibold text-slate-400 uppercase">Date</div>
                        <div className="col-span-2 text-xs font-semibold text-slate-400 uppercase">Size</div>
                        <div className="col-span-2 text-xs font-semibold text-slate-400 uppercase">Status</div>
                        <div className="col-span-3 text-xs font-semibold text-slate-400 uppercase">Actions</div>
                    </div>

                    {/* Data Rows */}
                    <div className="divide-y divide-slate-800">
                        {backups.map((backup) => (
                            <div key={backup.id} className="grid grid-cols-12 items-center gap-4 px-6 py-4 transition hover:bg-slate-800/30">
                                <div className="col-span-2">
                                    <span className="text-sm font-mono text-slate-300">{backup.id}</span>
                                </div>
                                <div className="col-span-2">
                                    <span className="text-sm text-white">{backup.date}</span>
                                </div>
                                <div className="col-span-2">
                                    <span className="text-sm text-slate-400">{backup.size}</span>
                                </div>
                                <div className="col-span-2">{getBackupStatusBadge(backup.status)}</div>
                                <div className="col-span-3 flex items-center gap-2">
                                    <button className="rounded-lg p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white">
                                        <Download className="size-4" />
                                    </button>
                                    <button className="rounded-lg p-2 text-slate-400 transition hover:bg-red-500/20 hover:text-red-400">
                                        <Trash2 className="size-4" />
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* System Audit Logs Table */}
                <div className="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900/50">
                    <div className="px-6 py-4">
                        <h2 className="text-lg font-semibold text-white">System Audit Logs</h2>
                    </div>
                    {/* Header Row */}
                    <div className="grid grid-cols-12 gap-4 bg-slate-800/50 px-6 py-4">
                        <div className="col-span-2 text-xs font-semibold text-slate-400 uppercase">Timestamp</div>
                        <div className="col-span-2 text-xs font-semibold text-slate-400 uppercase">User</div>
                        <div className="col-span-2 text-xs font-semibold text-slate-400 uppercase">Action</div>
                        <div className="col-span-3 text-xs font-semibold text-slate-400 uppercase">Details</div>
                        <div className="col-span-3 text-xs font-semibold text-slate-400 uppercase">IP Address</div>
                    </div>

                    {/* Data Rows */}
                    <div className="divide-y divide-slate-800">
                        {auditLogs.map((log, index) => (
                            <div key={index} className="grid grid-cols-12 items-center gap-4 px-6 py-4 transition hover:bg-slate-800/30">
                                <div className="col-span-2">
                                    <span className="text-xs text-slate-400 font-mono">{log.timestamp}</span>
                                </div>
                                <div className="col-span-2">
                                    <span className="text-sm text-white">{log.user}</span>
                                </div>
                                <div className="col-span-2">
                                    <span className="text-sm text-slate-300">{log.action}</span>
                                </div>
                                <div className="col-span-3">
                                    <span className="text-sm text-slate-400">{log.details}</span>
                                </div>
                                <div className="col-span-3">
                                    <span className="text-xs text-slate-500 font-mono">{log.ip}</span>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
