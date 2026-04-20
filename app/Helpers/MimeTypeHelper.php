<?php

namespace App\Helpers;

class MimeTypeHelper
{
    /**
     * Extension to MIME type mapping for common file types.
     * Used to derive MIME type from file extension so it's correct even when
     * the file on disk is encrypted (raw ciphertext bytes would fool mime_content_type).
     */
    public static function getMimeTypeMap(): array
    {
        return [
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'webp' => 'image/webp',
            'bmp'  => 'image/bmp',
            'mp4'  => 'video/mp4',
            'webm' => 'video/webm',
            'mov'  => 'video/quicktime',
            'avi'  => 'video/x-msvideo',
            'ogg'  => 'video/ogg',
            'mp3'  => 'audio/mpeg',
            'wav'  => 'audio/wav',
            'm4a'  => 'audio/mp4',
            'txt'  => 'text/plain',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt'  => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];
    }

    /**
     * Get MIME type from file extension.
     * Falls back to mime_content_type if extension is not in the map.
     *
     * @param string $extension File extension (without dot)
     * @param string|null $filePath Absolute file path for fallback mime_content_type
     * @param bool $isEncrypted Whether the file is encrypted
     * @return string MIME type
     */
    public static function getMimeType(string $extension, ?string $filePath = null, bool $isEncrypted = false): string
    {
        $mimeMap = self::getMimeTypeMap();
        $ext = strtolower($extension);

        // If found in map, return it
        if (isset($mimeMap[$ext])) {
            return $mimeMap[$ext];
        }

        // If file path provided and not encrypted, try mime_content_type
        if ($filePath && file_exists($filePath) && !$isEncrypted) {
            $detected = mime_content_type($filePath);
            if ($detected) {
                return $detected;
            }
        }

        // Default fallback
        return 'application/octet-stream';
    }
}
