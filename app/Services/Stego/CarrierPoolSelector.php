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
        $carriers = $query->orderBy('capacity_bytes', 'desc')
            ->orderBy('id', 'asc')
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
        $minDiverseTypes = (int) ($mandates['minimum_diverse_types'] ?? 1);
        $minCarriers = (int) ($mandates['minimum_carriers'] ?? 1);

        // Validate config bounds
        if ($minDiverseTypes < 1) $minDiverseTypes = 1;
        if ($minDiverseTypes > 3) $minDiverseTypes = 3; // max 3 types exist
        if ($minCarriers < 1) $minCarriers = 1;

        $selected = collect();
        $remainingBytes = $requiredBytes;
        $usedCarrierIds = [];
        $selectedTypes = [];

        // STEP A: Satisfy minimum diverse types requirement
        if ($minDiverseTypes > 0) {
            $this->satisfyDiverseTypes($minDiverseTypes, $userId, $allowSystemFallback, $selected, $selectedTypes, $usedCarrierIds, $remainingBytes);
        }

        // STEP B: Greedy fill remaining capacity (capacity-aware, minimizes carrier count)
        if ($remainingBytes > 0) {
            $greedyCarriers = $this->queryGreedyCarriers($userId, $remainingBytes, $usedCarrierIds, $allowSystemFallback);
            foreach ($greedyCarriers as $carrier) {
                $selected->push($carrier);
                $usedCarrierIds[] = $carrier->id;
                $remainingBytes -= $carrier->capacity_bytes;
                if ($remainingBytes <= 0) {
                    break;
                }
            }
        }

        // STEP C: Only add more carriers if greedy selection produced fewer than minimum_carriers
        // This is rare — only when payload is tiny and greedy picked very few carriers
        if ($selected->count() < $minCarriers) {
            $this->satisfyMinimumCarriers($minCarriers, $userId, $allowSystemFallback, $selected, $usedCarrierIds, $remainingBytes);
        }

        // Final capacity validation
        if ($remainingBytes > 0) {
            $selectedBytes = $requiredBytes - $remainingBytes;
            throw new \RuntimeException(
                "Insufficient capacity after mandate selection. " .
                "Need {$requiredBytes} bytes, selected {$selectedBytes} bytes. " .
                "Upload more carriers."
            );
        }

        Log::info('CarrierPoolSelector: Mandate-aware selection completed', [
            'user_id' => $userId,
            'selected_count' => $selected->count(),
            'selected_types' => $selectedTypes,
            'min_diverse_types' => $minDiverseTypes,
            'min_carriers' => $minCarriers,
            'required_bytes' => $requiredBytes,
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
            ->orderBy('capacity_bytes', 'desc')
            ->orderBy('id', 'asc')  // deterministic tie-breaking
            ->lockForUpdate()
            ->first();

        return $carrier;
    }

    /**
     * Satisfy minimum diverse types requirement.
     * Selects the largest carrier of each type until minimum_diverse_types is reached.
     *
     * @param int $minDiverseTypes Minimum number of different carrier types required
     * @param int $userId User ID
     * @param bool $allowSystemFallback Whether to use system carriers as fallback
     * @param Collection $selected Collection to add carriers to (by reference)
     * @param array $selectedTypes Array of selected type names (by reference)
     * @param array $usedIds Array of used carrier IDs (by reference)
     * @param int $remainingBytes Remaining bytes needed (by reference, will be reduced)
     * @throws \RuntimeException If diverse types requirement cannot be satisfied
     */
    private function satisfyDiverseTypes(int $minDiverseTypes, int $userId, bool $allowSystemFallback,
        Collection &$selected, array &$selectedTypes, array &$usedIds, int &$remainingBytes): void
    {
        $types = ['image', 'audio', 'text'];
        $candidates = [];

        // Get best carrier per type from user pool
        foreach ($types as $type) {
            $carrier = $this->findBestCarrier($userId, $type);
            if ($carrier) {
                $candidates[$type] = $carrier;
            }
        }

        // Sort candidates by capacity descending to pick largest first
        uasort($candidates, function ($a, $b) {
            return $b->capacity_bytes <=> $a->capacity_bytes;
        });

        // Select top N diverse types from user pool
        foreach ($candidates as $type => $carrier) {
            if (count($selectedTypes) >= $minDiverseTypes) {
                break;
            }
            $selected->push($carrier);
            $usedIds[] = $carrier->id;
            $selectedTypes[] = $type;
            $remainingBytes -= $carrier->capacity_bytes;
        }

        // Fallback to system pool if still insufficient
        if (count($selectedTypes) < $minDiverseTypes && $allowSystemFallback) {
            $systemUserId = $this->getSystemUserId();
            foreach ($types as $type) {
                if (in_array($type, $selectedTypes)) {
                    continue;
                }
                $carrier = $this->findBestCarrier($systemUserId, $type);
                if ($carrier) {
                    $selected->push($carrier);
                    $usedIds[] = $carrier->id;
                    $selectedTypes[] = $type;
                    $remainingBytes -= $carrier->capacity_bytes;
                    if (count($selectedTypes) >= $minDiverseTypes) {
                        break;
                    }
                }
            }
        }

        // Validate: did we get enough diverse types?
        if (count($selectedTypes) < $minDiverseTypes) {
            $available = empty($selectedTypes) ? 'none' : implode(', ', $selectedTypes);
            throw new \RuntimeException(
                "Mandate requires at least {$minDiverseTypes} different carrier types (image/audio/text). " .
                "Only " . count($selectedTypes) . " type(s) available: {$available}. " .
                "Upload carriers of different types."
            );
        }
    }

    /**
     * Satisfy minimum total carriers requirement.
     * Adds more carriers (any type) until minimum_carriers is reached.
     *
     * @param int $minCarriers Minimum total carriers required
     * @param int $userId User ID
     * @param bool $allowSystemFallback Whether to use system carriers as fallback
     * @param Collection $selected Collection to add carriers to (by reference)
     * @param array $usedIds Array of used carrier IDs (by reference)
     * @param int $remainingBytes Remaining bytes needed (by reference, will be reduced)
     * @throws \RuntimeException If minimum carriers requirement cannot be satisfied
     */
    private function satisfyMinimumCarriers(int $minCarriers, int $userId, bool $allowSystemFallback,
        Collection &$selected, array &$usedIds, int &$remainingBytes): void
    {
        $currentCount = $selected->count();
        if ($currentCount >= $minCarriers) {
            return; // Already satisfied
        }

        $needed = $minCarriers - $currentCount;
        $added = 0;

        // Try user carriers first (excluding already used)
        $userCarriers = StegoCarrier::where('uploaded_by', $userId)
            ->where('validation_status', 'valid')
            ->where('is_in_use', false)
            ->whereNotNull('capacity_bytes')
            ->where('capacity_bytes', '>', 0)
            ->whereNotIn('id', $usedIds)
            ->orderBy('capacity_bytes', 'desc')
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get();

        foreach ($userCarriers as $carrier) {
            if ($added >= $needed) {
                break;
            }
            $selected->push($carrier);
            $usedIds[] = $carrier->id;
            $remainingBytes -= $carrier->capacity_bytes;
            $added++;
        }

        // If still need more and system fallback allowed
        if ($added < $needed && $allowSystemFallback) {
            $systemUserId = $this->getSystemUserId();
            $systemCarriers = StegoCarrier::where('uploaded_by', $systemUserId)
                ->where('validation_status', 'valid')
                ->where('is_in_use', false)
                ->whereNotNull('capacity_bytes')
                ->where('capacity_bytes', '>', 0)
                ->whereNotIn('id', $usedIds)
                ->orderBy('capacity_bytes', 'desc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            foreach ($systemCarriers as $carrier) {
                if ($added >= $needed) {
                    break;
                }
                $selected->push($carrier);
                $usedIds[] = $carrier->id;
                $remainingBytes -= $carrier->capacity_bytes;
                $added++;
            }
        }

        $totalNow = $selected->count();
        if ($totalNow < $minCarriers) {
            throw new \RuntimeException(
                "Mandate requires at least {$minCarriers} total carriers. " .
                "Only {$totalNow} carrier(s) available after diversity requirement. " .
                "Upload more carriers."
            );
        }
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

        $userCarriers = $userQuery->orderBy('capacity_bytes', 'desc')
            ->orderBy('id', 'asc')
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

            $systemCarriers = $systemQuery->orderBy('capacity_bytes', 'desc')
                ->orderBy('id', 'asc')
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
