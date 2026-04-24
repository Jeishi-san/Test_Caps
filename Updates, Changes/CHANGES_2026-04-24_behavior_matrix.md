# StegoLock Carrier Support Matrix — Single Source of Truth

## Carrier Type Behaviour Contract

| Carrier Type | Image | Audio | Text |
|--------------|-------|-------|------|
| **Supported Extensions** | `.png`, `.bmp`, `.jpg`, `.jpeg` | `.wav` | `.txt` |
| **Allowed MIME Types** | `image/png`, `image/bmp`, `image/x-bmp`, `image/jpeg`, `image/jpg` | `audio/wav`, `audio/x-wav`, `audio/wave` | `text/plain` |
| **Maximum File Size** | 100 MB (configurable) | 200 MB (configurable) | 10 MB (configurable) |
| **Capacity Calculation Method** | Delegated to `StegoService::capacity()` (LSB pixel count) | `(filesize - 44) / 8` × 0.95 safety factor | `filesize × 0.5` conservative (append-mode) |
| **Steganography Method** | LSB (Python or PHP GD driver) | Python LSB on audio samples (1 bit/sample) | Append-mode: `MARKER + length + data` |
| **Validation: PSNR Required?** | Yes — baseline ≥ 40 dB | No (audio quality check not implemented) | No |
| **Validation: Minimum Capacity?** | > 0 bytes | > 0 bytes | > 0 bytes |
| **Validation: Additional Checks** | Image can be loaded by GD/Pillow | WAV header valid, PCM encoding, non-zero frames | File readable, not empty |
| **Unsupported Subtypes** | SVG, GIF (animated), TIFF | MP3, FLAC, OGG, AAC, compressed WAV | Markdown, CSV, HTML (only plain `.txt`) |
| **Embed/Extract Routing** | `StegoService::embedLSBPython()` or `embedLSB()` | `StegoService::embedAudio()` → Python `wav` module | `StegoService::embedAppend()` |
| **Queue Job** | `ValidateCarrierJob` measures PSNR + capacity | `ValidateCarrierJob` measures capacity only | `ValidateCarrierJob` measures capacity only |
| **Error on Upload** | MIME mismatch, size > max, invalid image | Non-PCM WAV, corrupted header, size > max | MIME not `text/plain`, size > max |
| **Failure JSON Code** | `INVALID_IMAGE_FORMAT`, `CORRUPTED_IMAGE` | `UNSUPPORTED_AUDIO_FORMAT`, `INVALID_WAV_HEADER` | `INVALID_TEXT_FORMAT` |
| **Cloud Storage Key Pattern** | `stego/carriers/{userId}/doc{id}_seg{idx}_{rand}.png` | `stego/carriers/{userId}/doc{id}_seg{idx}_{rand}.wav` | `stego/carriers/{userId}/doc{id}_seg{idx}_{rand}.txt` |

---

## Configuration Contract

### Config Keys (all in `config/stegolock.php`)

```php
return [
    // Carrier type allowlists — single source of truth
    'carriers' => [
        'allowed' => [
            'image' => [
                'mimes'      => ['png', 'bmp', 'jpeg', 'jpg'],
                'mime_types' => ['image/png', 'image/bmp', 'image/x-bmp', 'image/jpeg', 'image/jpg'],
                'max_kb'     => 102400,  // 100 MB
            ],
            'audio' => [
                'mimes'      => ['wav'],
                'mime_types' => ['audio/wav', 'audio/x-wav', 'audio/wave'],
                'max_kb'     => 204800,  // 200 MB
            ],
            'text' => [
                'mimes'      => ['txt'],
                'mime_types' => ['text/plain'],
                'max_kb'     => 10240,   // 10 MB
            ],
        ],
    ],

    // Mandate system — system-wide, not per-encode
    'carrier_mandates' => [
        'enabled'       => env('STEGO_MANDATES_ENABLED', false),
        'require_image' => env('STEGO_REQUIRE_IMAGE', true),   // always at least one image
        'require_audio' => env('STEGO_REQUIRE_AUDIO', false),  // optional
        'require_text'  => env('STEGO_REQUIRE_TEXT', false),   // optional
    ],

    // Capacity safety factors
    'capacity_safety_factor' => [
        'image' => 0.90,   // images use 90% of measured LSB capacity
        'audio' => 0.95,   // audio uses 95% of theoretical (1 bit/sample)
        'text'  => 0.50,   // append-mode conservative 50% of file size
    ],

    // Existing stegolock config preserved...
];
```

---

## Mandate Selection Logic Contract

### When `carrier_mandates.enabled = true`

1. **Mandatory Types** (based on `require_*` flags):
   - At least one carrier of each enabled type **must** be selected.
   - Selection order: **largest capacity** carrier of each type from user pool first.
   - If user pool lacks type → **system carriers** used as fallback (still counts toward mandate).
   - If neither user nor system has type → **hard failure** with clear error.

2. **Greedy Fill**:
   - After mandates satisfied, remaining `requiredBytes` filled via greedy algorithm.
   - Greedy pool excludes already-selected mandate carriers (by ID).
   - Ordered by `capacity_bytes DESC`, picks until `accumulated >= remaining`.

3. **Tie-breaking** (deterministic):
   - Equal capacity: sort by `id ASC` then pick first.
   - Guarantees reproducible selection for same pool state.

### When `carrier_mandates.enabled = false`

- Pure greedy selection (current behaviour) — no type diversity required.

---

## Capacity Formula Specification

### Image (PNG/BMP/JPEG)
**Formula**: Delegated to `StegoService::capacity($filePath)`
- Python driver: `stego_lsb.py capacity` → × 0.9 safety
- PHP driver: `(pixels × 3 bits) / 8 - 4` (4-byte length prefix)

**Source of Truth**: `StegoService` (no alternative calculation anywhere).

### Audio (WAV only)
**Formula**: `⌊ (filesize - 44) / 8 ⌋ × 0.95`
- 44-byte WAV header excluded
- 1 bit of secret data per byte of audio sample data
- 0.95 safety factor applied after division
- Rounded down to integer

**Rationale**: Sample data may include non-uniform bit distribution; 5% buffer prevents overrun.

### Text (TXT only — append mode)
**Formula**: `⌊ filesize × 0.5 ⌋`
- Append-mode: payload written at EOF after `MARKER + length`
- Conservative 50% of total file size to avoid filesystem edge cases
- Actual capacity likely much larger but this is deterministic

---

## Validation Gate Contract

### Upload-time (CarrierPoolController)
**Rule Source**: `config('stegolock.carriers.allowed')`
1. Extension in allowed mimes list
2. Size ≤ max_kb
3. MIME type detected matches allowed type group (optional but recommended)

### Job-time (ValidateCarrierJob)
**Rule Source**: Same config + capacity service
1. Call `CapacityService::calculate($path, $mimeType)`
2. If capacity ≤ 0 → `validation_status = 'invalid'`, `validation_error` set
3. If image: measure baseline PSNR; if < 40.0 → invalid
4. If audio/text: PSNR skipped, capacity > 0 sufficient
5. Save `capacity_bytes` and `validation_status = 'valid'`

### Encode-time (StegoDocumentService)
**Rule Source**: `CarrierPoolSelector` (queries valid, unused carriers)
1. Carriers must have `validation_status = 'valid'`
2. `capacity_bytes` must not be null
3. `is_in_use = false`
4. All selected carriers locked via `lockForUpdate()` in transaction

---

## Error Message Contract

User-facing errors must be stable for frontend to display:

| Error Condition | User Message | Technical Log |
|----------------|--------------|---------------|
| Mandate type missing from both pools | "Cannot encode: no {type} carrier available. Upload a {extension} file or contact support." | `{mandate_key} not satisfied after user+system pool scan` |
| WAV header invalid | "Unsupported audio format: WAV file is malformed." | `WAVRIFF/ERROR: {details}` |
| WAV non-PCM | "Compressed WAV not supported. Use PCM uncompressed WAV." | `comptype={actual}` |
| WAV zero frames | "Audio file contains no audio data." | `nframes=0` |
| TXT unreadable | "Text carrier could not be read." | `fopen/feof failure` |
| MIME mismatch on upload | "File type not allowed." | `MIME {mime} not in allowed list` |
| Capacity overestimation detected | "Carrier capacity insufficient. Try a larger carrier." | `Segment capacity exceeded during split` |

---

## Implementation Start Checklist

- [ ] Behavior matrix table above completed and reviewed
- [ ] Config keys defined in `config/stegolock.php` (Day 1 task)
- [ ] `CapacityService` interface designed (Day 1 task)
- [ ] `ValidateCarrierJob` modification plan clear (Day 1 task)
- [ ] `CarrierPoolController` mime list expansion clear (Day 1 task)
- [ ] Python LAV LSB algorithm sketch ready (Day 3 task)
- [ ] Mandate selector test cases written (Day 0/4 task)

**Rollback baseline verified**: `STEGO_MANDATES_ENABLED=false` → existing image-only encode/decode passes (to be tested before Day 1 code).

---

## Next: Proceed to Day 1 Implementation

I will now implement:
1. `config/stegolock.php` extensions
2. `app/Services/CapacityService.php`
3. Update `ValidateCarrierJob` to use CapacityService
4. Update `CarrierPoolController` to allow WAV/TXT

**Before coding**: Do you want me to also write the Day 0 test cases first (unit tests for capacity formulas and mandate selection) or implement code first then tests? The checklist says "write tests before implementation" for mandates (Day 4), but for capacity it's fine to code then test.
