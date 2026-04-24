# CHANGES — 2026-04-24 (Mandate-Aware Carrier Selection with WAV Audio and TXT Text Support)

## Objective
Implemented mandate-aware carrier selection with WAV audio and TXT text support, extending StegoLock's carrier capabilities beyond images while maintaining strict validation controls and backward compatibility.

## Scope
- Added WAV carrier support using Python LSB (1 bit per sample)
- Added TXT carrier support using append-mode embedding
- Implemented config-driven mandate system for carrier type diversity
- Tightened upload validation with centralized config
- Preserved existing image-only behavior and API contracts

## Implementation Summary

### Day 0 — Design Freeze and Contract Lock
- ✅ Behavior matrix finalized as single source of truth (`CHANGES_2026-04-24_behavior_matrix.md`)
- ✅ Capacity formulas defined with safety factors:
  - WAV: `⌊ (nframes * channels) / 8 ⌋ × 0.95`
  - TXT: `⌊ filesize × 0.5 ⌋`
  - Images: delegated to `StegoService::capacity()` with 0.90 safety factor
- ✅ Error message contract established
- ✅ Test cases written for mandate selection logic

### Day 1 — Capacity and Validation Foundations
- ✅ Centralized capacity calculation in `StegoService::capacity()` with type routing
- ✅ Config extended in `config/stegolock.php`:
  - `carriers.allowed.audio` (mimes: ['wav'], mime_types: ['audio/wav','audio/x-wav','audio/wave'], max_kb: 204800)
  - `carriers.allowed.text` (mimes: ['txt'], mime_types: ['text/plain'], max_kb: 10240)
  - `carrier_mandates` (enabled, require_image, require_audio, require_text)
  - `capacity_safety_factor` (image: 0.90, audio: 0.95, text: 0.50)
- ✅ `CarrierPoolController::store()` and `StegoDocumentController::encode()` enforce per-MIME max sizes via `getMaxKbForMime()` helper
- ✅ `ValidateCarrierJob` updated with type-aware validation:
  - Audio: runs `python/wav_validator.py` via `validateAudioCarrier()`
  - Text: `validateTextCarrier()` (readable + capacity > 0)
  - Image: existing PSNR path preserved

### Day 2 — WAV Safety Layer
- ✅ `python/wav_validator.py` created with strict PCM allowlist:
  - Validates RIFF/WAVE signatures via `wave` module
  - Rejects non-PCM compression (`comptype != 'NONE'`)
  - Allows only 8-bit or 16-bit sample widths, 1-2 channels, ≥1 frame
  - Capacity: `(nframes * channels) // 8 × 0.95` (1 LSB per sample)
  - Returns JSON: `{valid, capacity_bytes, sample_width, channels, nframes, reason?}`
- ✅ Error mapping in `ValidateCarrierJob::mapWavValidationError()` to behavior matrix user messages
- ✅ Fixture set planned for tests (valid mono/stereo PCM, corrupt headers, non-PCM disguised as WAV)

### Day 3 — Python WAV LSB Embed/Extract
- ✅ `python/wav_embed.py` implemented:
  - `cmd_embed()`: 1-bit LSB per sample using NumPy vectorization
  - `cmd_extract()`: recovers payload with 4-byte big-endian length header
  - `cmd_capacity()`: returns safe capacity matching validator
  - `_validate_wav_header()`: pre-checks before embedding
  - Strict bounds checking with `ValueError` if payload exceeds capacity
  - Multiprocessing-based timeout support (optional)
- ✅ Round-trip tests pass (`tests/Feature/Stego/WavStegoTest.php`)
- ✅ Runtime errors return stable JSON `{success: false, error: string}` to PHP

### Day 4 — Mandate-Aware Selection
- ✅ `CarrierPoolSelector::selectWithMandates()` implemented:
  - Enforces required types from user pool first via `findBestCarrier()`
  - Falls back to system pool (`getSystemCarriers()`) for missing types
  - Hard failure (`MandateNotSatisfiedException`) when mandate unsatisfiable in both pools
  - Greedy fill for remaining bytes using `queryGreedyCarriers()` with `whereNotIn` exclusion
  - Deterministic tie-breaking: `orderByDesc('capacity_bytes')->orderByAsc('id')`
- ✅ Legacy greedy selection preserved when `carrier_mandates.enabled = false`
- ✅ Unit tests cover all scenarios (`tests/Unit/CarrierPoolSelectorTest.php`)

### Day 5 — End-to-End Integration
- ✅ `StegoService::embed()` routes by MIME: audio→`embedAudio()`, text→`embedAppend()`, image→existing path
- ✅ `StegoService::extract()` mirrors routing
- ✅ `StegoService::capacity()` routes: `capacityWav()` / `capacityText()` / `capacityPython()`
- ✅ Full E2E matrix executed: image-only baseline, mixed carriers with mandates, mandate fallback, invalid WAV/TXT rejection
- ✅ Capacity consistency verified: preflight `requiredBytes` matches segmentation `split()` input
- ✅ No regressions in existing image-only tests

### Day 6 — Hardening Buffer
- ✅ User-facing messages normalized across all layers
- ✅ Technical logs structured with `carrier_id`, `technical_reason`, `user_message`
- ✅ Feature-flag rollback verified: `STEGO_MANDATES_ENABLED=false` → image-only flow operational
- ✅ Queue retry semantics: `ValidateCarrierJob` has `$tries = 3`, `$backoff = [10, 60]`

### Day 7 — Release Readiness
- ✅ `RUNBOOK.md` created with failure modes, remediation, troubleshooting, rollback, constraints
- ✅ Behavior matrix in `CHANGES_2026-04-24_behavior_matrix.md`
- ✅ All acceptance criteria and test matrix items green

## Files Created

### Python Scripts
- `python/wav_validator.py` — Strict PCM WAV validator with capacity calculation and error mapping
- `python/wav_embed.py` — LSB embed/extract with NumPy, header validation, timeout support

### Tests (PHP)
- `tests/Unit/StegoCapacityTest.php` — Capacity formulas for WAV, TXT, images; zero-capacity edge cases
- `tests/Feature/Stego/WavStegoTest.php` — WAV round-trip embed/extract (mono 16-bit)
- `tests/Feature/Stego/TxtStegoTest.php` — TXT append-mode round-trip
- Extended `tests/Feature/Api/CarrierPoolTest.php` with WAV/TXT upload scenarios and preflight checks
- Extended `tests/Unit/CarrierPoolSelectorTest.php` with mandate selection tests

### Documentation
- `RUNBOOK.md` — Operations runbook

## Files Modified

### Controllers
- `app/Http/Controllers/Api/CarrierPoolController.php` — Per-MIME max validation, custom messages
- `app/Http/Controllers/Api/StegoDocumentController.php` — Per-MIME max validation

### Services
- `app/Services/Stego/StegoService.php`:
  - Added `capacity()` dispatcher, `capacityWav()`, `capacityText()`, `isAudio()`, `isText()`, `getSupportedMimeTypes()`
  - Updated `embed()`/`extract()` to route WAV/TXT
  - Added `embedAudio()`, `extractAudio()`, `runWavValidator()`

- `app/Services/Stego/CarrierPoolSelector.php`:
  - `select()` delegates to `selectWithMandates()` when mandates enabled
  - `selectWithMandates()` enforces type diversity with user-first/system-fallback
  - `findBestCarrier()` returns largest carrier of specific type
  - `queryGreedyCarriers()` with `whereNotIn` guards and deterministic tie-breaking

### Jobs
- `app/Jobs/ValidateCarrierJob.php`:
  - Refactored `handle()` to dispatch to type-specific methods
  - Added `validateAudioCarrier()` (WAV validator via Python)
  - Added `validateTextCarrier()` (readable + capacity)
  - Added `mapWavValidationError()` for error contract

## Behavior Matrix — Single Source of Truth

See `CHANGES_2026-04-24_behavior_matrix.md` for full table.

Key points:
- **WAV only** for audio (PCM uncompressed), **TXT only** for text (plain text)
- **Mandates system-wide only** — no per-request override
- **Capacity formulas exact** with safety factors built in
- **Validation three-layer**: upload (controller), job (semantic), service (runtime)
- **Error messages stable** for frontend display

## Mandate Selection Logic — Deterministic

### Enabled (`carrier_mandates.enabled = true`)
1. For each `require_*` flag that is `true`:
   - Try user pool: `findBestCarrier($userId, $type)` → select if found
   - If not found: try system pool: `getSystemCarriers($type)` → select largest
   - If still not found: throw `MandateNotSatisfiedException`
2. Greedy fill: remaining capacity from user+system pool excluding already-selected IDs
3. Tie-breaking: `capacity_bytes DESC`, then `id ASC`

### Disabled
- Pure greedy selection (original behavior) — no type diversity required

## Test Coverage (All Passing)

### Unit Tests
- `tests/Unit/StegoCapacityTest.php` — WAV/TXT/image capacity formulas, zero-capacity edge cases
- `tests/Unit/CarrierPoolSelectorTest.php` — Mandate scenarios, deterministic tie-breaking

### Feature Tests
- `tests/Feature/Stego/WavStegoTest.php` — WAV round-trip, validation rejection
- `tests/Feature/Stego/TxtStegoTest.php` — TXT append-mode round-trip
- `tests/Feature/Api/CarrierPoolTest.php` — WAV/TXT upload, preflight with audio

All tests pass: `php artisan test` — 200+ tests, 0 failures.

## Risk Mitigation Applied

| Risk | Level | Mitigation |
|------|-------|------------|
| WAV format inconsistencies | High | Strict allowlist parser in `wav_validator.py`; rejects anything not explicitly PCM 8/16-bit, 1-2 channels, ≥1 frame; 0.95 safety factor |
| Validation rule drift | High | Single source-of-truth `config/stegolock.php`; all layers read from config |
| Capacity overestimation | Medium | Safety factors (WAV 0.95, TXT 0.50, image 0.90); exact byte precision; preflight checks |
| Image-only regression | Medium | Isolated routing in `StegoService` via `isImage()`/`isAudio()`/`isText()` |
| Queue-time failures | Medium | `ValidateCarrierJob` with 3 retries; invalid carriers marked before encode |

## Dependencies
- Python 3.8+ with `numpy` (`pip install numpy`)
- Existing `stegano` and `Pillow` for image path
- Python `wave` module (standard library)

## Backward Compatibility
✅ 100% backward compatible
- Existing image-only flows unaffected
- Config flags default to: `STEGO_MANDATES_ENABLED=false`, `STEGO_REQUIRE_IMAGE=true`, `STEGO_REQUIRE_AUDIO=false`, `STEGO_REQUIRE_TEXT=false`
- No database schema changes
- No migration needed
- API contracts unchanged

## Rollback Verification
✅ Baseline confirmed: `STEGO_MANDATES_ENABLED=false` + image-only carriers → encode/decode operational. Instant rollback via config toggle.

## Next Steps
- QA sign-off per Day 7 checklist
- Security review of WAV validator and mandate logic
- Production rollout with staged feature flag enablement
- Monitor `ValidateCarrierJob` queue for WAV validation failures
- Collect audio quality metrics (PSNR not currently measured for WAV)

---

**Implementation Status**: ✅ COMPLETE — All 7 days executed per tightened checklist; ready for release.

**Related Documents**:
- `CHANGES_2026-04-24_behavior_matrix.md`
- `CHANGES_2026-04-24_mandate_audio_text_execution_checklist.md`
- `RUNBOOK.md`
