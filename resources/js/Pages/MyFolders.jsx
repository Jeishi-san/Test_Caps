import { ref } from 'vue';

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
