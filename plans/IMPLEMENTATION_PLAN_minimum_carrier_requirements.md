# Implementation Plan: Minimum Carrier Count & Diversity Mandates

## Overview

Replace the current boolean-type-specific mandates (`require_image`, `require_audio`, `require_text`) with a more flexible policy system that enforces:
1. **Minimum diverse types**: At least N different carrier types (image/audio/text)
2. **Minimum total carriers**: At least M total carriers

**Default values**: Both set to `1` to maintain 100% backward compatibility.

---

## Current System Analysis

### Existing Config (`config/stegolock.php`)
```php
'carrier_mandates' => [
    'enabled'       => env('STEGO_MANDATES_ENABLED', false),
    'require_image' => env('STEGO_REQUIRE_IMAGE', true),   // bool
    'require_audio' => env('STEGO_REQUIRE_AUDIO', false),  // bool
    'require_text'  => env('STEGO_REQUIRE_TEXT', false),   // bool
],
```

### Existing Selection Logic (`CarrierPoolSelector::selectWithMandates()`)
1. Build `$requiredTypes` array from true `require_*` flags
2. For each type in `$requiredTypes`:
   - Find best carrier from user pool
   - Fallback to system pool if not found
   - Throw exception if type unavailable
3. Greedy fill remaining capacity
4. Return selected carriers

**Limitation**: Only enforces type presence, not total count. Can use 1 carrier if it satisfies all required types (impossible currently since only one type per carrier, but theoretically if a carrier could be multi-type, or if only one type is required).

---

## Proposed Design

### New Config Structure
```php
'carrier_mandates' => [
    'enabled'               => env('STEGO_MANDATES_ENABLED', false),
    'minimum_diverse_types' => env('STEGO_MINIMUM_DIVERSE_TYPES', 1),  // int: 1-3
    'minimum_carriers'      => env('STEGO_MINIMUM_CARRIERS', 1),       // int: 1+
],
```

**Rationale for defaults**:
- `minimum_diverse_types = 1`: Current behavior when `require_image=true` requires at least 1 type (image). Setting to 1 means "at least 1 type" which is always satisfied if any carrier is selected.
- `minimum_carriers = 1`: Current behavior uses minimum 1 carrier. This is the absolute floor.

**Backward compatibility**: Existing installations with `require_image=true` will have equivalent behavior if we set:
```
STEGO_MINIMUM_DIVERSE_TYPES=1
STEGO_MINIMUM_CARRIERS=1
```
Because the current system already ensures at least 1 carrier (the image) is selected. The new system doesn't add new constraints unless user explicitly increases these numbers.

**Migration path**: The old `require_*` flags are removed. If users had custom configurations like `require_audio=true`, they must set `STEGO_MINIMUM_DIVERSE_TYPES=2` (or higher) to enforce diversity. However, note that the original system's `require_image=true` by default would be replaced by `minimum_diverse_types=1` which doesn't force diversity. To maintain the "always require image" behavior, users would need to set `minimum_diverse_types=2` and ensure they have both image and another type. But that's a **policy change** — the new policy is about count/diversity, not specific types.

**Important distinction**: The new policy is **type-agnostic**. It doesn't care *which* types, only *how many* different types. This is intentionally different from the old `require_image` which forced a specific type. If the user wants to force specific types, that's a different feature (not requested).

---

## Algorithm: New `selectWithMandates()`

### Pseudocode
```
Input: $userId, $requiredBytes, $allowSystemFallback
Output: Collection of selected carriers

1. If mandates NOT enabled → call parent::select() (greedy)

2. Read config:
   minDiverseTypes = config('stegolock.carrier_mandates.minimum_diverse_types')  // default 1
   minCarriers     = config('stegolock.carrier_mandates.minimum_carriers')       // default 1

3. STEP A: Satisfy minimum_diverse_types
   selected = []
   usedIds = []
   selectedTypes = []

   // Get all available types sorted by capacity of best carrier (descending)
   // This tries to pick the largest carriers first to meet capacity efficiently
   allTypes = ['image', 'audio', 'text']
   typeBestCarriers = []
   for each type in allTypes:
       carrier = findBestCarrier(userId, type)  // user pool first
       if carrier:
           typeBestCarriers[type] = carrier

   // Sort types by carrier capacity (descending) to pick largest first
   sort typeBestCarriers by carrier.capacity_bytes DESC

   // Select top N diverse types until we reach minDiverseTypes
   for each (type, carrier) in typeBestCarriers:
       if count(selectedTypes) >= minDiverseTypes:
           break
       selected.push(carrier)
       usedIds.push(carrier.id)
       selectedTypes.push(type)
       remainingBytes -= carrier.capacity_bytes

   // If we still don't have enough diverse types, try system pool
   if count(selectedTypes) < minDiverseTypes and allowSystemFallback:
       for each type not in selectedTypes:
           carrier = findBestCarrier(systemUserId, type)
           if carrier:
               selected.push(carrier)
               usedIds.push(carrier.id)
               selectedTypes.push(type)
               remainingBytes -= carrier.capacity_bytes
               if count(selectedTypes) >= minDiverseTypes:
                   break

   // Check: Did we get enough diverse types?
   if count(selectedTypes) < minDiverseTypes:
       throw new MandateNotSatisfiedException(
           "Need at least {$minDiverseTypes} different carrier types, but only " .
           count($selectedTypes) . " type(s) available: " . implode(', ', $selectedTypes)
       )

4. STEP B: Satisfy minimum_carriers
   // We already have count(selected) carriers from Step A
   // If that's less than minCarriers, add more carriers (any type) until we reach minCarriers
   if count(selected) < minCarriers:
       // Query more carriers excluding already used IDs
       additionalNeeded = minCarriers - count(selected)
       additionalCarriers = queryGreedyCarriers(
           userId, 
           remainingBytes, 
           usedIds, 
           allowSystemFallback
       )
       // Take only as many as needed to reach minimum (but could take more if they fit)
       for i from 0 to additionalNeeded-1:
           if i < additionalCarriers.count:
               carrier = additionalCarriers[i]
               selected.push(carrier)
               usedIds.push(carrier.id)
               remainingBytes -= carrier.capacity_bytes
           else:
               break  // not enough carriers available

       // Check: Did we get enough carriers?
       if count(selected) < minCarriers:
           throw new MandateNotSatisfiedException(
               "Need at least {$minCarriers} total carriers, but only " .
               count($selected) . " available after diversity requirement"
           )

5. STEP C: Greedy fill remaining capacity
   if remainingBytes > 0:
       // Continue greedy selection from where we left off
       greedyCarriers = queryGreedyCarriers(userId, remainingBytes, usedIds, allowSystemFallback)
       for each carrier in greedyCarriers:
           selected.push(carrier)
           remainingBytes -= carrier.capacity_bytes
           if remainingBytes <= 0:
               break

6. STEP D: Final capacity validation
   if remainingBytes > 0:
       throw new RuntimeException("Insufficient capacity after mandate selection...")

7. Return selected
```

---

## Key Implementation Details

### 1. Config Changes (`config/stegolock.php`)

**Replace lines 198-203**:
```php
'carrier_mandates' => [
    'enabled'               => env('STEGO_MANDATES_ENABLED', false),
    'minimum_diverse_types' => env('STEGO_MINIMUM_DIVERSE_TYPES', 1),  // NEW
    'minimum_carriers'      => env('STEGO_MINIMUM_CARRIERS', 1),       // NEW
],
```

**Remove**: `require_image`, `require_audio`, `require_text`

**Add environment variables** to `.env.example`:
```
STEGO_MANDATES_ENABLED=false
STEGO_MINIMUM_DIVERSE_TYPES=1
STEGO_MINIMUM_CARRIERS=1
```

### 2. CarrierPoolSelector Changes

**File**: `app/Services/Stego/CarrierPoolSelector.php`

**Modify** `selectWithMandates()` method (lines 147-233):

Current structure:
```php
private function selectWithMandates(...): Collection
{
    // Build $requiredTypes from require_* flags
    // For each type: find best carrier (user → system)
    // Greedy fill
    // Return
}
```

New structure:
```php
private function selectWithMandates(...): Collection
{
    $mandates = config('stegolock.carrier_mandates', []);
    $minDiverseTypes = $mandates['minimum_diverse_types'] ?? 1;
    $minCarriers = $mandates['minimum_carriers'] ?? 1;

    $selected = collect();
    $remainingBytes = $requiredBytes;
    $usedCarrierIds = [];
    $selectedTypes = [];

    // STEP A: Minimum diverse types
    if ($minDiverseTypes > 0) {
        $this->satisfyDiverseTypes($minDiverseTypes, $userId, $allowSystemFallback, 
            $selected, $selectedTypes, $usedCarrierIds, $remainingBytes);
    }

    // STEP B: Minimum total carriers
    if ($minCarriers > count($selected)) {
        $this->satisfyMinimumCarriers($minCarriers, $userId, $allowSystemFallback,
            $selected, $usedCarrierIds, $remainingBytes);
    }

    // STEP C: Greedy fill remaining capacity
    if ($remainingBytes > 0) {
        $greedy = $this->queryGreedyCarriers($userId, $remainingBytes, $usedCarrierIds, $allowSystemFallback);
        foreach ($greedy as $carrier) {
            $selected->push($carrier);
            $remainingBytes -= $carrier->capacity_bytes;
            if ($remainingBytes <= 0) break;
        }
    }

    // Final capacity check
    if ($remainingBytes > 0) {
        throw new RuntimeException(...);
    }

    return $selected;
}
```

**Add helper methods**:
```php
/**
 * Satisfy minimum diverse types requirement.
 */
private function satisfyDiverseTypes(int $minDiverseTypes, int $userId, bool $allowSystemFallback,
    Collection &$selected, array &$selectedTypes, array &$usedIds, int &$remainingBytes): void
{
    $types = ['image', 'audio', 'text'];
    $candidates = [];
    foreach ($types as $type) {
        $carrier = $this->findBestCarrier($userId, $type);
        if ($carrier) {
            $candidates[$type] = $carrier;
        }
    }

    uasort($candidates, fn($a, $b) => $b->capacity_bytes <=> $a->capacity_bytes);

    foreach ($candidates as $type => $carrier) {
        if (count($selectedTypes) >= $minDiverseTypes) break;
        $selected->push($carrier);
        $usedIds[] = $carrier->id;
        $selectedTypes[] = $type;
        $remainingBytes -= $carrier->capacity_bytes;
    }

    if (count($selectedTypes) < $minDiverseTypes && $allowSystemFallback) {
        $systemUserId = $this->getSystemUserId();
        foreach ($types as $type) {
            if (in_array($type, $selectedTypes)) continue;
            $carrier = $this->findBestCarrier($systemUserId, $type);
            if ($carrier) {
                $selected->push($carrier);
                $usedIds[] = $carrier->id;
                $selectedTypes[] = $type;
                $remainingBytes -= $carrier->capacity_bytes;
                if (count($selectedTypes) >= $minDiverseTypes) break;
            }
        }
    }

    if (count($selectedTypes) < $minDiverseTypes) {
        $available = empty($selectedTypes) ? 'none' : implode(', ', $selectedTypes);
        throw new \RuntimeException(
            "Mandate requires at least {$minDiverseTypes} different carrier types. " .
            "Only " . count($selectedTypes) . " type(s) available: {$available}. " .
            "Upload carriers of different types (image, audio, text)."
        );
    }
}

/**
 * Satisfy minimum total carriers requirement.
 */
private function satisfyMinimumCarriers(int $minCarriers, int $userId, bool $allowSystemFallback,
    Collection &$selected, array &$usedIds, int &$remainingBytes): void
{
    $currentCount = $selected->count();
    if ($currentCount >= $minCarriers) {
        return;
    }

    $needed = $minCarriers - $currentCount;
    $greedy = $this->queryGreedyCarriers($userId, $remainingBytes, $usedIds, $allowSystemFallback);

    $added = 0;
    foreach ($greedy as $carrier) {
        if ($added >= $needed) break;
        $selected->push($carrier);
        $usedIds[] = $carrier->id;
        $remainingBytes -= $carrier->capacity_bytes;
        $added++;
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
```

**Keep existing methods**:
- `findBestCarrier()` — works as-is
- `queryGreedyCarriers()` — works as-is (already excludes IDs)
- `getSystemUserId()` — works as-is

### 3. Error Messages

**Diverse types failure**:
```
"Mandate requires at least {$minDiverseTypes} different carrier types (image/audio/text). " .
"Only " . count($selectedTypes) . " type(s) available: {$available}. " .
"Upload carriers of different types."
```

**Minimum carriers failure**:
```
"Mandate requires at least {$minCarriers} total carriers. " .
"Only {$totalNow} carrier(s) available. " .
"Upload more carriers."
```

---

## Testing Strategy

### Unit Tests

**File**: `tests/Unit/CarrierPoolSelectorTest.php`

**Update existing tests** (adapt from old `require_*` logic):
- `test_select_with_mandates_requires_image()` → `test_min_diverse_types_1_satisfied_by_any_type()`
- `test_select_with_mandates_requires_image_and_audio()` → `test_min_diverse_types_2_requires_two_types()`
- `test_select_with_mandates_fails_when_missing_required_type()` → `test_diverse_types_fails_when_insufficient_types()`
- `test_select_with_mandates_uses_system_fallback()` → update to test system fallback for diverse types

**New tests to add**:
1. `test_minimum_carriers_1_satisfied_by_single_carrier()`
2. `test_minimum_carriers_2_requires_two_carriers_even_if_one_large_enough()`
3. `test_minimum_carriers_3_requires_three_carriers()`
4. `test_min_diverse_types_2_with_min_carriers_2_combined()`
5. `test_min_diverse_types_2_fails_when_only_one_type_available()`
6. `test_min_carriers_2_fails_when_only_one_carrier_available()`
7. `test_min_diverse_types_2_and_min_carriers_3_both_constraints()`
8. `test_diverse_types_picks_largest_carriers_first()`
9. `test_backward_compatibility_defaults_allow_single_carrier()`

### Feature Tests

**File**: `tests/Feature/Api/CarrierPoolTest.php`

Add E2E scenarios:
- Upload 1 image, encode with `min_carriers=2` → expect failure
- Upload 1 image + 1 WAV, encode with `min_diverse_types=2` → success
- Upload 3 images only, encode with `min_diverse_types=2` → failure (only 1 type)
- Upload 2 images + 1 WAV, encode with `min_diverse_types=2, min_carriers=3` → success

---

## Files to Modify

### Core (3 files)
1. `config/stegolock.php` — config update
2. `app/Services/Stego/CarrierPoolSelector.php` — logic refactor
3. `.env.example` — add new env vars

### Tests (2 files)
4. `tests/Unit/CarrierPoolSelectorTest.php` — update + new tests
5. `tests/Feature/Api/CarrierPoolTest.php` — add E2E scenarios

### Documentation (2 files)
6. `RUNBOOK.md` — update failure modes
7. Create new changelog: `CHANGES_2026-04-24_minimum_carrier_mandates.md`

**Total**: 7 files, ~3-4 hours implementation

---

## Edge Cases

| Scenario | Expected |
|----------|----------|
| `min_diverse_types=0` | Treat as 1 |
| `min_carriers=0` | Treat as 1 |
| `min_diverse_types=4` | Fail (max 3 types) |
| User has 2 images + 1 WAV, `min_diverse_types=2` | Success: 1 image + 1 WAV |
| User has 1 image only, `min_diverse_types=2` | Fail: only 1 type |
| 1 image (1000B), secret=100B, `min_carriers=2` | Fail: need 2 carriers |
| 1 image + 1 WAV (50B each), secret=100B, `min_diverse_types=2` | Success: both used |
| System fallback enabled, user missing type | Use system carrier |
| System fallback disabled, missing type | Fail immediately |

---

## Rollback

- Set `STEGO_MANDATES_ENABLED=false` (disables all mandates)
- Or set both to `1` (minimal constraints)
- `php artisan config:clear`
- No DB migration needed

---

## Success Criteria

✅ Config updated with new parameters  
✅ CarrierPoolSelector implements new logic  
✅ Defaults (1,1) preserve backward compatibility when mandates enabled  
✅ `min_diverse_types=2` forces 2+ different types  
✅ `min_carriers=2` forces 2+ total carriers  
✅ Both constraints combinable  
✅ System fallback works  
✅ Clear error messages  
✅ Tests updated and passing  
✅ Documentation updated  

---

## Risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| Breaking `require_image=true` users | High | Document migration path |
| Constraint ordering bug | Medium | Comprehensive tests |
| Performance regression | Low | Reuse existing O(1) queries |
| System fallback double-count | Medium | Track used IDs carefully |
| `min_diverse_types=3` with only 2 types | High | Fail with clear message |

---

**Plan ready for user approval. Once approved, I'll switch to code mode to implement.**
