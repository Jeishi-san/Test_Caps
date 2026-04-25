import { useState } from 'react';
import { X, User, Mail, Lock, Eye, EyeOff } from 'lucide-react';

type CreateAdminModalProps = {
    isOpen: boolean;
    onClose: () => void;
    onSubmit?: (data: { name: string; email: string; password: string; role: string; mfaEnabled: boolean }) => void;
};

export default function CreateAdminModal({ isOpen, onClose, onSubmit }: CreateAdminModalProps) {
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [showPassword, setShowPassword] = useState(false);
    const [role, setRole] = useState('admin');
    const [mfaEnabled, setMfaEnabled] = useState(false);

    if (!isOpen) return null;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        onSubmit?.({ name, email, password, role, mfaEnabled });
        // Reset form
        setName('');
        setEmail('');
        setPassword('');
        setRole('admin');
        setMfaEnabled(false);
        onClose();
    };

    return (
        <div className="fixed inset-0 z-[150] flex items-center justify-center bg-black/60 backdrop-blur-sm" onClick={onClose}>
            <div
                className="w-full max-w-md rounded-xl border border-slate-800 bg-slate-900 shadow-2xl"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="flex items-center justify-between border-b border-slate-800 px-6 py-4">
                    <h2 className="text-lg font-semibold text-white">Create Admin</h2>
                    <button onClick={onClose} className="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-800 hover:text-white">
                        <X className="size-5" />
                    </button>
                </div>

                {/* Form */}
                <form onSubmit={handleSubmit} className="space-y-4 p-6">
                    {/* Full Name */}
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-slate-300">Full Name</label>
                        <div className="relative">
                            <User className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                placeholder="John Doe"
                                required
                                className="w-full rounded-xl border border-slate-800 bg-slate-800/50 py-2.5 pl-10 pr-4 text-sm text-white placeholder:text-slate-500 focus:border-red-500/50 focus:ring-2 focus:ring-red-500/50 focus:outline-none"
                            />
                        </div>
                    </div>

                    {/* Email */}
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-slate-300">Email Address</label>
                        <div className="relative">
                            <Mail className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                placeholder="john@example.com"
                                required
                                className="w-full rounded-xl border border-slate-800 bg-slate-800/50 py-2.5 pl-10 pr-4 text-sm text-white placeholder:text-slate-500 focus:border-red-500/50 focus:ring-2 focus:ring-red-500/50 focus:outline-none"
                            />
                        </div>
                    </div>

                    {/* Password */}
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-slate-300">Password</label>
                        <div className="relative">
                            <Lock className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type={showPassword ? 'text' : 'password'}
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                placeholder="Enter password"
                                required
                                className="w-full rounded-xl border border-slate-800 bg-slate-800/50 py-2.5 pl-10 pr-10 text-sm text-white placeholder:text-slate-500 focus:border-red-500/50 focus:ring-2 focus:ring-red-500/50 focus:outline-none"
                            />
                            <button
                                type="button"
                                onClick={() => setShowPassword(!showPassword)}
                                className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 transition hover:text-white"
                            >
                                {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                            </button>
                        </div>
                    </div>

                    {/* Role */}
                    <div>
                        <label className="mb-1.5 block text-sm font-medium text-slate-300">Role</label>
                        <select
                            value={role}
                            onChange={(e) => setRole(e.target.value)}
                            className="w-full rounded-xl border border-slate-800 bg-slate-800/50 py-2.5 px-4 text-sm text-white focus:border-red-500/50 focus:ring-2 focus:ring-red-500/50 focus:outline-none"
                        >
                            <option value="admin">Admin</option>
                            <option value="superadmin">Superadmin</option>
                        </select>
                    </div>

                    {/* MFA Checkbox */}
                    <div className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="mfa"
                            checked={mfaEnabled}
                            onChange={(e) => setMfaEnabled(e.target.checked)}
                            className="size-4 rounded border-slate-700 bg-slate-800 text-red-600 focus:ring-2 focus:ring-red-500/50"
                        />
                        <label htmlFor="mfa" className="text-sm text-slate-300">
                            Enable Multi-Factor Authentication
                        </label>
                    </div>

                    {/* Buttons */}
                    <div className="flex gap-3 pt-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="flex-1 rounded-xl border border-slate-800 bg-slate-800 px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-700"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            className="flex-1 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-red-500/30 transition hover:bg-red-700"
                        >
                            Create Admin
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
