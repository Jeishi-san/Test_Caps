# CHANGES — 2026-04-13 (Revised Document Share Workflow)

## Summary
Refactored the document sharing system to implement a streamlined share and invite workflow, fixing bugs, adding clipboard compatibility, and standardizing user notification handling.

## Commit Reference
> **Commit Message:**
> ```
> feat(sharing): implement revised document share workflow
>
> - Allow all authenticated users to access user list for sharing
> - Add bulk recipient notification support on share creation
> - Use default viewer-only permissions for all public shares
> - Replace preloaded user list with live search in share modal
> - Add fallback clipboard copy compatibility for old browsers
> - Add separate invite user action in share dialog
> - Remove unused development check utility scripts
>
> BREAKING CHANGE: Permission level selection removed from share API,
> share endpoint now accepts recipient_emails array instead of
> individual user_id parameters. Backwards compatibility maintained
> for older clients using legacy single email field.
> ```

---

## Changes Made

### 1. Share Modal UX Refactor
**File:** [`resources/js/Components/ShareModal.tsx`](resources/js/Components/ShareModal.tsx)

| Change | Details |
|---|---|
| ✅ **Button text updated** | Changed from "Create Share Link" → **"Copy Link"** |
| ✅ **Added separate Invite Users button** | Beside Copy Link button, directly uses existing share endpoint for notifications |
| ✅ **Removed nested invite modal** | Invite action runs inline without extra popup |
| ✅ **Clipboard copy fallback implementation** | Added `document.execCommand('copy')` fallback for non-HTTPS contexts and older browsers that don't support `navigator.clipboard` |
| ✅ **Success messaging** | Clear user feedback explaining that invited users receive an email with download link |
| ✅ **Improved error handling** | User-friendly alerts on copy / invite failures |

### 2. API & Backend Changes
**File:** [`app/Http/Controllers/ShareDocumentController.php`](app/Http/Controllers/ShareDocumentController.php)

| Change | Details |
|---|---|
| ✅ **Bulk recipient support** | Endpoint now accepts `recipient_emails[]` array for multiple users |
| ✅ **Viewer-only permissions default** | All shared grants are created with viewer permissions (no edit access) |
| ✅ **Live user search access** | All authenticated users can search the user list for sharing (removed admin/owner only restriction) |
| ✅ **Notification queueing** | Bulk email notifications are queued rather than sent inline |
| ✅ **Backwards compatibility** | Legacy single `email` parameter still works for old clients |

### 3. Removed Files
- `check_upload_limits.php` - unused development utility
- Legacy debug test scripts
- Old route check utility

---

## Behaviour Changes

| Before | After |
|---|---|
| Share created with permission level selection | All shares are now viewer-only by default |
| Single user invite only | Bulk invite multiple users in one action |
| Clipboard copy only worked on HTTPS | Clipboard copy works on all contexts including local development |
| Share modal required closing and reopening to invite users | Invite action is directly available on the main share dialog |
| User search restricted to admin/owner | All authenticated users can search for other users to share with |

---

## Verification

✅ **All existing share tests pass**
```
PASS  Tests\Feature\ShareDocumentTest.php
  ✓ can create share link
  ✓ can share document with other user
  ✓ can view shared document
  ✓ cannot edit shared document
  ✓ bulk recipient notifications queued
  5 tests passed
```

✅ **Backwards compatibility verified**
- Legacy API clients using single email parameter continue to work
- Existing share links remain valid
- Existing share grants are unaffected

✅ **Clipboard copy tested across contexts**
- ✅ HTTPS production context (`navigator.clipboard`)
- ✅ HTTP local development context (`execCommand` fallback)
- ✅ Private browsing mode
- ✅ Older browsers (Chrome 80+, Firefox 75+)

---

## Notes

This implementation follows the revised document sharing workflow specification. No database schema changes were required. All existing data remains fully compatible.

The invite button directly uses the existing `/api/collaboration/shares` endpoint which already had notification sending functionality implemented, eliminating the need for a separate invite endpoint.
