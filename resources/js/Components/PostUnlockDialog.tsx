import { useState } from 'react';
import { Shield, Trash2, Check, X, Loader2 } from 'lucide-react';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import DangerButton from '@/Components/DangerButton';

interface PostUnlockDialogProps {
  show: boolean;
  documentName: string;
  onClose: () => void;
  onKeepOriginal: () => Promise<void>;
  onDeleteOriginal: () => Promise<void>;
}

export default function PostUnlockDialog({
  show,
  documentName,
  onClose,
  onKeepOriginal,
  onDeleteOriginal,
}: PostUnlockDialogProps) {
  const [isLoading, setIsLoading] = useState(false);
  const [selectedAction, setSelectedAction] = useState<'keep' | 'delete' | null>(null);

  const handleKeep = async () => {
    setIsLoading(true);
    setSelectedAction('keep');
    await onKeepOriginal();
    setIsLoading(false);
    onClose();
  };

  const handleDelete = async () => {
    setIsLoading(true);
    setSelectedAction('delete');
    await onDeleteOriginal();
    setIsLoading(false);
    onClose();
  };

  return (
    <Modal show={show} onClose={onClose} maxWidth="md">
      <div className="p-6">
        <div className="flex items-center gap-4 mb-6">
          <div className="w-14 h-14 rounded-full bg-gradient-to-br from-indigo-100 to-purple-100 flex items-center justify-center">
            <Shield className="w-7 h-7 text-indigo-600" />
          </div>
          <div>
            <h3 className="text-lg font-semibold text-gray-900">
              Document Unlocked Successfully
            </h3>
            <p className="text-sm text-gray-500 mt-0.5">
              Your file has been decrypted and is ready for download
            </p>
          </div>
        </div>

        <div className="bg-gray-50 rounded-lg p-4 mb-6">
          <p className="text-sm text-gray-700">
            The protected original of <span className="font-medium text-gray-900">"{documentName}"</span> is still stored in Stegolock.
          </p>
        </div>

        <p className="text-sm text-gray-600 mb-6">
          Would you like to keep the encrypted original in your storage or permanently delete it now that you have unlocked the plaintext version?
        </p>

        <div className="grid grid-cols-2 gap-4">
          <button
            onClick={handleKeep}
            disabled={isLoading}
            className={`p-4 rounded-xl border-2 transition-all duration-200 text-left ${
              selectedAction === 'keep'
                ? 'border-indigo-500 bg-indigo-50'
                : 'border-gray-200 hover:border-gray-300'
            } ${isLoading && selectedAction !== 'keep' ? 'opacity-50 cursor-wait' : ''}`}
          >
            <div className="flex items-center gap-3 mb-2">
              <div className={`w-8 h-8 rounded-full flex items-center justify-center ${
                selectedAction === 'keep' ? 'bg-indigo-500 text-white' : 'bg-gray-100 text-gray-600'
              }`}>
                {isLoading && selectedAction === 'keep' ? (
                  <Loader2 className="w-4 h-4 animate-spin" />
                ) : (
                  <Check className="w-4 h-4" />
                )}
              </div>
              <span className="font-medium text-gray-900">Keep Original</span>
            </div>
            <p className="text-xs text-gray-500 ml-11">
              The encrypted copy will remain stored in your vault
            </p>
          </button>

          <button
            onClick={handleDelete}
            disabled={isLoading}
            className={`p-4 rounded-xl border-2 transition-all duration-200 text-left ${
              selectedAction === 'delete'
                ? 'border-red-500 bg-red-50'
                : 'border-gray-200 hover:border-gray-300'
            } ${isLoading && selectedAction !== 'delete' ? 'opacity-50 cursor-wait' : ''}`}
          >
            <div className="flex items-center gap-3 mb-2">
              <div className={`w-8 h-8 rounded-full flex items-center justify-center ${
                selectedAction === 'delete' ? 'bg-red-500 text-white' : 'bg-gray-100 text-gray-600'
              }`}>
                {isLoading && selectedAction === 'delete' ? (
                  <Loader2 className="w-4 h-4 animate-spin" />
                ) : (
                  <Trash2 className="w-4 h-4" />
                )}
              </div>
              <span className="font-medium text-gray-900">Delete Original</span>
            </div>
            <p className="text-xs text-gray-500 ml-11">
              Permanently remove the encrypted version from storage
            </p>
          </button>
        </div>

        <div className="mt-6 pt-4 border-t border-gray-100 flex justify-end gap-3">
          <SecondaryButton onClick={onClose} disabled={isLoading}>
            Cancel
          </SecondaryButton>
        </div>
      </div>
    </Modal>
  );
}
