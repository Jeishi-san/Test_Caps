import {
    forwardRef,
    InputHTMLAttributes,
    useEffect,
    useImperativeHandle,
    useRef,
    ReactNode
} from 'react';

interface TextInputProps extends InputHTMLAttributes<HTMLInputElement> {
    isFocused?: boolean;
    icon?: ReactNode;
    rightIcon?: ReactNode;
    error?: boolean;
}

export default forwardRef(function TextInput(
    {
        type = 'text',
        className = '',
        isFocused = false,
        icon,
        rightIcon,
        error = false,
        ...props
    }: TextInputProps,
    ref,
) {
    const localRef = useRef<HTMLInputElement>(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    return (
        <div className="relative">
            {icon && (
                <div className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">
                    {icon}
                </div>
            )}
            {rightIcon && (
                <div className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 cursor-pointer">
                    {rightIcon}
                </div>
            )}
            <input
                {...props}
                type={type}
                className={
                    `w-full h-12 rounded-xl border ${
                        error ? 'border-red-500' : 'border-gray-300'
                    } bg-white px-4 ${
                        icon ? 'pl-10' : ''
                    } ${
                        rightIcon ? 'pr-10' : ''
                    } text-gray-900 placeholder-gray-500 shadow-sm transition-all duration-200
                    focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none
                    disabled:opacity-50 disabled:cursor-not-allowed
                    hover:border-gray-400 ` +
                    className
                }
                ref={localRef}
            />
        </div>
    );
});
