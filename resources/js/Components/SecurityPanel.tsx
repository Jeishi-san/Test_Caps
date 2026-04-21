import { useState } from 'react';
import Modal from '@/Components/Modal';
import { AlertTriangle, CheckCircle2, Eye, Key, Lock, Shield } from 'lucide-react';

const securityFeatures = [
    {
        icon: Lock,
        title: 'End-to-end encryption',
        description: 'All documents are protected with AES-256 encryption at rest and in transit.',
    },
    {
        icon: Key,
        title: 'Private key control',
        description: 'Only the account owner can decode protected documents and manage tokens.',
    },
    {
        icon: Shield,
        title: 'Transfer protection',
        description: 'Uploads and downloads are guarded by signed requests and secure storage policies.',
    },
    {
        icon: Eye,
        title: 'Privacy by default',
        description: 'Sharing and visibility controls stay explicit so documents do not leak accidentally.',
    },
];

export default function SecurityPanel() {
    const [isOpen, setIsOpen] = useState(false);

    return (
        <>
            <button
                type="button"
                onClick={() => setIsOpen(true)}
                className="fixed bottom-6 right-6 z-40 inline-flex items-center gap-2 rounded-full bg-emerald-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-emerald-950/30 transition hover:-translate-y-0.5 hover:bg-emerald-500"
            >
                <Shield className="size-4" />
                Security Panel
            </button>

            <Modal show={isOpen} onClose={() => setIsOpen(false)} maxWidth="4xl" title="Security controls and posture">
                <div className="space-y-6">
                    <div className="grid gap-4 md:grid-cols-2">
                        {securityFeatures.map((feature) => {
                            const Icon = feature.icon;

                            return (
                                <div key={feature.title} className="flex gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <div className="rounded-xl bg-emerald-100 p-3 text-emerald-700">
                                        <Icon className="size-5" />
                                    </div>
                                    <div className="min-w-0">
                                        <h4 className="font-semibold text-slate-900">{feature.title}</h4>
                                        <p className="mt-1 text-sm leading-6 text-slate-600">{feature.description}</p>
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    <div className="rounded-2xl border border-cyan-200 bg-cyan-50 p-5">
                        <div className="flex items-start gap-3">
                            <AlertTriangle className="mt-0.5 size-5 shrink-0 text-cyan-700" />
                            <div>
                                <h4 className="font-semibold text-cyan-950">Security best practices</h4>
                                <ul className="mt-2 space-y-1 text-sm text-cyan-900/90">
                                    <li>Keep role access restricted to the smallest practical set of users.</li>
                                    <li>Review document sharing and watcher settings before publishing files.</li>
                                    <li>Rotate API tokens whenever access patterns change.</li>
                                    <li>Audit storage and upload history on a regular cadence.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div className="rounded-2xl bg-slate-950 p-5 text-slate-100">
                        <div className="mb-3 flex items-center gap-2">
                            <CheckCircle2 className="size-5 text-emerald-400" />
                            <h4 className="font-semibold">Encryption posture</h4>
                        </div>
                        <div className="grid gap-3 text-sm sm:grid-cols-3">
                            <div className="rounded-xl border border-white/10 bg-white/5 p-4">
                                <p className="text-slate-400">State</p>
                                <p className="mt-1 font-medium">Protected</p>
                            </div>
                            <div className="rounded-xl border border-white/10 bg-white/5 p-4">
                                <p className="text-slate-400">Coverage</p>
                                <p className="mt-1 font-medium">Documents, folders, and API tokens</p>
                            </div>
                            <div className="rounded-xl border border-white/10 bg-white/5 p-4">
                                <p className="text-slate-400">Default visibility</p>
                                <p className="mt-1 font-medium">Private</p>
                            </div>
                        </div>
                    </div>
                </div>
            </Modal>
        </>
    );
}