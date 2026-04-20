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

        // Get starred documents
        $starredDocuments = Document::with('tags')
            ->where('owner_id', $user->id)
            ->where('is_starred', true)
            ->orderBy('updated_at', 'desc')
            ->get();

        // Get starred folders
        $starredFolders = Folder::with(['categories', 'subfolders'])
            ->whereNull('parent_id')
            ->where('is_starred', true)
            ->where(function ($query) use ($user) {
                // Check if user is admin - can view all starred folders
                if ($user->isAdmin()) {
                    return;
                }

                // Check if folder is public or user has access to it
                $query->where(function ($q) use ($user) {
                    $q->where('visibility', 'public')
                        ->orWhere(function ($subq) use ($user) {
                            // Check if user has documents in this folder
                            $subq->whereHas('documents', function ($docQuery) use ($user) {
                                $docQuery->where('owner_id', $user->id);
                            });
                        });
                });
            })
            ->orderBy('updated_at', 'desc')
            ->get();

        return Inertia::render('Starred/Index', [
            'starredDocuments' => $starredDocuments,
            'starredFolders' => $starredFolders,
        ]);
    }
}
