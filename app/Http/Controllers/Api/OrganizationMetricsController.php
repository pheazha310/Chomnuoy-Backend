<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrganizationMetricsCache;
use App\Services\OrganizationMetricsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationMetricsController extends Controller
{
    public function summary(Request $request, OrganizationMetricsService $service): JsonResponse
    {
        $organizationId = $this->parseOrganizationId($request);
        $now = Carbon::now();
        $periodStart = $now->copy()->startOfMonth()->startOfDay();
        $periodEnd = $now->copy();
        $prevStart = $now->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay();
        $prevEnd = $now->copy()->subMonthNoOverflow();

        $summary = $service->computeSummary($organizationId, $periodStart, $periodEnd, $prevStart, $prevEnd);

        return response()->json($summary);
    }

    public function cached(Request $request): JsonResponse
    {
        $organizationId = $this->parseOrganizationId($request);

        $cache = OrganizationMetricsCache::query()
            ->when(
                $organizationId !== null,
                fn ($query) => $query->where('organization_id', $organizationId),
                fn ($query) => $query->whereNull('organization_id')
            )
            ->orderByDesc('period_start')
            ->first();

        if (!$cache) {
            return response()->json(null, 200);
        }

        return response()->json($cache);
    }

    private function parseOrganizationId(Request $request): ?int
    {
        $raw = $request->query('organization_id');

        return is_numeric($raw) ? (int) $raw : null;
    }
}
