<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\StegoDocument;
use App\Models\AccessLog;
use App\Services\Stego\CloudStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    private CloudStorageService $storage;

    public function __construct(CloudStorageService $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Securely download a document file
     * Validates permissions, logs access, and returns stream with byte range support
     */
    public function document(Request $request, Document $document)
    {
        // Validate user has view access via policy
        Gate::authorize('view', $document);

        // Log this access attempt
        AccessLog::create([
            'user_id' => $request->user()->id,
            'document_id' => $document->id,
            'action' => 'download',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $stegoDocument = $document->stegoDocument;

        if (!$stegoDocument) {
            abort(404, 'Document not available for download');
        }

        $s3Key = $this->storage->documentKey($stegoDocument->user_id, $stegoDocument->id);

        // Get signed temporary download URL (expires after 15 minutes)
        $downloadUrl = $this->storage->getDownloadUrl($s3Key);

        // For local disk: stream the file directly with proper headers
        if (config('stegolock.storage.disk') === 'local') {
            return $this->streamLocalFile($s3Key, $document->name);
        }

        // For cloud storage: redirect to signed URL
        return redirect()->away($downloadUrl);
    }

    /**
     * Stream file from local disk with proper headers and byte range support
     */
    private function streamLocalFile(string $s3Key, string $filename): StreamedResponse
    {
        $size = $this->storage->size($s3Key);
        $mimeType = $this->storage->mimeType($s3Key);

        return response()->stream(function () use ($s3Key) {
            $stream = $this->storage->readStream($s3Key);
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => $size,
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }
}
