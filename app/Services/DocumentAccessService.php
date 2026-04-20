<?php

namespace App\Services;

use App\Models\User;
use App\Models\Document;
use App\Models\ShareDocument;
use Illuminate\Support\Facades\Auth;

/**
 * Service for handling document access control and permission logic.
 * 
 * Manages:
 * - User authorization for viewing/editing documents
 * - Document visibility and sharing
 * - Access through direct ownership or sharing relationships
 */
class DocumentAccessService
{
    /**
     * Check if a user can access (view) a document.
     * Considers ownership, sharing, and visibility settings.
     *
     * @param User $user The user to check access for
     * @param Document $document The document to check access to
     * @return bool True if user can access the document
     */
    public function canUserAccessDocument(User $user, Document $document): bool
    {
        // Owner always has access
        if ($document->owner_id === $user->id) {
            return true;
        }

        // Check if document is directly shared with user
        if ($this->isDocumentSharedWithUser($document, $user)) {
            return true;
        }

        // Check if document is in a folder shared with user
        if ($this->isDocumentInSharedFolder($document, $user)) {
            return true;
        }

        // Admins can access any document
        if ($user->isAdmin()) {
            return true;
        }

        // Check public visibility as last resort
        return $document->visibility === 'public';
    }

    /**
     * Check if a document is directly shared with a user.
     *
     * @param Document $document
     * @param User $user
     * @return bool
     */
    public function isDocumentSharedWithUser(Document $document, User $user): bool
    {
        return ShareDocument::query()
            ->where('share_id', $document->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Check if a document is in a folder that's shared with a user.
     *
     * @param Document $document
     * @param User $user
     * @return bool
     */
    public function isDocumentInSharedFolder(Document $document, User $user): bool
    {
        if (!$document->folder_id) {
            return false;
        }

        return ShareDocument::query()
            ->where('share_id', $document->folder_id)
            ->where('user_id', $user->id)
            ->where('slug', 'folder')
            ->exists();
    }

    /**
     * Get all documents accessible by a user across all access types.
     *
     * @param User $user
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getAccessibleDocumentsQuery(User $user)
    {
        return Document::accessibleBy($user);
    }

    /**
     * Update document visibility.
     *
     * @param Document $document
     * @param string $visibility 'public' or 'private'
     * @return bool
     */
    public function updateDocumentVisibility(Document $document, string $visibility): bool
    {
        $newVisibility = $visibility === 'private' ? 'public' : 'private';
        
        return $document->update([
            'visibility' => $newVisibility,
        ]);
    }

    /**
     * Check if a document is public.
     *
     * @param Document $document
     * @return bool
     */
    public function isDocumentPublic(Document $document): bool
    {
        return $document->visibility === 'public';
    }
}
