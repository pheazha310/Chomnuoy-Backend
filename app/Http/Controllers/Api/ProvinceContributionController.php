<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProvinceContribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProvinceContributionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organizationId = $request->query('organization_id');
        $limit = (int) $request->query('limit', 5);
        $limit = max(1, min($limit, 20));

        $query = ProvinceContribution::query()
            ->when(
                is_numeric($organizationId),
                fn ($q) => $q->where('organization_id', (int) $organizationId)
            )
            ->orderByDesc('total_amount')
            ->limit($limit);

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $record = ProvinceContribution::create($request->all());

        return response()->json($record, 201);
    }

    public function show(int $id): JsonResponse
    {
        $record = ProvinceContribution::findOrFail($id);

        return response()->json($record);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = ProvinceContribution::findOrFail($id);
        $record->update($request->all());

        return response()->json($record);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = ProvinceContribution::findOrFail($id);
        $record->delete();

        return response()->json(null, 204);
    }
}
