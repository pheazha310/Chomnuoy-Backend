<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

// Define a controller class for handling Organization API requests
class OrganizationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Organization::query()->orderByDesc('id')->get());
    }

    public function findByEmail(Request $request): JsonResponse
    {
        $email = strtolower(trim((string) $request->query('email', '')));
        if ($email === '') {
            return response()->json(['message' => 'Email is required.'], 422);
        }

        $organization = Organization::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (!$organization) {
            return response()->json(null);
        }

        return response()->json($organization);
    }

     // Method to create new organization (POST /organizations)
    public function store(Request $request): JsonResponse
    {
        $data = $request->all();

        // Encrypt password
        $data['password'] = Hash::make($request->password);

        $record = Organization::create($data);

        return response()->json($record, 201);
    }

    // Method to get single organization by ID (GET /organizations/{id})
    public function show(int $id): JsonResponse
    {
        $record = Organization::findOrFail($id);

        return response()->json($record);
    }

    // Method to update organization (PUT/PATCH /organizations/{id})
    public function update(Request $request, int $id): JsonResponse
    {
        $record = Organization::findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('organizations', 'email')->ignore($record->id)],
            'location' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string'],
            'verified_status' => ['nullable', 'string', Rule::in(['pending', 'verified', 'inactive', 'Pending', 'Verified', 'Inactive'])],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            $storedPath = $request->file('avatar')->store('avatars', 'public');
            if (Schema::hasColumn('organizations', 'avatar_path')) {
                $data['avatar_path'] = $storedPath;
            }
        }

        $record->update($data);

        return response()->json($record);
    }

    // Method to delete organization (DELETE /organizations/{id})
    public function destroy(int $id): JsonResponse
    {
        $record = Organization::findOrFail($id);
        $record->delete();

        return response()->json(null, 204);
    }
}
