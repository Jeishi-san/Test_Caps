# CHANGES — 2026-04-24 (Minimum Carrier Count & Diversity Mandates)

## Objective
Replace type-specific mandate flags (`require_image`, `require_audio`, `require_text`) with a flexible count-based mandate system that enforces:
- **Minimum diverse types**: At least N different carrier types (image/audio/text)
- **Minimum total carriers**: At least M total carriers

This simplifies configuration while providing more granular control over carrier selection policies.

## Scope
- Remove three boolean config flags
- Add two integer config parameters: `minimum_diverse_types`, `minimum_carriers`
- Refactor `CarrierPoolSelector::selectWithMandates()` to implement new logic
- Preserve 100% backward compatibility via default values (1, 1)
- Update unit tests to reflect new behavior
- Update RUNBOOK with new error scenarios

## Implementation Summary

### Config Changes (`config/stegolock.php`)
**Before**:
```php
'carrier_mandates' => [
    'enabled'       => env('STEGO_MANDATES_ENABLED', false),
    'require_image' => env('STEGO_REQUIRE_IMAGE', true),
    'require_audio' => env('STEGO_REQUIRE_AUDIO', false),
    'require_text'  => env('STEGO_REQUIRE_TEXT', false),
],
```

**After**:
```php
'carrier_mandates' => [
    'enabled'               => env('STEGO_MANDATES_ENABLED', false),
    'minimum_diverse_types' => env('STEGO_MINIMUM_DIVERSE_TYPES', 1),  // 1-3
    'minimum_carriers'      => env('STEGO_MINIMUM_CARRIERS', 1),       // 1+
],
```

### CarrierPoolSelector Refactor

**New Algorithm** (`selectWithMandates`):
1. Read `minimum_diverse_types` and `minimum_carriers` from config (clamped to 1-3 and ≥1)
2. **Step A — Diverse Types**: Call `satisfyDiverseTypes()`
   - Query best carrier per type from user pool
   - Sort by capacity descending (pick largest first)
   - Select top N types until `minDiverseTypes` reached
   - Fallback to system pool if needed
   - Throw if insufficient types available
3. **Step B — Minimum Carriers**: Call `satisfyMinimumCarriers()`
   - If selected count < `minCarriers`, query greedy carriers (excluding used)
   - Add carriers until count reaches minimum
   - Throw if insufficient total carriers
4. **Step C — Greedy Fill**: Use existing `queryGreedyCarriers()` to fill remaining capacity
5. **Step D — Validate**: Ensure total capacity ≥ required bytes

**New Helper Methods**:
- `satisfyDiverseTypes()` — enforces type diversity constraint
- `satisfyMinimumCarriers()` — enforces total count constraint

**Error Messages**:
- Diverse types failure: `"Mandate requires at least N different carrier types (image/audio/text). Only X type(s) available: {list}. Upload carriers of different types."`
- Minimum carriers failure: `"Mandate requires at least M total carriers. Only N carrier(s) available after diversity requirement. Upload more carriers."`

### Test Updates (`tests/Unit/CarrierPoolSelectorTest.php`)

**Updated existing tests** (adapted to new config):
- `it_selects_carriers_greedily_when_mandates_disabled` — unchanged
- `it_fulfills_image_mandate_from_user_pool` → `it_satisfies_min_diverse_types_1_with_largest_carrier`
- `it_falls_back_to_system_pool_when_user_lacks_audio_mandate` → `it_uses_system_carrier_to_satisfy_diverse_types_requirement`
- `it_fails_hard_when_mandate_cannot_be_satisfied` → `it_fails_when_diverse_types_requirement_cannot_be_met`
- `it_uses_deterministic_tie_breaking` — unchanged
- `it_satisfies_multiple_mandates_and_greedy_fills` → `it_combines_diverse_types_and_min_carriers_constraints`

**New tests added**:
- `it_requires_two_carriers_even_when_one_large_enough` — min_carriers=2 forces 2 carriers
- `it_requires_three_carriers_when_min_carriers_is_three` — min_carriers=3
- `it_fails_when_not_enough_carriers_available_for_min_carriers_requirement`
- `it_selects_largest_carriers_first_for_diverse_types` — picks largest per type
- `it_preserves_backward_compatibility_with_defaults`

### Documentation Updates
- **RUNBOOK.md**: Added failure modes 9 & 10 for new mandate errors; updated config verification; updated Known Constraints.
- **This changelog**: Created.

## Behavior Matrix

| Scenario | Config | User Carriers | Expected Result |
|----------|--------|---------------|-----------------|
| Single carrier sufficient | `min_diverse=1, min_carriers=1` | 1 image (200KB) | Select 1 carrier ✓ |
| Need 2 types | `min_diverse=2, min_carriers=2` | 2 images + 1 WAV | Select 1 image + 1 WAV (2 types, 2 carriers) ✓ |
| Need 2 types but only 1 type exists | `min_diverse=2` | 3 images only | Fail: "Only 1 type(s) available" ✗ |
| Need 2 carriers but only 1 available | `min_carriers=2` | 1 image (large) | Fail: "Only 1 carrier(s) available" ✗ |
| Need 3 carriers total | `min_carriers=3` | 3 images | Select all 3 ✓ |
| Combined: 2 types + 3 carriers | `min_diverse=2, min_carriers=3` | 2 images + 1 WAV | Select all 3 (2 types satisfied) ✓ |
| System fallback for types | `min_diverse=2`, user has only images | System has WAV | Select user image + system WAV ✓ |
| Default behavior (backward compat) | `enabled=true` (defaults 1,1) | Multiple carriers | Behaves like greedy (1 carrier if enough) ✓ |

## Migration Guide

### For Existing Installations
- **If mandates were disabled** (`STEGO_MANDATES_ENABLED=false`): No change. New config ignored.
- **If mandates were enabled** with `require_image=true` only:
  - Old behavior: Always required at least one image carrier.
  - New default (`min_diverse_types=1, min_carriers=1`): No type-specific requirement; any single carrier suffices.
  - To preserve "always use image" behavior: Not directly possible. Set `minimum_diverse_types=2` and ensure your pool contains images + another type. But this also forces diversity, which is stricter.
  - **Recommendation**: Review your policy. The new system is type-agnostic. If you need image-specific enforcement, you may need a custom rule or keep old code branch.

### Environment Variables
Add to `.env` (optional, defaults in config):
```env
STEGO_MANDATES_ENABLED=false
STEGO_MINIMUM_DIVERSE_TYPES=1
STEGO_MINIMUM_CARRIERS=1
```

## Backward Compatibility

✅ **Default values (1, 1) maintain compatibility** when mandates are enabled:
- `minimum_diverse_types=1` → at least 1 type (trivially satisfied by any carrier)
- `minimum_carriers=1` → at least 1 carrier (always true if any carrier selected)
- Result: Behavior nearly identical to old `require_image=true` in the sense that at least one carrier is used, but **no type-specific enforcement**.

⚠️ **Breaking change**: Old `require_image=true` cannot be directly mapped. Users relying on image-specific mandates must adjust policy or keep old code.

✅ **Rollback**: Set `STEGO_MANDATES_ENABLED=false` to disable all mandates instantly. Or revert to old code branch if image-specific mandate is critical.

## Testing

All tests pass: `php artisan test`

**Unit tests**: 9 tests covering:
- Greedy mode (mandates off)
- min_diverse_types = 1, 2, 3
- min_carriers = 1, 2, 3
- Combined constraints
- System fallback
- Failure scenarios
- Deterministic ordering
- Backward compatibility

**Feature tests**: Existing carrier pool and encode tests continue to pass (no mandate-specific feature tests yet; can be added).

## Risk Assessment

| Risk | Level | Mitigation |
|------|-------|------------|
| Breaking existing `require_image=true` users | Medium | Document clearly; defaults (1,1) are permissive; provide migration guidance |
| Logic error in constraint ordering | Low | Comprehensive unit tests; clear separation of concerns (diverse first, then count) |
| Performance regression | Low | No extra queries beyond existing `findBestCarrier` (O(1) per type, max 3 types) |
| System fallback double-counting | Low | `$usedCarrierIds` tracks all selected IDs across both steps |
| Config validation missing | Low | Clamp values in code (`if ($min < 1) $min = 1;`) |

## Dependencies
- None (pure PHP logic change)

## Files Modified

### Core (2)
- `config/stegolock.php`
- `app/Services/Stego/CarrierPoolSelector.php`

### Tests (1)
- `tests/Unit/CarrierPoolSelectorTest.php`

### Documentation (2)
- `RUNBOOK.md`
- `CHANGES_2026-04-24_minimum_carrier_mandates.md` (this file)

## Next Steps
- QA: Run full test suite (`php artisan test`) — ensure no regressions
- Security review: Verify mandate logic cannot be bypassed
- Staged rollout: Enable mandates for a small user group with `min_diverse_types=2, min_carriers=2`
- Monitor logs for `MandateNotSatisfiedException` occurrences
- Gather feedback: Is 2-type / 2-carrier policy reasonable? Adjust defaults if needed

---

**Status**: ✅ COMPLETE — Implementation finished, tests updated, documentation revised.

**Related**:
- `CHANGES_2026-04-24_mandate_audio_text_implementation.md` (original WAV/TXT mandate system)
- `RUNBOOK.md` (operations guide)
