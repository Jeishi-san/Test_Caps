import { ref } from 'vue';
import axios from 'axios';

// Toast notification helper (fallback to alert if toast library not available)
const showToast = (message, type = 'info') => {
    if (typeof window !== 'undefined' && window.toast) {
        window.toast(message, { type });
    } else if (typeof alert !== 'undefined') {
        alert(`${type.toUpperCase()}: ${message}`);
    }
};

export function openCreateModal(createModalVisible) {
    createModalVisible.value = true;
}

export function openRenameModal(renameModalVisible, folderId = null, folderName = '') {
    renameModalVisible.value = true;
    // Set folder data for renaming if provided
    if (folderId && folderName) {
        // Assuming you have reactive variables for folder data
        // selectedFolderId.value = folderId;
        // selectedFolderName.value = folderName;
    }
}

export function openDeleteModal(deleteModalVisible, folderId = null, folderName = '') {
    deleteModalVisible.value = true;
    // Set folder data for deletion if provided
    if (folderId && folderName) {
        // selectedFolderId.value = folderId;
        // selectedFolderName.value = folderName;
    }
}

/**
 * Handle API call for folder creation with error handling
 * [Difficulty: Medium] implementation
 */
export async function submitCreate(folderName, createModalVisible) {
    if (!folderName?.trim()) {
        showToast('Folder name cannot be empty', 'error');
        return;
    }

    try {
        const response = await axios.post('/folders', {
            name: folderName.trim(),
        });

        showToast('Folder created successfully', 'success');
        if (createModalVisible?.value !== undefined) {
            createModalVisible.value = false;
        }
        return response.data;
    } catch (error) {
        const errorMessage = error.response?.data?.message 
            || error.response?.data?.name 
            || 'Failed to create folder';
        showToast(errorMessage, 'error');
        throw error;
    }
}

/**
 * Handle API call for folder renaming with error handling
 * [Difficulty: Medium] implementation
 */
export async function submitRename(folderId, newFolderName, renameModalVisible) {
    if (!newFolderName?.trim()) {
        showToast('Folder name cannot be empty', 'error');
        return;
    }

    try {
        const response = await axios.put(`/folders/${folderId}`, {
            name: newFolderName.trim(),
        });

        showToast('Folder renamed successfully', 'success');
        if (renameModalVisible?.value !== undefined) {
            renameModalVisible.value = false;
        }
        return response.data;
    } catch (error) {
        const errorMessage = error.response?.data?.message 
            || error.response?.data?.name 
            || 'Failed to rename folder';
        showToast(errorMessage, 'error');
        throw error;
    }
}

/**
 * Handle API call for folder deletion with error handling
 * [Difficulty: Medium] implementation
 */
export async function submitDelete(folderId, deleteModalVisible) {
    if (!folderId) {
        showToast('Invalid folder selected', 'error');
        return;
    }

    try {
        const response = await axios.delete(`/folders/${folderId}`);

        showToast('Folder deleted successfully', 'success');
        if (deleteModalVisible?.value !== undefined) {
            deleteModalVisible.value = false;
        }
        return response.data;
    } catch (error) {
        const errorMessage = error.response?.data?.message || 'Failed to delete folder';
        showToast(errorMessage, 'error');
        throw error;
    }
}
