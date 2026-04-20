<?php

namespace App\Http\Controllers\Traits;

use App\Models\Document;
use App\Models\AccessLog;
use App\Helpers\MimeTypeHelper;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Trait for handling common document serving logic.
 * 
 * Consolidates authorization, decryption, and response header logic
 * that was previously duplicated in download() and view() methods.
 */
trait HandlesDocumentServing
{
    /**
     * Authorize user access and serve the document (download or inline).
     *
     * @param Document $document The document to serve
     * @param bool $inline Whether to serve inline (true) or as attachment (false)
     * @param string $logAction The action to log ('download', 'view', etc.)
     * @return StreamedResponse||\Illuminate\Http\Response
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException On authorization or file errors
     */
    protected function authorizeAndServeDocument(
        Document $document,
        bool $inline = false,
        string $logAction = 'download'
    ) {
        $user = Auth::user();

        // Authorization check
        if (!$user->can('view', $document)) {
            abort(403, 'You do not have permission to access this document.');
        }

        // Handle URL-type documents (redirect to external URL)
        if (!empty($document->url)) {
            return redirect($document->url);
        }

        // Validate file exists on disk
        $absolutePath = public_path($document->file_path);
        if (!file_exists($absolutePath)) {
            abort(404, 'The requested file could not be found on the server.');
        }

        // Decrypt content if necessary
        try {
            $content = $this->documentService->decryptDocumentContent($document);
        } catch (\Exception $e) {
            abort(500, 'Failed to retrieve document: ' . $e->getMessage());
        }

        // Log the access
        AccessLog::log($logAction, 'document', $document->id, request());

        // Determine filename and MIME type
        $filename = $document->original_name ?? $document->name;
        $extension = $document->extension ?? pathinfo($document->file_path, PATHINFO_EXTENSION);
        $mimeType = MimeTypeHelper::getMimeType(
            $extension,
            $absolutePath,
            $document->is_encrypted
        );

        // Set response headers
        $disposition = $inline ? 'inline' : 'attachment';
        $headers = [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => "{$disposition}; filename=\"" . addslashes($filename) . '"',
            'Content-Length'      => strlen($content),
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ];

        // Add encryption status header if applicable
        if ($document->is_encrypted) {
            $headers['X-Encryption-Status'] = 'AES-256-GCM';
        }

        return response($content, 200, $headers);
    }
}
