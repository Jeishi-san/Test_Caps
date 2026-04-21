export default function DecorativeBackground() {
    return (
        <div className="pointer-events-none absolute inset-0 overflow-hidden">
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.14),_transparent_42%),linear-gradient(135deg,_rgba(15,23,42,0.98),_rgba(30,41,59,0.98))]" />
            <svg
                className="absolute inset-0 h-full w-full opacity-25"
                aria-hidden="true"
                viewBox="0 0 100 100"
                preserveAspectRatio="none"
            >
                <defs>
                    <pattern id="admin-grid" width="10" height="10" patternUnits="userSpaceOnUse">
                        <path d="M 10 0 L 0 0 0 10" fill="none" stroke="currentColor" strokeWidth="0.6" className="text-slate-100" />
                    </pattern>
                </defs>
                <rect width="100" height="100" fill="url(#admin-grid)" />
            </svg>
            <div className="absolute left-1/4 top-1/4 h-64 w-64 rounded-full border border-cyan-300/20" />
            <div className="absolute right-1/5 bottom-1/4 h-80 w-80 rounded-full border border-amber-300/20" />
            <div className="absolute -left-16 bottom-12 h-56 w-56 rounded-full bg-cyan-500/10 blur-3xl" />
            <div className="absolute right-0 top-0 h-72 w-72 rounded-full bg-amber-400/10 blur-3xl" />
        </div>
    );
}