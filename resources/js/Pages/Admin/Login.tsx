import DecorativeBackground from '@/Components/DecorativeBackground';
import { Head, router } from '@inertiajs/react';
import { Lock, Mail, Shield } from 'lucide-react';
import { useState, type FormEvent } from 'react';

export default function Login() {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setError('');

        if (!email || !password) {
            setError('Please fill in all fields');
            return;
        }

        router.post(route('login'), {
            email,
            password,
            remember: true,
        }, {
            onSuccess: () => {
                router.visit(route('admin.dashboard'));
            },
        });
    };

    return (
        <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 px-6">
            <Head title="Admin Login" />
            <DecorativeBackground />

            <div className="relative z-10 w-full max-w-md">
                <div className="rounded-3xl border border-slate-700/50 bg-slate-900/90 p-8 shadow-2xl shadow-slate-950/30 backdrop-blur-xl">
                    <div className="mb-8 text-center">
                        <div className="mx-auto mb-4 inline-flex size-16 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-500 to-emerald-600 shadow-lg shadow-cyan-950/30">
                            <Shield className="size-8 text-white" />
                        </div>
                        <h1 className="text-3xl font-bold text-white">StegoLock Admin</h1>
                        <p className="mt-2 text-sm text-slate-400">Secure system administration</p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-5">
                        {error && <div className="rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300">{error}</div>}

                        <label className="block">
                            <span className="mb-2 block text-sm font-medium text-slate-300">Email</span>
                            <div className="relative">
                                <Mail className="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-slate-500" />
                                <input
                                    type="email"
                                    value={email}
                                    onChange={(event) => setEmail(event.target.value)}
                                    placeholder="admin@stegolock.com"
                                    className="w-full rounded-xl border border-slate-700 bg-slate-800/50 py-3 pl-11 pr-4 text-white placeholder:text-slate-500 focus:border-cyan-500/60 focus:outline-none focus:ring-2 focus:ring-cyan-500/30"
                                />
                            </div>
                        </label>

                        <label className="block">
                            <span className="mb-2 block text-sm font-medium text-slate-300">Password</span>
                            <div className="relative">
                                <Lock className="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-slate-500" />
                                <input
                                    type="password"
                                    value={password}
                                    onChange={(event) => setPassword(event.target.value)}
                                    placeholder="••••••••"
                                    className="w-full rounded-xl border border-slate-700 bg-slate-800/50 py-3 pl-11 pr-4 text-white placeholder:text-slate-500 focus:border-cyan-500/60 focus:outline-none focus:ring-2 focus:ring-cyan-500/30"
                                />
                            </div>
                        </label>

                        <button
                            type="submit"
                            className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-cyan-600 to-emerald-600 px-4 py-3 font-semibold text-white shadow-lg shadow-cyan-950/30 transition hover:from-cyan-500 hover:to-emerald-500"
                        >
                            <Shield className="size-4" />
                            Sign in
                        </button>
                    </form>

                    <p className="mt-6 border-t border-slate-800 pt-5 text-center text-xs text-slate-500">
                        Authorized personnel should use their regular account credentials.
                    </p>
                </div>
            </div>
        </div>
    );
}