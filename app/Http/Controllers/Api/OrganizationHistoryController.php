<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrganizationHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationHistoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(OrganizationHistory::query()->orderByDesc('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $record = OrganizationHistory::create($request->all());

        return response()->json($record, 201);
    }

    public function show(int $id): JsonResponse
    {
        $record = OrganizationHistory::findOrFail($id);

        return response()->json($record);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = OrganizationHistory::findOrFail($id);
        $record->update($request->all());

        return response()->json($record);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = OrganizationHistory::findOrFail($id);
        $record->delete();

        return response()->json(null, 204);
    }
}
