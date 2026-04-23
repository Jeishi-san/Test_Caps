import { ButtonHTMLAttributes } from 'react';
import { Loader2 } from 'lucide-react';

interface PrimaryButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    loading?: boolean;
}

export default function PrimaryButton(
    {
        className = '',
        disabled = false,
        loading = false,
        children,
        ...props
    }: PrimaryButtonProps,
) {
    const isDisabled = disabled || loading;

    return (
        <button
            {...props}
            disabled={isDisabled}
            className={
                `inline-flex items-center justify-center rounded-xl border border-transparent bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-3 text-sm font-semibold text-white shadow-md transition-all duration-200 ease-in-out 
                hover:from-indigo-700 hover:to-purple-700 hover:shadow-lg 
                focus:from-indigo-700 focus:to-purple-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 
                active:shadow-sm 
                ${isDisabled ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''} ` +
                className
            }
        >
            {loading && (
                <Loader2 className="w-4 h-4 mr-2 animate-spin" />
            )}
            {children}
        </button>
    );
}
