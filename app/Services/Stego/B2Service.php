<?php

namespace App\Services\Stego;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;

/**
 * B2Service
 *
 * Handles concurrent file uploads to Backblaze B2 cloud storage.
 * Uses Guzzle Pool for parallel uploads with configurable concurrency limit.
 */
class B2Service
{
    protected Client $client;
    protected int $concurrency;

    public function __construct(int $concurrency = 5)
    {
        $this->client = new Client();
        $this->concurrency = $concurrency;
    }

    /**
     * Upload multiple files concurrently using Guzzle Pool.
     *
     * @param array $filePaths Array of local file paths to upload
     * @param int $concurrency Maximum number of concurrent uploads (default: 5)
     * @param callable|null $onProgress Callback for each successful upload
     * @return array Array of upload results mapped by file path
     */
    public function storeFilesBatch(array $filePaths, int $concurrency = 5, ?callable $onProgress = null): array
    {
        $concurrency = min($concurrency, 10); // Cap at 10 as per roadmap
        $results = [];
        $requests = function () use ($filePaths) {
            foreach ($filePaths as $filePath) {
                if (!file_exists($filePath)) {
                    continue;
                }

                // TODO: Implement actual B2 upload request
                // This is a placeholder that maintains the structure
                yield new Request('PUT', 'https://example.com/upload', [], file_get_contents($filePath));
            }
        };

        $pool = new Pool($this->client, $requests(), [
            'concurrency' => $concurrency,
            'fulfilled' => function ($response, $index) use (&$results, $filePaths, $onProgress) {
                $filePath = $filePaths[$index] ?? $index;
                $results[$filePath] = [
                    'success' => true,
                    'response' => $response,
                ];

                if ($onProgress) {
                    $onProgress($filePath, $results[$filePath]);
                }
            },
            'rejected' => function ($reason, $index) use (&$results, $filePaths) {
                $filePath = $filePaths[$index] ?? $index;
                $results[$filePath] = [
                    'success' => false,
                    'error' => $reason->getMessage(),
                ];
            },
        ]);

        $promise = $pool->promise();
        $promise->wait();

        return $results;
    }

    /**
     * Set the concurrency limit for batch uploads.
     *
     * @param int $concurrency
     */
    public function setConcurrency(int $concurrency): void
    {
        $this->concurrency = min($concurrency, 10); // Cap at 10
    }
}
