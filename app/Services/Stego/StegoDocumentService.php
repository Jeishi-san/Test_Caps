<?php

namespace App\Services\Stego;

use App\Models\Document;
use App\Models\StegoDocument;
use App\Services\Stego\CarrierPoolSelector;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * StegoDocumentService  —  Orchestrator
 *
 * Coordinates all 5 Stego micro-services to encode (hide) a document inside
 * carrier files and decode (recover) it again.
 *
 * Workflow — Encode:
 *   1. CryptoService  : derive DEK from master key + document ID
 *   2. CryptoService  : encrypt plaintext → { ciphertext, iv, auth_tag, hash }
 *   3. SegmentationService : split ciphertext into N chunks (one per carrier)
 *   4. StegoService   : embed each chunk into its carrier file
 *   5. CloudStorageService : upload modified carriers to S3
 *   6. PersistenceService  : persist StegoDocument + StegoCarriers + StegoSegments
 *
 * Workflow — Decode:
 *   1. PersistenceService  : load StegoDocument + ordered segments
 *   2. CloudStorageService : download carrier files (if stored on S3)
 *   3. StegoService   : extract encrypted chunk from each carrier
 *   4. SegmentationService : reassemble chunks (with hash verification)
 *   5. CryptoService  : re-derive DEK, decrypt → plaintext
 *   6. CryptoService  : verify document hash
 */
class StegoDocumentService
{
    public function __construct(
        private readonly CryptoService       $crypto,
        private readonly StegoService        $stego,
        private readonly SegmentationService $segmentation,
        private readonly CloudStorageService $cloud,
        private readonly PersistenceService  $persistence,
        private readonly CarrierPoolSelector $carrierPoolSelector,
    ) {}

    /**
     * Increase PHP script execution time for long-running stego operations.
     *
     * Web requests can hit a 30s max_execution_time while Python subprocess
     * work is still running, so we extend the limit here using config-based
     * values. CLI/test runs are left untouched.
     */
    private function extendExecutionTimeLimit(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $pythonTimeout = (int) config('stegolock.python_timeout', 60);
        $targetSeconds = max(620, $pythonTimeout + 80);

        // Some runtimes honor ini_set, others prefer set_time_limit.
        @ini_set('max_execution_time', (string) $targetSeconds);
        @ini_set('max_input_time', (string) min(600, $targetSeconds));
        @ini_set('memory_limit', '512M');
        if (function_exists('set_time_limit')) {
            @set_time_limit($targetSeconds);
        }
    }

    // =========================================================================
    // ENCODE
    // =========================================================================

    /**
     * Hide a document inside one or more carrier files.
     *
     * @param  int    $userId              Authenticated user ID
     * @param  string $plaintext           Raw document bytes to protect
     * @param  string $masterKey           Hex-encoded master key (from CryptoService::deriveMasterKey)
     * @param  array|null $carrierPaths    Optional absolute local paths to carrier files. If null, uses carrier pool.
     * @param  int|null $documentId        Optional FK to an existing documents.id record
     * @param  bool   $useSystemCarriers   Whether to use system carriers as fallback
     * @return \App\Models\StegoDocument  The persisted StegoDocument with id
     * @throws Throwable
     */
    public function encode(
        int $userId,
        string $plaintext,
        string $masterKey,
        ?array $carrierPaths = null,
        ?int $documentId = null,
        ?int $existingDocId = null,
        bool $useSystemCarriers = false,
    ): array {
        $this->extendExecutionTimeLimit();

        // Validate inputs
        if ($userId <= 0) {
            throw new Exception('Invalid user ID');
        }

        $this->extendExecutionTimeLimit();

        if (empty($plaintext)) {
            throw new Exception('Plaintext cannot be empty');
        }

        if (empty($masterKey) || strlen($masterKey) !== 64) {
            throw new Exception('Master key must be a 64-character hex string');
        }

        // If carrier paths are provided, validate them
        if ($carrierPaths !== null) {
            if (empty($carrierPaths)) {
                throw new Exception('At least one carrier file is required for encoding.');
            }

            foreach ($carrierPaths as $index => $path) {
                if (empty($path) || !is_string($path)) {
                    throw new Exception("Carrier path at index {$index} is invalid");
                }

                if (!file_exists($path)) {
                    throw new Exception("Carrier file not found: {$path}");
                }

                if (!is_readable($path)) {
                    throw new Exception("Carrier file is not readable: {$path}");
                }

                $size = filesize($path);
                if ($size === false || $size === 0) {
                    throw new Exception("Carrier file is empty: {$path}");
                }
            }
        }

        // -----------------------------------------------------------------
        // Step 1 & 2: Generate random DEK (envelope mode), wrap for owner, encrypt
        // -----------------------------------------------------------------
        $documentRef = $documentId ? (string) $documentId : uniqid('stego_', true);

        // Generate a random DEK for sharing-friendly envelope encryption
        $randomDek = $this->crypto->generateDEK();

        // Encrypt plaintext with the random DEK
        $encrypted = $this->crypto->encrypt($plaintext, $randomDek);
        $hash      = $this->crypto->hashDocument($plaintext);

        // Wrap the random DEK with owner's master key for envelope-mode storage
        $ownerWrappedResult = $this->crypto->wrapDekForUser($randomDek, $masterKey);

        // Keep legacy DEK derivation metadata for potential backward-compat reads
        $legacyDekResult = $this->crypto->deriveDEK($masterKey, $documentRef);

        // -----------------------------------------------------------------
        // Step 3: Select carriers from pool or use provided paths
        // -----------------------------------------------------------------
        $selectedCarriers = null;
        $carriersLocked = false;
        $carrierPathsToUse = $carrierPaths;
        $tmpDir = $this->makeTmpDir('stegolock_');
        try {
            if ($carrierPaths === null) {
                // Use carrier pool - select carriers based on required capacity
                $ciphertextSize = strlen(base64_decode($encrypted['ciphertext']));
                $selectedCarriers = $this->carrierPoolSelector->select($userId, $ciphertextSize, $useSystemCarriers);
                $this->carrierPoolSelector->markInUse($selectedCarriers);
                $carriersLocked = true;

                // Resolve pool carrier paths to local temp files for stego driver (cloud-compatible)
                $carrierPathsToUse = $selectedCarriers->map(function ($carrier) use ($tmpDir) {
                    $disk = Storage::disk(config('stegolock.storage.disk', 'local'));
                    $content = $disk->get($carrier->file_path);
                    
                    if ($content === null) {
                        throw new \Exception("Carrier file not found on storage: {$carrier->file_path}");
                    }
                    
                    // Create temp file in the encoding temp directory
                    $inputDir = $tmpDir . '/input_carriers';
                    if (!is_dir($inputDir)) {
                        mkdir($inputDir, 0700, true);
                    }
                    
                    $tempPath = $inputDir . '/' . uniqid('carrier_', true) . '.' . $carrier->file_type;
                    file_put_contents($tempPath, $content);
                    
                    return $tempPath;
                })->toArray();
            } else {
                // Resolve provided carrier paths to local temp files (cloud-compatible)
                $disk = Storage::disk(config('stegolock.storage.disk', 'local'));
                $carrierPathsToUse = array_map(function ($path) use ($disk, $tmpDir) {
                    // If it's already a local file, return as is
                    if (file_exists($path)) {
                        return $path;
                    }
                    // Otherwise, assume it's a storage-relative path; download to temp.
                    $content = $disk->get($path);
                    if ($content === null) {
                        throw new \Exception("Carrier file not found on storage: {$path}");
                    }
                    $inputDir = $tmpDir . '/input_carriers';
                    if (!is_dir($inputDir)) {
                        mkdir($inputDir, 0700, true);
                    }
                    $ext = pathinfo($path, PATHINFO_EXTENSION);
                    $tempPath = $inputDir . '/' . uniqid('carrier_', true) . ($ext ? '.' . $ext : '');
                    file_put_contents($tempPath, $content);
                    return $tempPath;
                }, $carrierPaths);
            }

        // -----------------------------------------------------------------
        // Step 4: Compute carrier capacities, then split ciphertext into chunks
        // -----------------------------------------------------------------
        try {
            // Cache capacity by file hash — avoids re-running the Python driver for
            // the same carrier image on repeated encode calls (e.g. test/dev cycles).
            $capacities  = array_map(function ($p) {
                $cacheKey = 'stego.capacity.' . hash_file('md5', $p);
                return Cache::remember($cacheKey, 3600, fn () => $this->stego->capacity($p));
            }, $carrierPathsToUse);
            $segments    = $this->segmentation->split($encrypted['ciphertext'], $capacities);
            $numSegments = count($segments);
        } catch (\Throwable $e) {
            if ($carriersLocked && $selectedCarriers !== null) {
                $this->carrierPoolSelector->release($selectedCarriers);
            }
            throw $e;
        }

        // -----------------------------------------------------------------
        // Step 4 & 5: Embed each chunk into its carrier and upload to S3
        // -----------------------------------------------------------------
        $carrierRecords  = [];
        $segmentRecords  = [];
        $qualityMetrics  = [];   // keyed by segment index

        $stegoDoc = null;
        try {
            // Persist or update the StegoDocument shell so we have an ID for key naming.
            // When $existingDocId is provided the controller pre-created a 'pending' row;
            // we fill in the crypto fields here.  Otherwise create a new row.
            $coreData = [
                'document_id'       => $documentId,
                'user_id'           => $userId,
                // Keep ciphertext local (DB) to avoid cloud roundtrips on slow object storage.
                'ciphertext'        => $encrypted['ciphertext'],
                'stego_iv'          => $encrypted['iv'],
                'stego_auth_tag'    => $encrypted['auth_tag'],
                'stego_hash_sha256' => $hash,
                // Legacy derived-DEK metadata (for backward compat)
                'stego_dek_salt'    => $legacyDekResult['salt'],
                'stego_dek_iter'    => $legacyDekResult['iterations'],
                // Envelope-mode metadata
                'stego_mode'                      => 'envelope_wrapped',
                'owner_wrapped_dek'               => $ownerWrappedResult['wrapped_dek'],
                'owner_wrapped_dek_iv'            => $ownerWrappedResult['iv'],
                'owner_wrapped_dek_auth_tag'      => $ownerWrappedResult['auth_tag'],
                'owner_wrapped_dek_alg'           => $ownerWrappedResult['algorithm'],
                'owner_wrapped_dek_version'       => $ownerWrappedResult['version'],
                'compressed'        => true,
                's3_key'            => null,
                'status'            => 'pending',
            ];

            $stegoDoc = $existingDocId
                ? $this->persistence->updateStegoDocument($existingDocId, $coreData)
                : $this->persistence->createStegoDocument($coreData);

            foreach ($segments as $seg) {
                $idx        = $seg['index'];
                $carrierIdx = $seg['carrier_index'] ?? $idx;

                if (!isset($carrierPathsToUse[$carrierIdx])) {
                    throw new Exception("Carrier mapping missing for segment index {$idx}.");
                }

                $carrierPath = $carrierPathsToUse[$carrierIdx];
                $outputPath  = $tmpDir . DIRECTORY_SEPARATOR . "carrier_{$idx}_" . basename($carrierPath);

                // Embed the encrypted chunk into the carrier.
                $this->stego->embed($carrierPath, $seg['chunk'], $outputPath);

                // PSNR quality gate + metric collection (image carriers only).
                $psnrValue          = $this->measurePsnrOrFail($carrierPath, $outputPath);
                $qualityMetrics[$idx] = $this->buildQualityMetric($carrierPath, $psnrValue);

                // Upload the modified carrier to S3.
                $s3Key    = $this->cloud->carrierKey($userId, "doc{$stegoDoc->id}_seg{$idx}_" . basename($carrierPath));
                $s3Result = $this->cloud->uploadFile($outputPath, $s3Key);

                // If using pool carriers, keep the original pool file intact and
                // only reference the selected carrier for segment linkage.
                if ($selectedCarriers !== null && isset($selectedCarriers[$carrierIdx])) {
                    $carrier = $selectedCarriers[$carrierIdx];
                } else {
                    // Persist the carrier record (for direct path uploads).
                    $carrier = $this->persistence->createStegoCarrier([
                        'name'        => basename($carrierPath),
                        'file_path'   => $outputPath,
                        'file_type'   => $this->resolveFileType($carrierPath),
                        'mime_type'   => mime_content_type($carrierPath) ?: null,
                        'size'        => filesize($outputPath),
                        's3_key'      => $s3Result['s3_key'],
                        'psnr'        => $psnrValue,
                        'uploaded_by' => $userId,
                    ]);
                }

                $carrierRecords[] = $carrier;

                // Persist the segment record.
                // base64-encode the raw binary chunk for safe storage in the longtext column.
                $segmentRecords[] = [
                    'stego_document_id' => $stegoDoc->id,
                    'stego_carrier_id'  => $carrier->id,
                    'segment_index'     => $idx,
                    'encrypted_chunk'   => base64_encode($seg['chunk']),
                    // Always persist uploaded stego artifact key for retrieval/auditability.
                    's3_key'            => $s3Result['s3_key'],
                    'chunk_hash'        => $seg['hash'],
                ];
            }

            $this->enforceAggregatePsnrThreshold($qualityMetrics);

            // Bulk-persist all segments in one transaction.
            $this->persistence->createStegoSegments($segmentRecords);

            // Log the encode operation.
            $this->persistence->logAccess([
                'user_id'     => $userId,
                'action'      => 'stego_encode',
                'resource'    => 'stego_document',
                'resource_id' => $stegoDoc->id,
                'payload'     => ['document_id' => $documentId, 'num_segments' => $numSegments],
            ]);

            $stegoDoc->update(['status' => 'ready']);

            // Bust all cached variants of the user's stego index responses.
            Cache::forget("stego.index.u{$userId}.status.all");
            Cache::forget("stego.index.u{$userId}.status.pending");
            Cache::forget("stego.index.u{$userId}.status.ready");
            Cache::forget("stego.index.u{$userId}.status.failed");

        } catch (\Throwable $e) {
            if ($carriersLocked && $selectedCarriers !== null) {
                $this->carrierPoolSelector->release($selectedCarriers);
            }
            $stegoDoc?->update([
                'status'        => 'failed',
                'failed_reason' => substr($e->getMessage(), 0, 500),
            ]);
            throw $e;
        }

        return [
            'stego_document'  => $stegoDoc->fresh(),
            'quality_metrics' => array_values($qualityMetrics),
        ];
        } finally {
            $this->cleanupDir($tmpDir);
        }
    }

    // =========================================================================
    // DECODE
    // =========================================================================

    /**
     * Resolve the DEK for a caller based on document mode and caller role.
     *
     * Supports dual-mode decoding:
     *  - envelope_wrapped: unwrap caller-specific DEK (owner or active viewer)
     *  - legacy_derived: re-derive from master key (owner-only)
     *
     * @param  StegoDocument $stegoDoc    The stego document with mode and wrap metadata
     * @param  int          $userId      Caller's user ID
     * @param  string       $masterKey   Caller's master key (hex, 64 chars)
     * @return string                    Hex-encoded DEK (64 hex chars)
     * @throws Exception                 If DEK cannot be resolved for caller/mode
     */
    private function resolveDekForCaller(StegoDocument $stegoDoc, int $userId, string $masterKey): string
    {
        $isOwner = (int) $stegoDoc->user_id === $userId;

        // Envelope-wrapped mode: unwrap caller-specific wrapped DEK
        if ($stegoDoc->stego_mode === 'envelope_wrapped') {
            if ($isOwner) {
                // Owner: use owner-wrapped DEK
                return $this->crypto->unwrapDekForUser(
                    $stegoDoc->owner_wrapped_dek,
                    $stegoDoc->owner_wrapped_dek_iv,
                    $stegoDoc->owner_wrapped_dek_auth_tag,
                    $masterKey
                );
            } else {
                // Viewer: use viewer-wrapped DEK from active grant
                $grant = $stegoDoc->viewerGrants()
                    ->where('viewer_user_id', $userId)
                    ->where('grant_status', 'active')
                    ->first();

                if (!$grant || empty($grant->viewer_wrapped_dek)) {
                    throw new Exception(
                        'Cannot decode: viewer grant not active or wrapped DEK missing. '
                        . 'Complete grant acceptance first.'
                    );
                }

                if (($grant->viewer_wrapped_dek_alg ?? null) === 'AES-256-GCM-SERVER') {
                    return $this->crypto->unwrapDekForServer(
                        $grant->viewer_wrapped_dek,
                        $grant->viewer_wrapped_dek_iv,
                        $grant->viewer_wrapped_dek_auth_tag
                    );
                }

                return $this->crypto->unwrapDekForUser(
                    $grant->viewer_wrapped_dek,
                    $grant->viewer_wrapped_dek_iv,
                    $grant->viewer_wrapped_dek_auth_tag,
                    $masterKey
                );
            }
        }

        // Legacy-derived mode: allow active wrapped-grant viewers, otherwise owner-only
        if ($stegoDoc->stego_mode === 'legacy_derived' || $stegoDoc->stego_mode === null) {
            if (!$isOwner) {
                $grant = $stegoDoc->viewerGrants()
                    ->where('viewer_user_id', $userId)
                    ->where('grant_status', 'active')
                    ->first();

                if ($grant && !empty($grant->viewer_wrapped_dek)) {
                    if (($grant->viewer_wrapped_dek_alg ?? null) === 'AES-256-GCM-SERVER') {
                        return $this->crypto->unwrapDekForServer(
                            $grant->viewer_wrapped_dek,
                            $grant->viewer_wrapped_dek_iv,
                            $grant->viewer_wrapped_dek_auth_tag
                        );
                    }

                    return $this->crypto->unwrapDekForUser(
                        $grant->viewer_wrapped_dek,
                        $grant->viewer_wrapped_dek_iv,
                        $grant->viewer_wrapped_dek_auth_tag,
                        $masterKey
                    );
                }
            }

            if (!$isOwner) {
                throw new Exception(
                    'This stego document uses the legacy encryption model. '
                    . 'Only the owner can decode it. Ask the owner to decode and share the output file.'
                );
            }

            // Re-derive using stored salt + iterations
            $documentRef = $stegoDoc->document_id ? (string) $stegoDoc->document_id : (string) $stegoDoc->id;
            $dekResult = $this->crypto->deriveDEK(
                $masterKey,
                $documentRef,
                $stegoDoc->stego_dek_salt,
                $stegoDoc->stego_dek_iter
            );

            return $dekResult['dek'];
        }

        throw new Exception('Unknown stego_mode: ' . $stegoDoc->stego_mode);
    }

    /**
     * Recover the original plaintext from a StegoDocument.
     *
     * @param  int    $stegoDocumentId  Primary key of the StegoDocument to decode
     * @param  string $masterKey        Hex-encoded master key
     * @param  int    $userId           Authenticated user ID (for access log)
     * @return string                   Recovered plaintext bytes
     * @throws Exception|Throwable
     */
    public function decode(int $stegoDocumentId, string $masterKey, int $userId): string
    {
        $this->extendExecutionTimeLimit();

        $tStart = microtime(true);

        // Validate inputs
        if ($stegoDocumentId <= 0) {
            throw new Exception('Invalid StegoDocument ID');
        }

        if ($userId <= 0) {
            throw new Exception('Invalid user ID');
        }

        if (empty($masterKey) || strlen($masterKey) !== 64) {
            throw new Exception('Master key must be a 64-character hex string');
        }

        // -----------------------------------------------------------------
        // Step 1: Load metadata and segments
        // -----------------------------------------------------------------
        $stegoDoc = $this->persistence->findStegoDocument($stegoDocumentId);
        $tMetaLoaded = microtime(true);

        // Resolve caller's DEK based on document mode (envelope_wrapped or legacy_derived)
        // This handles owner decode (all modes) and viewer decode (envelope-mode with active grant)
        $dek = $this->resolveDekForCaller($stegoDoc, $userId, $masterKey);
        $tDekResolved = microtime(true);

        $segments = $this->persistence->getSegments($stegoDocumentId);
        $tSegmentsLoaded = microtime(true);

        $tmpDir = $this->makeTmpDir('stegolock_dec_');

        try {
            $ciphertext = null;
            $ciphertextSource = null;

            if (!empty($segments)) {
                // -----------------------------------------------------------------
                // Steps 2 & 3: Download carriers from S3 and extract chunks
                // -----------------------------------------------------------------
                $reassemblySegments = [];

                foreach ($segments as $segment) {
                    // Use strict mode to fail fast if any stored segment is malformed.
                    $chunk = base64_decode($segment->encrypted_chunk, true);
                    if ($chunk === false) {
                        throw new Exception("Malformed segment payload at index {$segment->segment_index}.");
                    }

                    $reassemblySegments[] = [
                        'index' => $segment->segment_index,
                        'chunk' => $chunk,
                        'hash'  => $segment->chunk_hash,
                    ];
                }

                // -----------------------------------------------------------------
                // Step 4: Reassemble chunks (with hash integrity verification)
                // -----------------------------------------------------------------
                $ciphertext = $this->segmentation->reassemble($reassemblySegments, verifyHashes: true);
                $ciphertextSource = 'segments';
            } else {
                // Backward compatibility for records without segment rows.
                $ciphertext = $this->loadCiphertextForDecode($stegoDoc);
                $ciphertextSource = $stegoDoc->s3_key ? 'local_file' : 'db_column';
            }
            $tCipherReady = microtime(true);

            // -----------------------------------------------------------------
            // Step 5: Decrypt using the resolved DEK (envelope or legacy)
            // -----------------------------------------------------------------
            $plaintext = $this->crypto->decrypt(
                $ciphertext,
                $dek,
                $stegoDoc->stego_iv,
                $stegoDoc->stego_auth_tag
            );
            $tDecryptDone = microtime(true);

            // -----------------------------------------------------------------
            // Step 6: Verify document integrity hash
            // -----------------------------------------------------------------
            if (!$this->crypto->verifyHash($plaintext, $stegoDoc->stego_hash_sha256)) {
                throw new Exception('Document integrity check failed. Hash mismatch after decryption.');
            }
            $tHashVerified = microtime(true);

            // Log the decode operation.
            $this->persistence->logAccess([
                'user_id'     => $userId,
                'action'      => 'stego_decode',
                'resource'    => 'stego_document',
                'resource_id' => $stegoDoc->id,
                'payload'     => ['status' => 'success'],
            ]);

            $totalDuration = round(($tHashVerified - $tStart), 3);
            
            logger()->info('stego.decode.timing', [
                'stego_document_id' => $stegoDocumentId,
                'ciphertext_source' => $ciphertextSource,
                'db_meta_fetch_ms'  => round(($tMetaLoaded - $tStart) * 1000, 2),
                'db_segments_ms'    => round(($tSegmentsLoaded - $tMetaLoaded) * 1000, 2),
                'cipher_ready_ms'   => round(($tCipherReady - $tSegmentsLoaded) * 1000, 2),
                'decrypt_ms'        => round(($tDecryptDone - $tCipherReady) * 1000, 2),
                'hash_verify_ms'    => round(($tHashVerified - $tDecryptDone) * 1000, 2),
                'total_ms'          => round(($tHashVerified - $tStart) * 1000, 2),
            ]);
            
            // Save decoding duration
            $stegoDoc->update(['decoding_duration' => $totalDuration]);

        } finally {
            $this->cleanupDir($tmpDir);
        }

        return $plaintext;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Create a unique temporary directory. Throws on failure.
     */
    private function makeTmpDir(string $prefix): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . uniqid();
        if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new Exception("Failed to create temp directory: {$dir}");
        }
        return $dir;
    }

    /**
     * Measure PSNR for an image carrier after embedding.
     * Returns null for non-image carriers (PSNR is not meaningful there).
    * Throws Exception if measured PSNR falls below configured threshold.
     *
     * @throws Exception
     */
    private function measurePsnrOrFail(string $carrierPath, string $outputPath): ?float
    {
        $mime = mime_content_type($carrierPath) ?: '';

        if (!str_starts_with($mime, 'image/')) {
            return null;
        }

        $metrics = $this->stego->psnr($carrierPath, $outputPath);

        $threshold = (float) config('stegolock.carrier_pool.psnr_threshold', 40.0);
        $psnr = (float) ($metrics['psnr'] ?? 0.0);

        if ($psnr < $threshold) {
            throw new Exception(sprintf(
                'Carrier "%s": PSNR %.2f dB is below the %.2f dB imperceptibility threshold. '
                . 'Choose a larger carrier image.',
                basename($carrierPath),
                $psnr,
                $threshold
            ));
        }

        return $psnr;
    }

    /**
     * Build the per-carrier quality metric entry included in the encode() return value.
     */
    private function buildQualityMetric(string $carrierPath, ?float $psnr): array
    {
        $threshold = (float) config('stegolock.carrier_pool.psnr_threshold', 40.0);

        return [
            'carrier'          => basename($carrierPath),
            'psnr'             => $psnr,
            'threshold_db'     => $threshold,
            'passed_threshold' => $psnr !== null ? ($psnr >= $threshold) : null,
            // Backward compatibility for existing API consumers.
            'threshold_40db'   => $psnr !== null ? ($psnr >= 40.0) : null,
        ];
    }

    /**
     * Additional PSNR safety guard for concentrated bin-packing payloads.
     *
     * @param array<int, array{carrier: string, psnr: ?float, threshold_db?: float}> $qualityMetrics
     * @throws Exception
     */
    private function enforceAggregatePsnrThreshold(array $qualityMetrics): void
    {
        $imageMetrics = array_values(array_filter(
            $qualityMetrics,
            fn (array $metric): bool => isset($metric['psnr']) && $metric['psnr'] !== null
        ));

        if (empty($imageMetrics)) {
            return;
        }

        $avgThreshold = (float) config('stegolock.carrier_pool.encode_average_psnr_threshold', 41.0);
        $avgPsnr = array_sum(array_map(fn (array $metric): float => (float) $metric['psnr'], $imageMetrics)) / count($imageMetrics);

        if ($avgPsnr < $avgThreshold) {
            throw new Exception(sprintf(
                'Average PSNR %.2f dB is below the %.2f dB encode safety threshold. Choose larger carriers.',
                $avgPsnr,
                $avgThreshold
            ));
        }
    }

    private function resolveFileType(string $path): string
    {
        $mime = mime_content_type($path) ?: '';

        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }
        if (str_starts_with($mime, 'text/')) {
            return 'text';
        }

        return 'binary';
    }

    private function loadCiphertextForDecode($stegoDoc): string
    {
        if (!empty($stegoDoc->s3_key)) {
            try {
                return $this->cloud->getContents($stegoDoc->s3_key);
            } catch (\Throwable) {
                // Fallback to legacy local-path behavior for backward compatibility.
                if (Storage::disk('local')->exists($stegoDoc->s3_key)) {
                    return Storage::disk('local')->get($stegoDoc->s3_key);
                }
            }
        }

        // Query legacy DB ciphertext only when needed to keep regular fetches lean.
        $legacyCiphertext = StegoDocument::query()
            ->whereKey($stegoDoc->id)
            ->value('ciphertext');

        if (!empty($legacyCiphertext)) {
            return $legacyCiphertext;
        }

        throw new Exception("StegoDocument #{$stegoDoc->id} has no recoverable ciphertext source.");
    }

    /**
     * Estimate decoding time for a pending StegoDocument based on historical decoding speed.
     *
     * @param int $stegoDocumentId Primary key of the StegoDocument to estimate
     * @return float|null Estimated decoding time in seconds, or null if insufficient data
     */
    public function estimateDecodingTime(int $stegoDocumentId): ?float
    {
        $stegoDoc = $this->persistence->findStegoDocument($stegoDocumentId);
        
        // If document has already been decoded, return actual duration
        if (!empty($stegoDoc->decoding_duration)) {
            return $stegoDoc->decoding_duration;
        }
        
        // Get total carrier size for this document
        $totalCarrierSize = $stegoDoc->segments()
            ->join('stego_carriers', 'stego_segments.stego_carrier_id', '=', 'stego_carriers.id')
            ->sum('stego_carriers.size');
        
        if ($totalCarrierSize <= 0) {
            return null;
        }
        
        // Calculate average decoding speed (bytes per second) from completed documents
        $averageSpeed = StegoDocument::query()
            ->whereNotNull('decoding_duration')
            ->where('decoding_duration', '>', 0)
            ->with(['segments' => function ($query) {
                $query->join('stego_carriers', 'stego_segments.stego_carrier_id', '=', 'stego_carriers.id')
                    ->select('stego_segments.stego_document_id', 'stego_carriers.size');
            }])
            ->get()
            ->map(function ($doc) {
                $docCarrierSize = $doc->segments->sum('size');
                return $docCarrierSize > 0 ? $docCarrierSize / $doc->decoding_duration : null;
            })
            ->filter()
            ->avg();
        
        if ($averageSpeed <= 0) {
            return null;
        }
        
        // Estimate decoding time
        $estimatedTime = $totalCarrierSize / $averageSpeed;
        
        return round($estimatedTime, 3);
    }

    private function cleanupDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @rmdir($dir);
    }

    /**
     * Delete a file from cloud storage with retry logic.
     *
     * @param mixed $b2 The B2 service or cloud storage client
     * @param mixed $file The file object or path to delete
     * @param int $maxRetries Maximum number of retry attempts
     * @return bool True if deletion successful, false otherwise
     */
    public function deleteWithRetry($b2, $file, int $maxRetries = 3): bool
    {
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            try {
                // Attempt to delete the file from cloud storage
                if (method_exists($b2, 'deleteFile')) {
                    $b2->deleteFile($file);
                } elseif (method_exists($b2, 'delete')) {
                    $b2->delete($file);
                }
                
                return true;
            } catch (Exception $e) {
                $attempt++;
                \Illuminate\Support\Facades\Log::warning("Delete attempt {$attempt} failed: " . $e->getMessage());
                
                if ($attempt < $maxRetries) {
                    // Exponential backoff: 1s, 2s, 4s...
                    sleep(pow(2, $attempt - 1));
                }
            }
        }
        
        \Illuminate\Support\Facades\Log::error("Failed to delete file after {$maxRetries} attempts");
        return false;
    }

    /**
     * Clean up resources when document processing fails.
     * Per user request: comments out database and cloud storage cleanup,
     * retains local temp file cleanup.
     *
     * @param int $documentId The document ID to clean up
     */
    public function cleanupOnFailure(int $documentId): void
    {
        // Commented out: database cleanup
        // $document = \App\Models\Document::find($documentId);
        // if ($document) {
        //     $document->delete();
        // }

        // Commented out: cloud storage cleanup
        // if (method_exists($this, 'deleteFromCloud')) {
        //     try {
        //         $this->deleteFromCloud($documentId);
        //     } catch (Exception $e) {
        //         \Illuminate\Support\Facades\Log::error("Cloud cleanup failed: " . $e->getMessage());
        //     }
        // }

        // Retain: local temp file cleanup
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'stego_' . $documentId;
        if (is_dir($tmpDir)) {
            $this->cleanupDir($tmpDir);
        }
        
        \Illuminate\Support\Facades\Log::info("Local cleanup completed for document {$documentId}");
    }
}
