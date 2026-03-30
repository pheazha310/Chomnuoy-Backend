<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RegionalTopProvince;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegionalTopProvinceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organizationId = $request->query('organization_id');
        $limit = (int) $request->query('limit', 10);
        $limit = max(1, min($limit, 50));

        $query = RegionalTopProvince::query()
            ->when(
                is_numeric($organizationId),
                fn ($q) => $q->where('organization_id', (int) $organizationId)
            )
            ->orderBy('rank')
            ->limit($limit);

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $record = RegionalTopProvince::create($request->all());

        return response()->json($record, 201);
    }

    public function show(int $id): JsonResponse
    {
        $record = RegionalTopProvince::findOrFail($id);

        return response()->json($record);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = RegionalTopProvince::findOrFail($id);
        $record->update($request->all());

        return response()->json($record);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = RegionalTopProvince::findOrFail($id);
        $record->delete();

        return response()->json(null, 204);
    }
}
