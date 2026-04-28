import AdminLayout from '@/Admin/AdminLayout';
import { Head } from '@inertiajs/react';
import { AlertTriangle, Save, Key, CheckCircle, RefreshCw, XCircle, AlertCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import axios from 'axios';
import { useToast } from '@/Components/Toast';

interface KeyStats {
    total_keys: number;
    active_keys: number;
    rotated_keys: number;
    expired_keys: number;
}

export default function KeyManagement() {
    const [minLength, setMinLength] = useState(8);
    const [requireUppercase, setRequireUppercase] = useState(true);
    const [requireNumbers, setRequireNumbers] = useState(true);
    const [requireSpecial, setRequireSpecial] = useState(false);
    const [autoRotation, setAutoRotation] = useState(false);
    const [rotationInterval, setRotationInterval] = useState(90);
    const [keyStats, setKeyStats] = useState<KeyStats | null>(null);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const { success, error: showError } = useToast();

    const fetchPolicy = async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await axios.get('/api/admin/key-management');
            const data = response.data;
            setMinLength(data.password_min_length ?? 8);
            setRequireUppercase(data.password_require_uppercase ?? true);
            setRequireNumbers(data.password_require_number ?? true);
            setRequireSpecial(data.password_require_special ?? false);
            setAutoRotation(data.key_rotation_enabled ?? false);
            setRotationInterval(data.key_rotation_interval_days ?? 90);
        } catch (err: any) {
            const errorMsg = err.response?.data?.message || 'Failed to fetch key management policy';
            setError(errorMsg);
            showError(errorMsg);
        } finally {
            setLoading(false);
        }
    };


    useEffect(() => {
        fetchPolicy();
    }, []);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setError(null);

        try {
            await axios.put('/api/admin/key-management', {
                password_min_length: minLength,
                password_require_uppercase: requireUppercase,
                password_require_number: requireNumbers,
                password_require_special: requireSpecial,
                key_rotation_enabled: autoRotation,
                key_rotation_interval_days: rotationInterval,
            });
            success('Key management policy saved successfully!');
        } catch (err: any) {
            const errorMsg = err.response?.data?.message || 'Failed to save key management policy. Please try again.';
            setError(errorMsg);
            showError(errorMsg);
        } finally {
            setSaving(false);
        }
    };

    const displayStats = keyStats ? [
        { label: 'Total Keys', value: keyStats.total_keys, icon: Key, color: 'blue' as const },
        { label: 'Active Keys', value: keyStats.active_keys, icon: CheckCircle, color: 'green' as const },
        { label: 'Rotated Keys', value: keyStats.rotated_keys, icon: RefreshCw, color: 'yellow' as const },
        { label: 'Expired Keys', value: keyStats.expired_keys, icon: XCircle, color: 'red' as const },
    ] : [
        { label: 'Total Keys', value: '...', icon: Key, color: 'blue' as const },
        { label: 'Active Keys', value: '...', icon: CheckCircle, color: 'green' as const },
        { label: 'Rotated Keys', value: '...', icon: RefreshCw, color: 'yellow' as const },
        { label: 'Expired Keys', value: '...', icon: XCircle, color: 'red' as const },
    ];

    return (
        <AdminLayout>
            <Head title="Key Management Policy" />
            <div className="space-y-6">
                {/* Page Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-white">Key Management Policy</h1>
                        <p className="mt-1 text-sm text-slate-400">Configure key management and password policies (Superadmin only)</p>
                    </div>
                    <button
                        onClick={handleSubmit}
                        disabled={saving || loading}
                        className="inline-flex items-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-red-500/30 transition hover:bg-red-700 disabled:opacity-50"
                    >
                        <Save className="size-4" />
                        {saving ? 'Saving...' : 'Save Changes'}
                    </button>
                </div>

                {/* Error Message */}
                {error && (
                    <div className="flex items-center gap-3 rounded-xl border border-red-600/30 bg-red-600/10 p-4">
                        <AlertCircle className="size-5 text-red-400" />
                        <p className="text-sm text-red-200">{error}</p>
                        <button onClick={fetchPolicy} className="ml-auto text-sm text-red-400 hover:text-red-300">
                            Retry
                        </button>
                    </div>
                )}

                {/* Zero-Knowledge Banner */}
                <div className="flex items-start gap-3 rounded-xl border border-red-600/30 bg-red-600/10 p-4">
                    <AlertTriangle className="mt-0.5 size-5 shrink-0 text-red-400" />
                    <p className="text-sm text-red-200">
                        StegoLock uses zero-knowledge encryption. Private keys cannot be recovered if lost.
                    </p>
                </div>

                {loading ? (
                    <div className="rounded-xl border border-slate-800 bg-slate-900/50 p-8 text-center">
                        <div className="text-slate-400">Loading policy...</div>
                    </div>
                ) : (
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Password Strength Rules */}
                        <div className="rounded-xl border border-slate-800 bg-slate-900/50 p-6">
                            <h2 className="mb-4 text-lg font-semibold text-white">Password Strength Rules</h2>
                            <div className="grid gap-6 md:grid-cols-2">
                                <div>
                                    <label className="mb-1.5 block text-sm font-medium text-slate-300">Min Length</label>
                                    <input
                                        type="number"
                                        value={minLength}
                                        onChange={(e) => setMinLength(parseInt(e.target.value) || 8)}
                                        min={6}
                                        max={128}
                                        className="w-full rounded-xl border border-slate-800 bg-slate-800/50 py-2.5 px-4 text-sm text-white focus:border-red-500/50 focus:ring-2 focus:ring-red-500/50 focus:outline-none"
                                    />
                                </div>
                                <div className="space-y-3">
                                    <label className="block text-sm font-medium text-slate-300">Requirements</label>
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            id="uppercase"
                                            checked={requireUppercase}
                                            onChange={(e) => setRequireUppercase(e.target.checked)}
                                            className="size-4 rounded border-slate-700 bg-slate-800 text-red-600 focus:ring-2 focus:ring-red-500/50"
                                        />
                                        <label htmlFor="uppercase" className="text-sm text-slate-300">Require Uppercase</label>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            id="numbers"
                                            checked={requireNumbers}
                                            onChange={(e) => setRequireNumbers(e.target.checked)}
                                            className="size-4 rounded border-slate-700 bg-slate-800 text-red-600 focus:ring-2 focus:ring-red-500/50"
                                        />
                                        <label htmlFor="numbers" className="text-sm text-slate-300">Require Numbers</label>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            id="special"
                                            checked={requireSpecial}
                                            onChange={(e) => setRequireSpecial(e.target.checked)}
                                            className="size-4 rounded border-slate-700 bg-slate-800 text-red-600 focus:ring-2 focus:ring-red-500/50"
                                        />
                                        <label htmlFor="special" className="text-sm text-slate-300">Require Special Characters</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Key Rotation Policy */}
                        <div className="rounded-xl border border-slate-800 bg-slate-900/50 p-6">
                            <h2 className="mb-4 text-lg font-semibold text-white">Key Rotation Policy</h2>
                            <div className="grid gap-6 md:grid-cols-3">
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setAutoRotation(!autoRotation)}
                                        className={`relative inline-flex h-5 w-9 items-center rounded-full transition ${autoRotation ? 'bg-red-600' : 'bg-slate-600'}`}
                                    >
                                        <span
                                            className={`inline-block h-3 w-3 rounded-full bg-white transition ${autoRotation ? 'translate-x-5' : 'translate-x-1'}`}
                                        />
                                    </button>
                                    <label className="text-sm text-slate-300">Auto Rotation</label>
                                </div>
                                <div>
                                    <label className="mb-1.5 block text-sm font-medium text-slate-300">Rotation Interval (days)</label>
                                    <input
                                        type="number"
                                        value={rotationInterval}
                                        onChange={(e) => setRotationInterval(parseInt(e.target.value) || 90)}
                                        min={1}
                                        max={365}
                                        className="w-full rounded-xl border border-slate-800 bg-slate-800/50 py-2.5 px-4 text-sm text-white focus:border-red-500/50 focus:ring-2 focus:ring-red-500/50 focus:outline-none"
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Key Statistics */}
                        <div className="rounded-xl border border-slate-800 bg-slate-900/50 p-6">
                            <h2 className="mb-4 text-lg font-semibold text-white">Key Statistics</h2>
                            <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                                {displayStats.map((stat) => {
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
                                disabled={saving}
                                className="inline-flex items-center gap-2 rounded-xl bg-red-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-red-500/30 transition hover:bg-red-700 disabled:opacity-50"
                            >
                                <Save className="size-4" />
                                {saving ? 'Saving...' : 'Save Changes'}
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </AdminLayout>
    );
}
