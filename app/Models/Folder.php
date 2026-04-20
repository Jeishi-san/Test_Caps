<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Folder extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'parent_id', 'visibility', 'is_starred', 'background_color', 'foreground_color', 'category_id', 'position'];



    protected static function boot()
    {
        parent::boot();

        // Folders will be ordered alphabetically by name by default
        static::addGlobalScope('name', function ($builder) {
            $builder->orderBy('name', 'asc');
        });

        static::creating(function ($folder) {
            if (!isset($folder->position)) {
                $folder->position = static::max('position') + 1;
            }
            
            // Keep model default aligned with migration default.
            if (!isset($folder->visibility)) {
                $folder->visibility = 'public';
            }
        });
    }


    public function parent()
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    public function subfolders()
    {
        return $this->hasMany(Folder::class, 'parent_id')->orderBy('position');
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'folder_id', 'id');
    }

    public function deleteFolder()
    {
        // Check if the documents relationship is loaded
        if ($this->relationLoaded('documents')) {
            // Delete related documents and associated files
            foreach ($this->documents as $document) {
                $document->deleteFile();
                $document->delete();
            }
        } else {
            // If the relationship is not loaded, eager load it
            $this->load('documents');

            // Delete related documents and associated files
            foreach ($this->documents as $document) {
                $document->deleteFile();
                $document->delete();
            }
        }

        // Delete related categories; tags.category_id cascade removes their tags automatically.
        if ($this->relationLoaded('categories')) {
            foreach ($this->categories as $category) {
                $category->delete();
            }
        } else {
            $this->load('categories');
            foreach ($this->categories as $category) {
                $category->delete();
            }
        }
        // Detach the folder's own tags from the taggables pivot.
        if ($this->relationLoaded('tags')) {
            $this->tags()->detach();
        } else {
            $this->load('tags');
            $this->tags()->detach();
        }

        // Check if the subfolders relationship is loaded
        if ($this->relationLoaded('subfolders')) {
            // Recursively delete subfolders and their related records
            foreach ($this->subfolders as $subfolder) {
                $subfolder->deleteFolder();
            }
        } else {
            // If the relationship is not loaded, eager load it
            $this->load('subfolders');

            // Recursively delete subfolders and their related records
            foreach ($this->subfolders as $subfolder) {
                $subfolder->deleteFolder();
            }
        }

        $filePath = public_path('documents/' . $this->name);

        if (file_exists($filePath)) {
            if (is_file($filePath)) {
                unlink($filePath); // Delete the file if it's a file
            } else {
                // Handle the case if $filePath is a directory
                $this->deleteDirectory($filePath);
            }
        }
        // Delete folder itself
        $this->delete();
    }

    // Method to recursively delete directory and its contents
    private function deleteDirectory($dirPath)
    {
        if (!is_dir($dirPath)) {
            return;
        }

        // Check if the directory is empty
        if (count(scandir($dirPath)) === 2) { // 2 because scandir() returns '.' and '..'
            rmdir($dirPath); // Remove the directory itself if it's empty
            return;
        }

        // If the directory is not empty, delete its contents recursively
        $files = glob($dirPath . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            } elseif (is_dir($file)) {
                $this->deleteDirectory($file);
            }
        }
        // After deleting all contents, remove the directory itself
        rmdir($dirPath);
    }

    // -------------------------------------------------------------------------
    // Query Scopes - Fix N+1 Query Problems
    // -------------------------------------------------------------------------

    /**
     * Scope to get all folders accessible by a user.
     * Includes public folders, folders with user's documents, and shared folders.
     * 
     * Usage: Folder::accessibleBy($user)->get()
     */
    public function scopeAccessibleBy($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            // Public folders
            $q->where('visibility', 'public')
              // Folders containing user's documents
              ->orWhereHas('documents', function ($docQ) use ($user) {
                  $docQ->where('owner_id', $user->id);
              })
              // Shared folders
              ->orWhereIn('id', function ($shareQ) use ($user) {
                  $shareQ->select('share_id')
                         ->from('share_documents')
                         ->where('user_id', \Illuminate\Support\Facades\Auth::id())
                         ->where('slug', 'folder');
              });
            
            // Admin can see all folders
            if ($user->isAdmin()) {
                // Already covered by whereIn but keep explicit for clarity
            }
        });
    }

    /**
     * Scope to get only public folders.
     * 
     * Usage: Folder::public()->get()
     */
    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    /**
     * Scope to get only private folders.
     * 
     * Usage: Folder::private()->get()
     */
    public function scopePrivate($query)
    {
        return $query->where('visibility', 'private');
    }

    /**
     * Scope to get folders shared with a user.
     * 
     * Usage: Folder::sharedWith($user)->get()
     */
    public function scopeSharedWith($query, User $user)
    {
        return $query->whereIn('id', function ($subq) {
            $subq->select('share_id')
                 ->from('share_documents')
                 ->where('user_id', $user->id)
                 ->where('slug', 'folder');
        });
    }

    /**
     * Scope to get folders containing documents owned by a user.
     * 
     * Usage: Folder::withUserDocuments($user)->get()
     */
    public function scopeWithUserDocuments($query, User $user)
    {
        return $query->whereHas('documents', function ($q) use ($user) {
            $q->where('owner_id', $user->id);
        });
    }

    /**
     * Scope to get starred folders.
     * 
     * Usage: Folder::starred()->get()
     */
    public function scopeStarred($query)
    {
        return $query->where('is_starred', true);
    }

    /**
     * Scope to get root-level folders (no parent).
     * 
     * Usage: Folder::root()->get()
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to get subfolders of a parent.
     * 
     * Usage: Folder::childrenOf($parentId)->get()
     */
    public function scopeChildrenOf($query, int $parentId)
    {
        return $query->where('parent_id', $parentId);
    }

    /**
     * Scope to get folders ordered by position then name.
     * 
     * Usage: Folder::ordered()->get()
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('position')->orderBy('name');
    }

    /**
     * Scope to get folders with document count.
     * 
     * Usage: Folder::withCount('documents')->get()
     */
    public function scopeWithDocumentCount($query)
    {
        return $query->withCount('documents');
    }
}
