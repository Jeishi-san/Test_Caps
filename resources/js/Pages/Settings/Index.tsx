import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { PageProps } from '@/types';
import { useState } from 'react';
import PrimaryButton from '@/Components/PrimaryButton';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';

interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
}

interface Settings {
    theme: string;
    notifications: {
        email: boolean;
        browser: boolean;
    };
    privacy: {
        profile_visible: boolean;
        activity_visible: boolean;
    };
}

interface SettingsPageProps extends PageProps {
    user: User;
    settings: Settings;
}

export default function Index({ auth, user, settings }: SettingsPageProps) {
    const [activeTab, setActiveTab] = useState('general');

    const { data, setData, post, processing, errors } = useForm({
        theme: settings.theme,
        notifications: {
            email: settings.notifications.email,
            browser: settings.notifications.browser,
        },
        privacy: {
            profile_visible: settings.privacy.profile_visible,
            activity_visible: settings.privacy.activity_visible,
        },
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('settings.update'));
    };

    const tabs = [
        { id: 'general', name: 'General', icon: '⚙️' },
        { id: 'notifications', name: 'Notifications', icon: '🔔' },
        { id: 'privacy', name: 'Privacy', icon: '🔒' },
    ];

    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Settings
                </h2>
            }
        >
            <Head title="Settings" />

            <div className="py-12">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            {/* Tabs */}
                            <div className="border-b border-gray-200 dark:border-gray-700 mb-6">
                                <nav className="-mb-px flex space-x-8">
                                    {tabs.map((tab) => (
                                        <button
                                            key={tab.id}
                                            onClick={() => setActiveTab(tab.id)}
                                            className={`py-2 px-1 border-b-2 font-medium text-sm ${
                                                activeTab === tab.id
                                                    ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
                                            }`}
                                        >
                                            <span className="mr-2">{tab.icon}</span>
                                            {tab.name}
                                        </button>
                                    ))}
                                </nav>
                            </div>

                            <form onSubmit={submit}>
                                {/* General Settings */}
                                {activeTab === 'general' && (
                                    <div className="space-y-6">
                                        <div>
                                            <InputLabel htmlFor="theme" value="Theme" />
                                            <select
                                                id="theme"
                                                value={data.theme}
                                                onChange={(e) => setData('theme', e.target.value)}
                                                className="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                            >
                                                <option value="light">Light</option>
                                                <option value="dark">Dark</option>
                                                <option value="system">System</option>
                                            </select>
                                            {errors.theme && (
                                                <p className="mt-2 text-sm text-red-600 dark:text-red-400">
                                                    {errors.theme}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                )}

                                {/* Notifications Settings */}
                                {activeTab === 'notifications' && (
                                    <div className="space-y-6">
                                        <div className="flex items-center">
                                            <input
                                                id="email_notifications"
                                                type="checkbox"
                                                checked={data.notifications.email}
                                                onChange={(e) => setData('notifications', { ...data.notifications, email: e.target.checked })}
                                                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                            />
                                            <InputLabel
                                                htmlFor="email_notifications"
                                                value="Email notifications"
                                                className="ml-2 block text-sm"
                                            />
                                        </div>

                                        <div className="flex items-center">
                                            <input
                                                id="browser_notifications"
                                                type="checkbox"
                                                checked={data.notifications.browser}
                                                onChange={(e) => setData('notifications', { ...data.notifications, browser: e.target.checked })}
                                                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                            />
                                            <InputLabel
                                                htmlFor="browser_notifications"
                                                value="Browser notifications"
                                                className="ml-2 block text-sm"
                                            />
                                        </div>
                                    </div>
                                )}

                                {/* Privacy Settings */}
                                {activeTab === 'privacy' && (
                                    <div className="space-y-6">
                                        <div className="flex items-center">
                                            <input
                                                id="profile_visible"
                                                type="checkbox"
                                                checked={data.privacy.profile_visible}
                                                onChange={(e) => setData('privacy', { ...data.privacy, profile_visible: e.target.checked })}
                                                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                            />
                                            <InputLabel
                                                htmlFor="profile_visible"
                                                value="Profile visible to others"
                                                className="ml-2 block text-sm"
                                            />
                                        </div>

                                        <div className="flex items-center">
                                            <input
                                                id="activity_visible"
                                                type="checkbox"
                                                checked={data.privacy.activity_visible}
                                                onChange={(e) => setData('privacy', { ...data.privacy, activity_visible: e.target.checked })}
                                                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                            />
                                            <InputLabel
                                                htmlFor="activity_visible"
                                                value="Activity visible to others"
                                                className="ml-2 block text-sm"
                                            />
                                        </div>
                                    </div>
                                )}

                                {/* Save Button */}
                                <div className="flex justify-end mt-6">
                                    <PrimaryButton type="submit" disabled={processing}>
                                        {processing ? 'Saving...' : 'Save Settings'}
                                    </PrimaryButton>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}