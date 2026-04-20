<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Folder;
use App\Models\User;
use App\Services\Stego\CryptoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentService
{
    public function __construct(
        private readonly CryptoService $crypto,
        private readonly DocumentEncryptionService $encryptionService,
        private readonly DocumentFileService $fileService,
        private readonly DocumentAccessService $accessService,
        private readonly DocumentNotificationService $notificationService,
    ) {
    }

    public function isEncryptionEnabled(): bool
    {
        return $this->encryptionService->isEncryptionEnabled();
    }

    public function getMasterKey(): string
    {
        return $this->encryptionService->getMasterKey();
    }

    public function encryptDocumentFile(Document $document): void
    {
        $this->encryptionService->encryptDocumentFile($document);
    }

    public function decryptDocumentContent(Document $document): string
    {
        return $this->encryptionService->decryptDocumentContent($document);
    }

    public function createAndSaveDocument(
        UploadedFile $file,
        string $filePath,
        int $folderId,
        string $visibility = 'public'
    ): Document {
        return $this->fileService->createAndSaveDocument($file, $filePath, $folderId, $visibility);
    }

    public function deleteDocumentFile(Document $document): bool
    {
        return $this->fileService->deleteDocumentFile($document);
    }

    public function formatFileSize(int $bytes): string
    {
        return $this->fileService->formatFileSize($bytes);
    }

    public function getFolderInfo(int $folderId): ?array
    {
        return $this->fileService->getFolderInfo($folderId);
    }

    public function normalizeUploadedFiles(UploadedFile|array|null $files): array
    {
        return $this->fileService->normalizeUploadedFiles($files);
    }

    public function createUrlDocument(int $folderId, string $name, string $url, string $visibility = 'public'): Document
    {
        return $this->fileService->createUrlDocument($folderId, $name, $url, $visibility);
    }

    public function ensureChildFolderOnce(
        string $physicalParentPath,
        string $childName,
        int $parentFolderId,
        string $visibility,
        ?int &$createdChildFolderId
    ): void {
        $this->fileService->ensureChildFolderOnce($physicalParentPath, $childName, $parentFolderId, $visibility, $createdChildFolderId);
    }

    public function canUserAccessDocument(User $user, Document $document): bool
    {
        return $this->accessService->canUserAccessDocument($user, $document);
    }

    public function isDocumentSharedWithUser(Document $document, User $user): bool
    {
        return $this->accessService->isDocumentSharedWithUser($document, $user);
    }

    public function sendDocumentEmail(
        int $documentId,
        ?string $userEmail,
        string $type = 'email_shared',
        ?string $title = null,
        ?string $body = null,
        ?string $content = null
    ) {
        return $this->notificationService->sendDocumentEmail(
            $documentId,
            $userEmail,
            $type,
            $title,
            $body,
            $content
        );
    }

    public function setSendDocumentEmail($request)
    {
        return $this->sendDocumentEmail(
            (int)$request->document_id,
            $request->user_email,
            (string)$request->type,
            $request->title,
            $request->body,
            $request->content
        );
    }

    public function getDocumentNotifications(int $documentId)
    {
        return $this->notificationService->getDocumentNotifications($documentId);
    }

    public function notifyWatchers(Document $document, User $updatingUser): void
    {
        $this->notificationService->notifyWatchers($document, $updatingUser);
    }

    public function setUpdateDocumentOrder($folderId, $documentIds): int
    {
        foreach ($documentIds as $position => $documentId) {
            Document::whereFolderId($folderId)->whereId($documentId)->update(['position' => $position]);
        }

        return $folderId;
    }

    public function getFolderFiles($folderId, $tags = []): array
    {
        $documentIds = $this->getDocumentIdsByTags($tags);

        $documents = Document::whereFolderId($folderId)
            ->when($documentIds !== [], fn($q) => $q->whereIn('id', $documentIds))
            ->with('tags')
            ->latest()
            ->get();

        $folderInfo = $this->getFolderInfo((int)$folderId);
        $folderData = generateSidebarMenu();

        return compact('documents', 'folderInfo', 'folderData');
    }

    public function setFilterDocumentByTag($folderId, $tags = [])
    {
        $documentIds = $this->getDocumentIdsByTags($tags);

        $documents = Document::whereFolderId($folderId)
            ->when($documentIds !== [], fn($q) => $q->whereIn('id', $documentIds))
            ->with('tags')
            ->latest()
            ->get();

        return $documents->isEmpty() ? [] : $documents;
    }

    public function setUploadDocumentFiles($request): int
    {
        $folderId = $request->input('folder_id');

        if ($request->has('url')) {
            return $this->uploadUrl($request);
        }

        if ($request->has('folder_name')) {
            return (int)($this->uploadFolder($request) ?? $folderId);
        }

        return $this->uploadFiles($request);
    }

    public function setChangeFile($request)
    {
        $documentId = $request->input('document_id');
        $type = $request->input('type');
        $requestData = $request->input('data');
        $folderId = $request->input('folder_id');

        $document = Document::find($documentId);
        $folder = Folder::find($folderId);

        if (!$document) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        if (!$folder) {
            return response()->json(['message' => 'Folder not found'], 404);
        }

        match ($type) {
            'file_name' => $document->update(['name' => $requestData, 'last_updated_at' => now(), 'last_updated_by_user_id' => Auth::id()]),
            'owner' => $document->update(['owner_id' => $requestData, 'last_updated_at' => now(), 'last_updated_by_user_id' => Auth::id()]),
            'archive' => $document->delete(),
            default => null,
        };

        if ($request->hasFile('file') && $type === 'file') {
            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();

            if (!$file->isValid()) {
                Log::error("File {$fileName} is not valid");
                return response()->json(['message' => 'File is not valid'], 400);
            }

            if (Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            }

            $relativePath = 'documents/' . $folder->name . '/' . $fileName;
            $file->move(public_path('documents/' . $folder->name), $fileName);

            $document->update([
                'file_path' => $relativePath,
                'original_name' => $fileName,
                'size' => $file->getSize(),
                'extension' => $file->getClientOriginalExtension(),
                'last_updated_at' => now(),
                'last_updated_by_user_id' => Auth::id(),
            ]);
        }

        if ($type === 'folder') {
            $relativePath = 'documents/' . $folder->name . '/' . $document->original_name;
            Storage::disk('document_public')->move($document->file_path, $relativePath);

            $document->update([
                'folder_id' => $folderId,
                'file_path' => $relativePath,
                'last_updated_at' => now(),
                'last_updated_by_user_id' => Auth::id(),
            ]);
        }

        return $folderId;
    }

    private function getDocumentIdsByTags(array $tags): array
    {
        if (empty($tags)) {
            return [];
        }

        return DB::table('taggables')
            ->where('taggable_type', Document::class)
            ->whereIn('tag_id', $tags)
            ->pluck('taggable_id')
            ->all();
    }

    protected function uploadUrl($request): int
    {
        $folderId = (int)$request->input('folder_id');
        $urlName = (string)$request->input('name');
        $url = (string)$request->input('url');
        $visibility = (string)($request->input('visibility') ?? 'public');

        $this->createUrlDocument($folderId, $urlName, $url, $visibility);

        return $folderId;
    }

    protected function uploadFolder($request): ?int
    {
        $folderId = (int)$request->input('folder_id');
        $parentFolderModel = Folder::find($folderId);

        if (!$parentFolderModel) {
            throw new \InvalidArgumentException('Invalid folder_id provided for upload.');
        }

        $parentFolderName = $parentFolderModel->name;
        $childFolderName = $request->input('folder_name') ?? uniqid();
        $visibility = $request->input('visibility') ?? 'public';
        $parentFolder = public_path('documents/' . $parentFolderName);
        $createdChildFolder = null;

        if (!$request->hasFile('files')) {
            throw new \InvalidArgumentException('No files uploaded.');
        }

        foreach ($this->normalizeUploadedFiles($request->file('files')) as $file) {
            if (!$file->isValid()) {
                Log::error("File {$file->getClientOriginalName()} is not valid");
                continue;
            }

            $this->ensureChildFolderOnce($parentFolder, $childFolderName, $folderId, $visibility, $createdChildFolder);

            $childFolder = $parentFolder . '/' . $childFolderName;
            $relativePath = 'documents/' . $parentFolderName . '/' . $childFolderName . '/' . $file->getClientOriginalName();

            if (Storage::disk('public')->exists($relativePath)) {
                continue;
            }

            $file->move($childFolder, $file->getClientOriginalName());
            $this->createAndSaveDocument($file, $relativePath, $createdChildFolder ?? $folderId, $visibility);
        }

        return $createdChildFolder;
    }

    protected function uploadFiles($request): int
    {
        $folderId = (int)$request->input('folder_id');
        $folder = Folder::find($folderId);

        if (!$folder) {
            throw new \InvalidArgumentException('Invalid folder_id provided for upload.');
        }

        $folderName = $folder->name;
        $parentFolder = public_path('documents/' . $folderName);
        $childFolderName = uniqid();
        $createdChildFolder = null;

        if (!$request->hasFile('files')) {
            throw new \InvalidArgumentException('No files uploaded.');
        }

        foreach ($this->normalizeUploadedFiles($request->file('files')) as $file) {
            if (!$file->isValid()) {
                Log::error("File {$file->getClientOriginalName()} is not valid");
                continue;
            }

            if ($request->input('type') === 'folder' && $folderId) {
                $this->ensureChildFolderOnce($parentFolder, $childFolderName, $folderId, 'public', $createdChildFolder);

                $childFolder = $parentFolder . '/' . $childFolderName;
                $relativePath = 'documents/' . $folderName . '/' . $childFolderName . '/' . $file->getClientOriginalName();

                if (Storage::disk('public')->exists($relativePath)) {
                    continue;
                }

                $file->move($childFolder, $file->getClientOriginalName());
                $this->createAndSaveDocument($file, $relativePath, $createdChildFolder ?? $folderId);
                continue;
            }

            $relativePath = 'documents/' . $folderName . '/' . $file->getClientOriginalName();
            $file->move($parentFolder, $file->getClientOriginalName());

            if (Storage::disk('public')->exists($relativePath)) {
                continue;
            }

            $this->createAndSaveDocument($file, $relativePath, $folderId);
        }

        return $createdChildFolder ?? $folderId;
    }
}
