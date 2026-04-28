import AdminLayout from '@/Admin/AdminLayout';
import { Head } from '@inertiajs/react';
import { AlertTriangle, Save } from 'lucide-react';
import { useState } from 'react';
import axios from 'axios';
import { useToast } from '@/Components/Toast';

export default function EncryptionPolicy() {
    const [aesMode] = useState('AES-256-GCM');
    const [kdfIterations, setKdfIterations] = useState('100000');
    const [fragmentSize, setFragmentSize] = useState('1024');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const { success, error: showError } = useToast();

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setError(null);

        try {
            await axios.put('/api/admin/encryption-policy', {
                mkd_iterations: parseInt(kdfIterations) || 100000,
                fragment_size: parseInt(fragmentSize) || 1024,
            });
            success('Encryption policy saved successfully!');
        } catch (err: any) {
            const errorMsg = err.response?.data?.message || 'Failed to save encryption policy. Please try again.';
            setError(errorMsg);
            showError(errorMsg);
        } finally {
            setSaving(false);
        }
    };

    return (
        <AdminLayout>
            <Head title="Encryption Policy" />
            <div className="space-y-6">
                {/* Page Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-white">Encryption Policy</h1>
                        <p className="mt-1 text-sm text-slate-400">Configure system-wide encryption settings (Superadmin only)</p>
                    </div>
                    <button
                        onClick={handleSubmit}
                        disabled={saving}
                        className="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-red-500/30 transition hover:bg-red-700 disabled:opacity-50"
                    >
                        <Save className="size-4" />
                        {saving ? 'Saving...' : 'Save Changes'}
                    </button>
                </div>

                {/* Warning Banner */}
                <div className="flex items-start gap-3 rounded-xl border border-yellow-600/30 bg-yellow-600/10 p-4">
                    <AlertTriangle className="mt-0.5 size-5 shrink-0 text-yellow-400" />
                    <p className="text-sm text-yellow-200">
                        Encryption settings are critical system configurations. Changes may affect existing encrypted data.
                    </p>
                </div>

                {/* Error Message */}
                {error && (
                    <div className="flex items-center gap-3 rounded-xl border border-red-600/30 bg-red-600/10 p-4">
                        <AlertTriangle className="size-5 text-red-400" />
                        <p className="text-sm text-red-200">{error}</p>
                        <button onClick={() => setError(null)} className="ml-auto text-sm text-red-400 hover:text-red-300">
                            Dismiss
                        </button>
                    </div>
                )}

                {/* Form */}
                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="rounded-xl border border-slate-800 bg-slate-900/50 p-6">
                        <div className="grid gap-6 md:grid-cols-2">
                            {/* AES Mode (Read-only) */}
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-slate-300">AES Mode</label>
                                <input
                                    type="text"
                                    value={aesMode}
                                    readOnly
                                    className="w-full cursor-not-allowed rounded-xl border border-slate-800 bg-slate-800/50 py-2.5 px-4 text-sm text-slate-400"
                                />
                            </div>


                            {/* KDF Iterations */}
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-slate-300">KDF Iterations</label>
                                <input
                                    type="number"
                                    value={kdfIterations}
                                    onChange={(e) => setKdfIterations(e.target.value)}
                                    className="w-full rounded-xl border border-slate-800 bg-slate-800/50 py-2.5 px-4 text-sm text-white placeholder:text-slate-500 focus:border-red-500/50 focus:ring-2 focus:ring-red-500/50 focus:outline-none"
                                />
                            </div>

                            {/* Fragment Size */}
                            <div>
                                <label className="mb-1.5 block text-sm font-medium text-slate-300">Fragment Size (KB)</label>
                                <input
                                    type="number"
                                    value={fragmentSize}
                                    onChange={(e) => setFragmentSize(e.target.value)}
                                    className="w-full rounded-xl border border-slate-800 bg-slate-800/50 py-2.5 px-4 text-sm text-white placeholder:text-slate-500 focus:border-red-500/50 focus:ring-2 focus:ring-red-500/50 focus:outline-none"
                                />
                            </div>
                        </div>
                    </div>

                    {/* Save Button (Bottom) */}
                    <div className="flex justify-end">
                        <button
                            type="submit"
                            disabled={saving}
                            className="inline-flex items-center gap-2 rounded-xl bg-red-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-red-500/30 transition hover:bg-red-700 disabled:opacity-50"
                        >
                            <Save className="size-4" />
                            {saving ? 'Saving...' : 'Save Changes'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
