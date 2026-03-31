<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Display the specified user profile.
     */
    public function show(User $user): JsonResponse
    {
        $activities = $this->profileActivityModel()
            ? $this->profileActivityModel()::where('user_id', $user->id)
                ->orderByDesc('occurred_at')
                ->limit(10)
                ->get()
            : collect();

        return response()->json([
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'title' => $user->title,
                'email' => $user->email,
                'phone' => $user->phone,
                'location' => $user->location,
                'bio' => $user->bio,
                'website' => $user->website,
                'linkedin_url' => $user->linkedin_url,
                'avatar_url' => $user->avatar_url,
                'skills' => $user->skills ?? [],
                'status' => $user->status,
                'role_id' => $user->role_id,
                'created_at' => $user->created_at,
                'last_seen_at' => $user->last_seen_at,
            ],
            'network_stats' => [
                'rank' => $user->network_rank ?? 'Top 10%',
                'connections_count' => $user->connections_count ?? 0,
                'project_reviews_count' => $user->project_reviews_count ?? 0,
            ],
            'recent_activities' => $activities->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'type' => $activity->type,
                    'title' => $activity->title,
                    'description' => $activity->description,
                    'icon' => $activity->icon,
                    'status' => $activity->status,
                    'time_ago' => $this->getTimeAgo($activity->occurred_at),
                ];
            }),
        ]);
    }

    /**
     * Update the specified user profile.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'location' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'website' => ['nullable', 'url', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:50'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $persistableColumns = array_flip([
            'name',
            'title',
            'email',
            'phone',
            'location',
            'bio',
            'website',
            'linkedin_url',
            'skills',
            'avatar_path',
        ]);
        foreach (array_keys($persistableColumns) as $column) {
            if (!Schema::hasColumn('users', $column)) {
                unset($persistableColumns[$column]);
            }
        }

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            if (Schema::hasColumn('users', 'avatar_path') && $user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            if (isset($persistableColumns['avatar_path'])) {
                $data['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
            }
        }

        // Convert skills array to JSON
        if (isset($data['skills'])) {
            if (is_string($data['skills'])) {
                $decodedSkills = json_decode($data['skills'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedSkills)) {
                    $data['skills'] = $decodedSkills;
                } else {
                    $data['skills'] = array_values(array_filter(array_map('trim', explode(',', $data['skills']))));
                }
            }
            $data['skills'] = json_encode(array_values((array) $data['skills']));
        }

        $user->update(array_intersect_key($data, $persistableColumns));

        return $this->show($user->fresh());
    }

    /**
     * Update user password.
     */
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

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    /**
     * Upload avatar for user.
     */
    public function uploadAvatar(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar_path' => $path]);

        return response()->json([
            'message' => 'Avatar uploaded successfully.',
            'avatar_url' => $user->fresh()->avatar_url,
        ]);
    }

    /**
     * Get current authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }
        return $this->show($user);
    }

    /**
     * Update current authenticated user profile.
     */
    public function updateMe(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }
        return $this->update($request, $user);
    }

    /**
     * Add a profile activity.
     */
    public function addActivity(Request $request, User $user): JsonResponse
    {
        $activityModel = $this->profileActivityModel();
        if ($activityModel === null) {
            return response()->json([
                'message' => 'Profile activity tracking is not available.',
            ], 404);
        }

        $data = $request->validate([
            'type' => ['required', 'string', 'in:upload,review,certification,project,update,achievement'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $activity = $activityModel::create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?? null,
            'status' => $data['status'] ?? null,
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]);

        return response()->json([
            'message' => 'Activity added successfully.',
            'activity' => $activity,
        ], 201);
    }

    /**
     * Delete a profile activity.
     */
    public function deleteActivity(Request $request, User $user, int $activityId): JsonResponse
    {
        $activityModel = $this->profileActivityModel();
        if ($activityModel === null) {
            return response()->json([
                'message' => 'Profile activity tracking is not available.',
            ], 404);
        }

        $activity = $activityModel::where('user_id', $user->id)
            ->where('id', $activityId)
            ->first();

        if (!$activity) {
            return response()->json([
                'message' => 'Activity not found.',
            ], 404);
        }

        $activity->delete();

        return response()->json([
            'message' => 'Activity deleted successfully.',
        ]);
    }

    /**
     * Get time ago string.
     */
    private function getTimeAgo($datetime): string
    {
        $diff = $datetime->diffForHumans();
        return str_replace([' ago', 'from now'], ['', ''], $diff);
    }

    private function profileActivityModel(): ?string
    {
        $modelClass = \App\Models\ProfileActivity::class;

        if (!class_exists($modelClass) || !Schema::hasTable('profile_activities')) {
            return null;
        }

        return $modelClass;
    }
}
