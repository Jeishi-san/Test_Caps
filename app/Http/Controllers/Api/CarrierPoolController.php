<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ValidateCarrierJob;
use App\Models\StegoCarrier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * CarrierPoolController
 * 
 * Manages the carrier pool — a personal collection of pre-validated carrier images
 * that users upload once and reuse across multiple encode operations.
 * 
 * Routes:
 *   GET    /api/stego/carriers      — List pool carriers with optional status filter
 *   POST   /api/stego/carriers      — Upload a carrier into the pool
 *   DELETE /api/stego/carriers/{id}  — Remove a carrier from the pool
 */
class CarrierPoolController extends Controller
{
    /**
     * Upload a carrier into the pool.
     * 
     * The carrier is stored immediately, then dispatched to a background job
     * for validation (PSNR + capacity measurement). The upload response is instant.
     * 
     * @param Request $request
     * @return JsonResponse 202 Accepted with carrier_id and validation_status
     */
    public function store(Request $request): JsonResponse
    {
        $allowed = config('stegolock.carriers.allowed');
        $allMimes = collect($allowed)->pluck('mimes')->flatten()->implode(',');

        // Custom validation messages per behavior matrix
        $messages = [
            'carrier.required' => 'Carrier file is required.',
            'carrier.file' => 'Invalid file upload.',
            "carrier.mimes" => 'File type not allowed.', // matches contract
        ];

        $request->validate([
            'carrier' => ['required', 'file', "mimes:{$allMimes}"],
            'name' => ['nullable', 'string', 'max:255'],
        ], $messages);

        $user = Auth::user();
        $file = $request->file('carrier');

        // Enforce per-MIME-type max file size from config
        $mime = $file->getMimeType();
        $maxKb = $this->getMaxKbForMime($mime, $allowed);
        if ($maxKb && $file->getSize() > $maxKb * 1024) {
            return response()->json([
                'message' => 'File size exceeds maximum allowed for this file type.',
            ], 422);
        }

        // Enforce quota before storing
        $maxCarriers = config('stegolock.carrier_pool.max_carriers_per_user', 50);
        $maxSize = config('stegolock.carrier_pool.max_total_size_bytes', 500 * 1024 * 1024);

        $currentCount = StegoCarrier::where('uploaded_by', $user->id)->count();
        $currentSize = StegoCarrier::where('uploaded_by', $user->id)->sum('size');

        if ($currentCount >= $maxCarriers) {
            return response()->json([
                'message' => "Pool limit reached ({$maxCarriers} carriers). Remove unused carriers first.",
            ], 422);
        }

        if (($currentSize + $file->getSize()) > $maxSize) {
            $maxMb = round($maxSize / 1024 / 1024);
            return response()->json([
                'message' => "Pool storage limit of {$maxMb}MB would be exceeded.",
            ], 422);
        }

        // Store carrier file on configured disk
        $disk = Storage::disk(config('stegolock.storage.disk', 'local'));
        $path = $disk->putFile('stego/carriers/' . $user->id, $file);

        // Create carrier record with pending validation status
        $carrier = StegoCarrier::create([
            'name' => $request->input('name', $file->getClientOriginalName()),
            'file_path' => $path,
            'file_type' => $file->getClientOriginalExtension(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $user->id,
            'validation_status' => 'pending',
        ]);

        // Dispatch background validation job
        ValidateCarrierJob::dispatch($carrier->id);

        return response()->json([
            'carrier_id' => $carrier->id,
            'validation_status' => 'pending',
            'message' => 'Carrier queued for validation. Check status before encoding.',
        ], 202);
    }

    /**
     * Determine the maximum allowed file size (in KB) for a given MIME type
     * based on the stegolock.carriers.allowed config.
     *
     * @param string $mime
     * @param array $allowedConfig
     * @return int|null Max KB or null if MIME not found
     */
    private function getMaxKbForMime(string $mime, array $allowedConfig): ?int
    {
        foreach ($allowedConfig as $type => $cfg) {
            if (in_array($mime, $cfg['mime_types'] ?? [], true)) {
                return $cfg['max_kb'] ?? null;
            }
        }
        return null;
    }

    /**
     * List pool carriers with optional status filter.
     * 
     * @param Request $request
     * @return JsonResponse Paginated list of carriers
     */
    public function index(Request $request): JsonResponse
    {
        $carriers = StegoCarrier::where('uploaded_by', Auth::id())
            ->select([
                'id', 'name', 'file_type', 'mime_type', 'size',
                'psnr', 'capacity_bytes', 'validation_status',
                'validation_error', 'is_in_use', 'validated_at', 'created_at',
            ])
            ->when($request->query('status'), fn($q, $s) => $q->where('validation_status', $s))
            ->latest()
            ->paginate(20);

        return response()->json($carriers);
    }

    /**
     * Remove a carrier from the pool.
     * 
     * Carriers that are currently in use by an active encode operation cannot be removed.
     * 
     * @param int $id Carrier ID
     * @return JsonResponse 200 on success, 409 if carrier is in use
     */
    public function destroy(int $id): JsonResponse
    {
        $carrier = StegoCarrier::where('id', $id)
            ->where('uploaded_by', Auth::id())
            ->firstOrFail();

        if ($carrier->is_in_use) {
            return response()->json([
                'message' => 'Carrier is currently in use by an active stego document.',
            ], 409);
        }

        // Delete file from storage using configured disk
        $disk = Storage::disk(config('stegolock.storage.disk', 'local'));
        $disk->delete($carrier->file_path);

        // Delete carrier record
        $carrier->delete();

        return response()->json(['message' => 'Carrier removed from pool.']);
    }

    /**
     * Download or preview a carrier file (cloud-aware)
     * 
     * @param Request $request
     * @param int $id Carrier ID
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\RedirectResponse
     */
    public function download(Request $request, int $id)
    {
        $carrier = StegoCarrier::where('id', $id)
            ->where('uploaded_by', Auth::id())
            ->firstOrFail();

        $disk = Storage::disk(config('stegolock.storage.disk', 'local'));
        $filePath = $carrier->file_path;

        if (!$disk->exists($filePath)) {
            abort(404, 'Carrier file not found on storage disk');
        }

        $diskName = config('stegolock.storage.disk', 'local');

        // Local disk: stream file directly
        if ($diskName === 'local') {
            $localPath = $disk->path($filePath);
            return response()->file($localPath, [
                'Content-Disposition' => 'inline; filename="' . $carrier->name . '"',
                'Content-Type' => $carrier->mime_type ?? 'application/octet-stream',
            ]);
        }

        // Cloud storage: redirect to temporary signed URL using CloudStorageService
        $cloudStorage = new \App\Services\Stego\CloudStorageService();
        $temporaryUrl = $cloudStorage->temporaryUrl($filePath, now()->addMinutes(15));
        return redirect()->away($temporaryUrl);
    }
}
