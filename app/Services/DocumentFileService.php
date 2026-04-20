<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Folder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Service for handling document file operations.
 * 
 * Manages:
 * - File uploads and storage
 * - Physical file management (deletion, moving)
 * - Folder creation and organization
 * - File size tracking and formatting
 */
class DocumentFileService
{
    public function __construct(private readonly DocumentEncryptionService $encryptionService)
    {
    }

    /**
     * Create and persist a document record with uploaded file.
     * Automatically encrypts if encryption is enabled.
     *
     * @param UploadedFile $file The uploaded file
     * @param string $filePath Relative storage path
     * @param int $folderId ID of parent folder
     * @param string $visibility Document visibility ('public' or 'private')
     * @return Document The created document
     */
    public function createAndSaveDocument(
        UploadedFile $file,
        string $filePath,
        int $folderId,
        string $visibility = 'public'
    ): Document {
        // Get file size, with fallback to disk if temp file already moved
        try {
            $size = $file->getSize();
            if ($size === false || $size === null || $size < 0) {
                throw new \RuntimeException('Invalid size from getSize()');
            }
        } catch (\RuntimeException $e) {
            $absolutePath = public_path($filePath);
            $size = file_exists($absolutePath) ? filesize($absolutePath) : 0;
        }

        $document = Document::create([
            'name'          => $file->getClientOriginalName(),
            'original_name' => $file->getClientOriginalName(),
            'extension'     => $file->getClientOriginalExtension(),
            'file_path'     => $filePath,
            'size'          => (int)$size,
            'folder_id'     => $folderId,
            'visibility'    => $visibility,
            'owner_id'      => Auth::id(),
            'document_date' => now(),
        ]);

        if ($this->encryptionService->isEncryptionEnabled()) {
            $this->encryptionService->encryptDocumentFile($document);
        }

        return $document;
    }

    /**
     * Create a physical child directory and folder record exactly once per batch.
     * Idempotent - safe to call multiple times.
     *
     * @param string $physicalParentPath Absolute path to parent directory
     * @param string $childName Name of child directory/folder
     * @param int $parentFolderId Parent folder ID
     * @param string $visibility Folder visibility
     * @param int|null $createdChildFolderId Output: ID of created folder (or null on subsequent calls)
     */
    public function ensureChildFolderOnce(
        string $physicalParentPath,
        string $childName,
        int $parentFolderId,
        string $visibility,
        ?int &$createdChildFolderId
    ): void {
        if (!file_exists($physicalParentPath)) {
            mkdir($physicalParentPath, 0777, true);
        }

        $childPath = $physicalParentPath . '/' . $childName;

        if (!file_exists($childPath) && $createdChildFolderId === null) {
            mkdir($childPath, 0777, true);

            $folder = Folder::create([
                'name'       => $childName,
                'parent_id'  => $parentFolderId,
                'visibility' => $visibility,
            ]);
            $createdChildFolderId = $folder->id;
        }
    }

    /**
     * Normalize uploaded files payload to array format.
     * Handles both single file and multiple files scenarios.
     *
     * @param UploadedFile|array|null $files Single file, array of files, or null
     * @return array Array of UploadedFile instances
     */
    public function normalizeUploadedFiles(UploadedFile|array|null $files): array
    {
        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (is_array($files)) {
            return array_values(array_filter($files, fn($file) => $file instanceof UploadedFile));
        }

        return [];
    }

    /**
     * Delete a document's associated file from disk.
     *
     * @param Document $document
     * @return bool True if file was deleted, false if not found
     */
    public function deleteDocumentFile(Document $document): bool
    {
        if (!$document->hasPhysicalFile()) {
            return false; // No file to delete for URL documents
        }

        $filePath = public_path($document->file_path);
        if (file_exists($filePath)) {
            unlink($filePath);
            return true;
        }

        return false;
    }

    /**
     * Get human-readable formatted file size string.
     *
     * @param int $bytes Number of bytes
     * @return string Formatted size (e.g., "1.5 MB")
     */
    public function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, 2) . ' ' . $units[$index];
    }

    /**
     * Check if a URL is a YouTube link.
     *
     * @param string $url
     * @return bool
     */
    public function isYouTubeUrl(string $url): bool
    {
        return (bool)preg_match('~(youtube\.com/watch\?v=|youtu\.be/)~', $url);
    }

    /**
     * Create a URL document (external link to YouTube, etc).
     *
     * @param int $folderId ID of parent folder
     * @param string $name Display name
     * @param string $url External URL
     * @param string $visibility Visibility setting
     * @return Document
     */
    public function createUrlDocument(int $folderId, string $name, string $url, string $visibility = 'public'): Document
    {
        return Document::create([
            'name'          => $name,
            'original_name' => $name,
            'extension'     => $this->isYouTubeUrl($url) ? 'youtube' : '',
            'file_path'     => $url,
            'url'           => $url,
            'size'          => 0,
            'folder_id'     => $folderId,
            'visibility'    => $visibility,
            'owner_id'      => Auth::id(),
            'document_date' => now(),
        ]);
    }

    /**
     * Get information about folder including document counts and sizes.
     *
     * @param int $folderId
     * @return array|null Folder info or null if not found
     */
    public function getFolderInfo(int $folderId): ?array
    {
        $folder = Folder::find($folderId);

        if (!$folder) {
            return null;
        }

        $documents = Document::whereFolderId($folderId)->get();

        return [
            'folder_name'   => $folder->name,
            'num_documents' => [
                'total'   => $documents->count(),
                'public'  => $documents->where('visibility', 'public')->count(),
                'private' => $documents->where('visibility', 'private')->count(),
            ],
            'total_size' => $this->formatFileSize($documents->sum('size')),
            'created_at' => $folder->created_at->format('Y/m/d H:i:s'),
            'updated_at' => $folder->updated_at->format('Y/m/d H:i:s'),
        ];
    }
}
