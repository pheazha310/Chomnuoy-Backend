<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrganizationDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationDocumentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(OrganizationDocument::query()->orderByDesc('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $record = OrganizationDocument::create($request->all());

        return response()->json($record, 201);
    }

    public function show(int $id): JsonResponse
    {
        $record = OrganizationDocument::findOrFail($id);

        return response()->json($record);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = OrganizationDocument::findOrFail($id);
        $record->update($request->all());

        return response()->json($record);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = OrganizationDocument::findOrFail($id);
        $record->delete();

        return response()->json(null, 204);
    }
}
