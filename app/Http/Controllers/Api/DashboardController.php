<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Document;
use App\Models\Folder;
use App\Models\StegoDocument;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * DashboardController (API)
 *
 * Supplies aggregate counts and recent activity data consumed by the SPA
 * dashboard. Extracted from route closures in routes/api.php to centralise
 * query logic in controllers and keep route files as thin declarations (DRY / SRP).
 *
 * Routes (registered in routes/api.php):
 *   GET /api/dashboard/stats   — aggregate counts across all resources
 *   GET /api/dashboard/recent  — 8 most recently uploaded documents
 *   GET /api/admin/dashboard/stats — admin dashboard statistics
 */
class DashboardController extends Controller
{
    // -------------------------------------------------------------------------
    // GET /api/dashboard/stats
    // -------------------------------------------------------------------------

    /**
     * Return aggregate counts for the dashboard summary cards.
     *
     * Returns total documents, folders, categories, tags, and the authenticated
     * user's own stego document count.
     *
     * @return JsonResponse
     */
    public function stats(): JsonResponse
    {
        $userId = Auth::id();

        return response()->json([
            'documents'       => Document::count(),
            'folders'         => Folder::count(),
            'categories'      => Category::count(),
            'tags'            => Tag::count(),
            'stego_documents' => StegoDocument::where('user_id', $userId)->count(),
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/dashboard/recent
    // -------------------------------------------------------------------------

    /**
     * Return the 8 most recently uploaded documents with their tags and a
     * boolean flag indicating whether each document has been stego-encoded.
     *
     * @return JsonResponse
     */
    public function recent(): JsonResponse
    {
        $docs = Document::with('tags:id,name')
            ->withExists('stegoDocument as is_stegoed')
            ->latest()
            ->take(8)
            ->get(['id', 'name', 'extension', 'size', 'created_at']);

        return response()->json($docs);
    }

    // -------------------------------------------------------------------------
    // GET /api/admin/dashboard/stats
    // -------------------------------------------------------------------------

    /**
     * Return admin dashboard statistics.
     *
     * Returns total users, active users, encrypted containers (stego documents),
     * failed reconstructions, and system health metrics.
     *
     * @return JsonResponse
     */
    public function adminStats(): JsonResponse
    {
        $totalUsers = User::count();
        $activeUsers = User::where('active', true)->count();
        $encryptedContainers = StegoDocument::count();
        $failedReconstructions = StegoDocument::where('status', 'failed')->count();

        // System health checks (simplified - in production these would check actual services)
        $systemHealth = [
            [
                'label' => 'Fragment Storage',
                'status' => 'operational',
                'value' => '99.8%',
            ],
            [
                'label' => 'Encryption Service',
                'status' => 'operational',
                'value' => '100%',
            ],
            [
                'label' => 'User Authentication',
                'status' => 'operational',
                'value' => '99.9%',
            ],
            [
                'label' => 'API Gateway',
                'status' => 'operational',
                'value' => '99.5%',
            ],
        ];

        // Recent activity (simplified - in production this would come from an activity log)
        $recentActivity = [];

        return response()->json([
            'stats' => [
                [
                    'label' => 'Total Users',
                    'value' => number_format($totalUsers),
                    'change' => '+0%',
                    'trend' => 'up',
                    'color' => 'blue',
                ],
                [
                    'label' => 'Active Users',
                    'value' => number_format($activeUsers),
                    'change' => '+0%',
                    'trend' => 'up',
                    'color' => 'green',
                ],
                [
                    'label' => 'Encrypted Containers',
                    'value' => number_format($encryptedContainers),
                    'change' => '+0%',
                    'trend' => 'up',
                    'color' => 'purple',
                ],
                [
                    'label' => 'Failed Reconstructions',
                    'value' => number_format($failedReconstructions),
                    'change' => '-0%',
                    'trend' => 'down',
                    'color' => 'red',
                ],
            ],
            'systemHealth' => $systemHealth,
            'recentActivity' => $recentActivity,
        ]);
    }
}
