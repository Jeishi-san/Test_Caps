<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\StegoCarrier;
use App\Models\StegoSegment;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class FragmentController extends Controller
{
    /**
     * List all carriers and segments for admin monitoring
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        if (!$user->isAdmin() && !$user->isOwner()) {
            return response()->json(['message' => 'Forbidden: Admin access required.'], 403);
        }

        // Carrier stats
        $carrierStats = [
            'total' => StegoCarrier::count(),
            'validated' => StegoCarrier::where('validation_status', 'validated')->count(),
            'pending' => StegoCarrier::where('validation_status', 'pending')->count(),
            'failed' => StegoCarrier::where('validation_status', 'failed')->count(),
            'in_use' => StegoCarrier::where('is_in_use', true)->count(),
        ];

        // Segment stats
        $segmentStats = [
            'total' => StegoSegment::count(),
        ];

        // List all carriers with owner info
        $carriers = StegoCarrier::with('uploader:id,name,email')
            ->select([
                'id', 'name', 'file_type', 'mime_type', 'size',
                'psnr', 'capacity_bytes', 'validation_status',
                'validation_error', 'is_in_use', 'uploaded_by', 'validated_at', 'created_at',
            ])
            ->latest()
            ->paginate(20);

        // List all segments with document and carrier info
        $segments = StegoSegment::with([
            'stegoDocument:id,name',
            'carrier:id,name',
        ])
            ->select(['id', 'stego_document_id', 'stego_carrier_id', 'segment_index', 'chunk_hash'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'carrier_stats' => $carrierStats,
            'segment_stats' => $segmentStats,
            'carriers' => $carriers,
            'segments' => $segments,
        ]);
    }
}