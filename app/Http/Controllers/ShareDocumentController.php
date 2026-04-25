<?php

namespace App\Http\Controllers;

use App\Models\Folder;
use App\Models\Document;
use App\Models\StegoDocument;
use App\Models\StegoDocumentGrant;
use App\Services\NotificationService;
use App\Services\Stego\CryptoService;
use Illuminate\Http\Request;
use App\Models\ShareDocument;
use App\Models\Notification;
use App\Http\Requests\StoreShareDocumentRequest;
use App\Http\Requests\UpdateShareDocumentRequest;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ShareDocumentController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly CryptoService $cryptoService,
    ) {}

    public function getSharedDocuments($slug, $sharedid, $token)
    {
        // Require authentication for shared documents
        if (!Auth::check()) {
            return redirect()->guest(route('login'))->with('intended', request()->fullUrl());
        }

        $shareDocument = ShareDocument::whereSlug($slug)->whereToken($token)->whereSharedId($sharedid)->first();

        abort_if(!$shareDocument, 404, 'Not Found');

        if ($slug === 'folder') {
            $folder = Folder::with(['documents', 'subfolders'])->findOrFail($sharedid);
            return Inertia::render('Shares/Folder', [
                'share' => $shareDocument,
                'folder' => $folder
            ]);
        }

        return view('shares.index', compact('shareDocument'));
    }


    public function share(StoreShareDocumentRequest $request)
    {
        $validated = $request->validated();

        // Generate secure token if not provided
        if (!isset($validated['token'])) {
            $validated['token'] = Str::random(40);
        }
        
        // Determine share type based on slug
        $shareType = match($request->slug) {
            'folder' => Folder::class,
            'stego' => StegoDocument::class,
            default => Document::class,
        };
        
        $shareData = $validated + [
            'share_type' => $shareType,
            'share_id' => $request->shared_id,
            'user_type' => User::class,
            'user_id' => Auth::id() ?? 1,
        ];

        $shareDocument = ShareDocument::create($shareData);

        // Shared links are download-only by default.
        $shareDocument->setPermissionLevel(ShareDocument::PERMISSION_VIEWER);
        $shareDocument->save();

        $recipientEmails = array_values(array_unique(array_filter((array) ($validated['recipient_emails'] ?? []))));

        // Create notifications for selected recipients, if any.
        foreach ($recipientEmails as $email) {
            $recipient = User::where('email', $email)->first();

            if (!$recipient) {
                continue;
            }

            $sender = Auth::user();
            $shareName = $request->name ?? 'Document';
            
            // Create internal access grant for registered users
            if ($request->slug === 'stego') {
                $this->createOrUpdateStegoGrant(
                    (int) $request->shared_id,
                    $sender,
                    $recipient
                );
            }
            
            $alreadyNotified = Notification::query()
                ->where('notifiable_id', $recipient->id)
                ->where('notifiable_type', User::class)
                ->where('activity_type', 'document_shared')
                ->where('model_type', $request->slug)
                ->where('model_id', (int) $request->shared_id)
                ->where('created_by_user_id', $sender->id)
                ->exists();

            if (!$alreadyNotified) {
                $this->notificationService->createShareNotification(
                    $recipient,
                    $sender,
                    $request->slug,
                    $shareName,
                    $request->shared_id
                );
            }
        }

        // Backwards compatibility with older clients sending a single email.
        if (isset($validated['email'])) {
            $recipient = User::where('email', $validated['email'])->first();
            if ($recipient) {
                $sender = Auth::user();
                $shareName = $request->name ?? 'Document';
                
                // Create internal access grant for registered users
                if ($request->slug === 'stego') {
                    $this->createOrUpdateStegoGrant(
                        (int) $request->shared_id,
                        $sender,
                        $recipient
                    );
                }
                
                $alreadyNotified = Notification::query()
                    ->where('notifiable_id', $recipient->id)
                    ->where('notifiable_type', User::class)
                    ->where('activity_type', 'document_shared')
                    ->where('model_type', $request->slug)
                    ->where('model_id', (int) $request->shared_id)
                    ->where('created_by_user_id', $sender->id)
                    ->exists();

                if (!$alreadyNotified) {
                    $this->notificationService->createShareNotification(
                        $recipient,
                        $sender,
                        $request->slug,
                        $shareName,
                        $request->shared_id
                    );
                }
            }
        }

        return response()->json(['message' => 'shared successfully', 'share' => $shareDocument], 200);
    }

    public function updatePermissions(UpdateShareDocumentRequest $request, $id)
    {
        $validated = $request->validated();

        $shareDocument = ShareDocument::findOrFail($id);
        
        // Check if user has permission to update
        $this->authorize('update', $shareDocument);

        $shareDocument->setPermissionLevel($validated['permission_level']);
        $shareDocument->save();

        return response()->json(['message' => 'Permissions updated successfully', 'share' => $shareDocument], 200);
    }

    public function getSharePermissions($id)
    {
        $shareDocument = ShareDocument::findOrFail($id);
        
        return response()->json([
            'permissions' => $shareDocument->getAttributes(),
            'permission_levels' => ShareDocument::getPermissionLevels(),
        ], 200);
    }

    public function revokeShare($id)
    {
        $shareDocument = ShareDocument::findOrFail($id);
        
        // Check if user has permission to revoke
        $this->authorize('delete', $shareDocument);

        $shareDocument->delete();

        return response()->json(['message' => 'Share revoked successfully'], 200);
    }

    public function listSharedDocuments()
    {
        $user = Auth::user();
        
        $sharedDocuments = ShareDocument::where('user_id', $user->id)->get();

        return response()->json(['shared_documents' => $sharedDocuments], 200);
    }

    /**
     * Auto-activate stego grants for selected recipients when possible.
     *
     * For both envelope-mode and legacy-mode stego documents, when the owner
     * has an active session MKD we resolve the document DEK and wrap it with a
     * server-managed key so the recipient can decode immediately.
     */
    private function createOrUpdateStegoGrant(int $stegoDocumentId, User $owner, User $recipient): void
    {
        $stegoDoc = StegoDocument::query()
            ->whereKey($stegoDocumentId)
            ->where('user_id', $owner->id)
            ->first();

        if (!$stegoDoc) {
            return;
        }

        $attributes = [
            'granted_by'   => $owner->id,
            'grant_status' => 'pending',
        ];

        $ownerMasterKey = session('stego_mkd');
        $hasOwnerKey = is_string($ownerMasterKey) && $ownerMasterKey !== '';

        if ($hasOwnerKey) {
            try {
                $dekHex = null;

                if (
                    $stegoDoc->stego_mode === 'envelope_wrapped'
                    && !empty($stegoDoc->owner_wrapped_dek)
                    && !empty($stegoDoc->owner_wrapped_dek_iv)
                    && !empty($stegoDoc->owner_wrapped_dek_auth_tag)
                ) {
                    $dekHex = $this->cryptoService->unwrapDekForUser(
                        $stegoDoc->owner_wrapped_dek,
                        $stegoDoc->owner_wrapped_dek_iv,
                        $stegoDoc->owner_wrapped_dek_auth_tag,
                        $ownerMasterKey
                    );
                } elseif ($stegoDoc->stego_mode === 'legacy_derived' || $stegoDoc->stego_mode === null) {
                    $documentRef = $stegoDoc->document_id ? (string) $stegoDoc->document_id : (string) $stegoDoc->id;
                    $derived = $this->cryptoService->deriveDEK(
                        $ownerMasterKey,
                        $documentRef,
                        $stegoDoc->stego_dek_salt,
                        $stegoDoc->stego_dek_iter
                    );

                    $dekHex = $derived['dek'] ?? null;
                }

                if (empty($dekHex) || !is_string($dekHex)) {
                    throw new \RuntimeException('Unable to resolve DEK for automatic stego grant activation.');
                }

                $viewerWrapped = $this->cryptoService->wrapDekForServer($dekHex);

                $attributes = [
                    'granted_by'                  => $owner->id,
                    'grant_status'                => 'active',
                    'accepted_at'                 => now(),
                    'viewer_wrapped_dek'          => $viewerWrapped['wrapped_dek'],
                    'viewer_wrapped_dek_iv'       => $viewerWrapped['iv'],
                    'viewer_wrapped_dek_auth_tag' => $viewerWrapped['auth_tag'],
                    'viewer_wrapped_dek_alg'      => $viewerWrapped['algorithm'],
                    'viewer_wrapped_dek_version'  => $viewerWrapped['version'],
                ];
            } catch (\Throwable $e) {
                report($e);
            }
        }

        StegoDocumentGrant::updateOrCreate(
            [
                'stego_document_id' => $stegoDoc->id,
                'viewer_user_id'    => $recipient->id,
            ],
            $attributes
        );
    }
}