# Phase 2: Quick Reference Guide

## File Operations

### Upload Files
```php
use App\Http\Controllers\DocumentFileController;

// Route
Route::post('/files/upload', [DocumentFileController::class, 'upload'])->name('files.upload');

// Usage
POST /files/upload
{
  "folder_id": 1,
  "files": [file, file, ...],
  "visibility": "public"
}

// Response
{
  "message": "Files uploaded successfully",
  "url": "/files/1",
  "folder_id": 1
}
```

### Download Document
```php
// Route
Route::get('/files/{document}/download', [DocumentFileController::class, 'download'])->name('files.download');

// Usage - Browser GET
GET /files/123/download
```

### View Document (Inline Preview)
```php
// Route  
Route::get('/files/{document}/view', [DocumentFileController::class, 'view'])->name('files.view');

// Usage - Browser GET for preview
GET /files/123/view
```

---

## Query Scopes

### Accessing Documents

```php
use App\Models\Document;
use App\Models\User;

// Get all accessible documents
$docs = Document::accessibleBy($user)->get();

// Get starred documents accessible to user
$docs = Document::starred()
    ->accessibleBy($user)
    ->get();

// Get user's own documents
$docs = Document::ownedBy($user)->get();

// Get documents directly shared with user
$docs = Document::sharedDirectlyWith($user)->get();

// Get documents in shared folders
$docs = Document::inSharedFolders($user)->get();

// Get public documents only
$docs = Document::public()->get();

// Get recent documents (ordered by updated_at)
$docs = Document::recent()
    ->accessibleBy($user)
    ->limit(10)
    ->get();

// Get encrypted documents
$docs = Document::encrypted()
    ->ownedBy($user)
    ->get();

// Get documents in specific folder
$docs = Document::inFolder($folderId)->get();

// Get watched documents
$docs = Document::watchedBy($user)->get();
```

### Accessing Folders

```php
use App\Models\Folder;

// Get all accessible folders
$folders = Folder::accessibleBy($user)->get();

// Get shared folders
$folders = Folder::sharedWith($user)->get();

// Get folders with user's documents
$folders = Folder::withUserDocuments($user)->get();

// Get starred folders
$folders = Folder::starred()->get();

// Get root folders only
$folders = Folder::root()->ordered()->get();

// Get children of folder
$folders = Folder::childrenOf($parentFolderId)->get();

// Get folders with document count
$folders = Folder::withDocumentCount()->get();
```

---

## Error Handling

### Consistent Error Responses

```php
use App\Services\ErrorHandlingService;

// Validation errors
try {
    // ...
} catch (\Illuminate\Validation\ValidationException $e) {
    return ErrorHandlingService::response(
        ErrorHandlingService::handleValidationError($e, 'uploading files')
    );
}

// Authorization errors
if (!$user->can('update', $document)) {
    return ErrorHandlingService::response(
        ErrorHandlingService::handleAuthorizationError('Document', 'update')
    );
}

// File not found
if (!file_exists($path)) {
    return ErrorHandlingService::response(
        ErrorHandlingService::handleFileNotFound($path, 'document storage')
    );
}

// Upload errors
try {
    // ...
} catch (\Exception $e) {
    return ErrorHandlingService::response(
        ErrorHandlingService::handleUploadError($filename, $e->getMessage())
    );
}

// Decryption errors
try {
    $content = $this->encryptionService->decryptDocumentContent($document);
} catch (\Exception $e) {
    return ErrorHandlingService::response(
        ErrorHandlingService::handleDecryptionError($document->id, $e)
    );
}

// Server errors
try {
    // ... operation ...
} catch (\Exception $e) {
    return ErrorHandlingService::response(
        ErrorHandlingService::handleServerError($e, 'operation name')
    );
}
```

---

## Service Usage

### Encryption Service

```php
use App\Services\DocumentEncryptionService;

$encryptionService = app(DocumentEncryptionService::class);

// Check if encryption enabled
if ($encryptionService->isEncryptionEnabled()) {
    // Encrypt document
    $encryptionService->encryptDocumentFile($document);
}

// Decrypt document content
$content = $encryptionService->decryptDocumentContent($document);
```

### File Service

```php
use App\Services\DocumentFileService;

$fileService = app(DocumentFileService::class);

// Get folder info
$info = $fileService->getFolderInfo($folderId);

// Format file size
$size = $fileService->formatFileSize(1024000); // "1000 KB"

// Delete file
$fileService->deleteDocumentFile($document);

// Create URL document
$doc = $fileService->createUrlDocument(
    $folderId,
    'YouTube Video',
    'https://youtube.com/watch?v=...',
    'public'
);
```

### Access Service

```php
use App\Services\DocumentAccessService;

$accessService = app(DocumentAccessService::class);

// Check if user can access
$canAccess = $accessService->canUserAccessDocument($user, $document);

// Check if directly shared
$isShared = $accessService->isDocumentSharedWithUser($document, $user);

// Check if in shared folder
$inFolder = $accessService->isDocumentInSharedFolder($document, $user);

// Update visibility
$accessService->updateDocumentVisibility($document, 'private');
```

### Notification Service

```php
use App\Services\DocumentNotificationService;

$notificationService = app(DocumentNotificationService::class);

// Send email
$notificationService->sendDocumentEmail(
    $documentId,
    'user@example.com',
    'email_shared',
    'Check this document',
    'This is important',
    'Please review and provide feedback'
);

// Notify watchers
$notificationService->notifyWatchers($document, $updatingUser);

// Notify on share
$notificationService->notifyDocumentShared(
    $document,
    $recipientUser,
    $sharingUser
);
```

---

## Controller Examples

### DocumentFileController

```php
use App\Http\Controllers\DocumentFileController;
use Illuminate\Http\Request;

class Example {
    public function example() {
        $controller = app(DocumentFileController::class);
        
        // List files in folder
        $response = $controller->index($request);
        
        // Upload files
        $response = $controller->upload($storeDocumentRequest);
        
        // Download
        $response = $controller->download($document);
        
        // Preview
        $response = $controller->view($document);
        
        // Filter by tag
        $response = $controller->filterByTag($request);
        
        // Update visibility
        $response = $controller->updateVisibility($request);
    }
}
```

---

## Common Patterns

### Paginated Accessible Documents

```php
$documents = Document::accessibleBy($user)
    ->with('tags', 'owner')
    ->latest()
    ->paginate(15);
```

### Search with Permissions

```php
$search = 'invoice';

$documents = Document::accessibleBy($user)
    ->where('name', 'like', "%{$search}%")
    ->recent()
    ->limit(20)
    ->get();
```

### Folder with Document Count

```php
$folders = Folder::accessibleBy($user)
    ->root()
    ->ordered()
    ->withCount('documents')
    ->get();
```

### Recent Activity for User

```php
$activity = Document::accessibleBy($user)
    ->where('updated_at', '>=', now()->subMonth())
    ->recent()
    ->with('lastUpdatedByUser')
    ->limit(50)
    ->get();
```

### Shared with Me

```php
$shared = Document::sharedDirectlyWith($user)
    ->orWhere(function($q) use ($user) {
        $q->inSharedFolders($user);
    })
    ->latest()
    ->get();
```

---

## Routes Template

```php
use App\Http\Controllers\{DocumentController, DocumentFileController};

Route::middleware('auth:sanctum')->group(function () {
    // Document metadata
    Route::resource('documents', DocumentController::class);
    Route::post('documents/{document}/watch', [DocumentController::class, 'watch']);
    Route::delete('documents/{document}/watch', [DocumentController::class, 'unwatch']);
    
    // File operations
    Route::post('files/upload', [DocumentFileController::class, 'upload'])->name('files.upload');
    Route::get('files/{folder}', [DocumentFileController::class, 'index'])->name('files.index');
    Route::get('files/{document}/download', [DocumentFileController::class, 'download'])->name('files.download');
    Route::get('files/{document}/view', [DocumentFileController::class, 'view'])->name('files.view');
    Route::post('files/filter-tags', [DocumentFileController::class, 'filterByTag']);
    Route::post('files/visibility', [DocumentFileController::class, 'updateVisibility']);
    Route::post('files/order', [DocumentFileController::class, 'updateOrder']);
});
```

---

## Performance Tips

### Use Query Scopes to Avoid N+1

```php
// ❌ Bad - May trigger many queries
foreach ($documents as $doc) {
    if ($user->can('view', $doc)) { // Policy check
        // ...
    }
}

// ✅ Good - Single query
$documents = Document::accessibleBy($user)->get();
```

### Eager Load Relationships

```php
// ❌ Bad - N+1 queries for tags
$documents = Document::accessibleBy($user)->get();

// ✅ Good - Single query
$documents = Document::accessibleBy($user)
    ->with('tags', 'owner')
    ->get();
```

### Use Recently Modified Filter

```php
// ✅ Efficient - Only fetches recent docs
$recent = Document::recent()
    ->accessibleBy($user)
    ->limit(20)
    ->get();
```

---

## Troubleshooting

### Service Not Resolving
- Ensure service is registered in AppServiceProvider
- Check constructor parameter types match service class names

### Query Scope Not Found
- Verify scope method name matches query (e.g., `scopeAccessibleBy` for `->accessibleBy()`)
- Ensure scope is public method on Model class

### Wrong Error Response
- Check that correct ErrorHandlingService method is called
- Verify exception type is being caught correctly
- Ensure response is being returned, not aborted

