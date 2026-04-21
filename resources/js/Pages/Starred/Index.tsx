import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import { useState } from 'react';
import type { DocumentEntity, FolderEntity } from '@/types/entities';
import axios from 'axios';
import { formatFileSize } from '@/utils/fileSize';

interface StarredPageProps extends PageProps {
    starredDocuments: DocumentEntity[];
    starredFolders: FolderEntity[];
}

export default function Index({
    auth,
    starredDocuments,
    starredFolders
}: StarredPageProps) {
    const [loading, setLoading] = useState(false);

    const handleToggleStar = async (type: 'document' | 'folder', id: number) => {
        setLoading(true);
        try {
            const endpoint = type === 'document' ? `/documents/toggle-star` : `/folders/toggle-star`;
            await axios.post(endpoint, { [`${type}_id`]: id });
            router.reload();
        } catch (error) {
            console.error('Failed to toggle star:', error);
        } finally {
            setLoading(false);
        }
    };

    const getFileIcon = (extension: string) => {
        const iconMap: Record<string, string> = {
            pdf: '📄',
            doc: '📝',
            docx: '📝',
            xls: '📊',
            xlsx: '📊',
            ppt: '📈',
            pptx: '📈',
            jpg: '🖼️',
            jpeg: '🖼️',
            png: '🖼️',
            gif: '🖼️',
            mp4: '🎥',
            mp3: '🎵',
            zip: '🗜️',
        };
        return iconMap[extension?.toLowerCase()] || '📎';
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Starred Items
                </h2>
            }
        >
            <Head title="Starred Items" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Starred Folders Section */}
                    {starredFolders.length > 0 && (
                        <div className="mb-8">
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                                Starred Folders
                            </h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                                {starredFolders.map((folder) => (
                                    <div key={folder.id} className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                                        <div className="p-6">
                                            <div className="flex items-center justify-between mb-4">
                                                <div className="flex items-center">
                                                    <span className="text-2xl mr-3">🗂️</span>
                                                    <h4 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                                                        {folder.name}
                                                    </h4>
                                                </div>
                                                <button
                                                    onClick={() => handleToggleStar('folder', folder.id)}
                                                    disabled={loading}
                                                    className="text-yellow-500 hover:text-yellow-600 disabled:opacity-50"
                                                    title="Remove from starred"
                                                >
                                                    ⭐
                                                </button>
                                            </div>
                                            <div className="text-sm text-gray-500 dark:text-gray-400">
                                                Updated {folder.updated_at ? new Date(folder.updated_at).toLocaleDateString() : 'N/A'}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Starred Documents Section */}
                    {starredDocuments.length > 0 && (
                        <div className="mb-8">
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                                Starred Documents
                            </h3>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                                {starredDocuments.map((document) => (
                                    <div key={document.id} className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                                        <div className="p-6">
                                            <div className="flex items-center justify-between mb-4">
                                                <div className="flex items-center">
                                                    <span className="text-2xl mr-3">{getFileIcon(document.extension)}</span>
                                                    <h4 className="text-lg font-medium text-gray-900 dark:text-gray-100 truncate">
                                                        {document.name}
                                                    </h4>
                                                </div>
                                                <button
                                                    onClick={() => handleToggleStar('document', document.id)}
                                                    disabled={loading}
                                                    className="text-yellow-500 hover:text-yellow-600 disabled:opacity-50"
                                                    title="Remove from starred"
                                                >
                                                    ⭐
                                                </button>
                                            </div>
                                            <div className="space-y-2">
                                                <div className="text-sm text-gray-500 dark:text-gray-400">
                                                    {formatFileSize(document.size)} • {document.extension?.toUpperCase()}
                                                </div>
                                                <div className="text-sm text-gray-500 dark:text-gray-400">
                                                    Updated {document.updated_at ? new Date(document.updated_at).toLocaleDateString() : 'N/A'}
                                                </div>
                                                {document.tags && document.tags.length > 0 && (
                                                    <div className="flex flex-wrap gap-1">
                                                        {document.tags.slice(0, 3).map((tag) => (
                                                            <span
                                                                key={tag.id}
                                                                className="inline-flex items-center rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800"
                                                            >
                                                                {tag.name}
                                                            </span>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Empty State */}
                    {starredFolders.length === 0 && starredDocuments.length === 0 && (
                        <div className="text-center py-12">
                            <div className="text-gray-500 dark:text-gray-400">
                                <svg className="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                </svg>
                                <h3 className="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No starred items</h3>
                                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    You haven't starred any documents or folders yet.
                                </p>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}