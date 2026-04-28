import React, { useState } from 'react';
import { Inertia } from '@inertiajs/inertia';

const ShareFileModal = ({ documentId, isOpen, onClose }) => {
    const [selectedUsers, setSelectedUsers] = useState([]);
    const [searchQuery, setSearchQuery] = useState('');
    const [isLoading, setIsLoading] = useState(false);

    /**
     * Replaces mock sharing logic with actual API call to /documents/share
     * Adds error handling with toast notifications
     */
    const handleShare = async () => {
        if (selectedUsers.length === 0) {
            showToast('Please select at least one user to share with', 'error');
            return;
        }

        setIsLoading(true);

        try {
            await Inertia.post('/documents/share', {
                document_id: documentId,
                user_ids: selectedUsers,
            }, {
                onSuccess: () => {
                    showToast('Document shared successfully', 'success');
                    onClose();
                    setSelectedUsers([]);
                },
                onError: (errors) => {
                    const errorMessage = errors.message || errors.error || 'Failed to share document';
                    showToast(errorMessage, 'error');
                },
                onFinish: () => {
                    setIsLoading(false);
                }
            });
        } catch (error) {
            showToast('An unexpected error occurred while sharing', 'error');
            setIsLoading(false);
        }
    };

    // Fallback toast implementation if toast library is not available
    const showToast = (message, type = 'info') => {
        if (typeof window !== 'undefined' && window.toast) {
            window.toast(message, { type });
        } else {
            alert(`${type.toUpperCase()}: ${message}`);
        }
    };

    // Mock user search (replace with actual API call to search users)
    const searchUsers = (query) => {
        // TODO: Replace with actual API call: /api/users/search?query=${query}
        return [
            { id: 1, name: 'John Doe' },
            { id: 2, name: 'Jane Smith' },
            { id: 3, name: 'Bob Johnson' },
        ].filter(user => user.name.toLowerCase().includes(query.toLowerCase()));
    };

    if (!isOpen) return null;

    const filteredUsers = searchUsers(searchQuery);

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg shadow-xl w-full max-w-md">
                <div className="flex justify-between items-center p-4 border-b">
                    <h3 className="text-lg font-semibold">Share Document</h3>
                    <button
                        onClick={onClose}
                        className="text-gray-500 hover:text-gray-700 text-2xl"
                    >
                        &times;
                    </button>
                </div>
                <div className="p-4">
                    <input
                        type="text"
                        placeholder="Search users..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        className="w-full p-2 border border-gray-300 rounded mb-4"
                    />
                    <div className="max-h-60 overflow-y-auto">
                        {filteredUsers.length === 0 ? (
                            <p className="text-gray-500 text-center py-2">No users found</p>
                        ) : (
                            filteredUsers.map(user => (
                                <div key={user.id} className="flex items-center p-2 hover:bg-gray-100 rounded">
                                    <input
                                        type="checkbox"
                                        id={`user-${user.id}`}
                                        checked={selectedUsers.includes(user.id)}
                                        onChange={(e) => {
                                            if (e.target.checked) {
                                                setSelectedUsers([...selectedUsers, user.id]);
                                            } else {
                                                setSelectedUsers(selectedUsers.filter(id => id !== user.id));
                                            }
                                        }}
                                        className="mr-2"
                                    />
                                    <label htmlFor={`user-${user.id}`} className="cursor-pointer">
                                        {user.name}
                                    </label>
                                </div>
                            ))
                        )}
                    </div>
                </div>
                <div className="flex justify-end gap-2 p-4 border-t">
                    <button
                        onClick={onClose}
                        className="px-4 py-2 text-gray-700 border border-gray-300 rounded hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                    <button
                        onClick={handleShare}
                        disabled={isLoading || selectedUsers.length === 0}
                        className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:bg-blue-300"
                    >
                        {isLoading ? 'Sharing...' : 'Share Document'}
                    </button>
                </div>
            </div>
        </div>
    );
};

export default ShareFileModal;
