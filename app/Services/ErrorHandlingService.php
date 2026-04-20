<?php

namespace App\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Service for consistent error handling across the application.
 * 
 * Provides standardized error responses and logging for:
 * - Validation errors
 * - Authorization errors
 * - File operation errors
 * - Database/model errors
 */
class ErrorHandlingService
{
    /**
     * Handle validation errors with consistent format.
     *
     * @param ValidationException $exception
     * @param string $operation Description of operation that failed
     * @return array Error response
     */
    public static function handleValidationError(ValidationException $exception, string $operation = 'Operation'): array
    {
        Log::warning("Validation error during {$operation}: " . json_encode($exception->errors()));

        return [
            'status' => 422,
            'error' => 'Validation failed',
            'message' => 'The provided data is invalid.',
            'errors' => $exception->errors(),
        ];
    }

    /**
     * Handle authorization/permission errors.
     *
     * @param string $resource Resource type (e.g., 'Document', 'Folder')
     * @param string $action Action attempted (e.g., 'view', 'delete')
     * @return array Error response
     */
    public static function handleAuthorizationError(string $resource = 'Resource', string $action = 'access'): array
    {
        $message = "You do not have permission to {$action} this {$resource}.";
        
        Log::warning("Authorization error: {$message}");

        return [
            'status' => 403,
            'error' => 'Access denied',
            'message' => $message,
        ];
    }

    /**
     * Handle file not found errors.
     *
     * @param string $filePath Path or identifier of missing file
     * @param string $context Additional context about what was being done
     * @return array Error response
     */
    public static function handleFileNotFound(string $filePath, string $context = ''): array
    {
        $message = "File not found: {$filePath}";
        if ($context) {
            $message .= " ({$context})";
        }

        Log::warning($message);

        return [
            'status' => 404,
            'error' => 'File not found',
            'message' => $message,
        ];
    }

    /**
     * Handle model not found errors.
     *
     * @param string $model Model class name
     * @param mixed $identifier ID or identifier that was not found
     * @return array Error response
     */
    public static function handleModelNotFound(string $model, mixed $identifier): array
    {
        $message = "{$model} not found (ID: {$identifier})";
        
        Log::warning($message);

        return [
            'status' => 404,
            'error' => 'Not found',
            'message' => $message,
        ];
    }

    /**
     * Handle file upload errors.
     *
     * @param string $fileName Name of file that failed to upload
     * @param string $reason Reason for failure
     * @return array Error response
     */
    public static function handleUploadError(string $fileName, string $reason = 'Unknown error'): array
    {
        $message = "Failed to upload file '{$fileName}': {$reason}";
        
        Log::error($message);

        return [
            'status' => 422,
            'error' => 'Upload failed',
            'message' => $message,
        ];
    }

    /**
     * Handle decryption errors.
     *
     * @param mixed $documentId ID of document that failed decryption
     * @param Exception|string $reason Reason for failure
     * @return array Error response
     */
    public static function handleDecryptionError(mixed $documentId, Exception|string $reason = 'Unknown error'): array
    {
        if ($reason instanceof Exception) {
            $reason = $reason->getMessage();
        }

        $message = "Failed to retrieve document (ID: {$documentId}): {$reason}";
        
        Log::error($message);

        return [
            'status' => 500,
            'error' => 'Decryption failed',
            'message' => $message,
        ];
    }

    /**
     * Handle integrity check failures (tampered files).
     *
     * @param mixed $documentId ID of document that failed integrity check
     * @return array Error response
     */
    public static function handleIntegrityError(mixed $documentId): array
    {
        $message = "Document integrity check failed (ID: {$documentId}). File may be corrupt or tampered with.";
        
        Log::error($message);

        return [
            'status' => 500,
            'error' => 'Integrity check failed',
            'message' => $message,
        ];
    }

    /**
     * Handle general server errors.
     *
     * @param Exception $exception The exception that occurred
     * @param string $operation Description of operation
     * @return array Error response
     */
    public static function handleServerError(Exception $exception, string $operation = 'Operation'): array
    {
        Log::error("Server error during {$operation}: " . $exception->getMessage(), [
            'exception' => $exception,
            'trace' => $exception->getTraceAsString(),
        ]);

        return [
            'status' => 500,
            'error' => 'Server error',
            'message' => "An error occurred during {$operation}. Please try again later.",
        ];
    }

    /**
     * Handle database/transaction errors.
     *
     * @param Exception $exception The exception that occurred
     * @param string $operation Description of operation
     * @return array Error response
     */
    public static function handleDatabaseError(Exception $exception, string $operation = 'Operation'): array
    {
        Log::error("Database error during {$operation}: " . $exception->getMessage(), [
            'exception' => $exception,
        ]);

        return [
            'status' => 500,
            'error' => 'Database error',
            'message' => "A database error occurred during {$operation}. Please try again.",
        ];
    }

    /**
     * Convert error response array to JSON response.
     *
     * @param array $errorArray Error array from this service
     * @return \Illuminate\Http\JsonResponse
     */
    public static function response(array $errorArray)
    {
        $status = $errorArray['status'] ?? 500;
        $payload = array_diff_key($errorArray, ['status' => null]);

        return response()->json($payload, $status);
    }

    /**
     * Abort with error using this service's format.
     *
     * @param array $errorArray Error array from this service
     */
    public static function abort(array $errorArray): void
    {
        $status = $errorArray['status'] ?? 500;
        $message = $errorArray['message'] ?? 'An error occurred';

        abort($status, $message);
    }
}
