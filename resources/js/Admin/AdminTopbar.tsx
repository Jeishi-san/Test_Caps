export type AdminRole = 'admin' | 'owner' | 'superadmin';

type AdminTopbarProps = {
    email?: string;
    role?: AdminRole | string;
};

export default function AdminTopbar({ email, role }: AdminTopbarProps) {
    const isSuperadmin = role === 'superadmin' || role === 'owner';
    const label = isSuperadmin ? 'SUPERADMIN' : (role ?? 'ADMIN').toString().toUpperCase();

    return (
        <header className="border-b border-slate-800 bg-slate-900/50 px-6 py-4 backdrop-blur-xl">
            <div className="flex items-center justify-end gap-4">
                <div className="text-right">
                    <p className="text-sm font-medium text-white">{email ?? 'Admin user'}</p>
                </div>
                <div className="mt-1 flex items-center justify-end gap-2">
                    <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${
                        isSuperadmin 
                            ? 'border-red-600/30 bg-red-600/20 text-red-400' 
                            : 'border-blue-600/30 bg-blue-600/20 text-blue-400'
                    }`}>
                        {label}
                    </span>
                </div>
            </div>
        </header>
    );
}
