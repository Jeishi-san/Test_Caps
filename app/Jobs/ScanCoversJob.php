<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use App\Models\StegoCarrier;

class ScanCoversJob implements ShouldQueue
{
    use Dispatchable;

    public function __construct()
    {
        // No parameters needed for scanning all cover types
    }

    public function handle(): void
    {
        try {
            Log::info('Starting cover scan job...');

            // Scan all cover types with actual Python scripts
            $this->scan('audio', 'mp3', 'audio_covers', 'python/wav_capacity.py');
            $this->scan('image', 'png', 'image_covers', 'python/image_capacity.py');
            $this->scan('text', 'txt', 'text_covers', 'python/txt_capacity.py');

            Log::info('Cover scan job completed successfully.');
        } catch (\Exception $e) {
            Log::error('Cover scan job failed: ' . $e->getMessage());
            throw $e; // Retry the job
        }
    }

    /**
     * Scan specific folder for candidate cover files by extension
     * [Difficulty: Hard] Updated with actual scanning logic
     *
     * @param string $type The type of cover (audio, image, text)
     * @param string $extension The file extension to scan for
     * @param string $folderName The folder name to scan
     * @param string $scriptPath The Python script path to validate capacity
     */
    private function scan(string $type, string $extension, string $folderName, string $scriptPath): void
    {
        $scanPath = storage_path("app/covers/{$folderName}");
        if (!is_dir($scanPath)) {
            Log::warning("Scan folder not found: {$scanPath}");
            return;
        }

        // Get all files with the target extension
        $files = glob("{$scanPath}/*.{$extension}");
        Log::info("Found " . count($files) . " {$type} files to scan in {$folderName}");

        foreach ($files as $file) {
            $this->processFile($file, $type, $extension, $scriptPath);
        }
    }

    /**
     * Process individual cover file: validate capacity, check duplicates, rename, record in DB
     * [Difficulty: Hard] implementation
     *
     * @param string $filePath Full path to the file to process
     * @param string $type Cover type (audio, image, text)
     * @param string $extension File extension
     * @param string $scriptPath Python script path for capacity validation
     */
    private function processFile(string $filePath, string $type, string $extension, string $scriptPath): void
    {
        try {
            // 1. Validate file capacity via Python script
            $capacity = $this->validateCapacityWithPython($filePath, $scriptPath);
            if ($capacity <= 0) {
                Log::warning("Invalid capacity for file: {$filePath} (capacity: {$capacity})");
                $this->moveToFailedFolder($filePath, $type);
                return;
            }

            // 2. Check for duplicate hashes
            $fileHash = hash_file('sha256', $filePath);
            $existingCover = StegoCarrier::where('hash', $fileHash)->first();
            if ($existingCover) {
                Log::info("Duplicate cover file detected: {$filePath}, moving to failed");
                $this->moveToFailedFolder($filePath, $type);
                return;
            }

            // 3. Rename file to standardized format
            $newFilename = $this->generateStandardizedFilename($type, $extension);
            $newPath = dirname($filePath) . '/' . $newFilename;
            rename($filePath, $newPath);

            // 4. Record valid cover in DB
            StegoCarrier::create([
                'type' => $type,
                'path' => $newPath,
                'hash' => $fileHash,
                'capacity' => $capacity,
                'is_valid' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info("Successfully processed cover: {$newPath} (capacity: {$capacity} bytes)");
        } catch (\Exception $e) {
            Log::error("Failed to process file {$filePath}: " . $e->getMessage());
            $this->moveToFailedFolder($filePath, $type);
        }
    }

    /**
     * Validate file capacity using Python script
     */
    private function validateCapacityWithPython(string $filePath, string $scriptPath): int
    {
        if (!file_exists($scriptPath)) {
            Log::error("Python script not found: {$scriptPath}");
            return 0;
        }

        $output = [];
        $returnVar = 0;
        exec("python \"{$scriptPath}\" \"{$filePath}\"", $output, $returnVar);

        if ($returnVar !== 0) {
            Log::error("Python script failed: {$scriptPath} (return code: {$returnVar})");
            return 0;
        }

        return (int) ($output[0] ?? 0);
    }

    /**
     * Generate standardized filename for covers
     */
    private function generateStandardizedFilename(string $type, string $extension): string
    {
        return $type . '_' . dechex(now()->timestamp) . '_' . uniqid() . '.' . $extension;
    }

    /**
     * Move invalid files to failed folder
     */
    private function moveToFailedFolder(string $filePath, string $type): void
    {
        $failedDir = storage_path("app/covers/{$type}/failed");
        if (!is_dir($failedDir)) {
            mkdir($failedDir, 0755, true);
        }

        $filename = basename($filePath);
        $destPath = $failedDir . '/' . $filename;
        rename($filePath, $destPath);

        Log::info("Moved invalid file to failed folder: {$destPath}");
    }

    /**
     * Generates system-generated text covers using wiki_feeds data
     * Standardizes file naming with hex timestamp format
     * Adds info: 'System-generated' to metadata
     *
     * @param array $wikiFeedData Array of wiki feed data to use for content
     * @param string $outputPath Directory to save generated text files
     * @return string Path to generated file
     */
    private function generate_cover_text_file(array $wikiFeedData, string $outputPath): string
    {
        // Generate standardized filename with hex timestamp
        $hexTimestamp = dechex(now()->timestamp);
        $filename = "cover_{$hexTimestamp}.txt";
        $fullPath = rtrim($outputPath, '/') . '/' . $filename;

        // Build content from wiki feed data
        $content = "System-Generated Text Cover\n";
        $content .= "Generated at: " . now()->toDateTimeString() . "\n";
        $content .= "Info: System-generated\n";
        $content .= "----------------------------------------\n";

        foreach ($wikiFeedData as $item) {
            $content .= "Title: " . ($item['title'] ?? 'Untitled') . "\n";
            $content .= "Content: " . ($item['content'] ?? 'No content') . "\n\n";
        }

        // Save file
        file_put_contents($fullPath, $content);

        Log::info("Generated system text cover: {$fullPath}");

        return $fullPath;
    }
}
