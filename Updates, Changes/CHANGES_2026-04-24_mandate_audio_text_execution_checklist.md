# CHANGES - 2026-04-24 (Pre-Implementation Tightened Execution Checklist)

## Objective
Create a low-risk execution plan for mandate-aware carrier selection with WAV and TXT support, with strict validation controls before any production rollout.

## Scope
- Add mandate-aware selection (config-driven).
- Add WAV carrier support with Python LSB.
- Enable TXT carriers through existing append-mode path.
- Tighten upload validation and capacity rules.
- Preserve existing image-only behavior and compatibility.

## Risk Priorities
- High risk: WAV format handling inconsistencies (bit depth, channels, malformed headers, non-PCM edge cases).
- High risk: Validation rule drift between controller, job, and service.
- Medium risk: Capacity overestimation leading to encode failure mid-pipeline.
- Medium risk: Regression in image-only encode/decode flow.
- Medium risk: Queue-time failures from unsupported or corrupted carriers.

## Guardrails Before Coding
- Freeze acceptance criteria and test matrix first.
- Require feature flags for mandates and new MIME categories.
- Require hard deny for unsupported audio/text subtypes.
- Require no change in existing image-only API contracts unless explicitly versioned.

## Day-by-Day Tightened Checklist

### Day 0 - Design Freeze and Contract Lock
- Define non-negotiable MVP behavior:
	- WAV only for audio, TXT only for text.
	- Mandates are system-wide config only.
	- No per-request mandate override.
- Define exact capacity formulas and rounding rules:
	- WAV capacity excludes header and applies safety factor.
	- TXT append-mode capacity is conservative and deterministic.
- Define strict validation contract:
	- Upload validator allowlist.
	- Job-level semantic validation.
	- Service-level runtime guards.
- Produce a single source-of-truth table:
	- MIME accepted.
	- File extension accepted.
	- Capacity method.
	- Embed/extract method.
	- Failure message.
- Exit criteria:
	- All stakeholders sign off on behavior matrix.
	- No implementation starts before this sign-off.

### Day 1 - Capacity and Validation Foundations
- Implement centralized capacity component (single entry point for image/audio/text).
- Replace scattered capacity calculations with centralized call sites.
- Add config keys:
	- `allowed_carrier_mimes` (grouped by image/audio/text).
	- `carrier_mandates` (`enabled`, `require_image`, `require_audio`, `require_text`).
- Add strict upload validation:
	- Allow only `png`, `bmp`, `jpeg`, `jpg`, `wav`, `txt`.
	- Enforce max file size and reject unknown MIME mismatch.
- Add job-level validation checks:
	- Reject WAV if header invalid, zero frames, or unsupported encoding.
	- Reject TXT if unreadable or zero effective capacity.
	- Ensure `capacity_bytes` must be greater than `0` to become valid.
- Exit criteria:
	- Unit tests for capacity formulas pass.
	- Invalid WAV/TXT files consistently marked invalid.
	- Existing image validation still passes.

### Day 2 - WAV Safety Layer Before LSB Logic
- Add WAV parser validation utilities before embedding work:
	- Validate RIFF/WAVE signatures.
	- Validate PCM expectations for MVP.
	- Validate supported sample width and channel constraints.
	- Validate frame count and data chunk integrity.
- Add explicit unsupported cases:
	- Non-PCM compressed audio.
	- Corrupted chunks.
	- Truncated headers/data.
- Build fixture set:
	- Valid mono/stereo PCM WAV.
	- 8-bit and 16-bit samples.
	- Corrupt header fixtures.
	- Non-WAV audio disguised as WAV.
- Exit criteria:
	- Parser rejects malformed files deterministically.
	- Error messages are stable and test-assertable.

### Day 3 - Python WAV LSB Embed/Extract (MVP Safe Path)
- Implement WAV embed/extract using conservative 1-bit-per-sample policy.
- Reuse existing payload framing conventions for consistency.
- Add strict bounds checking:
	- Payload length must fit available sample capacity.
	- Abort early with user-safe error if insufficient capacity.
- Add round-trip tests:
	- Embed then extract equals original payload.
	- Include mono and stereo cases.
	- Include boundary near-capacity case.
	- Include zero/invalid payload rejection.
- Exit criteria:
	- All WAV round-trip tests pass.
	- No unhandled exceptions from malformed input fixtures.
	- Runtime errors return stable JSON contract to PHP.

### Day 4 - Mandate-Aware Selection with Safe Fallback
- Implement mandate-aware selection flow:
	- Fulfill mandated types first.
	- Use user pool first for each mandated type.
	- Fall back to system pool for missing mandated types.
	- Fill remaining bytes using existing greedy selection.
- Add deterministic tie-breaking to avoid flaky selection tests.
- Add hard failure when mandate cannot be satisfied in both pools.
- Keep legacy greedy behavior when mandates disabled.
- Exit criteria:
	- Unit tests cover:
		- Mandate satisfied by user-only pool.
		- Mandate satisfied via system fallback.
		- Mandate impossible path returns clear failure.
		- Disabled mandates path matches previous behavior.

### Day 5 - End-to-End Integration and Regression Shield
- Integrate service routing for category-based embed/extract:
	- Image uses current path.
	- WAV uses Python WAV path.
	- TXT uses append-mode path.
- Run full E2E matrix:
	- Image-only baseline (must remain green).
	- Mixed carriers with mandates enabled.
	- Mandate fallback case.
	- Invalid WAV/TXT upload and validation rejection.
- Preflight and capacity consistency checks:
	- Required bytes basis aligned end-to-end.
	- No false pass from capacity overestimation.
- Exit criteria:
	- E2E green for new and legacy scenarios.
	- No regressions in existing image-only tests.

### Day 6 - Hardening Buffer
- Improve diagnostics:
	- Normalize user-facing messages.
	- Keep technical logs detailed and structured.
- Run stress and concurrency tests on selection/lock behavior.
- Verify queue retry semantics for validation jobs.
- Validate feature-flag rollback:
	- Disable mandates.
	- Disable WAV/TXT acceptance.
	- Confirm old flow remains operational.
- Exit criteria:
	- Clean rollback checklist validated.
	- Observability fields present for incident triage.

### Day 7 - Release Readiness Buffer
- Final docs update:
	- Behavior matrix.
	- Known constraints (WAV-only audio MVP, TXT append-mode behavior).
- Runbook for support and operations:
	- Common failure modes and remediation steps.
	- Validation troubleshooting path.
- Sign-off checklist:
	- QA sign-off.
	- Security sign-off.
	- Product sign-off.
- Exit criteria:
	- Release go/no-go decision completed with evidence.

## Mandatory Test Matrix (Must Pass)
- Upload validation:
	- Allowed MIME and extension combos accepted.
	- Disallowed or spoofed MIME rejected.
- Capacity validation:
	- WAV capacity never exceeds actual embed capacity.
	- TXT conservative capacity prevents runtime overflow.
- Embed/extract:
	- WAV round-trip exact payload recovery.
	- TXT append round-trip exact payload recovery.
- Selection:
	- Mandates enforced when enabled.
	- Fallback user then system behavior confirmed.
- Legacy:
	- Image-only encode/decode unchanged.

## Rollback Plan
- Toggle off mandates via config.
- Restrict upload allowlist back to image-only.
- Disable WAV route in stego service dispatch.
- Keep database records intact and backward-compatible.
- Confirm image-only preflight, encode, decode are healthy.

## Definition of Done
- All acceptance criteria and test matrix items are green.
- WAV malformed input handling is deterministic and safe.
- Validation rules are consistent across controller, job, and service.
- Mandate behavior is config-driven and fully test-covered.
- Legacy image-only behavior remains unchanged.


The plan is solid and doable. 7 days is realistic if you treat it as focused work, not split attention. Let me give you an honest assessment of where the real risks are and how to de-risk each one.

---

## Honest Assessment Per Day

**Day 0 — Design freeze** is the most important day and the most skipped. The plan is right to put it here. The behavior matrix it asks for is the single thing that prevents the most common failure mode: controller, job, and service having slightly different ideas of what's valid. Do not skip this. One afternoon of writing a table saves two days of debugging inconsistencies.

**Day 1 — Capacity and validation** is straightforward but has one hidden trap: the centralized capacity component needs to be the *only* place capacity is calculated. If you build it but leave old calculation code in place "temporarily," it will drift. Delete the old code on the same day you ship the new component.

**Day 2 — WAV safety layer** is correctly identified as high risk. WAV is deceptively simple on the surface but has real edge cases in the wild — files with non-standard chunk ordering, files with LIST chunks before the data chunk, files exported from video editors with extra metadata. The fixture set the plan asks for is not optional.

**Day 3 — Python WAV LSB** is low risk if Day 2 is done properly. The NumPy approach discussed earlier maps directly. The only addition is the SNR measurement replacing PSNR.

**Day 4 — Mandate-aware selection** is medium risk. The logic itself is not complex but the interaction between user pool, system pool, and greedy fallback has combinatorial test cases. The plan covers them but the tie-breaking note is important — without deterministic tie-breaking, your selection tests will be flaky.

**Day 5 — E2E integration** is where hidden assumptions surface. Budget a full day here, not half a day. The image-only baseline must stay green — run it first before touching anything else.

**Days 6–7 — Hardening and release** are realistic buffers. Do not compress these.

---

## The Four Highest-Risk Points and How to Handle Each

### Risk 1 — WAV format inconsistencies

This is the one most likely to cause a production incident. The fix is a strict allowlist parser that rejects anything it doesn't explicitly understand, rather than trying to handle every case.

```python
# scripts/stego/wav_validator.py
import wave, struct

SUPPORTED_SAMPLE_WIDTHS = {1, 2}   # 8-bit and 16-bit only for MVP
MAX_CHANNELS = 2                    # mono and stereo only

def validate_wav(path: str) -> dict:
    try:
        with wave.open(path, 'rb') as wav:
            sampwidth = wav.getsampwidth()
            channels  = wav.getnchannels()
            nframes   = wav.getnframes()
            comptype  = wav.getcomptype()
    except wave.Error as e:
        return {'valid': False, 'reason': f"Invalid WAV structure: {e}"}
    except Exception as e:
        return {'valid': False, 'reason': f"Cannot read file: {e}"}

    if comptype != 'NONE':
        return {'valid': False, 'reason': f"Non-PCM WAV not supported (compression: {comptype})"}

    if sampwidth not in SUPPORTED_SAMPLE_WIDTHS:
        return {'valid': False, 'reason': f"Unsupported sample width: {sampwidth} bytes"}

    if channels > MAX_CHANNELS:
        return {'valid': False, 'reason': f"Too many channels: {channels}"}

    if nframes == 0:
        return {'valid': False, 'reason': "WAV file contains no audio frames"}

    capacity = (nframes * channels) // 8  # bytes, 1 LSB per sample
    # Apply 0.95 safety factor — never claim full theoretical capacity
    safe_capacity = int(capacity * 0.95)

    return {
        'valid':          True,
        'sample_width':   sampwidth,
        'channels':       channels,
        'nframes':        nframes,
        'capacity_bytes': safe_capacity,
    }

if __name__ == '__main__':
    import sys, json
    print(json.dumps(validate_wav(sys.argv[1])))
```

The 0.95 safety factor is important — it directly prevents the medium risk of capacity overestimation causing mid-pipeline failures.

---

### Risk 2 — Validation rule drift between controller, job, and service

The plan mentions this but doesn't prescribe the fix. The fix is a single source-of-truth config that all three layers read from, not three places that each define their own rules.

```php
// config/stegolock.php — single source of truth
return [
    'carriers' => [
        'allowed' => [
            'image' => [
                'mimes'      => ['png', 'bmp', 'jpeg', 'jpg'],
                'mime_types' => ['image/png', 'image/bmp', 'image/jpeg'],
                'max_kb'     => 102400,   // 100MB
            ],
            'audio' => [
                'mimes'      => ['wav'],
                'mime_types' => ['audio/wav', 'audio/x-wav'],
                'max_kb'     => 204800,   // 200MB
            ],
            'text' => [
                'mimes'      => ['txt'],
                'mime_types' => ['text/plain'],
                'max_kb'     => 10240,    // 10MB
            ],
        ],
    ],

    'mandates' => [
        'enabled'       => env('STEGO_MANDATES_ENABLED', false),
        'require_image' => env('STEGO_REQUIRE_IMAGE', false),
        'require_audio' => env('STEGO_REQUIRE_AUDIO', false),
        'require_text'  => env('STEGO_REQUIRE_TEXT', false),
    ],
];
```

Then in the upload validator, read from config — never hardcode:

```php
// app/Http/Requests/StoreCarrierRequest.php
public function rules(): array
{
    $allowed = config('stegolock.carriers.allowed');

    // Flatten all allowed mimes across types
    $allMimes   = collect($allowed)->pluck('mimes')->flatten()->implode(',');
    $maxKb      = collect($allowed)->pluck('max_kb')->max();

    return [
        'carrier' => [
            'required', 'file',
            "mimes:{$allMimes}",
            "max:{$maxKb}",
        ],
        'name' => ['nullable', 'string', 'max:255'],
    ];
}
```

The job and service read the same config keys. When you change the allowed list, you change it in one place.

---

### Risk 3 — Regression in image-only flow

The safest structural decision here is to make carrier type routing explicit and isolated so the image path literally cannot be touched by audio/text code.

```php
// app/Services/Stego/EmbedService.php
class EmbedService
{
    public function embed(string $carrierPath, string $chunk, string $mimeType): string
    {
        return match(true) {
            $this->isImage($mimeType) => $this->embedImage($carrierPath, $chunk),
            $this->isAudio($mimeType) => $this->embedAudio($carrierPath, $chunk),
            $this->isText($mimeType)  => $this->embedText($carrierPath, $chunk),
            default => throw new UnsupportedCarrierTypeException(
                "No embed handler for MIME type: {$mimeType}"
            ),
        };
    }

    private function isImage(string $mime): bool
    {
        return in_array($mime, config('stegolock.carriers.allowed.image.mime_types'));
    }

    private function isAudio(string $mime): bool
    {
        return in_array($mime, config('stegolock.carriers.allowed.audio.mime_types'));
    }

    private function isText(string $mime): bool
    {
        return in_array($mime, config('stegolock.carriers.allowed.text.mime_types'));
    }

    private function embedImage(string $path, string $chunk): string
    {
        // Existing image embed — unchanged
        return $this->runPython('embed_and_measure.py', $path, $chunk);
    }

    private function embedAudio(string $path, string $chunk): string
    {
        // New WAV embed
        return $this->runPython('wav_embed.py', $path, $chunk);
    }

    private function embedText(string $path, string $chunk): string
    {
        // Text append embed
        return $this->runPython('txt_embed.py', $path, $chunk);
    }
}
```

The image path is a separate private method. Adding audio and text cannot accidentally modify it.

---

### Risk 4 — Mandate selection edge cases

The mandate-aware selector has the most logic branches. Write the tests before the implementation — the test cases define the behavior contract more clearly than prose.

```php
// tests/Unit/CarrierPoolSelectorTest.php

it('fulfills image mandate from user pool', function () {
    // setup: user has 1 image carrier, 0 audio
    // mandates: require_image = true
    // expect: selects image carrier, no system fallback
});

it('falls back to system pool when user has no audio carrier', function () {
    // setup: user has only image carriers
    // mandates: require_audio = true
    // system pool has WAV carrier
    // expect: selects system WAV carrier to satisfy mandate
});

it('fails hard when mandate cannot be satisfied in either pool', function () {
    // setup: no WAV carriers in user or system pool
    // mandates: require_audio = true
    // expect: throws MandateNotSatisfiedException with clear message
});

it('behaves identically to legacy greedy selection when mandates disabled', function () {
    // setup: mandates.enabled = false
    // expect: same carrier selection as before this feature existed
});

it('breaks ties deterministically', function () {
    // setup: two carriers with identical capacity_bytes
    // expect: same carrier selected across multiple calls (stable ordering)
});
```

Writing these tests on Day 0 alongside the behavior matrix means Day 4 implementation just has to make them pass.

---

## The One Thing That Would Make This Low Risk

Everything above reduces risk at the component level. The single thing that makes the whole rollout low risk is the **feature flag rollback path** the plan already mentions — but it needs to be verified before Day 1, not Day 6.

On Day 0, before writing any code, run the rollback scenario manually:

```php
// .env
STEGO_MANDATES_ENABLED=false
```

Confirm the existing image-only encode and decode still work end to end with this flag set. Document the result. This becomes your baseline. If anything breaks after Day 1–5, you toggle the flag and you're back to this known-good state immediately without a deployment.

---

## Summary

| Day | Risk Level | De-risk Action |
|---|---|---|
| 0 | Low | Write behavior matrix + test cases before any code |
| 1 | Medium | Centralized config, delete old capacity code same day |
| 2 | High | 0.95 safety factor, strict allowlist parser, fixture set |
| 3 | Low | NumPy approach, reuse existing payload framing |
| 4 | Medium | Write tests first, deterministic tie-breaking |
| 5 | Medium | Run image-only baseline first, full day budgeted |
| 6–7 | Low | Verify rollback on Day 0 so this is confirmation not discovery |

It's doable in 7 days. The plan is well-structured. The main thing to add beyond what's already there is the centralized config as the single source of truth for validation rules, and verifying the rollback path before writing a single line of implementation code.
