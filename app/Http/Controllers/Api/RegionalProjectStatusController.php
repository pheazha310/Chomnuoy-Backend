<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RegionalProjectStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegionalProjectStatusController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organizationId = $request->query('organization_id');
        $limit = (int) $request->query('limit', 10);
        $limit = max(1, min($limit, 50));

        $query = RegionalProjectStatus::query()
            ->when(
                is_numeric($organizationId),
                fn ($q) => $q->where('organization_id', (int) $organizationId)
            )
            ->orderByDesc('campaigns')
            ->limit($limit);

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $record = RegionalProjectStatus::create($request->all());

        return response()->json($record, 201);
    }

    public function show(int $id): JsonResponse
    {
        $record = RegionalProjectStatus::findOrFail($id);

        return response()->json($record);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = RegionalProjectStatus::findOrFail($id);
        $record->update($request->all());

        return response()->json($record);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = RegionalProjectStatus::findOrFail($id);
        $record->delete();

        return response()->json(null, 204);
    }
}
