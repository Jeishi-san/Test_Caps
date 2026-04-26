<?php

namespace App\Http\Controllers;

use ZipArchive;
use App\Models\Folder;
use Illuminate\Http\Request;
use App\Http\Requests\StoreFolderRequest;
use App\Http\Requests\UpdateFolderRequest;
use App\Services\FolderService;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class FolderController extends Controller
{

    public function __construct(protected FolderService $folderService)
    {
    }


    public function index()
    {
        $user = Auth::user();
        
        // Get all folders the user has permission to view using optimized scope
        $folders = Folder::with(['categories', 'subfolders.categories', 'subfolders.subfolders', 'documents'])
            ->whereNull('parent_id')
            ->accessibleBy($user)
            ->orderBy('position')
            ->get();

        return Inertia::render('Folders/Index', [
            'folders' => $folders,
        ]);
    }

    public function create()
    {
        $folders = Folder::with('categories', 'subfolders')->whereNull('parent_id')->get();

        return Inertia::render('Folders/Create', [
            'folders' => $folders,
        ]);
    }


    public function store(StoreFolderRequest $request)
    {
        $folders = $this->folderService->setStoreFolder($request);

        if ($request->header('X-Inertia')) {
            return redirect()->route('folders.index');
        }

        return response()->json(['html' => $folders]);
    }



    public function updateFolderPositions(Request $request)
    {
        $request->validate([
            'positions' => ['required', 'array', 'min:1'],
            'positions.*' => ['integer', 'min:0'],
        ]);

        $this->folderService->setUpdateFolderPositions($request);

        return response()->json(['message' => 'Positions updated successfully for parent rows']);
    }


    public function updateFolderChildPositions(Request $request)
    {
        $request->validate([
            'parent_id' => ['required', 'integer', 'exists:folders,id'],
            'positions' => ['required', 'array', 'min:1'],
            'positions.*' => ['integer', 'min:0'],
        ]);

        $this->folderService->setUpdateFolderChildPositions($request);

        return response()->json(['message' => 'Positions updated successfully for child rows']);
    }



    public function fetchDetails(Request $request)
    {
        $request->validate([
            'folder_ids' => 'required|array'
        ]);

        // Fetch folder details based on the received IDs
        $folders = Folder::with('documents')->whereIn('id', $request->folder_ids)->get();

        // Return folder details to frontend
        return response()->json(['folders' => $folders]);
    }



    public function downloadZip(Request $request)
    {
        $request->validate([
            'folders' => 'required|array',
        ]);

        $zipResult = $this->folderService->setDownloadZip($request);

        if (!$zipResult) {
            return response()->json(['error' => 'Error generating zip file. The folder may be empty, and you cannot create a zip file from an empty folder.'], 400);
        }

        $zipFilePath = $zipResult['zipFilePath'];
        $zipFileName = $zipResult['zipFileName'];

        if ($zipFilePath) {
            return response()->download($zipFilePath, $zipFileName)->deleteFileAfterSend(true);
        }

        return response()->json(['error' => 'Error generating zip file. The folder may be empty, and you cannot create a zip file from an empty folder.'], 400);
    }


    public function deleteSelecetdFolder(Request $request)
    {
        return $this->deleteSelectedFolder($request);
    }

    public function update(UpdateFolderRequest $request, $id)
    {
        $folder = Folder::findOrFail($id);
        $this->authorize('update', $folder);
        $folder->update($request->validated());
        return response()->json(['message' => 'Folder renamed successfully']);
    }

    public function destroy($id)
    {
        $folder = Folder::findOrFail($id);
        $this->authorize('delete', $folder);
        // Move documents to root before deleting folder
        $folder->documents()->update(['folder_id' => null]);
        $folder->delete();
        return response()->json(['message' => 'Folder deleted']);
    }

    public function deleteSelectedFolder(Request $request)
    {
        $validated = $request->validate([
            'folder_ids' => ['required', 'array', 'min:1'],
            'folder_ids.*' => ['integer', 'exists:folders,id'],
        ]);

        $folders = Folder::whereIn('id', $validated['folder_ids'])->get();

        foreach ($folders as $folder) {
            // Move documents to root before deleting folder
            $folder->documents()->update(['folder_id' => null]);
            $folder->deleteFolder();
        }

        if ($request->header('X-Inertia')) {
            return redirect()->route('folders.index');
        }

        return response()->json(['html' => $this->getParentFolders(), 'message' => 'Folder and its related records deleted successfully'], 200);
    }


    public function getParentFolders()
    {
        $folders = Folder::with(['categories'])->whereNull('parent_id')->get();

        // Return JSON data for React frontend instead of rendered HTML
        return response()->json(['folders' => $folders]);
    }

    public function toggleStar(Request $request)
    {
        $validated = $request->validate([
            'folder_id' => ['required', 'integer', 'exists:folders,id'],
        ]);

        $folder = Folder::findOrFail($validated['folder_id']);
        $this->authorize('update', $folder);

        $folder->update([
            'is_starred' => !$folder->is_starred,
        ]);

        return response()->json([
            'message' => $folder->is_starred ? 'Folder starred successfully' : 'Folder unstarred successfully',
            'is_starred' => $folder->is_starred,
        ]);
    }
}