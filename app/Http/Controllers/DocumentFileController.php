<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Folder;
use App\Models\AccessLog;
use App\Http\Requests\StoreDocumentRequest;
use App\Services\DocumentService;
use App\Services\DocumentFileService;
use App\Services\DocumentEncryptionService;
use App\Services\ErrorHandlingService;
use App\Http\Controllers\Traits\HandlesDocumentServing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Controller for document file operations.
 * 
 * Handles file uploads, downloads, streaming, and file-related metadata operations.
 * Separates file concerns from document metadata concerns.
 */
class DocumentFileController extends Controller
{
    use HandlesDocumentServing;

    public function __construct(
        protected DocumentService $documentService,
        protected DocumentFileService $fileService,
        protected DocumentEncryptionService $encryptionService,
    ) {
    }

    /**
     * Get all files in a folder with optional tag filtering.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, ?int $folder = null)
    {
        try {
            $folderId = $folder ?? $request->route('folder');
            $tags = $request->input('tags') ?? [];

            $result = $this->documentService->getFolderFiles($folderId, $tags);

            return response()->json([
                'documents'  => $result['documents'],
                'folderInfo' => $result['folderInfo'],
                'folders'    => $result['folderData'],
                'folder_id'  => $folderId,
            ]);
        } catch (\Exception $e) {
            return ErrorHandlingService::response(
                ErrorHandlingService::handleServerError($e, 'retrieving folder files')
            );
        }
    }

    /**
     * Upload document files.
     *
     * Supports single and batch uploads with optional folder creation.
     *
     * @param StoreDocumentRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(StoreDocumentRequest $request)
    {
        try {
            Log::info('Upload request received:', [
                'has_files' => $request->hasFile('files'),
                'has_files_array' => $request->hasFile('files[]'),
                'folder_id' => $request->input('folder_id'),
            ]);

            // Handle both single file and array formats
            if (!$request->hasFile('files') && $request->hasFile('files[]')) {
                $request->merge(['files' => $request->file('files[]')]);
            }

            $folderId = DB::transaction(function () use ($request) {
                return $this->documentService->setUploadDocumentFiles($request);
            });

            AccessLog::log('upload', 'document', $folderId, $request);

            return response()->json([
                'message' => 'Files uploaded successfully',
                'url' => route('files.index', $folderId),
                'folder_id' => $folderId,
            ], 200);
        } catch (\InvalidArgumentException $e) {
            Log::warning('Validation error during upload: ' . $e->getMessage());
            return ErrorHandlingService::response(
                ErrorHandlingService::handleUploadError('files', $e->getMessage())
            );
        } catch (\Exception $e) {
            return ErrorHandlingService::response(
                ErrorHandlingService::handleServerError($e, 'uploading files')
            );
        }
    }

    /**
     * Download a document (as attachment).
     *
     * @param Document $document
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function download(Document $document)
    {
        try {
            return $this->authorizeAndServeDocument($document, inline: false, logAction: 'download');
        } catch (\Exception $e) {
            return ErrorHandlingService::response(
                ErrorHandlingService::handleServerError($e, 'downloading document')
            );
        }
    }

    /**
     * View a document (inline, for preview).
     *
     * @param Document $document
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function view(Document $document)
    {
        try {
            return $this->authorizeAndServeDocument($document, inline: true, logAction: 'view');
        } catch (\Exception $e) {
            return ErrorHandlingService::response(
                ErrorHandlingService::handleServerError($e, 'viewing document')
            );
        }
    }

    /**
     * Filter documents in a folder by tags.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function filterByTag(Request $request)
    {
        try {
            $request->validate([
                'folder' => ['required', 'integer', 'exists:folders,id'],
                'tags' => ['nullable', 'array'],
                'tags.*' => ['integer', 'exists:tags,id'],
            ]);

            $documents = $this->documentService->setFilterDocumentByTag(
                $request->input('folder'),
                $request->input('tags') ?? []
            );

            return response()->json(['documents' => $documents]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ErrorHandlingService::response(
                ErrorHandlingService::handleValidationError($e, 'filtering documents by tag')
            );
        } catch (\Exception $e) {
            return ErrorHandlingService::response(
                ErrorHandlingService::handleServerError($e, 'filtering documents')
            );
        }
    }

    /**
     * Update document visibility (public/private toggle).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateVisibility(Request $request)
    {
        try {
            $validated = $request->validate([
                'document_id' => ['required', 'integer', 'exists:documents,id'],
                'visibility' => ['required', 'in:public,private'],
            ]);

            $document = Document::findOrFail($validated['document_id']);

            if (!Auth::user()->can('update', $document)) {
                return ErrorHandlingService::response(
                    ErrorHandlingService::handleAuthorizationError('Document', 'modify')
                );
            }

            $document->update([
                'visibility' => $validated['visibility'] === 'private' ? 'public' : 'private',
            ]);

            return response()->json([
                'message' => 'Visibility updated successfully',
                'visibility' => $document->visibility,
                'url' => route('files.index', $document->folder_id),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ErrorHandlingService::response(
                ErrorHandlingService::handleValidationError($e, 'updating visibility')
            );
        } catch (\Exception $e) {
            return ErrorHandlingService::response(
                ErrorHandlingService::handleServerError($e, 'updating document visibility')
            );
        }
    }

    /**
     * Update document order within a folder.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateOrder(Request $request)
    {
        try {
            $validated = $request->validate([
                'folder_id' => ['required', 'exists:folders,id'],
                'document_ids' => ['required', 'array', 'min:1'],
                'document_ids.*' => ['integer', 'exists:documents,id'],
            ]);

            DB::transaction(function () use ($validated) {
                $this->documentService->setUpdateDocumentOrder(
                    $validated['folder_id'],
                    $validated['document_ids']
                );
            });

            return response()->json([
                'message' => 'Document order updated successfully',
                'url' => route('files.index', $validated['folder_id']),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ErrorHandlingService::response(
                ErrorHandlingService::handleValidationError($e, 'updating document order')
            );
        } catch (\Exception $e) {
            return ErrorHandlingService::response(
                ErrorHandlingService::handleDatabaseError($e, 'updating document order')
            );
        }
    }
}
