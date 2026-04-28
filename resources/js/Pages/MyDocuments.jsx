import React, { useState } from 'react';
import { Inertia } from '@inertiajs/inertia';
import Tooltip from '@/Components/Tooltip';

const MyDocuments = ({ documents = [], folders = [] }) => {
    const [selectedDocument, setSelectedDocument] = useState(null);
    const [targetFolder, setTargetFolder] = useState('');

    /**
     * Toggles star status via API, updates local state, shows toast notifications
     * @param {number} documentId - ID of the document to toggle star status
     * @param {boolean} currentStarredStatus - Current starred status of the document
     */
    const handleToggleStar = async (documentId, currentStarredStatus) => {
        try {
            await Inertia.put(`/documents/${documentId}/toggle-star`, {
                is_starred: !currentStarredStatus,
            }, {
                onSuccess: () => {
                    showToast('Document star status updated successfully', 'success');
                    // Update local state to reflect change
                    if (selectedDocument?.id === documentId) {
                        setSelectedDocument({
                            ...selectedDocument,
                            is_starred: !currentStarredStatus
                        });
                    }
                },
                onError: (errors) => {
                    const errorMessage = errors.message || errors.error || 'Failed to update star status';
                    showToast(errorMessage, 'error');
                }
            });
        } catch (error) {
            showToast('An unexpected error occurred while updating star status', 'error');
        }
    };

    /**
     * Handles document movement to selected folder via API call
     * @param {number} documentId - ID of the document to move
     */
    const handleMove = async (documentId) => {
        if (!targetFolder) {
            showToast('Please select a target folder', 'error');
            return;
        }

        try {
            await Inertia.put(`/documents/${documentId}/move`, {
                folder_id: targetFolder,
            }, {
                onSuccess: () => {
                    showToast('Document moved successfully', 'success');
                    setTargetFolder('');
                    setSelectedDocument(null);
                },
                onError: (errors) => {
                    const errorMessage = errors.message || errors.error || 'Failed to move document';
                    showToast(errorMessage, 'error');
                }
            });
        } catch (error) {
            showToast('An unexpected error occurred while moving document', 'error');
        }
    };

    // Fallback toast implementation
    const showToast = (message, type = 'info') => {
        if (typeof window !== 'undefined' && window.toast) {
            window.toast(message, { type });
        } else {
            alert(`${type.toUpperCase()}: ${message}`);
        }
    };

    return (
        <div className="container mx-auto p-4">
            <h1 className="text-2xl font-bold mb-6">My Documents</h1>
            
            {/* Folder selector for moving documents */}
            <div className="mb-6 p-4 bg-gray-50 rounded-lg">
                <h2 className="text-lg font-semibold mb-2">Move Document</h2>
                <div className="flex items-center gap-2">
                    <select
                        value={targetFolder}
                        onChange={(e) => setTargetFolder(e.target.value)}
                        className="flex-1 p-2 border border-gray-300 rounded"
                    >
                        <option value="">Select Target Folder</option>
                        {folders.map(folder => (
                            <option key={folder.id} value={folder.id}>
                                {folder.name}
                            </option>
                        ))}
                    </select>
                    <button
                        onClick={() => selectedDocument && handleMove(selectedDocument.id)}
                        disabled={!selectedDocument || !targetFolder}
                        className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:bg-blue-300"
                    >
                        Move Selected Document
                    </button>
                </div>
                {!selectedDocument && (
                    <p className="text-sm text-gray-500 mt-2">Select a document from the list below to move</p>
                )}
            </div>

            {/* Documents list */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {documents.length === 0 ? (
                    <p className="text-gray-500 col-span-full text-center py-8">No documents found</p>
                ) : (
                    documents.map(doc => {
                        const hasError = doc.ingest_status === 'failed' || doc.ingest_status === 'error';
                        const errorContent = hasError ? `Error: ${doc.ingest_error || 'Unknown error'}` : '';
                        
                        return (
                            <Tooltip key={doc.id} content={errorContent} position="top">
                                <div
                                    className={`p-4 border rounded-lg cursor-pointer transition-colors ${
                                        selectedDocument?.id === doc.id
                                            ? 'border-blue-500 bg-blue-50'
                                            : hasError
                                            ? 'border-red-300 bg-red-50 hover:bg-red-100'
                                            : 'border-gray-300 hover:bg-gray-50'
                                    }`}
                                    onClick={() => setSelectedDocument(doc)}
                                >
                                    <div className="flex justify-between items-center mb-2">
                                        <h3 className="font-semibold truncate">{doc.name}</h3>
                                        <div className="flex items-center gap-2">
                                            {hasError && (
                                                <span className="text-red-500 text-sm" aria-label="Document has errors">
                                                    ⚠️
                                                </span>
                                            )}
                                            <button
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    handleToggleStar(doc.id, doc.is_starred);
                                                }}
                                                className={`text-xl ${
                                                    doc.is_starred ? 'text-yellow-500' : 'text-gray-400'
                                                }`}
                                                aria-label={doc.is_starred ? 'Unstar document' : 'Star document'}
                                            >
                                                {doc.is_starred ? '★' : '☆'}
                                            </button>
                                        </div>
                                    </div>
                                    <p className="text-sm text-gray-600">
                                        {doc.extension?.toUpperCase() || 'N/A'} • {doc.size ? `${(doc.size / 1024).toFixed(2)} KB` : 'Unknown size'}
                                    </p>
                                    {doc.folder_id && (
                                        <p className="text-xs text-gray-500 mt-1">
                                            Folder ID: {doc.folder_id}
                                        </p>
                                    )}
                                    {hasError && (
                                        <p className="text-xs text-red-500 mt-1 truncate">
                                            Status: {doc.ingest_status}
                                        </p>
                                    )}
                                </div>
                            </Tooltip>
                        );
                    })
                )}
            </div>
        </div>
    );
};

export default MyDocuments;
