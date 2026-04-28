import { useState, useCallback } from 'react';
import { CheckCircle, XCircle, AlertCircle, Info, X } from 'lucide-react';

export type ToastType = 'success' | 'error' | 'warning' | 'info';

export interface Toast {
    id: number;
    type: ToastType;
    message: string;
    duration?: number;
}

// Module-level state for toast management
let toasts: Toast[] = [];
let nextToastId = 0;
let listeners: (() => void)[] = [];

const notifyListeners = () => {
    listeners.forEach(listener => listener());
};

export const useToast = () => {
    const [_, setUpdate] = useState(0);

    const subscribe = useCallback(() => {
        const listener = () => setUpdate(prev => prev + 1);
        listeners.push(listener);
        return () => {
            listeners = listeners.filter(l => l !== listener);
        };
    }, []);

    // Subscribe on mount
    useState(() => {
        const unsubscribe = subscribe();
        return unsubscribe;
    });

    const showToast = useCallback((type: ToastType, message: string, duration = 5000) => {
        const id = nextToastId++;
        const newToast: Toast = { id, type, message, duration };
        toasts = [...toasts, newToast];
        notifyListeners();

        if (duration > 0) {
            setTimeout(() => {
                toasts = toasts.filter(toast => toast.id !== id);
                notifyListeners();
            }, duration);
        }
    }, []);

    const removeToast = useCallback((id: number) => {
        toasts = toasts.filter(toast => toast.id !== id);
        notifyListeners();
    }, []);

    const success = useCallback((message: string, duration?: number) => showToast('success', message, duration), [showToast]);
    const error = useCallback((message: string, duration?: number) => showToast('error', message, duration), [showToast]);
    const warning = useCallback((message: string, duration?: number) => showToast('warning', message, duration), [showToast]);
    const info = useCallback((message: string, duration?: number) => showToast('info', message, duration), [showToast]);

    return {
        toasts,
        showToast,
        success,
        error,
        warning,
        info,
        removeToast,
    };
};

export function ToastContainer() {
    const { toasts, removeToast } = useToast();

    const getToastStyles = (type: ToastType) => {
        switch (type) {
            case 'success':
                return {
                    container: 'bg-green-600/90 border-green-500/50',
                    icon: <CheckCircle className="h-5 w-5 text-green-100" />,
                };
            case 'error':
                return {
                    container: 'bg-red-600/90 border-red-500/50',
                    icon: <XCircle className="h-5 w-5 text-red-100" />,
                };
            case 'warning':
                return {
                    container: 'bg-yellow-600/90 border-yellow-500/50',
                    icon: <AlertCircle className="h-5 w-5 text-yellow-100" />,
                };
            case 'info':
                return {
                    container: 'bg-blue-600/90 border-blue-500/50',
                    icon: <Info className="h-5 w-5 text-blue-100" />,
                };
        }
    };

    if (toasts.length === 0) return null;

    return (
        <div className="fixed top-4 right-4 flex flex-col gap-2 w-full max-w-sm" style={{ zIndex: 9999 }}>
            {toasts.map(toast => {
                const styles = getToastStyles(toast.type);
                return (
                    <div
                        key={toast.id}
                        className={`${styles.container} flex items-center gap-3 rounded-lg border backdrop-blur-sm p-4 shadow-lg animate-slide-in-right`}
                        role="alert"
                    >
                        {styles.icon}
                        <p className="flex-1 text-sm font-medium text-white">{toast.message}</p>
                        <button
                            onClick={() => removeToast(toast.id)}
                            className="text-white/80 hover:text-white transition-colors"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                );
            })}
        </div>
    );
}
