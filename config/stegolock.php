<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Steganography Driver
    |--------------------------------------------------------------------------
    |
    | Controls which steganography engine is used for LSB operations.
    |
    |   'python' — Uses the Python stegano library via subprocess (recommended).
    |              Requires Python + stegano + Pillow to be installed:
    |                  pip install stegano Pillow
    |
    |   'php'    — Uses the built-in PHP GD-based LSB implementation.
    |              No external dependencies required.
    |
    */

    'driver' => env('STEGO_DRIVER', 'python'),

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | Controls where stego artifacts are stored. Set to 'b2' to use Backblaze,
    | or 'local' for local development.
    |
    */

    'storage' => [
        'disk' => env('STEGOLOCK_STORAGE_DISK', 'local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Python Executable
    |--------------------------------------------------------------------------
    |
    | Path to the Python interpreter. Use 'python' or 'python3' if it is on
    | your system PATH, or provide the full absolute path for Windows:
    |
    |   Example (Windows):
    |       C:\Users\USER\AppData\Local\Programs\Python\Python312\python.exe
    |
    */

    'python_path' => env('PYTHON_PATH', 'python'),

    /*
    |--------------------------------------------------------------------------
    | Python Script Path
    |--------------------------------------------------------------------------
    |
    | Absolute path to the stego_lsb.py script. Defaults to the python/
    | directory at the project root.
    |
    */

    'python_script' => env(
        'PYTHON_SCRIPT_PATH',
        base_path('python' . DIRECTORY_SEPARATOR . 'stego_lsb.py')
    ),

    /*
    |--------------------------------------------------------------------------
    | Python Process Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum number of seconds to wait for the Python subprocess to complete.
    | Increase this for very large carrier images.
    |
    */

    'python_timeout' => (int) env('PYTHON_TIMEOUT', 540),

    /*
    |--------------------------------------------------------------------------
    | Master Key Derivation (MKD) Iterations
    |--------------------------------------------------------------------------
    |
    | PBKDF2-SHA256 iteration count for deriving the master key from the
    | user's password. Higher = more secure but slower.
    |
    */

    'mkd_iterations' => (int) env('STEGOLOCK_MKD_ITERATIONS', 100_000),

    /*
    |--------------------------------------------------------------------------
    | Document Encryption Key (DEK) Iterations
    |--------------------------------------------------------------------------
    |
    | PBKDF2-SHA256 iteration count for per-document key derivation.
    |
    */

    'dek_iterations' => (int) env('STEGOLOCK_DEK_ITERATIONS', 10_000),

    /*
    |--------------------------------------------------------------------------
    | Maximum Carrier File Size (MB)
    |--------------------------------------------------------------------------
    |
    | Mirrors the controller validation rule 'max:20480' (20 MB).
    | Used for informational capacity checks.
    |
    */

    'max_carrier_size_mb' => (int) env('STEGOLOCK_MAX_CARRIER_MB', 100),

    /*
    |--------------------------------------------------------------------------
    | Carrier Pool Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the carrier pool feature that allows users to upload and
    | validate carrier images once, then reuse them across multiple encode
    | operations without re-uploading.
    |
    */

    'carrier_pool' => [
        // Maximum number of carriers a user can have in their pool
        'max_carriers_per_user' => (int) env('STEGOLOCK_MAX_CARRIERS_PER_USER', 50),

        // Maximum total size of all carriers in a user's pool (in bytes)
        // Default: 500 MB
        'max_total_size_bytes' => (int) env('STEGOLOCK_MAX_POOL_SIZE_BYTES', 500 * 1024 * 1024),

        // PSNR threshold for carrier validation (in dB)
        // Carriers below this threshold are marked as invalid
        'psnr_threshold' => (float) env('STEGOLOCK_PSNR_THRESHOLD', 40.0),

        // Additional encode-time quality guard for bin-packing concentration.
        // If average PSNR across used image carriers drops below this value, encode fails.
        'encode_average_psnr_threshold' => (float) env('STEGOLOCK_ENCODE_AVG_PSNR_THRESHOLD', 41.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Carrier Type Allowlist (Single Source of Truth)
    |--------------------------------------------------------------------------
    |
    | Defines allowed file extensions, MIME types, max sizes, and safety
    | factors per carrier category. All validation layers (controller, job,
    | service) MUST read from this config.
    |
    */
    'carriers' => [
        'allowed' => [
            'image' => [
                'mimes'      => ['png', 'bmp', 'jpeg', 'jpg'],
                'mime_types' => ['image/png', 'image/bmp', 'image/x-bmp', 'image/jpeg', 'image/jpg'],
                'max_kb'     => 102400, // 100 MB
            ],
            'audio' => [
                'mimes'      => ['wav'],
                'mime_types' => ['audio/wav', 'audio/x-wav', 'audio/wave'],
                'max_kb'     => 204800, // 200 MB
            ],
            'text' => [
                'mimes'      => ['txt'],
                'mime_types' => ['text/plain'],
                'max_kb'     => 10240, // 10 MB
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Capacity Safety Factors
    |--------------------------------------------------------------------------
    |
    | Multiplicative safety buffer applied to raw theoretical capacity.
    | Prevents overestimation that would cause mid-pipeline failures.
    |
    */
    'capacity_safety_factor' => [
        'image' => 0.90,  // images already apply 90% inside StegoService::capacity()
        'audio' => 0.95,  // audio: 5% buffer below theoretical LSB capacity
        'text'  => 0.50,  // append-mode conservative: half of file size available
    ],

    /*
    |--------------------------------------------------------------------------
    | Carrier Mandates (System-Wide)
    |--------------------------------------------------------------------------
    |
    | When enabled, these mandates force carrier selection to include at
    | least one carrier of each enabled type. Mandates are satisfied first,
    | then greedy fill covers remaining capacity. System carriers can be
    | used to fulfill mandates if user pool lacks a required type.
    |
    */
    'carrier_mandates' => [
        'enabled'       => env('STEGO_MANDATES_ENABLED', false),
        'require_image' => env('STEGO_REQUIRE_IMAGE', true),   // always require an image carrier
        'require_audio' => env('STEGO_REQUIRE_AUDIO', false),  // optional audio carrier
        'require_text'  => env('STEGO_REQUIRE_TEXT', false),   // optional text carrier
    ],

];

];
