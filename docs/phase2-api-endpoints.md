# Phase 2 API Endpoints and Breaking Changes

## Summary
Phase 2 introduces a dedicated file operations controller and route group. Existing legacy web routes remain available, but canonical file routes are now grouped under files endpoints.

## New Canonical Web Endpoints
All endpoints require authenticated user context.

- GET /files/folders/{folder}
  - Name: files.index
  - Action: list documents in folder with optional tags query param.

- POST /files/upload
  - Name: files.upload
  - Action: upload one or more files to folder.

- GET /files/documents/{document}/download
  - Name: files.download
  - Action: download file as attachment.

- GET /files/documents/{document}/view
  - Name: files.view
  - Action: preview file inline.

- GET /files/filter-by-tags
  - Name: files.filterByTag
  - Action: filter folder files by tag IDs.

- POST /files/visibility
  - Name: files.updateVisibility
  - Action: toggle visibility for a document.

- POST /files/order
  - Name: files.updateOrder
  - Action: reorder documents within a folder.

## New Canonical API Endpoints
All endpoints require auth:sanctum token.

- GET /api/documents/files/folders/{folder}
  - Name: api.documents.files.index

- POST /api/documents/files/upload
  - Name: api.documents.files.upload

- GET /api/documents/files/{document}/download
  - Name: api.documents.files.download

- GET /api/documents/files/{document}/view
  - Name: api.documents.files.view

- GET /api/documents/files/filter-by-tags
  - Name: api.documents.files.filterByTag

- POST /api/documents/files/visibility
  - Name: api.documents.files.updateVisibility

- POST /api/documents/files/order
  - Name: api.documents.files.updateOrder

## Backward Compatibility
Legacy web endpoints are still available and mapped to the new file controller:

- GET /getFiles/{folder}
- POST /upload
- GET /documents/{document}/download
- GET /documents/{document}/view
- GET /filter-documents-by-tags
- POST /update-visibility
- POST /update-document-order

## Integration Notes
- DocumentController now delegates file operations to DocumentFileController.
- DocumentService remains the facade for backward compatibility and now delegates to specialized services.
- AppServiceProvider now registers singleton bindings for:
  - DocumentEncryptionService
  - DocumentFileService
  - DocumentAccessService
  - DocumentNotificationService
  - DocumentService

## Breaking Changes
No hard-breaking route removals were made in this integration pass.

Behavioral changes to note:
- File operation logic now runs through DocumentFileController and ErrorHandlingService.
- Error payloads are more consistent for file operations.
- Canonical endpoint names have changed to files.* and api.documents.files.* for new integrations.
