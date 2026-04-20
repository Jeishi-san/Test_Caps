# Chapter III Update Checklist - April 17, 2026

## High-Priority Updates Needed in Chapter III (Based on Recent Changes)

I've finished comparing Chapter III against the March 23 to April 17 logs, and there are several concrete mismatches in key management, sharing workflow, status lifecycle, and storage behavior. I'll now give you a prioritized "what to change" checklist with replacement wording suggestions per section so you can update the chapter quickly.

---

## 1. Update key management model from single DEK derivation to dual-mode encryption

### Why
Chapter text currently states DEK is always PBKDF2-derived from Master Key per document.

### Current Implementation Changed to Dual-Mode
- `legacy_derived` (old behavior)
- `envelope_wrapped` (random DEK + per-user wrapped DEK)

### Sources
- CHANGES_2026-04-16_envelope_sharing.md
- CHANGES_2026-04-17_automatic_grant_activation.md

### Sections to Revise
- 3.1.6 Document Key Derivation
- 3.1.7.1 Phase One
- 3.1.8 Decryption

### Required Change
Explain that DEK derivation is mode-dependent:
- Legacy documents: derived DEK from owner MK + salt
- Envelope documents: randomly generated DEK, wrapped per authorized user with that user's MK

---

## 2. Update sharing workflow: no acceptance step, automatic grant activation

### Why
Chapter still frames sharing as grant-table-only and implies no key material changes.

### Current Implementation
- On grant creation, system automatically wraps DEK for viewer and stores wrapped key material in grant record
- Viewer can decode immediately (no separate accept/confirm flow)

### Sources
- CHANGES_2026-04-17_automatic_grant_activation.md
- CHANGES_2026-04-16_envelope_sharing.md

### Section to Revise
- 3.1.8 Document Sharing and Access Control

### Required Change
Replace statements like "no transfer of keys / no key changes" with:
- "Grant creation stores wrapped DEK, IV, and auth tag for each authorized user in grant records."
- Keep "no plaintext key transfer" statement.

---

## 3. Update decode description to include dual-mode resolution

### Why
Chapter currently describes one decryption path.

### Current Implementation Auto-Detects Mode
- Envelope mode: fetch wrapped DEK from grants, unwrap with viewer MK, decrypt
- Legacy mode: derive DEK as before

### Sources
- CHANGES_2026-04-17_automatic_grant_activation.md
- CHANGES_2026-04-16_envelope_sharing.md

### Sections to Revise
- 3.1.8 Extraction and Reassembly / Decryption

### Required Change
Add explicit "mode detection" step before cryptographic decode.

---

## 4. Update authorization narrative: ownership/policy guards now enforced before crypto

### Why
Chapter is broad but now there are explicit ownership checks and policy enforcement for encode/decode.

### Sources
- CHANGES_2026-04-17_automatic_grant_activation.md

### Sections to Revise
- 3.1.2 Layer 2
- 3.1.8 Authorization

### Required Change
State that encode/decode are policy-gated and user-scoped before any crypto or file retrieval.

---

## 5. Update lifecycle/state model in workflows

### Why
Chapter currently emphasizes pending/processing/completed/failed, while recent updates enforce decode only when record is ready and add failure reason exposure.

### Sources
- CHANGES_2026-04-10.md
- CHANGES_2026-04-17_automatic_grant_activation.md

### Sections to Revise
- 3.1.3 async job discussion
- 3.1.8 retrieval preconditions

### Required Change
- Add that decode is blocked unless status is ready
- Mention failed_reason surfaced for failed records
- Note polling/status endpoint behavior

---

## 6. Clarify storage behavior for ciphertext vs stego artifacts

### Why
Recent updates changed ciphertext persistence behavior.

### Most Recent State (April 10)
- Ciphertext retained in DB fallback path; not persisted to Backblaze as document binary in new flow
- Final stego artifacts/carriers still uploaded through storage service

### Sources
- CHANGES_2026-04-10.md

### Sections to Revise
- 3.1.4 Layer 4
- 3.1.5 Layer 5
- 3.1.7.5 Finalization

### Required Change
Distinguish clearly:
- Layer 4 stores ciphertext metadata/payload fields and operational status
- Layer 5 stores stego image artifacts/carriers

### Important Note
April 6 log says cloud ciphertext upload existed, but April 10 supersedes this in latest behavior. Keep chapter aligned to latest release.

---

## 7. Update segmentation/capacity methodology details

### Why
Chapter currently gives generic segmentation; implementation now includes bin-packing and strict capacity alignment with safety buffer.

### Sources
- CHANGES_2026-03-24_bin_packing_segmentation.md
- CHANGES_2026-04-08_capacity_calculation_fix.md

### Sections to Revise
- 3.1.7.3 Segmentation
- 3.1.6 File Input (capacity checks)

### Required Change
- Add greedy bin-packing carrier allocation (largest-first, may use fewer carriers)
- Add exact byte-precision capacity checks with 10% safety buffer
- State cross-layer calculation alignment (frontend/backend/python) to avoid fit mismatch

---

## 8. Update carrier handling narrative to include carrier pool workflow

### Why
Chapter still reads like user provides carriers each time.

### Current Implementation Supports Reusable Validated Carrier Pool
- Users may upload carriers once to a pool
- System selects validated carriers automatically
- Validation states and preflight capacity check

### Sources
- CHANGES_2026-03-23_carrier_pool.md

### Sections to Revise
- 3.1.6 File Input
- 3.1.7.3 Segmentation

### Required Change
- Add that users may upload carriers once to a pool; system selects validated carriers automatically
- Mention validation states and preflight capacity check

---

## 9. Add secure authenticated download flow and audit logging details

### Why
Retrieval now includes dedicated authenticated download controller with access logs and temporary signed URLs.

### Sources
- CHANGES_2026-04-14_zindex_notification_download.md

### Section to Revise
- 3.1.8 Output and Retrieval

### Required Change
- Include policy check before file bytes are served
- Include access log recording and signed temporary URL behavior for cloud mode

---

## 10. Update notification behavior on shared decode

### Why
Now not only share notifications; owner is notified when viewer decodes (deduplicated).

### Sources
- CHANGES_2026-04-17_automatic_grant_activation.md

### Section to Revise
- 3.1.8 (post-decode or collaboration subsection)

### Required Change
Add decode-completion notification to owner, one-time per viewer-document pair.

---

## Medium-Priority Alignment Edits

### 1. Role naming consistency
- Chapter uses Document Owner / Viewer / System Administrator
- Recent RBAC uses owner/admin/user model
- Source: CHANGES_2026-03-21.md
- Update: Add a short mapping sentence so terminology stays consistent with implementation

### 2. Share API wording
- Collaboration share workflow now supports bulk recipient emails and viewer-only default in share flow
- Source: CHANGES_2026-04-13_share_workflow_refactor.md
- Update: If Chapter discusses invite/share UX, reflect bulk invite and viewer-only semantics

---

## Potential Contradictions You Should Resolve in Chapter Text

### 1. Status labels conflict across logs
- One change log uses pending/processing/completed/failed; another enforces ready gating
- Use implementation-neutral wording if uncertain:
  - "decode is allowed only after encode reaches terminal success state (ready/completed in UI mapping)."

### 2. Ciphertext cloud persistence changed within same month
- April 6 and April 10 differ
- Keep final chapter synced to latest approved behavior from April 10 unless your code currently reverted afterward

---

If you want, I can do the next step in one pass: provide a section-by-section replacement draft for 3.1.6, 3.1.7.1-3.1.7.5, and 3.1.8 that is fully aligned to these updates and ready to paste.