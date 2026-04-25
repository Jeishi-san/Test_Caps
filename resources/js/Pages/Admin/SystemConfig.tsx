import AdminLayout from '@/Admin/AdminLayout';
import { Head } from '@inertiajs/react';
import { Save, Info, Monitor, Code } from 'lucide-react';
import { useState } from 'react';

export default function SystemConfig() {
    const [maintenanceMode, setMaintenanceMode] = useState(false);
    const [maintenanceMessage, setMaintenanceMessage] = useState('');
    const [maxFileSize, setMaxFileSize] = useState('100');
    const [maxFragments, setMaxFragments] = useState('10000');
    const [maxStorage, setMaxStorage] = useState('10');
    const [sessionTimeout, setSessionTimeout] = useState('120');
    const [maxLoginAttempts, setMaxLoginAttempts] = useState('5');
    const [lockoutDuration, setLockoutDuration] = useState('15');

    const systemInfo = [
        { label: 'App Version', value: '1.0.0', icon: Info, color: 'blue' },
        { label: 'Environment', value: 'Production', icon: Monitor, color: 'green' },
        { label: 'PHP Version', value: '8.2.0', icon: Code, color: 'purple' },
        { label: 'Laravel Version', value: '10.x', icon: Code, color: 'red' },
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        console.log('Saving system configuration...');
    };

    return (
        <AdminLayout>
            <Head title="System Configuration" />
            <div className="space-y-6">
                {/* Page Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-white">System Configuration</h1>
                        <p className="mt-1 text-sm text-slate-400">Configure system-wide settings (Superadmin only)</p>
                    </div>
                    <button
                        onClick={handleSubmit}
                        className="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-red-500/30 transition hover:bg-red-700"
                    >
                        <Save className="size-4" />
                        Save Changes
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Maintenance Mode */}
                    <div className="rounded-xl border border-slate-800 bg-slate-900/50 p-6">
                        <h2 className="mb-4 text-lg font-semibold text-white">Maintenance Mode</h2>
                        <div className="space-y-4">
                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    onClick={() => setMaintenanceMode(!maintenanceMode)}
                                    className={`relative inline-flex h-5 w-9 items-center rounded-full transition ${
                                        maintenanceMode ? 'bg-red-600' : 'bg-slate-600'
                                    }`}
                                >
                                    <span
                                        className={`inline-block h-3 w-3 rounded-full bg-white transition ${
                                            maintenanceMode ? 'translate-x-5' : 'translate-x-1'
                                        }`}
                                    />
                                </button>
                                <label className="text-sm text-slate-300">Enable Maintenance Mode</label>
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-slate-300">Maintenance Message</label>
                                <textarea
                                    value={maintenanceMessage}
                                    onChange={(e) => setMaintenanceMessage(e.target.value)}
                                    placeholder="System is under maintenance. Please try again later."
                                    rows={3}
                                    className="w-full rounded-xl border border-slate-800 bg-slate-800/50 p-3 text-sm text-white placeholder:text-slate-500 focus:border-red-500/50 focus:ring-2 focus:ring-red-500/50 focus:outline-none"
                                />
                            </div>
                        </div>
                    </div>

                    {/* File/Fragment Limits */}
                    <div className="rounded-xl border border-slate-800 bg-slate-900/50 p-6">
                        <h2 className="mb-4 text-lg font-semibold text-white">File/Fragment Limits</h2>
                        <div className="grid gap-6 md:grid-cols-3">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-slate-300">Max File Size (MB)</label>
                                <input
                                    type="number"
                                    value={maxFileSize}
                                    onChange={(e) => setMaxFileSize(e.target.value)}
                                    className="w-full rounded-xl border border-slate-800 bg-slate-800/50 py-2.5 px-4 text-sm text-white focus:border-red-500/50 focus:ring-2 focus:ring-red-500/50 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-slate-300">Max Fragments</label>
                                <input
                                    type="number"
                                    value={maxFragments}
                                    onChange={(e) => setMaxFragments(e.target.value)}
                                    className="w-full rounded-xl border border-slate-800 bg-slate-800/50 py-2.5 px-4 text-sm text-white focus:border-red-500/50 focus:ring-2 focus:ring-red-500/50 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-slate-300">Max Storage Per User (GB)</label>
                                <input
                                    type="number"
                                    value={maxStorage}
                                    onChange={(e) => setMaxStorage(e.target.value)}
                                    className="w-full rounded-xl border border-slate-800 bg-slate-800/50 py-2.5 px-4 text-sm text-white focus:border-red-500/50 focus:ring-2 focus:ring-red-500/50 focus:outline-none"
                                />
                            </div>
                        </div>
                    </div>

                    {/* User/Session Settings */}
                    <div className="rounded-xl border border-slate-800 bg-slate-900/50 p-6">
                        <h2 className="mb-4 text-lg font-semibold text-white">User/Session Settings</h2>
                        <div className="grid gap-6 md:grid-cols-3">
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-slate-300">Session Timeout (min)</label>
                                <input
                                    type="number"
                                    value={sessionTimeout}
                                    onChange={(e) => setSessionTimeout(e.target.value)}
                                    className="w-full rounded-xl border border-slate-800 bg-slate-800/50 py-2.5 px-4 text-sm text-white focus:border-red-500/50 focus:ring-2 focus:ring-red-500/50 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-slate-300">Max Login Attempts</label>
                                <input
                                    type="number"
                                    value={maxLoginAttempts}
                                    onChange={(e) => setMaxLoginAttempts(e.target.value)}
                                    className="w-full rounded-xl border border-slate-800 bg-slate-800/50 py-2.5 px-4 text-sm text-white focus:border-red-500/50 focus:ring-2 focus:ring-red-500/50 focus:outline-none"
                                />
                            </div>
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-slate-300">Lockout Duration (min)</label>
                                <input
                                    type="number"
                                    value={lockoutDuration}
                                    onChange={(e) => setLockoutDuration(e.target.value)}
                                    className="w-full rounded-xl border border-slate-800 bg-slate-800/50 py-2.5 px-4 text-sm text-white focus:border-red-500/50 focus:ring-2 focus:ring-red-500/50 focus:outline-none"
                                />
                            </div>
                        </div>
                    </div>

                    {/* System Information */}
                    <div className="rounded-xl border border-slate-800 bg-slate-900/50 p-6">
                        <h2 className="mb-4 text-lg font-semibold text-white">System Information</h2>
                        <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                            {systemInfo.map((info) => {
                                const Icon = info.icon;
                                return (
                                    <div key={info.label} className="rounded-lg border border-slate-800 bg-slate-800/30 p-4">
                                        <div className="flex items-center gap-3">
                                            <div className={`rounded-lg p-2 bg-${info.color}-600/20 text-${info.color}-400`}>
                                                <Icon className="size-5" />
                                            </div>
                                            <div>
                                                <p className="text-sm text-slate-400">{info.label}</p>
                                                <p className="text-xl font-bold text-white">{info.value}</p>
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Save Button (Bottom) */}
                    <div className="flex justify-end">
                        <button
                            type="submit"
                            className="inline-flex items-center gap-2 rounded-xl bg-red-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-red-500/30 transition hover:bg-red-700"
                        >
                            <Save className="size-4" />
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
