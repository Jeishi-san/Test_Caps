import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import {
    FileText,
    FolderInput,
    Info,
    MoreVertical,
    Pencil,
    Share2,
    Shield,
    Star,
    Trash2,
    Unlock,
} from 'lucide-react';
import FileInfoModal, { FileInfoDocument } from '@/Components/FileInfoModal';
import ShareModal from '@/Components/ShareModal';
import SecurityPanel from '@/Components/SecurityPanel';
import { formatFileSize } from '@/utils/fileSize';
import type { PageProps } from '@/types';

type MyDocumentCard = FileInfoDocument & {
    document_id?: number;
    file_type?: string;
    filename?: string;
    in_cloud_size?: number;
};

type MyDocumentsProps = PageProps & {
    folders?: Array<{ id: number; name: string }>;
    documents: MyDocumentCard[];
    totalStorage?: number;
    storageLimit?: number;
};

export default function MyDocuments({ auth, documents, folders = [] }: MyDocumentsProps) {
    const menuRef = useRef<HTMLDivElement | null>(null);
    const [openMenuId, setOpenMenuId] = useState<number | null>(null);
    const [showDeleteModal, setShowDeleteModal] = useState(false);
    const [selectedDocId, setSelectedDocId] = useState<number | null>(null);
    const [showKeepFileModal, setShowKeepFileModal] = useState<number | null>(null);
    const [selectedInfo, setSelectedInfo] = useState<FileInfoDocument | null>(null);
    const [showInfo, setShowInfo] = useState(false);
    const [showMoveModal, setShowMoveModal] = useState(false);
    const [selectedMoveDoc, setSelectedMoveDoc] = useState<number | null>(null);
    const [selectedFolderId, setSelectedFolderId] = useState<string>('');
    const [showShareModal, setShowShareModal] = useState(false);
    const [selectedShareDoc, setSelectedShareDoc] = useState<number | null>(null);

    const getDocId = (document: MyDocumentCard) => document.document_id ?? document.id;

    const getFileColor = (document: MyDocumentCard) => {
        const ext = (document.file_type ?? document.extension ?? '').toLowerCase();
        switch (ext) {
            case 'pdf':
                return 'text-red-500 bg-red-50';
            case 'doc':
            case 'docx':
                return 'text-blue-500 bg-blue-50';
            case 'txt':
                return 'text-gray-600 bg-gray-50';
            default:
                return 'text-violet-500 bg-violet-50';
        }
    };

    const toggleMenu = (id: number) => {
        setOpenMenuId((prev) => (prev === id ? null : id));
    };

    const handleInfo = (document: MyDocumentCard) => {
        setSelectedInfo(document);
        setShowInfo(true);
        setOpenMenuId(null);
    };

    const openDeleteModal = (id: number) => {
        setOpenMenuId(null);
        setSelectedDocId(id);
        setShowDeleteModal(true);
    };

    const cancelDelete = () => {
        setShowDeleteModal(false);
        setSelectedDocId(null);
    };

    const confirmDelete = async () => {
        if (!selectedDocId) {
            return;
        }
        setShowDeleteModal(false);
        setSelectedDocId(null);
        console.log('Delete requested', selectedDocId);
    };

    const handleUnlock = async (id: number) => {
        setOpenMenuId(null);
        console.log('Unlock requested', id);
        setShowKeepFileModal(id);
        setSelectedDocId(id);
    };

    const keepFile = async () => {
        if (!selectedDocId) {
            return;
        }
        console.log('Keep file requested', selectedDocId);
        setShowKeepFileModal(null);
        setSelectedDocId(null);
    };

    const handleDeleteFromKeepModal = () => {
        if (showKeepFileModal) {
            openDeleteModal(showKeepFileModal);
        }
        setShowKeepFileModal(null);
    };

    const handleMove = (id: number) => {
        setSelectedMoveDoc(id);
        setShowMoveModal(true);
        setOpenMenuId(null);
    };

    const confirmMove = () => {
        if (!selectedMoveDoc || !selectedFolderId) {
            return;
        }
        router.put(`/documents/${selectedMoveDoc}/move`, {
            folder_id: selectedFolderId === 'root' ? null : selectedFolderId,
        }, {
            onSuccess: () => {
                setShowMoveModal(false);
                setSelectedMoveDoc(null);
                setSelectedFolderId('');
            },
        });
    };

    const handleShare = (id: number) => {
        setSelectedShareDoc(id);
        setShowShareModal(true);
        setOpenMenuId(null);
    };

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (openMenuId && menuRef.current && !menuRef.current.contains(event.target as Node)) {
                setOpenMenuId(null);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, [openMenuId]);

    useEffect(() => {
        const handleEsc = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                cancelDelete();
                setShowKeepFileModal(null);
            }
        };

        if (showDeleteModal || showKeepFileModal) {
            document.addEventListener('keydown', handleEsc);
        }

        return () => {
            document.removeEventListener('keydown', handleEsc);
        };
    }, [showDeleteModal, showKeepFileModal]);

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">My Documents</h2>}
        >
            <Head title="My Documents" />

            <div className="h-full">
                {documents.length > 0 ? (
                    <div className="h-full overflow-y-auto">
                        <div className="grid grid-cols-1 gap-4 p-6 sm:grid-cols-3 lg:grid-cols-4">
                            {documents.map((document) => {
                                const id = getDocId(document);

                                return (
                                    <div
                                        key={id}
                                        className="group relative w-full rounded-lg bg-white p-4 shadow transition hover:shadow-lg hover:ring-1 hover:ring-purple-600"
                                    >
                                        <div className="absolute right-0 top-0 space-x-1 p-4 opacity-0 transition group-hover:opacity-100">
                                            <button type="button">
                                                <Star className="size-8 rounded-md p-1.5 text-gray-400 hover:bg-gray-100" />
                                            </button>

                                            <button type="button" onClick={() => toggleMenu(id)}>
                                                <MoreVertical className="size-8 rounded-md p-1.5 text-gray-400 hover:bg-gray-100" />
                                            </button>
                                        </div>

                                        <FileText className={`size-14 rounded-xl p-2 ${getFileColor(document)}`} />

                                        <h3 className="my-3 truncate text-md font-semibold text-gray-800" title={document.name}>
                                            {document.name}
                                        </h3>

                                        <div className="flex justify-between">
                                            <p className="text-sm text-gray-500">
                                                {formatFileSize(document.in_cloud_size ?? document.size)}
                                            </p>
                                            <p className="text-sm text-gray-500">
                                                {new Date(document.created_at).toLocaleDateString()}
                                            </p>
                                        </div>

                                        {openMenuId === id && (
                                            <div
                                                ref={menuRef}
                                                className="absolute right-2 top-14 z-50 w-40 overflow-hidden rounded-xl border bg-white shadow-lg"
                                            >
                                                <button
                                                    type="button"
                                                    onClick={() => handleUnlock(id)}
                                                    className="flex w-full items-center gap-3 px-4 py-2.5 text-sm hover:bg-gray-50"
                                                >
                                                    <Unlock className="h-4 w-4 text-gray-600" />
                                                    Unlock File
                                                </button>

                                                <div className="border-t" />

                                                <button
                                                    type="button"
                                                    className="flex w-full items-center gap-3 px-4 py-2.5 text-sm hover:bg-gray-50"
                                                >
                                                    <Pencil className="h-4 w-4 text-gray-600" />
                                                    Rename
                                                </button>

                                                <button
                                                    type="button"
                                                    onClick={() => handleMove(id)}
                                                    className="flex w-full items-center gap-3 px-4 py-2.5 text-sm hover:bg-gray-50"
                                                >
                                                    <FolderInput className="h-4 w-4 text-gray-600" />
                                                    Move File
                                                </button>

                                                <div className="border-t" />

                                                <button
                                                    type="button"
                                                    onClick={() => handleShare(id)}
                                                    className="flex w-full items-center gap-3 px-4 py-2.5 text-sm hover:bg-gray-50"
                                                >
                                                    <Share2 className="h-4 w-4 text-gray-600" />
                                                    Share File
                                                </button>

                                                <button
                                                    type="button"
                                                    onClick={() => handleInfo(document)}
                                                    className="flex w-full items-center gap-3 px-4 py-2.5 text-sm hover:bg-gray-50"
                                                >
                                                    <Info className="h-4 w-4 text-gray-600" />
                                                    File Info
                                                </button>

                                                <div className="border-t" />

                                                <button
                                                    type="button"
                                                    onClick={() => openDeleteModal(id)}
                                                    className="flex w-full items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50"
                                                >
                                                    <Trash2 className="h-4 w-4 text-red-500" />
                                                    Delete
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                ) : (
                    <div className="flex flex-1 items-center justify-center p-8">
                        <div className="text-center">
                            <div className="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gray-100">
                                <Shield className="size-10 text-gray-400" />
                            </div>
                            <h3 className="mb-2 text-lg font-semibold text-gray-900">No Documents Found</h3>
                            <p className="text-gray-500">Upload files to get started</p>
                        </div>
                    </div>
                )}

                {showDeleteModal && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={cancelDelete}>
                        <div className="w-80 rounded-xl bg-white p-6 shadow-xl" onClick={(event) => event.stopPropagation()}>
                            <h2 className="mb-2 text-lg font-semibold text-gray-800">Delete File</h2>

                            <p className="mb-6 text-sm text-gray-500">
                                Are you sure you want to delete this file? This action cannot be undone.
                            </p>

                            <div className="flex justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={cancelDelete}
                                    className="rounded-md bg-gray-100 px-4 py-2 text-sm hover:bg-gray-200"
                                >
                                    Cancel
                                </button>

                                <button
                                    type="button"
                                    onClick={confirmDelete}
                                    className="rounded-md bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {showKeepFileModal && (
                    <div
                        className="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
                        onClick={() => setShowKeepFileModal(null)}
                    >
                        <div
                            className="w-80 rounded-xl bg-white p-6 shadow-xl"
                            onClick={(event) => event.stopPropagation()}
                        >
                            <h2 className="mb-2 text-lg font-semibold text-gray-800">File Unlocked</h2>

                            <p className="mb-6 text-sm text-gray-500">
                                Do you want to keep the unlocked file on the system or remove it?
                            </p>

                            <div className="flex justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={keepFile}
                                    className="rounded-md bg-gray-100 px-4 py-2 text-sm hover:bg-gray-200"
                                >
                                    Keep File
                                </button>

                                <button
                                    type="button"
                                    onClick={handleDeleteFromKeepModal}
                                    className="rounded-md bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700"
                                >
                                    Delete File
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                <SecurityPanel />

                <FileInfoModal
                    show={showInfo}
                    document={selectedInfo}
                    onClose={() => setShowInfo(false)}
                    currentUserEmail={auth.user.email}
                />

                {showMoveModal && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40" onClick={() => setShowMoveModal(false)}>
                        <div className="w-96 rounded-xl bg-white p-6 shadow-xl" onClick={(event) => event.stopPropagation()}>
                            <h2 className="mb-4 text-lg font-semibold text-gray-800">Move Document</h2>

                            <p className="mb-4 text-sm text-gray-500">
                                Select a folder to move the document to:
                            </p>

                            <select
                                className="mb-6 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={selectedFolderId}
                                onChange={(e) => setSelectedFolderId(e.target.value)}
                            >
                                <option value="root">— Root (No Folder) —</option>
                                {folders.map((folder) => (
                                    <option key={folder.id} value={folder.id}>{folder.name}</option>
                                ))}
                            </select>

                            <div className="flex justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={() => setShowMoveModal(false)}
                                    className="rounded-md bg-gray-100 px-4 py-2 text-sm hover:bg-gray-200"
                                >
                                    Cancel
                                </button>

                                <button
                                    type="button"
                                    onClick={confirmMove}
                                    disabled={!selectedFolderId}
                                    className="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    Move
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {showShareModal && selectedShareDoc && (
                    <ShareModal
                        show={showShareModal}
                        onClose={() => {
                            setShowShareModal(false);
                            setSelectedShareDoc(null);
                        }}
                        documentId={selectedShareDoc}
                        documentName={documents.find(d => (d.document_id ?? d.id) === selectedShareDoc)?.name ?? 'Document'}
                        slug="document"
                    />
                )}
            </div>
        </AuthenticatedLayout>
    );
}
