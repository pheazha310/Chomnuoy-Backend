<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(User::query()->orderByDesc('id')->get());
    }

    public function findByEmail(Request $request): JsonResponse
    {
        $email = strtolower(trim((string) $request->query('email', '')));
        if ($email === '') {
            return response()->json(['message' => 'Email is required.'], 422);
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if (!$user) {
            return response()->json(null);
        }

        return response()->json($user);
    }

    public function store(Request $request): JsonResponse
    {
        $record = User::create($request->all());

        return response()->json($record, 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user);
    }


    /**
     * Update user information
     * 
     * This function receives a request from the client (frontend/Postman),
     * validates the input data, uploads avatar if provided, and updates the user record.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        // Validate request data before updating
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'max:50'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        // Check if avatar file is uploaded
        if ($request->hasFile('avatar')) {
            // Store avatar file and only persist path when schema has avatar_path.
            $storedPath = $request->file('avatar')->store('avatars', 'public');
            if (Schema::hasColumn('users', 'avatar_path')) {
                $data['avatar_path'] = $storedPath;
            }
        }

        if (!empty($data['password'])) {
            if (Schema::hasColumn('users', 'password')) {
                $data['password'] = Hash::make($data['password']);
            } else {
                UserCredential::updateOrCreate(
                    ['user_id' => $user->id],
                    ['password' => Hash::make($data['password'])],
                );
                unset($data['password']);
            }
        } else {
            unset($data['password']);
        }

        // Update user data in database
        $user->update($data);

        // Return updated user data as JSON response
        return response()->json($user);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json(null, 204);
    }

    public function updateLastSeen(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $user->last_seen_at = now();
        $user->save();

        return response()->json([
            'message' => 'Last seen updated.',
            'last_seen_at' => $user->last_seen_at,
        ]);
    }
}
