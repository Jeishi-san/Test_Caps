<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Folder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class StarredController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Get starred documents accessible by user
        $starredDocuments = Document::with('tags')
            ->where('is_starred', true)
            ->accessibleBy($user)
            ->orderBy('updated_at', 'desc')
            ->get();

        // Get starred folders accessible by user
        $starredFolders = Folder::with(['categories', 'subfolders'])
            ->whereNull('parent_id')
            ->where('is_starred', true)
            ->accessibleBy($user)
            ->orderBy('updated_at', 'desc')
            ->get();

        return Inertia::render('Starred/Index', [
            'starredDocuments' => $starredDocuments,
            'starredFolders' => $starredFolders,
        ]);
    }
}
