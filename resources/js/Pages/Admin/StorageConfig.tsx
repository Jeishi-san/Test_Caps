import AdminLayout from '@/Admin/AdminLayout';
import { Head } from '@inertiajs/react';
import { HardDrive, Database, CheckCircle, FileText, Activity, Clock, Eye, EyeOff, Save } from 'lucide-react';
import { useState } from 'react';

export default function StorageConfig() {
    const [provider, setProvider] = useState('b2');
    const [apiKey, setApiKey] = useState('');
    const [showApiKey, setShowApiKey] = useState(false);
    const [bucket, setBucket] = useState('');
    const [region, setRegion] = useState('');

    const storageHealth = [
        { label: 'Total Storage', value: '2.5 TB', icon: HardDrive, color: 'blue' },
        { label: 'Used Storage', value: '1.2 TB', icon: Database, color: 'orange' },
        { label: 'Available', value: '1.3 TB', icon: CheckCircle, color: 'green' },
        { label: 'Fragment Count', value: '12,485', icon: FileText, color: 'purple' },
        { label: 'Avg Integrity', value: '98.5%', icon: Activity, color: 'green' },
        { label: 'Last Backup', value: '2 hours ago', icon: Clock, color: 'yellow' },
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        console.log('Saving storage configuration...');
    };

    const handleTestConnection = () => {
        console.log('Testing connection...');
    };

    return (
        <AdminLayout>
            <Head title="Storage Configuration" />
            <div className="space-y-6">
                {/* Page Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-white">Storage Configuration</h1>
                        <p className="mt-1 text-sm text-slate-400">Configure cloud storage provider settings (Superadmin only)</p>
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
                    {/* Provider Settings */}
                    <div className="rounded-xl border border-slate-800 bg-slate-900/50 p-6">
                        <h2 className="mb-4 text-lg font-semibold text-white">Provider Settings</h2>
                        <div className="grid gap-6 md:grid-cols-2">
                            {/* Provider */}
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-slate-300">Provider</label>
                                <select
                                    value={provider}
                                    onChange={(e) => setProvider(e.target.value)}
                                    className="w-full rounded-xl border border-slate-800 bg-slate-800/50 py-2.5 px-4 text-sm text-white focus:border-red-500/50 focus:ring-2 focus:ring-red-500/50 focus:outline-none"
                                >
                                    <option value="b2">Backblaze B2</option>
                                    <option value="s3">AWS S3</option>
                                    <option value="azure">Azure Blob</option>
                                </select>
                            </div>

                            {/* API Key */}
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-slate-300">API Key</label>
                                <div className="relative">
                                    <input
                                        type={showApiKey ? 'text' : 'password'}
                                        value={apiKey}
                                        onChange={(e) => setApiKey(e.target.value)}
                                        placeholder="Enter API key"
                                        className="w-full rounded-xl border border-slate-800 bg-slate-800/50 py-2.5 pl-4 pr-10 text-sm text-white placeholder:text-slate-500 focus:border-red-500/50 focus:ring-2 focus:ring-red-500/50 focus:outline-none"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowApiKey(!showApiKey)}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 transition hover:text-white"
                                    >
                                        {showApiKey ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                                    </button>
                                </div>
                            </div>

                            {/* Bucket Name */}
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-slate-300">Bucket Name</label>
                                <input
                                    type="text"
                                    value={bucket}
                                    onChange={(e) => setBucket(e.target.value)}
                                    placeholder="my-secure-bucket"
                                    className="w-full rounded-xl border border-slate-800 bg-slate-800/50 py-2.5 px-4 text-sm text-white placeholder:text-slate-500 focus:border-red-500/50 focus:ring-2 focus:ring-red-500/50 focus:outline-none"
                                />
                            </div>

                            {/* Region */}
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-slate-300">Region</label>
                                <input
                                    type="text"
                                    value={region}
                                    onChange={(e) => setRegion(e.target.value)}
                                    placeholder="us-west-001"
                                    className="w-full rounded-xl border border-slate-800 bg-slate-800/50 py-2.5 px-4 text-sm text-white placeholder:text-slate-500 focus:border-red-500/50 focus:ring-2 focus:ring-red-500/50 focus:outline-none"
                                />
                            </div>
                        </div>

                        {/* Test Connection Button */}
                        <div className="mt-4">
                            <button
                                type="button"
                                onClick={handleTestConnection}
                                className="inline-flex items-center gap-2 rounded-xl border border-slate-800 bg-slate-800 px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-700"
                            >
                                Test Connection
                            </button>
                        </div>
                    </div>

                    {/* Storage Health Monitoring */}
                    <div className="rounded-xl border border-slate-800 bg-slate-900/50 p-6">
                        <h2 className="mb-4 text-lg font-semibold text-white">Storage Health Monitoring</h2>
                        <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                            {storageHealth.map((stat) => {
                                const Icon = stat.icon;
                                return (
                                    <div key={stat.label} className="rounded-lg border border-slate-800 bg-slate-800/30 p-4">
                                        <div className="flex items-center gap-3">
                                            <div className={`rounded-lg p-2 bg-${stat.color}-600/20 text-${stat.color}-400`}>
                                                <Icon className="size-5" />
                                            </div>
                                            <div>
                                                <p className="text-sm text-slate-400">{stat.label}</p>
                                                <p className="text-xl font-bold text-white">{stat.value}</p>
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
