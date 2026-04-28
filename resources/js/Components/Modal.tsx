import {
    Dialog,
    DialogPanel,
    DialogTitle,
    Transition,
    TransitionChild,
} from '@headlessui/react';
import { X } from 'lucide-react';
import { PropsWithChildren } from 'react';

export default function Modal({
    children,
    show = false,
    maxWidth = '2xl',
    closeable = true,
    onClose = () => {},
    title,
    zIndex = 100,
}: PropsWithChildren<{
    show: boolean;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl' | '4xl' | '5xl' | '6xl';
    closeable?: boolean;
    onClose: CallableFunction;
    title?: string;
    zIndex?: number;
}>) {
    const close = () => {
        if (closeable) {
            onClose();
        }
    };

    const maxWidthClass = {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
        '3xl': 'sm:max-w-3xl',
        '4xl': 'sm:max-w-4xl',
        '5xl': 'sm:max-w-5xl',
        '6xl': 'sm:max-w-6xl',
    }[maxWidth];

    return (
        <Transition show={show} leave="duration-200">
            <Dialog
                as="div"
                id="modal"
                className="fixed inset-0 flex transform items-center overflow-y-auto px-4 py-6 transition-all sm:px-0"
                style={{ zIndex }}
                onClose={close}
            >
                <TransitionChild
                    enter="ease-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="absolute inset-0 bg-black/50 backdrop-blur-sm" />
                </TransitionChild>

                <TransitionChild
                    enter="ease-out duration-300"
                    enterFrom="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    enterTo="opacity-100 translate-y-0 sm:scale-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100 translate-y-0 sm:scale-100"
                    leaveTo="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                >
                    <DialogPanel
                        className={`mb-6 transform overflow-hidden rounded-2xl bg-white shadow-2xl transition-all sm:mx-auto sm:w-full ${maxWidthClass}`}
                    >
                        {title && (
                            <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                                <DialogTitle as="h3" className="text-lg font-semibold text-gray-900">
                                    {title}
                                </DialogTitle>
                                {closeable && (
                                    <button
                                        type="button"
                                        onClick={close}
                                        className="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-500 transition-colors"
                                        aria-label="Close modal"
                                    >
                                        <X className="size-5" />
                                    </button>
                                )}
                            </div>
                        )}
                        <div className="p-6">
                            {children}
                        </div>
                    </DialogPanel>
                </TransitionChild>
            </Dialog>
        </Transition>
    );
}
