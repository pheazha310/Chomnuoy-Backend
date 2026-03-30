<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DonationTrend;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DonationTrendController extends Controller
{
    public function index(): JsonResponse
    {
        $records = DonationTrend::query()
            ->orderByDesc('year')
            ->orderBy('month_index')
            ->get();

        return response()->json($records);
    }

    public function store(Request $request): JsonResponse
    {
        $record = DonationTrend::create($request->all());

        return response()->json($record, 201);
    }

    public function show(int $id): JsonResponse
    {
        $record = DonationTrend::findOrFail($id);

        return response()->json($record);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = DonationTrend::findOrFail($id);
        $record->update($request->all());

        return response()->json($record);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = DonationTrend::findOrFail($id);
        $record->delete();

        return response()->json(null, 204);
    }
}
