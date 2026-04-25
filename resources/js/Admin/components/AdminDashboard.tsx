import { Activity, AlertCircle, Database, Shield, UserCheck, Users, Lock } from 'lucide-react';

const stats = [
    { label: 'Total Users', value: '1,284', change: '+12%', trend: 'up', icon: Users, color: 'blue' },
    { label: 'Active Users', value: '892', change: '+5%', trend: 'up', icon: UserCheck, color: 'green' },
    { label: 'Encrypted Containers', value: '15,432', change: '+18%', trend: 'up', icon: Lock, color: 'purple' },
    { label: 'Failed Reconstructions', value: '23', change: '-8%', trend: 'down', icon: AlertCircle, color: 'red' },
];

const systemHealth = [
    { label: 'Fragment Storage', status: 'operational', value: '99.8%' },
    { label: 'Encryption Service', status: 'operational', value: '100%' },
    { label: 'User Authentication', status: 'operational', value: '99.9%' },
    { label: 'API Gateway', status: 'degraded', value: '95.2%' },
];

const recentActivity = [
    { user: 'john.smith@company.com', action: 'Created new container', time: '2 minutes ago', status: 'success' },
    { user: 'admin@stegolock.com', action: 'Suspended user account', time: '15 minutes ago', status: 'warning' },
    { user: 'jane.doe@company.com', action: 'Failed reconstruction attempt', time: '1 hour ago', status: 'error' },
    { user: 'system', action: 'Automated backup completed', time: '2 hours ago', status: 'success' },
    { user: 'mike.jones@company.com', action: 'Fragment integrity check passed', time: '3 hours ago', status: 'success' },
];

export default function AdminDashboard() {
    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-3xl font-bold text-white">Dashboard</h1>
                <p className="mt-2 text-slate-400">System overview and key metrics</p>
            </div>

            <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                {stats.map((stat) => {
                    const Icon = stat.icon;

                    return (
                        <div key={stat.label} className="rounded-xl border border-slate-800 bg-slate-900/50 p-6 transition hover:border-slate-700">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <p className="text-sm text-slate-400">{stat.label}</p>
                                    <p className="mt-2 text-3xl font-bold text-white">{stat.value}</p>
                                    <div className="mt-2 flex items-center gap-2 text-sm">
                                        <span className={stat.trend === 'up' ? 'font-medium text-emerald-400' : 'font-medium text-rose-400'}>
                                            {stat.change}
                                        </span>
                                        <span className="text-slate-500">vs last month</span>
                                    </div>
                                </div>
                                <div className={`rounded-lg p-3 ${stat.color === 'blue' ? 'bg-blue-600/20 text-blue-400' : stat.color === 'green' ? 'bg-green-600/20 text-green-400' : stat.color === 'purple' ? 'bg-purple-600/20 text-purple-400' : 'bg-red-600/20 text-red-400'}`}>
                                    <Icon className="size-6" />
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <div className="rounded-xl border border-slate-800 bg-slate-900/50 p-6">
                    <div className="mb-6 flex items-center gap-2">
                        <Activity className="size-5 text-slate-400" />
                        <h2 className="text-xl font-semibold text-white">System Health</h2>
                    </div>
                    <div className="space-y-4">
                        {systemHealth.map((item) => (
                            <div key={item.label} className="flex items-center justify-between gap-4 rounded-xl border border-slate-800 bg-slate-900/50 px-4 py-3">
                                <div className="flex items-center gap-3">
                                    <span className={`size-2 rounded-full ${item.status === 'operational' ? 'bg-green-500' : item.status === 'degraded' ? 'bg-yellow-500' : 'bg-red-500'}`} />
                                    <span className="text-sm text-slate-300">{item.label}</span>
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className="text-sm font-medium text-white">{item.value}</span>
                                    <span className={`rounded-md px-2 py-1 text-xs font-medium ${item.status === 'operational' ? 'bg-green-600/20 text-green-400' : item.status === 'degraded' ? 'bg-yellow-600/20 text-yellow-400' : 'bg-red-600/20 text-red-400'}`}>
                                        {item.status}
                                    </span>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="rounded-xl border border-slate-800 bg-slate-900/50 p-6">
                    <div className="mb-6 flex items-center gap-2">
                        <Database className="size-5 text-slate-400" />
                        <h2 className="text-xl font-semibold text-white">Recent Activity</h2>
                    </div>
                    <div className="space-y-4">
                        {recentActivity.map((activity) => (
                            <div key={`${activity.user}-${activity.time}`} className="flex items-start gap-3 rounded-xl border border-slate-800 bg-slate-900/50 px-4 py-3">
                                <div className={`mt-1 size-3 rounded-full ${activity.status === 'success' ? 'bg-green-500' : activity.status === 'warning' ? 'bg-yellow-500' : 'bg-red-500'}`} />
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm text-slate-300">{activity.action}</p>
                                    <p className="mt-1 text-xs text-slate-500">
                                        {activity.user} · {activity.time}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            <div className="rounded-2xl border border-cyan-500/20 bg-cyan-500/10 p-5 text-cyan-50">
                <div className="flex items-start gap-3">
                    <Shield className="mt-0.5 size-5 shrink-0 text-cyan-200" />
                    <div>
                        <h3 className="font-semibold">Administrative context</h3>
                        <p className="mt-1 text-sm leading-6 text-cyan-100/90">
                            This module is wired to the existing authenticated shell, so the admin view can reuse the current user role and route protection without adding a separate auth system.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}