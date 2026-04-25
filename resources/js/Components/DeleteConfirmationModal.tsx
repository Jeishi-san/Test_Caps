import { AlertTriangle } from 'lucide-react';
import Modal from '@/Components/Modal';
import DangerButton from '@/Components/DangerButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { PropsWithChildren } from 'react';

interface DeleteConfirmationModalProps {
    show: boolean;
    onClose: () => void;
    onConfirm: () => void;
    itemName: string;
    itemType?: string; // e.g., 'document', 'folder'
    processing?: boolean;
}

export default function DeleteConfirmationModal({
    show,
    onClose,
    onConfirm,
    itemName,
    itemType = 'item',
    processing = false,
}: DeleteConfirmationModalProps) {
    const handleConfirm = () => {
        onConfirm();
    };

    return (
        <Modal
            show={show}
            onClose={onClose}
            title="Delete Confirmation"
            zIndex="100"
        >
            <div className="space-y-4">
                <div className="flex items-start gap-4">
                    <div className="flex-shrink-0">
                        <div className="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                            <AlertTriangle className="w-6 h-6 text-red-600" />
                        </div>
                    </div>
                    <div>
                        <h4 className="text-lg font-medium text-gray-900">
                            Delete {itemType}
                        </h4>
                        <p className="mt-1 text-sm text-gray-600">
                            Are you sure you want to delete "<span className="font-semibold">{itemName}</span>"? This action cannot be undone.
                        </p>
                    </div>
                </div>

                <div className="mt-6 flex justify-end gap-3 border-t border-gray-200 pt-4">
                    <SecondaryButton onClick={onClose} type="button">
                        Cancel
                    </SecondaryButton>
                    <DangerButton
                        onClick={handleConfirm}
                        disabled={processing}
                    >
                        {processing ? 'Deleting…' : 'Delete'}
                    </DangerButton>
                </div>
            </div>
        </Modal>
    );
}
