<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Models\Role;
use App\Models\User;
use App\Models\UserCredential;
use App\Models\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuditLogController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(AuditLog::query()->orderByDesc('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $record = AuditLog::create($request->all());

        return response()->json($record, 201);
    }

    public function show(int $id): JsonResponse
    {
        $record = AuditLog::findOrFail($id);

        return response()->json($record);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = AuditLog::findOrFail($id);
        $record->update($request->all());

        return response()->json($record);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = AuditLog::findOrFail($id);
        $record->delete();

        return response()->json(null, 204);
    }
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in(['Donor', 'Organization'])],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'status' => 'active',
            ]);

            UserCredential::create([
                'user_id' => $user->id,
                'password' => Hash::make($data['password']),
            ]);

            $role = Role::firstOrCreate(['role_name' => $data['role']]);

            UserRole::create([
                'user_id' => $user->id,
                'role_id' => $role->id,
            ]);

            return $user;
        });

        return response()->json([
            'message' => 'Registered successfully',
            'token' => Str::random(60),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $data['role'],
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            return response()->json([
                'message' => 'Invalid email or password.',
                'errors' => [
                    'email' => ['The provided credentials are incorrect.'],
                ],
            ], 422);
        }

        $credential = UserCredential::where('user_id', $user->id)->first();
        if (!$credential || !Hash::check($data['password'], $credential->password)) {
            return response()->json([
                'message' => 'Invalid email or password.',
                'errors' => [
                    'password' => ['The provided credentials are incorrect.'],
                ],
            ], 422);
        }

        $credential->last_login = now();
        $credential->save();

        $roleName = UserRole::query()
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $user->id)
            ->value('roles.role_name') ?? 'Donor';

        return response()->json([
            'message' => 'Logged in successfully',
            'token' => Str::random(60),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $roleName,
            ],
        ]);
    }
}
