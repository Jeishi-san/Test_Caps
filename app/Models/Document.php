<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Document extends Model
{
    use HasFactory;
    use SoftDeletes;


    protected static function boot()
    {
        parent::boot();

        // Define a global scope to always order by position
        static::addGlobalScope('position', function ($builder) {
            $builder->orderBy('position');
        });

        static::creating(function ($document) {
            if (!isset($document->position)) {
                $document->position = static::max('position') + 1;
            }
            
            // Set private visibility by default
            if (!isset($document->visibility)) {
                $document->visibility = 'private';
            }
        });
    }

    protected $fillable = [
        'name', 'original_name', 'file_path', 'size', 'extension', 'folder_id', 'visibility', 'is_starred', 'share', 'download', 'email',
        'url', 'owner_id', 'document_date', 'position',
        'ingest_status', 'ingest_error',
        // AES-256-GCM encryption metadata
        'is_encrypted', 'enc_iv', 'enc_auth_tag', 'enc_dek_salt', 'enc_dek_iterations', 'enc_hash_sha256',
        // Document watcher fields
        'last_updated_at', 'last_updated_by_user_id',
        // Encryption mode (legacy or envelope_wrapped)
        'encryption_mode',
    ];

    protected function casts(): array
    {
        return [
            'is_encrypted'       => 'boolean',
            'is_starred'         => 'boolean',
            'enc_dek_iterations' => 'integer',
        ];
    }

    protected $appends = [
        'is_stegoed',
    ];

    /**
     * Accessor for frontend compatibility - maps is_encrypted to is_stegoed
     *
     * @return bool
     */
    public function getIsStegoedAttribute(): bool
    {
        return (bool) $this->is_encrypted;
    }

    /**
     * Returns true if this document has a physical file on disk that can be decrypted.
     * URL-type documents (YouTube links, etc.) have no file to decrypt.
     */
    public function hasPhysicalFile(): bool
    {
        return empty($this->url) && !empty($this->file_path);
    }

    public function getFileIcon()
    {
        // Check if the extension is in the array
        if (!in_array($this->extension, getImageExtensions())) {
            if (!empty($this->extension)) {
                return asset('img/' . $this->extension . '.png');
            } else {
                return asset($this->file_path);
            }
        } else {
            return asset($this->file_path);
        }
    }

    public function isPublic()
    {
        return $this->visibility;
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * User who last updated this document (3NF fix — replaced text column with FK).
     */
    public function lastUpdatedByUser()
    {
        return $this->belongsTo(User::class, 'last_updated_by_user_id');
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /**
     * Emojis attached to this document via the emojiables polymorphic pivot.
     * Replaces the old 1NF-violating emojis varchar column.
     */
    public function emojis()
    {
        return $this->morphToMany(Emoji::class, 'emojiable');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id')->latest();
    }

    // -------------------------------------------------------------------------
    // StegoLock relationships
    // -------------------------------------------------------------------------

    public function stegoDocument()
    {
        return $this->hasOne(StegoDocument::class);
    }

    public function isStegoed(): bool
    {
        return $this->stegoDocument()->exists();
    }

    public function watchers()
    {
        return $this->hasMany(DocumentWatcher::class);
    }

    public function isWatchedByUser($userId)
    {
        return $this->watchers()->where('user_id', $userId)->exists();
    }

    /**
     * Defines the relationship for users this document is shared with.
     * Explicitly sets relationship keys and includes wrapped DEK pivot columns.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function sharedWith()
    {
        return $this->belongsToMany(User::class, 'share_documents', 'share_id', 'user_id')
            ->withPivot(['wrapped_dek', 'wrapped_dek_iv', 'wrapped_dek_auth_tag']);
    }

    /**
     * Retrieves wrapped DEK, IV, and auth tag for a specific user from the share_documents pivot table.
     *
     * @param int $userId
     * @return array|null Array with wrapped_dek, wrapped_dek_iv, wrapped_dek_auth_tag, or null if user not found
     */
    public function getWrappedDekForUser(int $userId): ?array
    {
        $sharedUser = $this->sharedWith()->where('user_id', $userId)->first();
        
        if (!$sharedUser) {
            return null;
        }

        return [
            'wrapped_dek' => $sharedUser->pivot->wrapped_dek,
            'wrapped_dek_iv' => $sharedUser->pivot->wrapped_dek_iv,
            'wrapped_dek_auth_tag' => $sharedUser->pivot->wrapped_dek_auth_tag,
        ];
    }

    // -------------------------------------------------------------------------
    // Query Scopes - Fix N+1 Query Problems
    // -------------------------------------------------------------------------

    /**
     * Scope to get all documents accessible by a user.
     * Combines owned documents, shared documents, and documents in shared folders.
     * 
     * Usage: Document::accessibleBy($user)->get()
     */
    public function scopeAccessibleBy($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            // User's own documents
            $q->where('owner_id', $user->id)
              // Documents directly shared with user
              ->orWhereIn('id', function ($subq) {
                  $subq->select('share_id')
                       ->from('share_documents')
                       ->where('user_id', Auth::id());
              })
              // Documents in folders shared with user
              ->orWhereIn('folder_id', function ($subq) {
                  $subq->select('share_id')
                       ->from('share_documents')
                       ->where('user_id', Auth::id())
                       ->where('slug', 'folder');
              });
            
            // Admin can see all documents
            if ($user->isAdmin()) {
                $q->orWhere('visibility', 'private');
            }
        });
    }

    /**
     * Scope to get only publicly visible documents.
     * 
     * Usage: Document::public()->get()
     */
    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    /**
     * Scope to get only private documents.
     * 
     * Usage: Document::private()->get()
     */
    public function scopePrivate($query)
    {
        return $query->where('visibility', 'private');
    }

    /**
     * Scope to get documents owned by a specific user.
     * 
     * Usage: Document::ownedBy($user)->get()
     */
    public function scopeOwnedBy($query, User $user)
    {
        return $query->where('owner_id', $user->id);
    }

    /**
     * Scope to get documents directly shared with a user.
     * 
     * Usage: Document::sharedDirectlyWith($user)->get()
     */
    public function scopeSharedDirectlyWith($query, User $user)
    {
        return $query->whereIn('id', function ($subq) use ($user) {
            $subq->select('share_id')
                 ->from('share_documents')
                 ->where('user_id', $user->id)
                 ->where('slug', '!=', 'folder');
        });
    }

    /**
     * Scope to get documents in folders shared with a user.
     * 
     * Usage: Document::inSharedFolders($user)->get()
     */
    public function scopeInSharedFolders($query, User $user)
    {
        return $query->whereIn('folder_id', function ($subq) use ($user) {
            $subq->select('share_id')
                 ->from('share_documents')
                 ->where('user_id', $user->id)
                 ->where('slug', 'folder');
        });
    }

    /**
     * Scope to get starred documents.
     * 
     * Usage: Document::starred()->get()
     */
    public function scopeStarred($query)
    {
        return $query->where('is_starred', true);
    }

    /**
     * Scope to get documents watched by a user.
     * 
     * Usage: Document::watchedBy($user)->get()
     */
    public function scopeWatchedBy($query, User $user)
    {
        return $query->whereHas('watchers', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
    }

    /**
     * Scope to get recently updated documents.
     * 
     * Usage: Document::recent()->get()
     */
    public function scopeRecent($query)
    {
        return $query->orderByDesc('updated_at');
    }

    /**
     * Scope to get documents in a specific folder.
     * 
     * Usage: Document::inFolder($folderId)->get()
     */
    public function scopeInFolder($query, int $folderId)
    {
        return $query->where('folder_id', $folderId);
    }

    /**
     * Scope to get documents encrypted at rest.
     * 
     * Usage: Document::encrypted()->get()
     */
    public function scopeEncrypted($query)
    {
        return $query->where('is_encrypted', true);
    }

    // Method to delete associated file from public path
    public function deleteFile()
    {
        $filePath = public_path($this->file_path);
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
