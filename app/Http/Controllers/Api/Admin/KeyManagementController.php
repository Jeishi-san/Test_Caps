<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

class KeyManagementController extends Controller
{
    /**
     * Get key management policy settings
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->isOwner()) {
            return response()->json(['message' => 'Forbidden: Admin access required.'], 403);
        }

        return response()->json([
            'password_min_length' => Config::get('stegolock.key_management.mk_min_length', 8),
            'password_require_uppercase' => Config::get('stegolock.key_management.mk_require_uppercase', true),
            'password_require_number' => Config::get('stegolock.key_management.mk_require_number', true),
            'password_require_special' => Config::get('stegolock.key_management.mk_require_special', true),
            'key_rotation_enabled' => Config::get('stegolock.key_management.key_rotation_enabled', false),
            'key_rotation_interval_days' => Config::get('stegolock.key_management.key_rotation_interval_days', 90),
        ]);
    }

    /**
     * Update key management policy settings
     */
    public function update(): JsonResponse
    {
        $user = Auth::user();
        if (!$user->isOwner()) {
            return response()->json(['message' => 'Forbidden: Superadmin access required.'], 403);
        }

        // Note: Updating .env via web is not recommended for production.
        return response()->json([
            'message' => 'Key management settings are managed via .env variables. Please update STEGOLOCK_MK_MIN_LENGTH, STEGOLOCK_KEY_ROTATION_ENABLED, etc.',
        ]);
    }
}