<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RegionalMetric;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegionalMetricController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organizationId = $request->query('organization_id');
        $limit = (int) $request->query('limit', 10);
        $limit = max(1, min($limit, 50));

        $query = RegionalMetric::query()
            ->when(
                is_numeric($organizationId),
                fn ($q) => $q->where('organization_id', (int) $organizationId)
            )
            ->orderBy('id')
            ->limit($limit);

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $record = RegionalMetric::create($request->all());

        return response()->json($record, 201);
    }

    public function show(int $id): JsonResponse
    {
        $record = RegionalMetric::findOrFail($id);

        return response()->json($record);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = RegionalMetric::findOrFail($id);
        $record->update($request->all());

        return response()->json($record);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = RegionalMetric::findOrFail($id);
        $record->delete();

        return response()->json(null, 204);
    }
}
