import { Head, Link } from '@inertiajs/react';
import DecorativeBackground from '@/Components/DecorativeBackground';

export default function Welcome({
    auth,
    laravelVersion,
    phpVersion,
}: {
    auth: { user: any };
    laravelVersion: string;
    phpVersion: string;
}) {
    return (
        <>
            <Head title="Welcome - StegoLock" />
            <div className="min-h-screen bg-slate-950 text-white">
                {/* Custom CSS Animations */}
                <style>{`
                    @keyframes fade-in {
                        from { opacity: 0; transform: translateY(20px); }
                        to { opacity: 1; transform: translateY(0); }
                    }
                    @keyframes float {
                        0%, 100% { transform: translateY(0); }
                        50% { transform: translateY(-10px); }
                    }
                    @keyframes bounce-slow {
                        0%, 100% { transform: translateY(0); }
                        50% { transform: translateY(-15px); }
                    }
                    .animate-fade-in {
                        animation: fade-in 0.8s ease-out forwards;
                    }
                    .animate-float {
                        animation: float 3s ease-in-out infinite;
                    }
                    .animate-bounce-slow {
                        animation: bounce-slow 2s ease-in-out infinite;
                    }
                    .animation-delay-200 { animation-delay: 200ms; }
                    .animation-delay-400 { animation-delay: 400ms; }
                    .animation-delay-600 { animation-delay: 600ms; }
                `}</style>

                {/* Hero Section */}
                <section className="relative overflow-hidden">
                    <DecorativeBackground />
                    <div className="relative mx-auto max-w-7xl px-6 py-24 sm:py-32 lg:px-8">
                        <div className="text-center">
                            {/* Logo/Brand */}
                            <div className="mb-8 flex justify-center animate-fade-in">
                                <div className="flex items-center space-x-3">
                                    <div className="h-12 w-12 rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500 flex items-center justify-center">
                                        <svg className="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                    </div>
                                    <span className="text-3xl font-bold bg-gradient-to-r from-cyan-400 to-blue-500 bg-clip-text text-transparent">
                                        StegoLock
                                    </span>
                                </div>
                            </div>

                            {/* Hero Title with Gradient */}
                            <h1 className="animate-fade-in animation-delay-200 text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
                                <span className="block text-white">Secure Document Sharing</span>
                                <span className="mt-2 block bg-gradient-to-r from-cyan-400 via-blue-500 to-purple-500 bg-clip-text text-transparent">
                                    with Steganography
                                </span>
                            </h1>

                            {/* Hero Description */}
                            <p className="animate-fade-in animation-delay-400 mx-auto mt-6 max-w-2xl text-lg leading-8 text-slate-300">
                                Protect your sensitive documents using advanced AES-GCM encryption and invisible data embedding. 
                                Share files securely with end-to-end protection.
                            </p>

                            {/* CTA Buttons */}
                            <div className="animate-fade-in animation-delay-600 mt-10 flex items-center justify-center gap-6">
                                {auth.user ? (
                                    <Link
                                        href={route('dashboard')}
                                        className="rounded-lg bg-gradient-to-r from-cyan-500 to-blue-600 px-8 py-3 text-base font-semibold text-white shadow-lg transition hover:from-cyan-600 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 focus:ring-offset-slate-950"
                                    >
                                        Go to Dashboard
                                    </Link>
                                ) : (
                                    <>
                                        <Link
                                            href={route('register')}
                                            className="rounded-lg bg-gradient-to-r from-cyan-500 to-blue-600 px-8 py-3 text-base font-semibold text-white shadow-lg transition hover:from-cyan-600 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 focus:ring-offset-slate-950"
                                        >
                                            Get Started Free
                                        </Link>
                                        <Link
                                            href={route('login')}
                                            className="rounded-lg border border-slate-700 bg-slate-900/50 px-8 py-3 text-base font-semibold text-white shadow-lg transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 focus:ring-offset-slate-950"
                                        >
                                            Sign In
                                        </Link>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                </section>

                {/* How it Works Section */}
                <section className="relative bg-slate-900/50 py-24">
                    <div className="mx-auto max-w-7xl px-6 lg:px-8">
                        <div className="text-center mb-16">
                            <h2 className="text-3xl font-bold tracking-tight text-white sm:text-4xl">
                                How It Works
                            </h2>
                            <p className="mt-4 text-lg text-slate-400">
                                Three powerful technologies working together to protect your data
                            </p>
                        </div>

                        <div className="grid grid-cols-1 gap-8 md:grid-cols-3">
                            {/* Feature Card 1: AES-GCM Encryption */}
                            <div className="group relative overflow-hidden rounded-2xl bg-slate-800/50 p-8 transition hover:bg-slate-800">
                                <div className="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-cyan-400 to-blue-500">
                                    <svg className="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </div>
                                <h3 className="mb-3 text-xl font-semibold text-white">
                                    AES-GCM Encryption
                                </h3>
                                <p className="text-slate-400">
                                    Military-grade 256-bit encryption with authenticated encryption, 
                                    ensuring both confidentiality and integrity of your documents.
                                </p>
                            </div>

                            {/* Feature Card 2: Intelligent Segmentation */}
                            <div className="group relative overflow-hidden rounded-2xl bg-slate-800/50 p-8 transition hover:bg-slate-800">
                                <div className="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-purple-400 to-pink-500">
                                    <svg className="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                </div>
                                <h3 className="mb-3 text-xl font-semibold text-white">
                                    Intelligent Segmentation
                                </h3>
                                <p className="text-slate-400">
                                    Smart document splitting that breaks files into encrypted fragments, 
                                    distributing them across multiple carrier files for enhanced security.
                                </p>
                            </div>

                            {/* Feature Card 3: Stegano-Embedding */}
                            <div className="group relative overflow-hidden rounded-2xl bg-slate-800/50 p-8 transition hover:bg-slate-800">
                                <div className="mb-6 flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-green-400 to-emerald-500">
                                    <svg className="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <h3 className="mb-3 text-xl font-semibold text-white">
                                    Stegano-Embedding
                                </h3>
                                <p className="text-slate-400">
                                    Invisible data hiding using LSB (Least Significant Bit) technique, 
                                    embedding encrypted fragments into image carriers without visible changes.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Study Objectives Section */}
                <section className="py-24">
                    <div className="mx-auto max-w-7xl px-6 lg:px-8">
                        <div className="grid grid-cols-1 gap-12 lg:grid-cols-2 lg:gap-16">
                            <div>
                                <h2 className="text-3xl font-bold tracking-tight text-white sm:text-4xl">
                                    Study Objectives
                                </h2>
                                <p className="mt-6 text-lg leading-8 text-slate-300">
                                    This project explores the intersection of cryptography and steganography 
                                    to create a comprehensive document protection system.
                                </p>
                                <ul className="mt-8 space-y-4">
                                    {[
                                        'Implement envelope encryption for secure key management',
                                        'Develop efficient LSB embedding algorithms for various image formats',
                                        'Create scalable cloud storage integration with concurrent uploads',
                                        'Design intuitive user interfaces for complex security operations',
                                    ].map((item, index) => (
                                        <li key={index} className="flex items-start">
                                            <svg className="mt-1 h-5 w-5 flex-shrink-0 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span className="ml-3 text-slate-300">{item}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            {/* Primary Beneficiaries Section */}
                            <div>
                                <h2 className="text-3xl font-bold tracking-tight text-white sm:text-4xl">
                                    Primary Beneficiaries
                                </h2>
                                <p className="mt-6 text-lg leading-8 text-slate-300">
                                    Our system is designed to serve various user groups with different security needs.
                                </p>
                                <div className="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    {[
                                        { title: 'Healthcare', desc: 'Protect patient records and medical documents' },
                                        { title: 'Legal', desc: 'Secure confidential case files and contracts' },
                                        { title: 'Finance', desc: 'Safeguard financial reports and transactions' },
                                        { title: 'Research', desc: 'Protect intellectual property and study data' },
                                    ].map((item, index) => (
                                        <div key={index} className="rounded-lg bg-slate-800/50 p-4">
                                            <h3 className="font-semibold text-white">{item.title}</h3>
                                            <p className="mt-1 text-sm text-slate-400">{item.desc}</p>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Call to Action Section */}
                <section className="relative overflow-hidden">
                    <div className="absolute inset-0 bg-gradient-to-r from-cyan-600 to-blue-700" />
                    <div className="relative mx-auto max-w-7xl px-6 py-24 sm:py-32 lg:px-8">
                        <div className="text-center">
                            <h2 className="text-3xl font-bold tracking-tight text-white sm:text-4xl">
                                Ready to Secure Your Documents?
                            </h2>
                            <p className="mx-auto mt-6 max-w-2xl text-lg leading-8 text-cyan-100">
                                Join thousands of users who trust StegoLock for their document security needs.
                            </p>
                            <div className="mt-10">
                                {auth.user ? (
                                    <Link
                                        href={route('documents.my')}
                                        className="rounded-lg bg-white px-8 py-3 text-base font-semibold text-cyan-600 shadow-lg transition hover:bg-cyan-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-cyan-600"
                                    >
                                        My Documents
                                    </Link>
                                ) : (
                                    <Link
                                        href={route('register')}
                                        className="rounded-lg bg-white px-8 py-3 text-base font-semibold text-cyan-600 shadow-lg transition hover:bg-cyan-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-cyan-600"
                                    >
                                        Start Free Trial
                                    </Link>
                                )}
                            </div>
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="bg-slate-950 py-12">
                    <div className="mx-auto max-w-7xl px-6 lg:px-8">
                        <div className="grid grid-cols-1 gap-8 md:grid-cols-4">
                            {/* Quick Links */}
                            <div>
                                <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">
                                    Quick Links
                                </h3>
                                <ul className="mt-4 space-y-2">
                                    {[
                                        { label: 'Home', href: '/' },
                                        { label: 'Dashboard', href: '/dashboard' },
                                        { label: 'My Documents', href: '/documents/my' },
                                        { label: 'Starred', href: '/documents/starred' },
                                    ].map((link, index) => (
                                        <li key={index}>
                                            <Link
                                                href={link.href}
                                                className="text-sm text-slate-300 hover:text-white transition"
                                            >
                                                {link.label}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            {/* Technologies */}
                            <div>
                                <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">
                                    Technologies
                                </h3>
                                <ul className="mt-4 space-y-2">
                                    {[
                                        'Laravel 10',
                                        'React + Inertia.js',
                                        'MySQL',
                                        'Backblaze B2',
                                        'Python (Steganography)',
                                    ].map((tech, index) => (
                                        <li key={index} className="text-sm text-slate-300">
                                            {tech}
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            {/* Resources */}
                            <div>
                                <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">
                                    Resources
                                </h3>
                                <ul className="mt-4 space-y-2">
                                    {[
                                        { label: 'Documentation', href: '#' },
                                        { label: 'API Reference', href: '#' },
                                        { label: 'User Guide', href: '/user-guide' },
                                        { label: 'Runbook', href: '/runbook' },
                                    ].map((link, index) => (
                                        <li key={index}>
                                            <Link
                                                href={link.href}
                                                className="text-sm text-slate-300 hover:text-white transition"
                                            >
                                                {link.label}
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            {/* Social Icons */}
                            <div>
                                <h3 className="text-sm font-semibold uppercase tracking-wider text-slate-400">
                                    Connect
                                </h3>
                                <div className="mt-4 flex space-x-4">
                                    {[
                                        { name: 'GitHub', path: 'M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z' },
                                        { name: 'Twitter', path: 'M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z' },
                                    ].map((social, index) => (
                                        <a
                                            key={index}
                                            href="#"
                                            className="text-slate-400 hover:text-white transition"
                                            aria-label={social.name}
                                        >
                                            <svg className="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                                <path d={social.path} />
                                            </svg>
                                        </a>
                                    ))}
                                </div>
                                <p className="mt-4 text-sm text-slate-400">
                                    Laravel v{laravelVersion} (PHP v{phpVersion})
                                </p>
                            </div>
                        </div>

                        {/* Copyright */}
                        <div className="mt-12 border-t border-slate-800 pt-8 text-center">
                            <p className="text-sm text-slate-400">
                                &copy; {new Date().getFullYear()} StegoLock. All rights reserved.
                            </p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
