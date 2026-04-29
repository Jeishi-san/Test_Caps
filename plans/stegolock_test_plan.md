# StegoLock Process Flow Test Plan

## 1. Test Overview
### 1.1 Scope
This test plan covers validation of the core StegoLock process flows including:
- Carrier management (upload, validation, pool management, preflight checks)
- Document encode workflow (encryption, segmentation, steganographic embedding, cloud storage)
- Document decode workflow (carrier retrieval, extraction, reassembly, decryption, integrity verification)
- Cross-user sharing (grant access, accept/revoke grants, viewer decoding)
- Error handling and edge cases (invalid inputs, tampered files, timeouts, capacity limits)

### 1.2 Objectives
1. Verify all StegoLock process flows work as expected end-to-end
2. Identify regressions in existing functionality
3. Validate edge cases and error handling robustness
4. Confirm security controls (encryption, access control) are enforced
5. Measure performance and reliability of steganography operations
6. Document issues and provide actionable improvement recommendations

### 1.3 Deliverables
- Test Plan (this document)
- Test Report with execution results, issues, and recommendations

## 2. Test Environment Setup
### 2.1 Required Tools
| Tool | Purpose | Version |
|------|---------|---------|
| PHP 8.2+ | Laravel application runtime | 8.2+ |
| Composer | PHP dependency management | 2.6+ |
| Node.js | Frontend asset compilation | 18+ |
| Python 3.10+ | Steganography Python scripts | 3.10+ |
| pip | Python package manager | 23+ |
| PHPUnit | PHP unit/feature testing | 10.5+ |
| Laravel Sail/Docker | Local development environment (optional) | latest |
| Backblaze B2 CLI | Cloud storage validation (optional) | latest |

### 2.2 Required Python Dependencies
```bash
pip install stegano Pillow numpy opencv-python
```

### 2.3 Environment Configuration
1. Copy `.env.example` to `.env` and configure:
   ```env
   STEGO_DRIVER=python
   PYTHON_PATH=python
   STEGOLOCK_STORAGE_DISK=local  # Use 'b2' for cloud storage tests
   STEGO_MANDATES_ENABLED=false
   ```
2. Run migrations: `php artisan migrate --seed`
3. Install PHP dependencies: `composer install`
4. Generate application key: `php artisan key:generate`

### 2.4 Test Data Preparation
Create the following test fixtures in `tests/fixtures/`:
- `carriers/` directory containing:
  - `test_100x100.png` (100x100 PNG image)
  - `test_800x600.png` (800x600 PNG image)
  - `test_100x100.bmp` (100x100 BMP image)
  - `test_100x100.jpg` (100x100 JPEG image)
  - `test_10s.wav` (10-second PCM WAV file, 16-bit, mono)
  - `test_60s.wav` (60-second PCM WAV file, 16-bit, stereo)
  - `test.txt` (1KB text file)
- `documents/` directory containing:
  - `short_text.txt` (100-byte plaintext document)
  - `medium_text.txt` (10KB plaintext document)
  - `large_binary.pdf` (1MB binary document)
- `stego_outputs/` directory (empty, for test artifacts)

## 3. Test Coverage Matrix
| Process Flow | Unit Tests | Feature Tests | Integration Tests | Priority |
|--------------|------------|----------------|-------------------|----------|
| Carrier Upload & Validation | ✓ | ✓ | | High |
| Carrier Pool Management | ✓ | ✓ | | High |
| Preflight Capacity Check | ✓ | ✓ | | High |
| Document Encode (Single Carrier) | ✓ | ✓ | ✓ | Critical |
| Document Encode (Multi-Carrier) | ✓ | ✓ | ✓ | Critical |
| Document Decode (Owner) | ✓ | ✓ | ✓ | Critical |
| Document Decode (Viewer) | ✓ | ✓ | ✓ | High |
| Envelope Key Sharing | ✓ | ✓ | | High |
| Legacy Derived Key Decode | ✓ | ✓ | | Medium |
| Steganography (LSB Image) | ✓ | | ✓ | Critical |
| Steganography (WAV Audio) | ✓ | | ✓ | High |
| Steganography (TXT Append) | ✓ | | ✓ | Medium |
| Error Handling & Edge Cases | ✓ | ✓ | | High |
| Performance & Timeout Handling | | ✓ | | Medium |
| Access Control & Security | ✓ | ✓ | | Critical |

## 4. Detailed Test Cases

### 4.1 Carrier Management Flow
#### TC-CM-001: Upload Valid PNG Carrier
- **Preconditions**: Authenticated user with valid session
- **Steps**:
  1. Send POST request to `/api/stego/carriers` with valid PNG file
  2. Verify queue has `ValidateCarrierJob` pushed
  3. Run queue worker to process validation
  4. Check database for new `stego_carriers` record
- **Expected Outcome**: 201 response, carrier record created with `status=valid`, PSNR ≥ 40dB
- **Priority**: High

#### TC-CM-002: Upload Invalid Carrier (Non-Image)
- **Preconditions**: Authenticated user
- **Steps**:
  1. Send POST request with PDF file to `/api/stego/carriers`
- **Expected Outcome**: 422 response with validation error
- **Priority**: High

#### TC-CM-003: Delete In-Use Carrier
- **Preconditions**: Carrier linked to active StegoDocument
- **Steps**:
  1. Send DELETE request to `/api/stego/carriers/{id}` for in-use carrier
- **Expected Outcome**: 409 response with "Carrier is currently in use" message
- **Priority**: High

#### TC-CM-004: Preflight Check With Sufficient Capacity
- **Preconditions**: User has 3 valid carriers with total capacity > document size
- **Steps**:
  1. Send POST request to `/api/stego/preflight` with `document_id`
- **Expected Outcome**: 200 response with selected carrier IDs and capacity details
- **Priority**: High

### 4.2 Document Encode Flow
#### TC-DE-001: Encode Short Document With Single PNG Carrier (Python Driver)
- **Preconditions**: Valid master key in session, PNG carrier with sufficient capacity
- **Steps**:
  1. Create document record
  2. Send POST request to `/api/stego/encode` with `document_id`
  3. Wait for `EncodeStegoDocumentJob` to complete
  4. Verify `stego_documents` record has `status=ready`
- **Expected Outcome**: 202 response, stego document created, carrier file modified with embedded data
- **Priority**: Critical

#### TC-DE-002: Encode Large Document With Multi-Carrier Bin-Packing
- **Preconditions**: Document size exceeds single carrier capacity, multiple carriers in pool
- **Steps**:
  1. Send encode request for large document
  2. Verify multiple carriers are selected via bin-packing
  3. Verify all segments are created in `stego_segments` table
- **Expected Outcome**: Stego document with multiple segments, all carriers marked as `is_in_use=true`
- **Priority**: Critical

#### TC-DE-003: Encode With WAV Audio Carrier
- **Preconditions**: Valid WAV carrier, Python dependencies installed
- **Steps**:
  1. Select WAV carrier for encoding
  2. Execute encode workflow
  3. Verify WAV file has embedded data via `StegoService::extract()`
- **Expected Outcome**: Successful encode, extracted data matches original payload
- **Priority**: High

#### TC-DE-004: Encode With Insufficient Capacity
- **Preconditions**: Document size exceeds total carrier pool capacity
- **Steps**:
  1. Send encode request for oversized document
- **Expected Outcome**: 422 response with capacity exceeded error
- **Priority**: High

### 4.3 Document Decode Flow
#### TC-DD-001: Decode Document As Owner (Envelope Mode)
- **Preconditions**: Ready stego document in envelope mode, valid owner master key
- **Steps**:
  1. Send POST request to `/api/stego/decode` with `stego_document_id`
  2. Wait for `DecodeStegoDocumentJob` to complete
  3. Verify decrypted output matches original document
- **Expected Outcome**: 202 response, decoded file matches original plaintext
- **Priority**: Critical

#### TC-DD-002: Decode Document As Viewer With Active Grant
- **Preconditions**: Active viewer grant, viewer master key
- **Steps**:
  1. Accept grant as viewer
  2. Send decode request as viewer
  3. Verify decoded output matches original
- **Expected Outcome**: Successful decode, viewer can access document content
- **Priority**: High

#### TC-DD-003: Decode With Tampered Stego Carrier
- **Preconditions**: Stego document with tampered carrier file (modified LSB bits)
- **Steps**:
  1. Modify stego carrier pixels programmatically
  2. Attempt decode
- **Expected Outcome**: GCM auth tag mismatch error, decode fails
- **Priority**: High

#### TC-DD-004: Decode With Wrong Master Key
- **Preconditions**: Stego document, invalid master key
- **Steps**:
  1. Send decode request with incorrect master key
- **Expected Outcome**: 401 response with authentication error
- **Priority**: Critical

### 4.4 Sharing Flow
#### TC-SH-001: Grant Viewer Access (Pending Status)
- **Preconditions**: Owner of stego document, valid viewer user ID
- **Steps**:
  1. Send POST request to `/api/stego/documents/{id}/grant`
- **Expected Outcome**: 201 response, grant created with `grant_status=pending`
- **Priority**: High

#### TC-SH-002: Accept Grant As Viewer
- **Preconditions**: Pending grant exists for viewer
- **Steps**:
  1. Send POST request to `/api/stego/documents/{id}/grant/{viewerId}/accept`
  2. Verify wrapped DEK is stored in grant record
- **Expected Outcome**: Grant status updated to `active`, viewer can decode
- **Priority**: High

#### TC-SH-003: Revoke Grant As Owner
- **Preconditions**: Active grant exists
- **Steps**:
  1. Send DELETE request to `/api/stego/documents/{id}/grant/{viewerId}`
- **Expected Outcome**: 200 response, grant record deleted, viewer can no longer decode
- **Priority**: High

### 4.5 Error Handling & Edge Cases
#### TC-EH-001: Embed Payload Exceeding Carrier Capacity
- **Preconditions**: Small carrier, payload larger than capacity
- **Steps**:
  1. Call `StegoService::embed()` with oversized payload
- **Expected Outcome**: Exception thrown with "Payload too large" message
- **Priority**: High

#### TC-EH-002: Extract From Non-Stego File
- **Preconditions**: Garbage file with no steganographic data
- **Steps**:
  1. Call `StegoService::extract()` on garbage file
- **Expected Outcome**: Exception thrown with "No StegoLock marker found" or similar
- **Priority**: High

#### TC-EH-003: Python Process Timeout
- **Preconditions**: Very large carrier, low timeout setting
- **Steps**:
  1. Set `STEGO_TIMEOUT=10` in `.env`
  2. Attempt encode with large carrier
- **Expected Outcome**: Timeout exception, stego document status set to `failed`
- **Priority**: Medium

#### TC-EH-004: Decode Non-Existent Stego Document
- **Preconditions**: Invalid stego document ID
- **Steps**:
  1. Send decode request with non-existent ID
- **Expected Outcome**: 404 response
- **Priority**: High

### 4.6 Steganography Driver Tests
#### TC-SD-001: LSB Embed/Extract With Python Driver (PNG)
- **Preconditions**: Python stegano library installed
- **Steps**:
  1. Call `StegoService::embed()` with PNG carrier and test payload
  2. Call `StegoService::extract()` on output file
  3. Verify extracted payload matches original
- **Expected Outcome**: Byte-for-byte match between input and output payload
- **Priority**: Critical

#### TC-SD-002: LSB Embed/Extract With PHP GD Driver (PNG)
- **Preconditions**: PHP GD extension enabled
- **Steps**:
  1. Set `STEGO_DRIVER=php` in config
  2. Repeat TC-SD-001 steps
- **Expected Outcome**: Successful embed/extract with PHP driver
- **Priority**: High

#### TC-SD-003: WAV Audio Embed/Extract
- **Preconditions**: WAV carrier, numpy installed
- **Steps**:
  1. Call `StegoService::embed()` with WAV carrier
  2. Extract and verify payload
- **Expected Outcome**: Successful audio steganography round-trip
- **Priority**: High

#### TC-SD-004: TXT Append Embed/Extract
- **Preconditions**: TXT carrier file
- **Steps**:
  1. Embed payload into TXT carrier
  2. Verify original content is preserved at start of file
  3. Extract and verify payload
- **Expected Outcome**: Append-mode steganography works correctly
- **Priority**: Medium

## 5. Test Execution Strategy
1. **Unit Tests**: Execute first with `php artisan test --testsuite=Unit`
2. **Feature Tests**: Execute with `php artisan test --testsuite=Feature`
3. **Integration Tests**: Execute manually or via custom test scripts
4. **Regression Tests**: Re-run all tests after code changes
5. **Performance Tests**: Measure encode/decode time for various document/carrier sizes

## 6. Success Criteria
- 100% pass rate for Critical priority test cases
- ≥95% pass rate for High priority test cases
- ≥90% pass rate for Medium priority test cases
- All Critical/High priority issues identified must be resolved before release
- Test report completed with actionable recommendations
