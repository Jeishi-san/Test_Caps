# StegoLock Operations Runbook

## Common Failure Modes and Remediation

### 1. Carrier Upload Rejected — "File type not allowed."
**Cause:** Uploaded file extension or MIME type is not in the allowed list.
**Check:**
- Allowed image types: PNG, BMP, JPEG, JPG
- Allowed audio types: WAV (PCM only)
- Allowed text types: TXT (plain text)
**Remediation:** Upload a carrier with one of the supported formats.

### 2. Carrier Upload Rejected — "File size exceeds maximum allowed for this file type."
**Cause:** File size exceeds the per-type maximum.
**Limits:**
- Images: 100 MB
- Audio (WAV): 200 MB
- Text (TXT): 10 MB
**Remediation:** Use a smaller carrier file or compress the carrier if possible.

### 3. Carrier Validation Fails — "Unsupported audio format: WAV file is malformed."
**Cause:** WAV file has invalid header or corrupted structure.
**Remediation:** Re-export the WAV as PCM WAV using a trusted audio editor (e.g., Audacity) or generate a fresh recording.

### 4. Carrier Validation Fails — "Compressed WAV not supported. Use PCM uncompressed WAV."
**Cause:** WAV uses compressed encoding (e.g., ADPCM, μ-law, MP3-in-WAV).
**Remediation:** Convert to PCM uncompressed WAV. In Audacity: Export → WAV (Microsoft) → PCM 16-bit.

### 5. Carrier Validation Fails — "Audio file contains no audio data."
**Cause:** WAV file has zero audio frames (empty).
**Remediation:** Use a WAV file with actual audio content.

### 6. Carrier Validation Fails — "Text carrier could not be read."
**Cause:** TXT file is not readable by the system (permissions or I/O error).
**Remediation:** Ensure the file is readable and not locked. Re-upload if necessary.

### 7. Carrier Validation Fails — "Text carrier is empty"
**Cause:** TXT file has zero bytes.
**Remediation:** Use a non-empty text file.

### 8. Encode Fails — "Carrier capacity insufficient. Try a larger carrier."
**Cause:** The selected carriers cannot hold the encrypted payload.
**Remediation:** Upload carriers with larger capacity or add more carriers to the pool.

### 9. Encode Fails — "Cannot encode: no {type} carrier available. Upload a {extension} file or contact support."
**Cause:** Mandate is enabled but no carrier of required type exists in user or system pool.
**Remediation:** Upload a carrier of the missing type (image/audio/text) or contact support to add system carriers.

### 10. Validation Job Stuck / Timeout
**Cause:** Python subprocess timed out (large carrier or slow system).
**Check:** `config('stegolock.python_timeout')` default 540 seconds.
**Remediation:** Increase timeout or optimize carrier size.

## Validation Troubleshooting Path

1. **Check carrier status** via `GET /api/stego/carriers`:
   - `validation_status`: `valid`, `invalid`, `pending`
   - `validation_error`: human-readable reason if invalid

2. **Re-run validation** if stuck in `pending`:
   - Dispatch `ValidateCarrierJob` manually for the carrier ID or wait for queue worker.

3. **Inspect logs**:
   - `ValidateCarrierJob` logs detailed technical reasons under `ValidateCarrierJob: WAV validation failed` or similar.
   - Look for `carrier_id` and `technical_reason` fields.

4. **Verify config**:
   - `config/stegolock.php` — ensure allowed MIME lists and max sizes are correct.
   - Environment variables: `STEGO_MANDATES_ENABLED`, `STEGO_REQUIRE_AUDIO`, etc.

5. **Test locally**:
   - Run `python/wav_validator.py <path>` to check WAV validity.
   - Run `python/wav_embed.py capacity <path>` to see capacity.

## Feature Flag Rollback

To revert to legacy image-only behavior:

1. Set in `.env`:
   ```env
   STEGO_MANDATES_ENABLED=false
   ```
2. Optionally restrict `config/stegolock.carriers.allowed` to only image types (png, bmp, jpeg, jpg).
3. Clear config cache: `php artisan config:clear`.
4. Verify image-only encode/decode works.

## Known Constraints

- **Audio MVP:** Only PCM WAV (8-bit or 16-bit, mono or stereo) is supported. Compressed WAV will be rejected.
- **Text MVP:** Append-mode steganography is used; payload is stored at EOF after a marker. This is not secure against targeted tampering but provides simple embedding.
- **Capacity Safety:** All capacities include safety buffers (90% for images, 95% for audio, 50% for text) to prevent overestimation.
- **Mandates:** System-wide only; cannot be overridden per request.

## Support Contacts

- Engineering: #stegolock-support
- Security: security@example.com
