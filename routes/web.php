<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StegoWebController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Models\Category;
use App\Models\Document;
use App\Models\Folder;
use App\Models\StegoDocument;
use App\Models\Tag;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentFileController;
use App\Http\Controllers\FileRequestController;
use App\Http\Controllers\ShareDocumentController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StarredController;
use App\Http\Controllers\SettingsController;
use Inertia\Inertia;

// Root entry point - guests see welcome page, auth users go to documents
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('documents.index');
    }
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
})->name('welcome');

// Dashboard
Route::get('/dashboard', function () {
    $userId = Auth::id();

    $stats = [
        'documents'  => Document::count(),
        'folders'    => Folder::count(),
        'categories' => Category::count(),
        'tags'       => Tag::count(),
        'stego_docs' => StegoDocument::where('user_id', $userId)->count(),
    ];

    $recentDocuments = Document::with('tags')
        ->latest()
        ->take(8)
        ->get()
        ->map(fn ($doc) => [
            'id'         => $doc->id,
            'name'       => $doc->name,
            'extension'  => $doc->extension ?? pathinfo($doc->name, PATHINFO_EXTENSION),
            'size'       => $doc->size ?? 0,
            'created_at' => $doc->created_at,
            'is_stegoed' => $doc->stegoDocument()->exists(),
            'tags'       => $doc->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name])->values(),
        ]);

    return Inertia::render('Dashboard', [
        'stats'           => $stats,
        'recentDocuments' => $recentDocuments,
    ]);
})->middleware(['auth'])->name('dashboard');

Route::get('/admin/login', function () {
    return Inertia::render('Admin/Login');
})->name('admin.login');

// Profile routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('profile.avatar.upload');
    Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar'])->name('profile.avatar.delete');
});

Route::middleware('auth')->group(function () {
    $adminGate = function () {
        $user = Auth::user();
        abort_unless($user && in_array($user->role, ['admin', 'superadmin', 'owner'], true), 403);
    };

    Route::get('/admin', function () use ($adminGate) {
        $adminGate();
        return Inertia::render('Admin/Dashboard');
    })->name('admin.dashboard');

    Route::get('/admin/dashboard', function () use ($adminGate) {
        $adminGate();
        return redirect()->route('admin.dashboard');
    })->name('admin.dashboard.alias');

    $adminSection = function (string $title, string $description) use ($adminGate) {
        $adminGate();

        return Inertia::render('Admin/Section', [
            'title' => $title,
            'description' => $description,
        ]);
    };

    Route::get('/admin/users', function () use ($adminGate) {
        $adminGate();
        return Inertia::render('Admin/Users');
    })->name('admin.users');
    Route::get('/admin/fragments', function () use ($adminGate) {
        $adminGate();
        return Inertia::render('Admin/Fragments');
    })->name('admin.fragments');
    Route::get('/admin/activity', function () use ($adminGate) {
        $adminGate();
        return Inertia::render('Admin/Activity');
    })->name('admin.activity');
    Route::get('/admin/admin-management', function () use ($adminGate) {
        $adminGate();
        // Restrict to superadmin/owner only
        $user = Auth::user();
        abort_unless($user && ($user->role === 'owner' || $user->role === 'superadmin'), 403);
        return Inertia::render('Admin/AdminManagement');
    })->name('admin.management');
    Route::get('/admin/encryption-policy', function () use ($adminGate) {
        $adminGate();
        return Inertia::render('Admin/EncryptionPolicy');
    })->name('admin.encryption-policy');
    Route::get('/admin/key-management', function () use ($adminGate) {
        $adminGate();
        return Inertia::render('Admin/KeyManagement');
    })->name('admin.key-management');

    Route::get('/my-documents', function () {
        $user = Auth::user();

        $folders = Folder::query()
            ->latest()
            ->take(6)
            ->get()
            ->map(fn (Folder $folder) => [
                'id' => $folder->id,
                'name' => $folder->name,
                'documentCount' => $folder->documents()->count(),
                'description' => $folder->description ?? 'Secure document collection',
            ])
            ->values();

        $documents = Document::query()
            ->latest()
            ->take(8)
            ->get()
            ->map(fn (Document $document) => [
                'id' => $document->id,
                'name' => $document->name,
                'extension' => $document->extension ?? pathinfo($document->name, PATHINFO_EXTENSION),
                'size' => (int) ($document->size ?? 0),
                'created_at' => $document->created_at?->toISOString() ?? now()->toISOString(),
                'file_path' => $document->file_path,
                'owner' => $user?->email,
            ])
            ->values();

        return Inertia::render('MyDocuments', [
            'folders' => $folders,
            'documents' => $documents,
            'totalStorage' => (int) Document::sum('size'),
            'storageLimit' => 1024 * 1024 * 1024 * 5,
        ]);
    })->middleware(['auth'])->name('my-documents');
});

// Application routes
Route::middleware('auth')->group(function () {

    // Documents index (main app entry point for auth users)
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/watched-ids', [DocumentController::class, 'watchedIds'])->name('documents.watchedIds');

    // Folder Index (list all folders)
    Route::get('/folders', [FolderController::class, 'index'])->name('folders.index');
    
    // Document Routes
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/documents/{document}/download', [DocumentFileController::class, 'download'])->middleware(['auth'])->name('documents.download');
    Route::get('/documents/{document}/view', [DocumentFileController::class, 'view'])->name('documents.view');
    Route::put('/documents/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::post('/documents/{document}/watch', [DocumentController::class, 'watch'])->name('documents.watch');
    Route::delete('/documents/{document}/unwatch', [DocumentController::class, 'unwatch'])->name('documents.unwatch');
    Route::get('/documents/{document}/is-watched', [DocumentController::class, 'isWatched'])->name('documents.isWatched');
    Route::post('/update-visibility', [DocumentFileController::class, 'updateVisibility'])->name('update.visibility');

    Route::get('/getFiles/{folder}', [DocumentFileController::class, 'index'])->name('getFiles');
    Route::post('/send-email-document', [DocumentController::class, 'sendDocumentEmail'])->name('send.email');
    Route::get('/getDocumentComments', [DocumentController::class, 'getDocumentComments'])->name('getDocumentComments');

    Route::post('/upload', [DocumentFileController::class, 'upload'])->name('upload');
    Route::get('/upload', fn () => redirect()->route('documents.index'))->name('upload.fallback');
    Route::post('/change-document', [DocumentController::class, 'changeFile'])->name('changeFile');
    Route::get('/filter-documents-by-tags', [DocumentFileController::class, 'filterByTag'])->name('filterDocumentByTag');
    Route::post('/update-document-order', [DocumentFileController::class, 'updateOrder'])->name('update.document.order');

    // Phase 2 canonical file-operation routes.
    Route::prefix('files')->name('files.')->group(function () {
        Route::get('/folders/{folder}', [DocumentFileController::class, 'index'])->name('index');
        Route::post('/upload', [DocumentFileController::class, 'upload'])->name('upload');
        Route::get('/documents/{document}/download', [DocumentFileController::class, 'download'])->name('download');
        Route::get('/documents/{document}/view', [DocumentFileController::class, 'view'])->name('view');
        Route::get('/filter-by-tags', [DocumentFileController::class, 'filterByTag'])->name('filterByTag');
        Route::post('/visibility', [DocumentFileController::class, 'updateVisibility'])->name('updateVisibility');
        Route::post('/order', [DocumentFileController::class, 'updateOrder'])->name('updateOrder');
    });

    Route::get('/api/users', [UserController::class, 'search'])->name('users.search');

    // File Request Routes
    Route::post('/request-document', [FileRequestController::class, 'store'])->name('fileRequest.store');

    // Folder Routes
    Route::resource('folders', FolderController::class);
    Route::post('/update-folder-positions', [FolderController::class, 'updateFolderPositions'])->name('folders.updatePositions');
    Route::post('/update-folder-child-positions', [FolderController::class, 'updateFolderChildPositions'])->name('folders.updateChildPositions');
    Route::post('/folders/details', [FolderController::class, 'fetchDetails'])->name('folders.fetchDetails');
    Route::post('/folders/download-zip', [FolderController::class, 'downloadZip'])->name('folders.downloadZip');
    Route::post('/folders/delete', [FolderController::class, 'deleteSelecetdFolder'])->name('folders.deleteSelecetdFolder');

    // Tags Routes
    Route::resource('tags', TagController::class);
    Route::get('/search-tags', [TagController::class, 'searchTags'])->name('searchTags');
    Route::get('/add-tag', [TagController::class, 'addTag'])->name('addTag');

    // Comments Routes
    Route::get('/comments', [App\Http\Controllers\CommentController::class, 'index'])->name('comments.index');
    Route::post('/comments', [App\Http\Controllers\CommentController::class, 'store'])->name('comments.store');
    Route::put('/comments/{comment}', [App\Http\Controllers\CommentController::class, 'update'])->name('comments.update');
    Route::delete('/comments/{comment}', [App\Http\Controllers\CommentController::class, 'destroy'])->name('comments.destroy');

    // Categories Routes
    Route::resource('categories', App\Http\Controllers\CategoryController::class);

    // Search Route
    Route::get('/search', [SearchController::class, 'index'])->name('search.index');

    // Starred Routes
    Route::get('/starred', [StarredController::class, 'index'])->name('starred.index');
    Route::post('/documents/toggle-star', [DocumentController::class, 'toggleStar'])->name('documents.toggleStar');
    Route::post('/folders/toggle-star', [FolderController::class, 'toggleStar'])->name('folders.toggleStar');

    // Settings Routes
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::resource('workspaces', FolderController::class);

    // Home
    Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

    // StegoLock web routes
    Route::prefix('stego')->name('stego.')->group(function () {
        Route::get('/',        [StegoWebController::class, 'index'])->name('index');
        Route::get('/encode',  [StegoWebController::class, 'encodeForm'])->name('encode.form');
        Route::post('/encode', [StegoWebController::class, 'encode'])->name('encode');
        Route::get('/decode',  [StegoWebController::class, 'decodeForm'])->name('decode.form');
        Route::post('/decode', [StegoWebController::class, 'decode'])->name('decode');
        Route::get('/carriers', [StegoWebController::class, 'carrierPool'])->name('carriers');
        Route::get('/tokens',  [StegoWebController::class, 'tokens'])->name('tokens');
        Route::delete('/{id}', [StegoWebController::class, 'destroy'])->name('destroy');
    });

     // User management routes
     Route::get('/users/roles', [UserManagementController::class, 'roles'])->name('users.roles');
     
     // Projects and Contacts placeholder routes
     Route::get('/projects', function () {
         return Inertia::render('Projects/Index');
     })->name('projects.index');
     
     Route::get('/contacts', function () {
         return Inertia::render('Contacts/Index');
     })->name('contacts.index');
});

// Standalone StegoLock React SPA shell — public (SPA handles its own auth internally)
Route::get('/stego-app/{any?}', fn () => view('stegolock'))
    ->where('any', '.*')
    ->name('stego.spa');

// Share Documents Route (public)
Route::get('/{slug?}/share/{id?}/{token?}', [ShareDocumentController::class, 'getSharedDocuments'])->name('getSharedDocuments');
Route::post('/share-document', [ShareDocumentController::class, 'share'])->name('sharedDocuments');

Route::post('/debug-upload', function (Illuminate\Http\Request $request) {
    \Illuminate\Support\Facades\Log::info('Debug upload request:', [
        'has_files' => $request->hasFile('files'),
        'files' => $request->file('files'),
        'all' => $request->all(),
    ]);
    return response()->json([
        'has_files' => $request->hasFile('files'),
        'files' => $request->file('files'),
        'all' => $request->all(),
    ]);
});

require __DIR__.'/auth.php';
