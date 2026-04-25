import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { useState } from 'react';
import { User, Mail, Lock, Eye, EyeOff, CheckCircle2, Loader2 } from 'lucide-react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);
    const hasErrors = Object.keys(errors).length > 0;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    const passwordRequirements = [
        { text: 'At least 8 characters', met: data.password.length >= 8 },
        { text: 'Contains a number', met: /\d/.test(data.password) },
        { text: 'Contains uppercase letter', met: /[A-Z]/.test(data.password) },
    ];

    return (
        <GuestLayout>
            <Head title="Register" />

            {/* Title Section */}
            <div className="mb-6 text-center">
                <h2 className="text-2xl font-semibold text-gray-900">Join StegoLock</h2>
                <p className="mt-1 text-sm text-gray-500">Create your secure document vault</p>
            </div>

            <form onSubmit={submit} className={hasErrors ? 'animate-shake' : ''}>
                {/* Full Name Field */}
                <div>
                    <InputLabel htmlFor="name" value="Full Name" />

                    <TextInput
                        id="name"
                        name="name"
                        value={data.name}
                        className="mt-1 block w-full"
                        autoComplete="name"
                        isFocused={true}
                        icon={<User className="w-5 h-5" />}
                        placeholder="John Doe"
                        error={!!errors.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                    />

                    <InputError message={errors.name} className="mt-2" />
                </div>

                {/* Email Field */}
                <div className="mt-4">
                    <InputLabel htmlFor="email" value="Email Address" />

                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        autoComplete="username"
                        icon={<Mail className="w-5 h-5" />}
                        error={!!errors.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                {/* Password Field */}
                <div className="mt-4">
                    <InputLabel htmlFor="password" value="Password" />

                    <TextInput
                        id="password"
                        type={showPassword ? 'text' : 'password'}
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="new-password"
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
                        required
                    />

                    <InputError message={errors.password} className="mt-2" />

                    {/* Password Requirements Checklist */}
                    {data.password.length > 0 && (
                        <div className="mt-3 space-y-2">
                            {passwordRequirements.map((req, index) => (
                                <div key={index} className="flex items-center gap-2">
                                    <CheckCircle2
                                        className={`w-4 h-4 ${req.met ? 'text-green-600' : 'text-gray-500 opacity-30'}`}
                                    />
                                    <span className={`text-sm ${req.met ? 'text-green-600' : 'text-gray-500 opacity-30'}`}>
                                        {req.text}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Confirm Password Field */}
                <div className="mt-4">
                    <InputLabel htmlFor="password_confirmation" value="Confirm Password" />

                    <TextInput
                        id="password_confirmation"
                        type={showConfirmPassword ? 'text' : 'password'}
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className="mt-1 block w-full"
                        autoComplete="new-password"
                        icon={<Lock className="w-5 h-5" />}
                        rightIcon={
                            <button
                                type="button"
                                onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                                className="focus:outline-none"
                            >
                                {showConfirmPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
                            </button>
                        }
                        error={!!errors.password_confirmation}
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                        required
                    />

                    <InputError
                        message={errors.password_confirmation}
                        className="mt-2"
                    />
                </div>

                {/* Submit Button */}
                <div className="mt-6">
                    <PrimaryButton className="w-full flex items-center justify-center gap-2" disabled={processing}>
                        {processing && <Loader2 className="w-4 h-4 animate-spin" />}
                        {processing ? 'Creating Account...' : 'Create Account'}
                    </PrimaryButton>
                </div>

                {/* Divider */}
                <div className="mt-6 pt-6 border-t border-gray-200 flex items-center justify-center gap-2">
                    <span className="text-sm text-gray-600">Already have an account?</span>
                </div>

                {/* Sign In Link */}
                <div className="mt-4">
                    <Link href={route('login')} className="w-full block">
                        <button
                            type="button"
                            className="w-full border-2 border-indigo-200 text-indigo-700 hover:bg-indigo-50 rounded-xl py-3.5 font-medium transition-colors"
                        >
                            Sign In Instead
                        </button>
                    </Link>
                </div>

                {/* Terms Footer */}
                <div className="mt-6 text-center text-xs text-gray-500">
                    By creating an account, you agree to our Terms of Service and Privacy Policy
                </div>
            </form>
        </GuestLayout>
    );
}
