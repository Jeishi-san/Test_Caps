import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';
import { Shield } from 'lucide-react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="min-h-screen flex flex-col items-center justify-center bg-gradient-to-br from-gray-50 to-gray-100 relative overflow-hidden">
            {/* Decorative Background Elements */}
            <div className="absolute inset-0 overflow-hidden pointer-events-none">
                <div className="absolute -top-40 -right-40 w-80 h-80 rounded-full bg-gradient-to-br from-indigo-400/10 to-purple-500/10 blur-3xl" />
                <div className="absolute -bottom-40 -left-40 w-80 h-80 rounded-full bg-gradient-to-br from-purple-400/10 to-indigo-500/10 blur-3xl" />
            </div>

            <div className="relative z-10 flex flex-col items-center">
                {/* Brand Header */}
                <Link href="/" className="mb-8 flex flex-col items-center">
                    <div className="w-20 h-20 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg">
                        <Shield className="w-10 h-10 text-white" />
                    </div>
                    <h1 className="mt-4 text-4xl font-bold text-gray-900">
                        Welcome to Stegolock</h1>
                </Link>

                {/* Authentication Card */}
                <div className="w-full max-w-sm bg-white rounded-3xl shadow-xl px-8 py-8">
                    {children}
                </div>
            </div>
        </div>
    );
}
