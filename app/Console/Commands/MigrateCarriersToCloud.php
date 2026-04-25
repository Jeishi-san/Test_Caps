<?php

namespace App\Console\Commands;

use App\Models\StegoCarrier;
use App\Services\Stego\CloudStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class MigrateCarriersToCloud extends Command
{
    protected $signature = 'stegolock:migrate-carriers-to-cloud {--disk=s3 : Destination cloud disk to migrate files to}';
    protected $description = 'Migrate existing local carrier files to cloud storage (one-time use)';

    public function handle(): int
    {
        $destinationDiskName = $this->option('disk');
        $this->info("Starting carrier migration to disk: {$destinationDiskName}");

        $destinationDisk = Storage::disk($destinationDiskName);
        $localDisk = Storage::disk('local');
        $cloudStorage = new CloudStorageService();

        $carriers = StegoCarrier::all();
        $this->info("Found {$carriers->count()} carrier records to process");

        $successCount = 0;
        $errorCount = 0;

        foreach ($carriers as $carrier) {
            $filePath = $carrier->file_path;

            // Check if file exists on local disk
            if (!$localDisk->exists($filePath)) {
                $this->warn("Local file not found for carrier ID {$carrier->id}: {$filePath}");
                $errorCount++;
                continue;
            }

            // Check if file already exists on destination disk
            if ($destinationDisk->exists($filePath)) {
                $this->info("File already exists on destination for carrier ID {$carrier->id}: {$filePath}");
                $successCount++;
                continue;
            }

            try {
                // Get file content from local disk
                $content = $localDisk->get($filePath);
                if ($content === null) {
                    $this->error("Failed to read local file for carrier ID {$carrier->id}: {$filePath}");
                    $errorCount++;
                    continue;
                }

                // Upload to destination disk
                $result = $destinationDisk->put($filePath, $content);
                if (!$result) {
                    $this->error("Failed to upload file for carrier ID {$carrier->id}: {$filePath}");
                    $errorCount++;
                    continue;
                }

                $this->info("Successfully migrated carrier ID {$carrier->id}: {$filePath}");
                $successCount++;

            } catch (\Throwable $e) {
                $this->error("Error migrating carrier ID {$carrier->id}: {$e->getMessage()}");
                Log::error('Carrier migration failed', [
                    'carrier_id' => $carrier->id,
                    'file_path' => $filePath,
                    'error' => $e->getMessage(),
                ]);
                $errorCount++;
            }
        }

        $this->info("Migration complete. Success: {$successCount}, Errors: {$errorCount}");
        return $errorCount === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
