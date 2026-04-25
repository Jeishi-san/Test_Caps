<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityLogController extends Controller
{
    /**
     * List all activity logs with filtering
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->isOwner()) {
            return response()->json(['message' => 'Forbidden: Admin access required.'], 403);
        }

        $query = AccessLog::with('user:id,name,email');

        // Filter by action
        if ($request->has('action') && !empty($request->action)) {
            $query->where('action', $request->action);
        }

        // Filter by status code
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status_code', (int) $request->status);
        }

        // Filter by user ID
        if ($request->has('user_id') && !empty($request->user_id)) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by date range
        if ($request->has('start_date') && !empty($request->start_date)) {
            $query->where('accessed_at', '>=', $request->start_date);
        }
        if ($request->has('end_date') && !empty($request->end_date)) {
            $query->where('accessed_at', '<=', $request->end_date);
        }

        // Search by URL or IP
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('url', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $logs = $query->select([
            'id', 'user_id', 'action', 'resource', 'resource_id',
            'ip_address', 'method', 'url', 'status_code', 'accessed_at'
        ])
            ->latest('accessed_at')
            ->paginate(50);

        return response()->json($logs);
    }
}