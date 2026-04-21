import AdminLayout from '@/Admin/AdminLayout';
import { Head } from '@inertiajs/react';

type SectionProps = {
    title: string;
    description: string;
};

export default function Section({ title, description }: SectionProps) {
    return (
        <AdminLayout>
            <Head title={title} />
            <div className="max-w-4xl space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-white">{title}</h1>
                    <p className="mt-2 text-slate-400">{description}</p>
                </div>

                <div className="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 text-slate-300 shadow-xl shadow-slate-950/20">
                    <p className="text-sm leading-6">
                        This area is wired as a placeholder route so the admin sidebar stays functional while the backend-specific screens are implemented.
                    </p>
                </div>
            </div>
        </AdminLayout>
    );
}