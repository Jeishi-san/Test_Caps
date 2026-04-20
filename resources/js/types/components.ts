import type { FolderEntity } from '@/types/entities';

export interface UploadModalBaseProps {
    show: boolean;
    onClose: () => void;
    folders: FolderEntity[];
    currentFolderId?: number;
    onSuccess?: () => void;
}

export type ShareableSlug = 'document' | 'folder' | 'stego';

export interface ShareModalComponentProps {
    show: boolean;
    onClose: () => void;
    documentId: number;
    documentName: string;
    slug?: ShareableSlug;
    onSuccess?: () => void;
}
