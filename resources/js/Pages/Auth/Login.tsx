import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { useState } from 'react';
import { Mail, Lock, Eye, EyeOff, AlertCircle, Loader2, Shield } from 'lucide-react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });
    const [showPassword, setShowPassword] = useState(false);
    const hasErrors = Object.keys(errors).length > 0;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Log in" />

            {status && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className={hasErrors ? 'animate-shake' : ''}>

                <div className="mt-1">
                    <InputLabel htmlFor="email" value="Email Address" />

                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        autoComplete="username"
                        isFocused={true}
                        icon={<Mail className="w-5 h-5" />}
                        rightIcon={errors.email ? <AlertCircle className="w-5 h-5 text-red-500" /> : undefined}
                        error={!!errors.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="password" value="Password" />

                    <TextInput
                        id="password"
                        type={showPassword ? 'text' : 'password'}
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        icon={<Lock className="w-5 h-5" />}
                        rightIcon={
                            <button
                                type="button"
                                onClick={() => setShowPassword(!showPassword)}
                                className="focus:outline-none"
                            >
                                {showPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                            </button>
                        }
                        error={!!errors.password}
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="mt-4 block">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData(
                                    'remember',
                                    (e.target.checked || false) as false,
                                )
                            }
                        />
                        <span className="ms-2 text-sm text-gray-600">
                            Remember me
                        </span>
                    </label>
                </div>

                <div className="mt-6">
                    {canResetPassword && (
                        <div className="text-right mb-4">
                            <Link
                                href={route('password.request')}
                                className="text-sm text-blue-600 hover:text-blue-700"
                            >
                                Forgot your password?
                            </Link>
                        </div>
                    )}

                    <PrimaryButton className="w-full flex items-center justify-center gap-2" disabled={processing}>
                        {processing && <Loader2 className="w-4 h-4 animate-spin" />}
                        {processing ? 'Signing In...' : 'Sign In'}
                    </PrimaryButton>
                </div>

                <div className="mt-6 pt-6 border-t border-gray-200 flex items-center justify-center gap-2">
                    <span className="text-sm text-gray-600">New to StegoLock?</span>
                </div>

                <div className="mt-4">
                    <Link href={route('register')} className="w-full block">
                        <button
                            type="button"
                            className="w-full border-2 border-indigo-200 text-indigo-700 hover:bg-indigo-50 rounded-xl py-3.5 font-medium transition-colors"
                        >
                            Create Account
                        </button>
                    </Link>
                </div>
            </form>


        </GuestLayout>
    );
}
