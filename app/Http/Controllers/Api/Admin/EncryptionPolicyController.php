<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

class EncryptionPolicyController extends Controller
{
    /**
     * Get current encryption policy settings
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->isOwner()) {
            return response()->json(['message' => 'Forbidden: Admin access required.'], 403);
        }

        return response()->json([
            'aes_mode' => 'AES-256-GCM', // Fixed as per project spec
            'key_size' => 256, // Fixed
            'mkd_iterations' => Config::get('stegolock.mkd_iterations'),
            'dek_iterations' => Config::get('stegolock.dek_iterations'),
            'fragment_size' => Config::get('stegolock.max_carrier_size_mb') * 1024 * 1024, // Convert MB to bytes
            'psnr_threshold' => Config::get('stegolock.carrier_pool.psnr_threshold'),
            'encode_avg_psnr_threshold' => Config::get('stegolock.carrier_pool.encode_average_psnr_threshold'),
        ]);
    }

    /**
     * Update encryption policy settings (updates .env values)
     */
    public function update(): JsonResponse
    {
        $user = Auth::user();
        if (!$user->isOwner()) {
            return response()->json(['message' => 'Forbidden: Superadmin access required.'], 403);
        }

        // Note: Updating .env via web is not recommended for production.
        // This is a placeholder; in production, use config management tools.
        return response()->json([
            'message' => 'Encryption policy settings are managed via .env variables. Please update STEGOLOCK_MKD_ITERATIONS, STEGOLOCK_DEK_ITERATIONS, etc.',
        ]);
    }
}