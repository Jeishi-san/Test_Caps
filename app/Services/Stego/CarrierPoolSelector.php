<?php

namespace App\Services\Stego;

use App\Models\StegoCarrier;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CarrierPoolSelector
 *
 * Selects the minimum set of valid carriers from a user's pool
 * that can collectively hold the required bytes.
 *
 * Uses greedy bin-packing: largest carriers first to minimize the number of carriers needed.
 * Implements lockForUpdate to prevent race conditions during concurrent encode requests.
 *
 * Supports system carrier fallback for new users with empty pools.
 *
 * Mandate-aware selection (when enabled):
 *   - Ensures at least one carrier of each mandated type (image/audio/text) is included.
 *   - User pool is used first for each mandated type; system pool as fallback.
 *   - Hard failure if a mandated type is missing from both pools.
 *   - After mandates, greedy fill covers remaining capacity.
 */
class CarrierPoolSelector
{
    /**
     * Select carriers from the pool that can hold the required bytes.
     *
     * @param int $userId User ID
     * @param int $requiredBytes Total bytes needed
     * @param bool $allowSystemFallback Whether to fall back to system carriers if user pool is insufficient
     * @return Collection Selected carriers
     * @throws \RuntimeException If pool has insufficient capacity or mandate not satisfied
     */
    public function select(int $userId, int $requiredBytes, bool $allowSystemFallback = true): Collection
    {
        $mandatesEnabled = config('stegolock.carrier_mandates.enabled', false);

        if ($mandatesEnabled) {
            return $this->selectWithMandates($userId, $requiredBytes, $allowSystemFallback);
        }

        // Legacy greedy selection (no mandates)
        $userCarriers = $this->queryValidCarriers($userId, $requiredBytes);
        $accumulated = $userCarriers->sum('capacity_bytes');

        if ($accumulated >= $requiredBytes) {
            return $userCarriers;
        }

        if (!$allowSystemFallback) {
            $shortfall = $requiredBytes - $accumulated;
            throw new \RuntimeException(
                "Pool has {$accumulated} bytes available but {$requiredBytes} bytes needed. " .
                "Upload approximately " . ceil($shortfall / 500000) . " more carrier image(s)."
            );
        }

        $shortfall = $requiredBytes - $accumulated;
        $systemCarriers = $this->queryValidCarriers($this->getSystemUserId(), $shortfall);

        $totalAvailable = $accumulated + $systemCarriers->sum('capacity_bytes');
        if ($totalAvailable < $requiredBytes) {
            throw new \RuntimeException(
                "Pool insufficient even with system carriers. " .
                "User pool: {$accumulated} bytes, System pool: {$systemCarriers->sum('capacity_bytes')} bytes, " .
                "Required: {$requiredBytes} bytes. Contact support."
            );
        }

        Log::info('CarrierPoolSelector: Using system carriers to fill gap', [
            'user_id' => $userId,
            'user_pool_bytes' => $accumulated,
            'system_carriers_used' => $systemCarriers->count(),
            'system_carriers_bytes' => $systemCarriers->sum('capacity_bytes'),
            'required_bytes' => $requiredBytes,
        ]);

        return $userCarriers->merge($systemCarriers)->values();
    }

    /**
     * Query valid carriers for a user that can collectively hold the required bytes.
     *
     * @param int $userId User ID
     * @param int $requiredBytes Total bytes needed
     * @return Collection Selected carriers
     */
    /**
     * Query valid carriers for a user, optionally filtered by carrier type.
     * Carriers are ordered by capacity DESC, id ASC (deterministic tie-breaking).
     *
     * @param int $userId
     * @param int $requiredBytes
     * @param string|null $typeFilter 'image', 'audio', or 'text' or null for all
     * @return Collection
     */
    private function queryValidCarriers(int $userId, int $requiredBytes, ?string $typeFilter = null): Collection
    {
        $query = StegoCarrier::where('uploaded_by', $userId)
            ->where('validation_status', 'valid')
            ->where('is_in_use', false)
            ->whereNotNull('capacity_bytes')
            ->where('capacity_bytes', '>', 0);

        if ($typeFilter !== null) {
            $allowedConfig = config("stegolock.carriers.allowed.{$typeFilter}.mime_types", []);
            if (empty($allowedConfig)) {
                return collect(); // no allowed mimes for this type
            }
            $query->whereIn('mime_type', $allowedConfig);
        }

        // Order by capacity DESC, id ASC for deterministic tie-breaking
        $carriers = $query->orderByDesc('capacity_bytes')
            ->orderByAsc('id')
            ->lockForUpdate()
            ->get();

        $selected = collect();
        $accumulated = 0;

        foreach ($carriers as $carrier) {
            if ($accumulated >= $requiredBytes) {
                break;
            }
            $selected->push($carrier);
            $accumulated += $carrier->capacity_bytes;
        }

        return $selected;
    }

    /**
     * Mandate-aware carrier selection.
     *
     * @param int $userId
     * @param int $requiredBytes
     * @param bool $allowSystemFallback
     * @return Collection
     * @throws \RuntimeException If any mandate cannot be satisfied
     */
    private function selectWithMandates(int $userId, int $requiredBytes, bool $allowSystemFallback): Collection
    {
        $mandates = config('stegolock.carrier_mandates', []);
        $requireImage = $mandates['require_image'] ?? false;
        $requireAudio = $mandates['require_audio'] ?? false;
        $requireText  = $mandates['require_text']  ?? false;

        $requiredTypes = [];
        if ($requireImage) $requiredTypes[] = 'image';
        if ($requireAudio) $requiredTypes[] = 'audio';
        if ($requireText)  $requiredTypes[] = 'text';

        // If no types required (all false), fall back to greedy
        if (empty($requiredTypes)) {
            return $this->select($userId, $requiredBytes, $allowSystemFallback);
        }

        $selected = collect();
        $remainingBytes = $requiredBytes;
        $usedCarrierIds = [];

        // Step 1: Satisfy each mandated type with best carrier from user pool, else system pool
        foreach ($requiredTypes as $type) {
            // Try user pool first
            $userCarrier = $this->findBestCarrier($userId, $type);
            if ($userCarrier) {
                $selected->push($userCarrier);
                $usedCarrierIds[] = $userCarrier->id;
                $remainingBytes -= $userCarrier->capacity_bytes;
                continue;
            }

            // Fallback to system pool if allowed
            if ($allowSystemFallback) {
                $systemUserId = $this->getSystemUserId();
                $systemCarrier = $this->findBestCarrier($systemUserId, $type);
                if ($systemCarrier) {
                    $selected->push($systemCarrier);
                    $usedCarrierIds[] = $systemCarrier->id;
                    $remainingBytes -= $systemCarrier->capacity_bytes;
                    Log::info('CarrierPoolSelector: Mandate satisfied by system carrier', [
                        'type' => $type,
                        'carrier_id' => $systemCarrier->id,
                    ]);
                    continue;
                }
            }

            // Mandate cannot be satisfied
            $ext = collect(config("stegolock.carriers.allowed.{$type}.mimes", []))->first();
            $extMsg = $ext ? "Upload a .{$ext} file" : "Upload a compatible carrier";
            throw new \RuntimeException(
                "Cannot encode: no {$type} carrier available. {$extMsg} or contact support."
            );
        }

        // Step 2: Greedy fill remaining bytes with best carriers from all types (excluding used)
        if ($remainingBytes > 0) {
            $greedyCarriers = $this->queryGreedyCarriers($userId, $remainingBytes, $usedCarrierIds, $allowSystemFallback);
            foreach ($greedyCarriers as $carrier) {
                $selected->push($carrier);
                $remainingBytes -= $carrier->capacity_bytes;
                if ($remainingBytes <= 0) {
                    break;
                }
            }
        }

        // Final check: did we gather enough capacity?
        if ($remainingBytes > 0) {
            throw new \RuntimeException(
                "Insufficient capacity after mandate selection. " .
                "Need {$requiredBytes} bytes, selected " . ($requiredBytes - $remainingBytes) . " bytes. " .
                "Upload more carriers."
            );
        }

        Log::info('CarrierPoolSelector: Mandate-aware selection completed', [
            'user_id' => $userId,
            'selected_count' => $selected->count(),
            'mandates' => $requiredTypes,
            'initial_remaining' => $requiredBytes,
            'final_remaining' => $remainingBytes,
        ]);

        return $selected;
    }

    /**
     * Find the best (largest capacity) carrier of a given type for a user.
     *
     * @param int $userId
     * @param string $type 'image', 'audio', 'text'
     * @return StegoCarrier|null
     */
    private function findBestCarrier(int $userId, string $type): ?StegoCarrier
    {
        $allowedMimes = config("stegolock.carriers.allowed.{$type}.mime_types", []);
        if (empty($allowedMimes)) {
            return null;
        }

        $carrier = StegoCarrier::where('uploaded_by', $userId)
            ->where('validation_status', 'valid')
            ->where('is_in_use', false)
            ->whereNotNull('capacity_bytes')
            ->where('capacity_bytes', '>', 0)
            ->whereIn('mime_type', $allowedMimes)
            ->orderByDesc('capacity_bytes')
            ->orderByAsc('id')  // deterministic tie-breaking
            ->lockForUpdate()
            ->first();

        return $carrier;
    }

    /**
     * Query carriers for greedy fill, excluding already-used carrier IDs.
     *
     * @param int $userId
     * @param int $requiredBytes
     * @param array $excludeIds
     * @param bool $allowSystemFallback
     * @return Collection
     */
    private function queryGreedyCarriers(int $userId, int $requiredBytes, array $excludeIds, bool $allowSystemFallback): Collection
    {
        // Start with user's carriers (excluding used)
        $userQuery = StegoCarrier::where('uploaded_by', $userId)
            ->where('validation_status', 'valid')
            ->where('is_in_use', false)
            ->whereNotNull('capacity_bytes')
            ->where('capacity_bytes', '>', 0);

        if (!empty($excludeIds)) {
            $userQuery->whereNotIn('id', $excludeIds);
        }

        $userCarriers = $userQuery->orderByDesc('capacity_bytes')
            ->orderByAsc('id')
            ->lockForUpdate()
            ->get();

        $selected = collect();
        $accumulated = 0;

        foreach ($userCarriers as $carrier) {
            if ($accumulated >= $requiredBytes) {
                break;
            }
            $selected->push($carrier);
            $accumulated += $carrier->capacity_bytes;
        }

        // If still need more and system fallback allowed
        if ($allowSystemFallback && $accumulated < $requiredBytes) {
            $systemUserId = $this->getSystemUserId();
            $systemQuery = StegoCarrier::where('uploaded_by', $systemUserId)
                ->where('validation_status', 'valid')
                ->where('is_in_use', false)
                ->whereNotNull('capacity_bytes')
                ->where('capacity_bytes', '>', 0);

            // Exclude initial excluded IDs and any already selected from user pool
            $systemExclude = array_merge($excludeIds, $selected->pluck('id')->all());
            if (!empty($systemExclude)) {
                $systemQuery->whereNotIn('id', $systemExclude);
            }

            $systemCarriers = $systemQuery->orderByDesc('capacity_bytes')
                ->orderByAsc('id')
                ->lockForUpdate()
                ->get();

            foreach ($systemCarriers as $carrier) {
                if ($accumulated >= $requiredBytes) {
                    break;
                }
                $selected->push($carrier);
                $accumulated += $carrier->capacity_bytes;
            }
        }

        return $selected;
    }

    /**
     * Get the system user ID (admin user who owns system carriers).
     *
     * @return int System user ID
     * @throws \RuntimeException If no admin user found
     */
    private function getSystemUserId(): int
    {
        $adminUser = User::where('role', 'admin')->first();

        if (!$adminUser) {
            throw new \RuntimeException('No admin user found. System carriers cannot be used.');
        }

        return $adminUser->id;
    }

    /**
     * Mark selected carriers as in use.
     * 
     * @param Collection $carriers Carriers to mark
     */
    public function markInUse(Collection $carriers): void
    {
        if ($carriers->isEmpty()) {
            return;
        }

        StegoCarrier::whereIn('id', $carriers->pluck('id'))
            ->update(['is_in_use' => true]);
    }

    /**
     * Release carriers back to the pool.
     * 
     * @param Collection $carriers Carriers to release
     */
    public function release(Collection $carriers): void
    {
        if ($carriers->isEmpty()) {
            return;
        }

        StegoCarrier::whereIn('id', $carriers->pluck('id'))
            ->update(['is_in_use' => false]);
    }

    /**
     * Get total available capacity for a user's pool.
     * 
     * @param int $userId User ID
     * @return int Total bytes available
     */
    public function getAvailableCapacity(int $userId): int
    {
        return StegoCarrier::where('uploaded_by', $userId)
            ->where('validation_status', 'valid')
            ->where('is_in_use', false)
            ->whereNotNull('capacity_bytes')
            ->sum('capacity_bytes');
    }

    /**
     * Check if pool has sufficient capacity for required bytes.
     * 
     * @param int $userId User ID
     * @param int $requiredBytes Required bytes
     * @return bool
     */
    public function hasSufficientCapacity(int $userId, int $requiredBytes): bool
    {
        return $this->getAvailableCapacity($userId) >= $requiredBytes;
    }
}
