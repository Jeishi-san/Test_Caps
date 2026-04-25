import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';
import { Shield } from 'lucide-react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="min-h-screen flex flex-col items-center justify-center bg-gradient-to-br from-indigo-50 via-white to-purple-50 bg-grid-pattern relative overflow-hidden">
            {/* Decorative Background Elements */}
            <div className="absolute inset-0 overflow-hidden pointer-events-none">
                <div className="orb-purple" />
                <div className="orb-indigo" />
                <div className="orb-blue" />
                <div className="circle-top-right" />
                <div className="circle-bottom-left" />
            </div>

            <div className="relative z-10 flex flex-col items-center">
                {/* Brand Header */}
                <Link href="/" className="mb-8 flex flex-col items-center">
                    <div className="p-4 rounded-2xl bg-gradient-to-br from-indigo-600 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/50">
                        <Shield className="w-12 h-12 text-white" />
                    </div>
                    <h1 className="mt-4 text-4xl font-bold text-gray-900">
                        Welcome to StegoLock
                    </h1>
                </Link>

                {/* Authentication Card */}
                <div className="w-full max-w-md bg-white/80 backdrop-blur-xl border border-white/20 rounded-3xl shadow-2xl shadow-indigo-200/50 px-8 py-8">
                    <h2 className="text-2xl font-semibold text-gray-900 mb-6">Sign In</h2>
                    {children}
                </div>
            </div>
        </div>
    );
}
