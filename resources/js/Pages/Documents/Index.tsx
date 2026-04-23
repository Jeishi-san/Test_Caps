import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import { useState, useEffect } from 'react';
import type { DocumentEntity, FolderEntity, OwnerSummary } from '@/types/entities';
import DragDropUploadModal from '@/Components/DragDropUploadModal';
import DocumentPreview from '@/Components/DocumentPreview';
import PrimaryButton from '@/Components/PrimaryButton';
import ShareModal from '@/Components/ShareModal';
import DocumentCard from '@/Components/DocumentCard';
import PostUnlockDialog from '@/Components/PostUnlockDialog';
import axios from 'axios';
import { formatFileSize } from '@/utils/fileSize';
import {
  FileText,
  File,
  Image as ImageIcon,
  Film,
  Music,
  Archive,
  Star,
  MoreVertical,
  Eye,
  Download,
  Share2,
  Edit3,
  Trash2,
  Lock,
  Loader2,
  FolderOpen,
  Info,
  Unlock
} from 'lucide-react';

interface DocumentsPageProps extends PageProps {
    documents: DocumentEntity[];
    folders: FolderEntity[];
    owners: OwnerSummary[];
    rightFolders: FolderEntity[];
}

export default function Index({
    auth,
    documents,
    folders,
    owners,
    rightFolders,
}: DocumentsPageProps) {
    const [showUploadModal, setShowUploadModal] = useState(false);
    const [selectedDocument, setSelectedDocument] = useState<DocumentEntity | null>(null);
    const [showPreview, setShowPreview] = useState(false);
    const [editingDocument, setEditingDocument] = useState<number | null>(null);
    const [editName, setEditName] = useState('');
    const [showShareModal, setShowShareModal] = useState(false);
    const [documentToShare, setDocumentToShare] = useState<{id: number; name: string} | null>(null);
    const [watchedDocuments, setWatchedDocuments] = useState<number[]>([]);
    const [processingDocuments, setProcessingDocuments] = useState<Record<number, string>>({});
    const [showPostUnlockDialog, setShowPostUnlockDialog] = useState(false);
    const [unlockedDocument, setUnlockedDocument] = useState<DocumentEntity | null>(null);

    const handleUploadSuccess = () => {
        router.reload();
    };

    const handlePreview = (document: DocumentEntity) => {
        setSelectedDocument(document);
        setShowPreview(true);
    };

    const handleEdit = (document: DocumentEntity) => {
        setEditingDocument(document.id);
        setEditName(document.name);
    };

    const handleSaveEdit = async (documentId: number) => {
        try {
            await axios.put(`/documents/${documentId}`, {
                name: editName,
            });
            setEditingDocument(null);
            router.reload();
        } catch (error) {
            console.error('Failed to update document:', error);
        }
    };

    const handleDelete = async (documentId: number, documentName: string) => {
        if (!confirm(`Are you sure you want to delete "${documentName}"?`)) return;

        try {
            await axios.delete(`/documents/${documentId}`);
            router.reload();
        } catch (error) {
            console.error('Failed to delete document:', error);
        }
    };

    const handleDownload = (document: DocumentEntity) => {
        const fileUrl = document.file_path.startsWith('http') 
            ? document.file_path 
            : `/${document.file_path}`;
        
        const link = window.document.createElement('a');
        link.href = fileUrl;
        link.download = document.name;
        link.click();
    };

    const handleShare = (document: DocumentEntity) => {
        setDocumentToShare({
            id: document.id,
            name: document.name,
        });
        setShowShareModal(true);
    };

    const handleWatch = async (documentId: number) => {
        try {
            await axios.post(`/documents/${documentId}/watch`);
            setWatchedDocuments(prev => (prev.includes(documentId) ? prev : [...prev, documentId]));
        } catch (error) {
            console.error('Failed to watch document:', error);
        }
    };

    const handleUnwatch = async (documentId: number) => {
        try {
            await axios.delete(`/documents/${documentId}/unwatch`);
            setWatchedDocuments(prev => prev.filter(id => id !== documentId));
        } catch (error) {
            console.error('Failed to unwatch document:', error);
        }
    };

    const handleUnlock = async (document: DocumentEntity) => {
        setProcessingDocuments(prev => ({
            ...prev,
            [document.id]: 'Decrypting document...'
        }));

        try {
            // Poll backend for decryption job status
            const pollInterval = setInterval(async () => {
                // For demo - after 3 seconds complete and show post unlock dialog
                clearInterval(pollInterval);
                setProcessingDocuments(prev => {
                    const newState = {...prev};
                    delete newState[document.id];
                    return newState;
                });
                
                setUnlockedDocument(document);
                setShowPostUnlockDialog(true);
                
                // Trigger download
                handleDownload(document);
            }, 3000);

        } catch (error) {
            console.error('Failed to unlock document:', error);
            setProcessingDocuments(prev => {
                const newState = {...prev};
                delete newState[document.id];
                return newState;
            });
        }
    };

    const handleToggleStar = async (documentId: number) => {
        await axios.post(`/documents/toggle-star`, { document_id: documentId });
        router.reload();
    };

    const handleMove = (document: DocumentEntity) => {
        // Implement move to folder dialog
        console.log('Move document:', document);
    };

    const handleInfo = (document: DocumentEntity) => {
        // Open file info modal
        handlePreview(document);
    };

    // Load all watched document IDs in one request to avoid N+1 calls.
    useEffect(() => {
        const loadWatchedDocuments = async () => {
            try {
                const response = await axios.get('/documents/watched-ids');
                const watchedIds = Array.isArray(response.data?.watched_document_ids)
                    ? (response.data.watched_document_ids as number[])
                    : [];
                setWatchedDocuments(watchedIds);
            } catch (error) {
                console.error('Failed to load watched documents:', error);
                setWatchedDocuments([]);
            }
        };

        void loadWatchedDocuments();
    }, [documents]);

    const getFileIcon = (extension: string) => {
        const ext = extension?.toLowerCase();
        
        if (ext === 'pdf') return <FileText className="w-8 h-8 text-red-500" />;
        if (['doc', 'docx'].includes(ext)) return <FileText className="w-8 h-8 text-blue-600" />;
        if (['xls', 'xlsx'].includes(ext)) return <FileText className="w-8 h-8 text-green-600" />;
        if (['ppt', 'pptx'].includes(ext)) return <FileText className="w-8 h-8 text-orange-500" />;
        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return <ImageIcon className="w-8 h-8 text-purple-500" />;
        if (['mp4', 'webm', 'mov'].includes(ext)) return <Film className="w-8 h-8 text-indigo-500" />;
        if (['mp3', 'wav', 'flac'].includes(ext)) return <Music className="w-8 h-8 text-pink-500" />;
        if (['zip', 'rar', '7z', 'tar', 'gz'].includes(ext)) return <Archive className="w-8 h-8 text-amber-600" />;
        
        return <File className="w-8 h-8 text-gray-500" />;
    };

    const [hoveredCard, setHoveredCard] = useState<number | null>(null);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Documents
                    </h2>
                    <PrimaryButton onClick={() => setShowUploadModal(true)}>
                        <svg
                            className="mr-2 h-4 w-4"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                            />
                        </svg>
                        Upload Files
                    </PrimaryButton>
                </div>
            }
        >
            <Head title="Documents" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            {documents.length === 0 ? (
                                <div className="py-12 text-center">
                                    <svg
                                        className="mx-auto h-12 w-12 text-gray-400"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={2}
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                        />
                                    </svg>
                                    <h3 className="mt-2 text-sm font-medium text-gray-900">
                                        No documents
                                    </h3>
                                    <p className="mt-1 text-sm text-gray-500">
                                        Get started by uploading a document.
                                    </p>
                                    <div className="mt-6">
                                        <PrimaryButton onClick={() => setShowUploadModal(true)}>
                                            Upload your first document
                                        </PrimaryButton>
                                    </div>
                                </div>
                            ) : (
                                /* Responsive Document Card Grid */
                                <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
                                    {documents.map((document) => (
                                        <DocumentCard
                                            key={document.id}
                                            document={document}
                                            onPreview={handlePreview}
                                            onUnlock={handleUnlock}
                                            onShare={handleShare}
                                            onRename={handleEdit}
                                            onMove={handleMove}
                                            onDelete={() => handleDelete(document.id, document.name)}
                                            onInfo={handleInfo}
                                            onToggleStar={handleToggleStar}
                                            isProcessing={!!processingDocuments[document.id]}
                                            processingStatus={processingDocuments[document.id]}
                                            isWatched={watchedDocuments.includes(document.id)}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            <DragDropUploadModal
                show={showUploadModal}
                onClose={() => setShowUploadModal(false)}
                folders={rightFolders}
                onSuccess={handleUploadSuccess}
            />

            <DocumentPreview
                show={showPreview}
                onClose={() => setShowPreview(false)}
                document={selectedDocument}
            />

            {documentToShare && (
                <ShareModal
                    show={showShareModal}
                    onClose={() => setShowShareModal(false)}
                    documentId={documentToShare.id}
                    documentName={documentToShare.name}
                    onSuccess={() => {
                        setShowShareModal(false);
                        setDocumentToShare(null);
                    }}
                />
            )}

            {unlockedDocument && (
                <PostUnlockDialog
                    show={showPostUnlockDialog}
                    documentName={unlockedDocument.name}
                    onClose={() => {
                        setShowPostUnlockDialog(false);
                        setUnlockedDocument(null);
                    }}
                    onKeepOriginal={async () => {
                        // Keep original - no action required on backend
                    }}
                    onDeleteOriginal={async () => {
                        await axios.delete(`/documents/${unlockedDocument.id}`);
                        router.reload();
                    }}
                />
            )}
        </AuthenticatedLayout>
    );
}
