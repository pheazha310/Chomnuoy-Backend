<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminProfileController extends Controller
{
    public function show(User $user): JsonResponse
    {
        $roleName = Role::query()
            ->where('id', $user->role_id)
            ->value('role_name') ?? 'Admin';

        return response()->json([
            'basic_information' => [
                'id' => $user->id,
                'name' => $user->name,
                'title' => $user->title,
                'email' => $user->email,
                'phone' => $user->phone,
                'bio' => $user->bio,
                'location' => $user->location,
                'website' => $user->website,
                'linkedin_url' => $user->linkedin_url,
                'skills' => json_decode($user->skills ?? '[]', true),
                'profile_picture' => $user->avatar_url,
                'avatar_path' => $user->avatar_path,
                'role' => $roleName,
                'status' => $user->status,
                'created_at' => $user->created_at,
                'last_seen_at' => $user->last_seen_at,
            ],
            'account_settings' => [
                'two_factor_enabled' => (bool) $user->two_factor_enabled,
            ],
            'role_permissions' => [
                'role' => $roleName,
                'view_assigned_permissions' => $this->permissionsForRole($roleName),
                'manage_roles' => $this->canManageRoles($roleName),
                'access_levels' => $this->accessLevelsForRole($roleName),
            ],
            'activity_log' => $this->activityLogForUser($user),
            'network_stats' => [
                'rank' => $user->network_rank ?? 'Top 10%',
                'connections_count' => $user->connections_count ?? 0,
                'project_reviews_count' => $user->project_reviews_count ?? 0,
            ],
            'platform_stats' => [
                'users_managed' => User::count(),
                'organizations_count' => \App\Models\Organization::count(),
                'total_donations' => \App\Models\Donation::where('status', 'completed')->sum('amount'),
            ],
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'linkedin_url' => ['nullable', 'string', 'max:255'],
            'skills' => ['nullable'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'two_factor_enabled' => ['nullable', 'boolean'],
        ]);

        // Handle skills as JSON
        if (isset($data['skills']) && is_array($data['skills'])) {
            $data['skills'] = json_encode($data['skills']);
        } elseif (isset($data['skills']) && is_string($data['skills'])) {
            $decodedSkills = json_decode($data['skills'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedSkills)) {
                $data['skills'] = json_encode(array_values($decodedSkills));
            } else {
                $data['skills'] = json_encode(array_values(array_filter(array_map('trim', explode(',', $data['skills'])))));
            }
        }

        $persistableColumns = array_flip([
            'name',
            'title',
            'email',
            'phone',
            'bio',
            'location',
            'website',
            'linkedin_url',
            'skills',
            'avatar_path',
            'two_factor_enabled',
        ]);
        foreach (array_keys($persistableColumns) as $column) {
            if (!Schema::hasColumn('users', $column)) {
                unset($persistableColumns[$column]);
            }
        }

        if ($request->hasFile('avatar')) {
            if (Schema::hasColumn('users', 'avatar_path') && $user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }

            if (isset($persistableColumns['avatar_path'])) {
                $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
            }
        }

        if (array_key_exists('two_factor_enabled', $data) && !Schema::hasColumn('users', 'two_factor_enabled')) {
            unset($data['two_factor_enabled']);
        }

        $user->update(array_intersect_key($data, $persistableColumns));

        $this->recordAuditLog(
            $user->id,
            $request,
            'Updated admin profile information',
            'users',
        );

        return $this->show($user->fresh());
    }

    public function updatePassword(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        if (!Hash::check($data['current_password'], (string) $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
                'errors' => [
                    'current_password' => ['Current password is incorrect.'],
                ],
            ], 422);
        }

        $user->password = Hash::make($data['new_password']);
        $user->save();

        $this->recordAuditLog(
            $user->id,
            $request,
            'Changed admin password',
            'users',
        );

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    private function permissionsForRole(string $roleName): array
    {
        return match (strtolower($roleName)) {
            'super admin' => ['view dashboard', 'manage users', 'manage organizations', 'approve requests', 'manage roles', 'read', 'write', 'delete'],
            'admin' => ['view dashboard', 'manage users', 'manage organizations', 'approve requests', 'read', 'write'],
            default => ['read'],
        };
    }

    private function accessLevelsForRole(string $roleName): array
    {
        return match (strtolower($roleName)) {
            'super admin' => ['read', 'write', 'delete', 'approve'],
            'admin' => ['read', 'write', 'approve'],
            default => ['read'],
        };
    }

    private function canManageRoles(string $roleName): bool
    {
        return strtolower($roleName) === 'super admin';
    }

    private function activityLogForUser(User $user): array
    {
        return AuditLog::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(12)
            ->get()
            ->map(function (AuditLog $log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'affected_table' => $log->affected_table,
                    'created_at' => $log->created_at,
                    'ip_address' => $log->ip_address ?? null,
                ];
            })
            ->values()
            ->all();
    }

    private function recordAuditLog(int $userId, Request $request, string $action, string $table): void
    {
        $payload = [
            'user_id' => $userId,
            'action' => $action,
            'affected_table' => $table,
        ];

        if (Schema::hasColumn('audit_logs', 'ip_address')) {
            $payload['ip_address'] = $request->ip();
        }

        AuditLog::create($payload);
    }
}
