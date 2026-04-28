import { Activity, AlertCircle, Database, Shield, UserCheck, Users, Lock } from 'lucide-react';
import { useState, useEffect } from 'react';

interface StatItem {
    label: string;
    value: string;
    change: string;
    trend: 'up' | 'down';
    color: string;
    icon: any;
}

interface SystemHealthItem {
    label: string;
    status: string;
    value: string;
}

interface RecentActivityItem {
    user: string;
    action: string;
    time: string;
    status: string;
}

interface DashboardData {
    stats: StatItem[];
    systemHealth: SystemHealthItem[];
    recentActivity: RecentActivityItem[];
}

export default function AdminDashboard() {
    const [loading, setLoading] = useState(true);
    const [data, setData] = useState<DashboardData | null>(null);

    useEffect(() => {
        const fetchData = async () => {
            try {
                const response = await window.axios.get('/api/admin/dashboard/stats');
                setData(response.data);
            } catch (error) {
                console.error('Failed to fetch admin dashboard stats:', error);
            } finally {
                setLoading(false);
            }
        };

        fetchData();
    }, []);

    if (loading) {
        return (
            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-white">Dashboard</h1>
                    <p className="mt-2 text-slate-400">Loading...</p>
                </div>
            </div>
        );
    }

    if (!data) {
        return (
            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-white">Dashboard</h1>
                    <p className="mt-2 text-slate-400">Failed to load dashboard data</p>
                </div>
            </div>
        );
    }

    const getIcon = (color: string) => {
        switch (color) {
            case 'blue': return Users;
            case 'green': return UserCheck;
            case 'purple': return Lock;
            case 'red': return AlertCircle;
            default: return Users;
        }
    };

    return (
        <div className="space-y-6">
            <div>
                <h1 className="text-3xl font-bold text-white">Dashboard</h1>
                <p className="mt-2 text-slate-400">System overview and key metrics</p>
            </div>

            <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                {data.stats.map((stat) => {
                    const Icon = getIcon(stat.color);

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
                        {data.systemHealth.map((item) => (
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
                        {data.recentActivity.length > 0 ? (
                            data.recentActivity.map((activity) => (
                                <div key={`${activity.user}-${activity.time}`} className="flex items-start gap-3 rounded-xl border border-slate-800 bg-slate-900/50 px-4 py-3">
                                    <div className={`mt-1 size-3 rounded-full ${activity.status === 'success' ? 'bg-green-500' : activity.status === 'warning' ? 'bg-yellow-500' : 'bg-red-500'}`} />
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm text-slate-300">{activity.action}</p>
                                        <p className="mt-1 text-xs text-slate-500">
                                            {activity.user} · {activity.time}
                                        </p>
                                    </div>
                                </div>
                            ))
                        ) : (
                            <p className="text-sm text-slate-400">No recent activity</p>
                        )}
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