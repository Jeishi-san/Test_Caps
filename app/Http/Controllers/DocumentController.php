<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Folder;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Services\DocumentService;
use App\Models\DocumentWatcher;
use App\Models\Notification;
use Inertia\Inertia;
use Illuminate\Http\JsonResponse;


class DocumentController extends Controller
{
    public function __construct(
        protected DocumentService $documentService,
        protected DocumentFileController $documentFileController,
    ) {
    }


    public function index()
    {
        $user = Auth::user();
        
        // Refresh storage used for accurate tracking
        $user->refreshStorageUsed();
        
        // Get all documents the user has permission to view using optimized scope
        $documents = Document::with('tags')
            ->accessibleBy($user)
            ->latest()
            ->get();

        $folders = generateSidebarMenu();
        $owners = User::get(['id', 'name', 'email']);
        $rightFolders = Folder::orderBy('name', 'asc')->get(['id', 'name']);

        return Inertia::render('Documents/Index', [
            'documents' => $documents,
            'folders' => $folders,
            'owners' => $owners,
            'rightFolders' => $rightFolders,
        ]);
    }


    public function updateDocumentOrder(Request $request)
    {
        return $this->documentFileController->updateOrder($request);
    }


    public function getFiles($folder)
    {
        return $this->documentFileController->index(request(), (int)$folder);
    }


    function filterDocumentByTag(Request $request)
    {
        return $this->documentFileController->filterByTag($request);
    }


    public function updateVisibility(Request $request)
    {
        return $this->documentFileController->updateVisibility($request);
    }


    public function sendDocumentEmail(Request $request)
    {
        $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'type' => ['required', 'string', 'max:100'],
            'document_id' => ['required', 'integer', 'exists:documents,id'],
            'content' => ['required', 'string'],
            'user_email' => ['nullable', 'email', 'max:255'],
        ]);

        $notifications = $this->documentService->setSendDocumentEmail($request);

        // Return JSON data for React frontend
        return response()->json([
            'message' => 'Email sent successfully!',
            'notifications' => $notifications
        ]);
    }


    public function getDocumentComments(Request $request)
    {
        $validated = $request->validate([
            'document_id' => ['required', 'integer', 'exists:documents,id'],
        ]);

        $notifications = $this->documentService->getDocumentNotifications($validated['document_id']);

        // Return JSON data for React frontend
        return response()->json(['notifications' => $notifications]);
    }



    public function uploadDocumentFiles(StoreDocumentRequest $request)
    {
        return $this->documentFileController->upload($request);
    }

    public function show(Document $document)
    {
        $document->load(['tags', 'comments']);

        return Inertia::render('Documents/Show', [
            'document' => $document,
        ]);
    }

    /**
     * Download a document, decrypting it first if it was stored with AES-256-GCM.
     *
     * - Encrypted documents are decrypted in memory and streamed to the browser.
     * - Legacy plaintext documents are served directly.
     * - URL-type documents (YouTube, etc.) redirect to the external URL.
     */
    public function download(Document $document)
    {
        return $this->documentFileController->download($document);
    }

    /**
     * Serve a document inline (for in-browser preview), decrypting if necessary.
     * Uses Content-Disposition: inline so the browser renders it instead of downloading.
     */
    public function view(Document $document)
    {
        return $this->documentFileController->view($document);
    }

    public function update(UpdateDocumentRequest $request, Document $document)
    {
        // Check if user has permission to update the document
        if (!Auth::user()->can('update', $document)) {
            abort(403, 'You do not have permission to update this document.');
        }

        $validated = $request->validated();
        $user = Auth::user();

        $document->update(array_merge($validated, [
            'last_updated_at' => now(),
            'last_updated_by_user_id' => $user->id,
        ]));

        // Notify watchers about the update
        $this->notifyWatchers($document, $user);

        return response()->json([
            'message' => 'Document updated successfully',
            'document' => $document->fresh(['tags']),
        ]);
    }

    private function notifyWatchers(Document $document, $user)
    {
        $watchers = $document->watchers()->with('user')->get();
        
        foreach ($watchers as $watcher) {
            if ($watcher->user->id !== $user->id) {
                Notification::create([
                    'notifiable_id' => $watcher->user->id,
                    'notifiable_type' => User::class,
                    'activity_type' => 'document_updated',
                    'model_type' => Document::class,
                    'model_id' => $document->id,
                    'message' => "Document '{$document->name}' has been updated by {$user->name}",
                    'status' => 'UNREAD',
                    'dismiss_status' => 'UNDISMISSED',
                    'created_by_user_id' => $user->id,
                ]);
            }
        }
    }

    public function watch(Document $document)
    {
        $user = Auth::user();
        
        if (!$user->can('view', $document)) {
            abort(403, 'You do not have permission to watch this document.');
        }

        DocumentWatcher::firstOrCreate([
            'user_id' => $user->id,
            'document_id' => $document->id,
        ]);

        return response()->json(['message' => 'Document added to watch list']);
    }

    public function unwatch(Document $document)
    {
        $user = Auth::user();
        
        DocumentWatcher::where('user_id', $user->id)
            ->where('document_id', $document->id)
            ->delete();

        return response()->json(['message' => 'Document removed from watch list']);
    }

    public function isWatched(Document $document)
    {
        $user = Auth::user();
        $isWatched = $document->isWatchedByUser($user->id);

        return response()->json(['is_watched' => $isWatched]);
    }

    public function watchedIds(): JsonResponse
    {
        $user = Auth::user();

        $watchedIds = DocumentWatcher::where('user_id', $user->id)
            ->pluck('document_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        return response()->json(['watched_document_ids' => $watchedIds]);
    }

    public function destroy(Document $document)
    {
        // Check if user has permission to delete the document
        if (!Auth::user()->can('delete', $document)) {
            abort(403, 'You do not have permission to delete this document.');
        }

        // Define deletable states (including intermediate processing states)
        $deletableStates = ['uploaded', 'encrypted', 'fragmented', 'embedded', 'error', 'completed'];
        
        // If document is in an intermediate state, perform additional cleanup
        if (in_array($document->ingest_status, $deletableStates)) {
            // Cloud deletion - remove files from cloud storage
            if (method_exists($document, 'deleteFromCloud')) {
                try {
                    $document->deleteFromCloud();
                } catch (\Exception $e) {
                    // Log error but continue with deletion
                    \Illuminate\Support\Facades\Log::error('Failed to delete from cloud: ' . $e->getMessage());
                }
            }
            
            // Storage cleanup - remove local temp files
            if (method_exists($document, 'cleanupLocalFiles')) {
                $document->cleanupLocalFiles();
            }
        }

        // Delete physical file
        $document->deleteFile();
        
        // Delete database record
        $document->delete();
        
        // Refresh storage used for accurate tracking
        $user = Auth::user();
        $user->refreshStorageUsed();

        return response()->json(['message' => 'Document deleted successfully']);
    }

    public function changeFile(Request $request)
    {
        $request->validate([
            'document_id' => ['required', 'integer', 'exists:documents,id'],
            'folder_id' => ['required', 'integer', 'exists:folders,id'],
            'type' => ['required', 'in:file_name,owner,archive,file,folder'],
            'data' => ['nullable'],
            'file' => ['nullable', 'file'],
        ]);

        $documentId = $request->input('document_id');
        $document = Document::find($documentId);
        $user = Auth::user();

        $folderId = $this->documentService->setChangeFile($request);

        if ($folderId instanceof JsonResponse) {
            return $folderId;
        }

        // Notify watchers about the update
        $this->notifyWatchers($document, $user);

        return response()->json(['message' => 'Document updated successfully', 'url' => route('getFiles', $folderId)], 200);
    }

    public function toggleStar(Request $request)
    {
        $validated = $request->validate([
            'document_id' => ['required', 'integer', 'exists:documents,id'],
        ]);

        $document = Document::findOrFail($validated['document_id']);
        $this->authorize('update', $document);

        $document->update([
            'is_starred' => !$document->is_starred,
        ]);

        return response()->json([
            'message' => $document->is_starred ? 'Document starred successfully' : 'Document unstarred successfully',
            'is_starred' => $document->is_starred,
        ]);
    }

    public function moveDocument(Request $request, $id)
    {
        $document = Document::findOrFail($id);
        $this->authorize('update', $document);

        $request->validate(['folder_id' => 'nullable|exists:folders,id']);

        // Verify folder ownership if folder_id is provided
        if ($request->folder_id) {
            $folder = Folder::findOrFail($request->folder_id);
            if ($folder->user_id !== Auth::id()) {
                abort(403);
            }
        }

        $document->update(['folder_id' => $request->folder_id]);
        return response()->json(['message' => 'Document moved']);
    }
}