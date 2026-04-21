export type AdminRole = 'admin' | 'owner' | 'superadmin';

type AdminTopbarProps = {
    email?: string;
    role?: AdminRole | string;
};

export default function AdminTopbar({ email, role }: AdminTopbarProps) {
    const label = role === 'owner' ? 'SUPERADMIN' : (role ?? 'ADMIN').toString().toUpperCase();

    return (
        <header className="border-b border-slate-800 bg-slate-950/80 px-6 py-4 backdrop-blur-xl">
            <div className="flex items-center justify-end gap-4">
                <div className="text-right">
                    <p className="text-sm font-medium text-white">{email ?? 'Admin user'}</p>
                    <div className="mt-1 flex items-center justify-end gap-2">
                        <span className="inline-flex items-center rounded-full border border-cyan-500/30 bg-cyan-500/10 px-2.5 py-0.5 text-xs font-semibold text-cyan-300">
                            {label}
                        </span>
                    </div>
                </div>
            </div>
        </header>
    );
}