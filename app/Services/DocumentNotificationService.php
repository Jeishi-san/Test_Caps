<?php

namespace App\Services;

use App\Models\User;
use App\Models\Document;
use App\Models\Notification;
use App\Jobs\SendDocumentJob;
use Illuminate\Support\Facades\Auth;

/**
 * Service for handling document notifications and email communications.
 * 
 * Manages:
 * - Creating and managing notifications
 * - Sending email notifications
 * - Document watchers and update notifications
 * - Activity tracking and logging
 */
class DocumentNotificationService
{
    /**
     * Send a document via email with optional message.
     *
     * @param int $documentId ID of document to send
     * @param string|null $userEmail Recipient email address
     * @param string $type Notification type (e.g., 'email_shared')
     * @param string|null $title Email title
     * @param string|null $body Email body
     * @param string|null $content Additional content message
     * @return \Illuminate\Database\Eloquent\Collection Notifications for this document
     */
    public function sendDocumentEmail(
        int $documentId,
        ?string $userEmail,
        string $type = 'email_shared',
        ?string $title = null,
        ?string $body = null,
        ?string $content = null
    ) {
        $details = [
            'title' => $title,
            'body'  => $body,
            'content' => $content,
        ];

        $currentUser = Auth::user();
        $targetUser = $userEmail ? User::whereEmail($userEmail)->first() : null;

        // Create notification record
        Notification::create([
            'notifiable_id'      => $targetUser?->id,
            'notifiable_type'    => $targetUser ? User::class : null,
            'activity_type'      => $type,
            'model_type'         => Document::class,
            'model_id'           => $documentId,
            'message'            => $content,
            'created_by_user_id' => $currentUser->id,
        ]);

        // Send email if recipient provided
        if ($userEmail) {
            dispatch(new SendDocumentJob($details));
        }

        return $this->getDocumentNotifications($documentId);
    }

    /**
     * Get all notifications for a document.
     *
     * @param int $documentId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getDocumentNotifications(int $documentId)
    {
        return Notification::with('notifiable', 'createdBy')
            ->select('id', 'notifiable_id', 'notifiable_type', 'activity_type', 'model_type', 'model_id', 'message', 'status', 'dismiss_status', 'created_by_user_id', 'created_at')
            ->selectRaw("DATE_FORMAT(created_at, '%M %e %Y') as date, COUNT(*) as count")
            ->where('model_id', $documentId)
            ->groupBy('date', 'id', 'notifiable_id', 'notifiable_type', 'activity_type', 'model_type', 'model_id', 'message', 'status', 'dismiss_status', 'created_by_user_id', 'created_at')
            ->latest()
            ->get();
    }

    /**
     * Notify all watchers of a document about an update.
     *
     * @param Document $document The document that was updated
     * @param User $updatingUser The user who made the update
     */
    public function notifyWatchers(Document $document, User $updatingUser): void
    {
        $watchers = $document->watchers()->with('user')->get();

        foreach ($watchers as $watcher) {
            // Don't notify the user who made the change
            if ($watcher->user->id === $updatingUser->id) {
                continue;
            }

            Notification::create([
                'notifiable_id'      => $watcher->user->id,
                'notifiable_type'    => User::class,
                'activity_type'      => 'document_updated',
                'model_type'         => Document::class,
                'model_id'           => $document->id,
                'message'            => "Document '{$document->name}' has been updated by {$updatingUser->name}",
                'status'             => 'UNREAD',
                'dismiss_status'     => 'UNDISMISSED',
                'created_by_user_id' => $updatingUser->id,
            ]);
        }
    }

    /**
     * Create a notification for document sharing.
     *
     * @param Document $document Document being shared
     * @param User $targetUser User receiving share
     * @param User $sharingUser User sharing the document
     * @param string $message Optional custom message
     * @return Notification
     */
    public function notifyDocumentShared(
        Document $document,
        User $targetUser,
        User $sharingUser,
        string $message = ''
    ): Notification {
        if (empty($message)) {
            $message = "Document '{$document->name}' has been shared with you by {$sharingUser->name}";
        }

        return Notification::create([
            'notifiable_id'      => $targetUser->id,
            'notifiable_type'    => User::class,
            'activity_type'      => 'document_shared',
            'model_type'         => Document::class,
            'model_id'           => $document->id,
            'message'            => $message,
            'status'             => 'UNREAD',
            'dismiss_status'     => 'UNDISMISSED',
            'created_by_user_id' => $sharingUser->id,
        ]);
    }

    /**
     * Create a notification for document comment.
     *
     * @param Document $document Document with new comment
     * @param User $commentingUser User who commented
     * @param string $comment The comment text
     * @return Notification
     */
    public function notifyDocumentCommented(
        Document $document,
        User $commentingUser,
        string $comment
    ): Notification {
        return Notification::create([
            'notifiable_id'      => $document->owner_id,
            'notifiable_type'    => User::class,
            'activity_type'      => 'document_commented',
            'model_type'         => Document::class,
            'model_id'           => $document->id,
            'message'            => "New comment on '{$document->name}' by {$commentingUser->name}: {$comment}",
            'status'             => 'UNREAD',
            'dismiss_status'     => 'UNDISMISSED',
            'created_by_user_id' => $commentingUser->id,
        ]);
    }

    /**
     * Mark a notification as read.
     *
     * @param int $notificationId
     * @return bool
     */
    public function markNotificationAsRead(int $notificationId): bool
    {
        return (bool)Notification::where('id', $notificationId)->update(['status' => 'READ']);
    }

    /**
     * Mark a notification as dismissed.
     *
     * @param int $notificationId
     * @return bool
     */
    public function markNotificationAsDismissed(int $notificationId): bool
    {
        return (bool)Notification::where('id', $notificationId)->update(['dismiss_status' => 'DISMISSED']);
    }
}
