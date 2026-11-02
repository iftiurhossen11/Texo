<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\RoleAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function __invoke(Request $request, RoleAccessService $roleAccessService): JsonResponse
    {
        $totalIndexed = Document::query()
            ->where('workspace_id', $request->user()->workspace_id)
            ->where('status', 'indexed')
            ->whereIn('department', $roleAccessService->allowedDepartments($request->user()))
            ->count();

        $byDepartment = Document::query()
            ->where('workspace_id', $request->user()->workspace_id)
            ->where('status', 'indexed')
            ->whereIn('department', $roleAccessService->allowedDepartments($request->user()))
            ->select('department', DB::raw('COUNT(*) as count'))
            ->groupBy('department')
            ->orderBy('department')
            ->get()
            ->map(fn (object $row): array => [
                'department' => $row->department ?? 'general',
                'count' => (int) $row->count,
            ])
            ->values();

        return response()->json([
            'total_indexed' => $totalIndexed,
            'total_audits_prepared' => 0,
            'by_department' => $byDepartment,
            'expired_certificates' => 0,
            'pending_tasks' => 0,
            'readiness_score' => $totalIndexed > 0 ? 85 : 0,
            'weekly_queries' => [
                ['week' => 'W1', 'queries' => 10],
                ['week' => 'W2', 'queries' => 20],
                ['week' => 'W3', 'queries' => 30],
                ['week' => 'W4', 'queries' => 25],
                ['week' => 'W5', 'queries' => 40],
                ['week' => 'W6', 'queries' => 50],
            ],
        ]);
    }
}
