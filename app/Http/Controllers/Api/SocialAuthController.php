<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    protected array $providers = ['google', 'facebook'];

    public function status(): JsonResponse
    {
        return response()->json([
            'providers' => [
                'google' => [
                    'configured' => filled((string) env('GOOGLE_CLIENT_ID')),
                ],
                'facebook' => [
                    'configured' => filled((string) env('FACEBOOK_CLIENT_ID')) && filled((string) env('FACEBOOK_CLIENT_SECRET')),
                ],
            ],
        ]);
    }

    public function redirect(string $provider): RedirectResponse
    {
        if (!in_array($provider, $this->providers, true)) {
            abort(404);
        }

        return Socialite::driver($provider)->stateless()->redirect();
    }

    public function token(Request $request, string $provider): JsonResponse
    {
        if (!in_array($provider, $this->providers, true)) {
            abort(404);
        }

        $validated = $request->validate([
            'credential' => ['required', 'string'],
        ]);

        try {
            $socialUser = Socialite::driver($provider)->stateless()->userFromToken($validated['credential']);
            return response()->json($this->buildSocialLoginPayload($socialUser));
        } catch (\Throwable $e) {
            Log::error('Social token login failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage() ?: 'Social login failed. Please try again.',
            ], 422);
        }
    }

    public function callback(string $provider): RedirectResponse
    {
        if (!in_array($provider, $this->providers, true)) {
            abort(404);
        }

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
            return $this->redirectToFrontendPayload($this->buildSocialLoginPayload($socialUser));
        } catch (\Throwable $e) {
            Log::error('Social login failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return $this->redirectToFrontendError('Social login failed. Please try again.');
        }
    }

    protected function redirectToFrontendPayload(array $payload): RedirectResponse
    {
        $frontend = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');
        $encoded = base64_encode(json_encode($payload));
        
        return redirect("{$frontend}/oauth/callback?payload=" . urlencode($encoded));
    }

    protected function buildSocialLoginPayload(object $socialUser): array
    {
        $email = strtolower(trim((string) ($socialUser->getEmail() ?? '')));

        if (!$email) {
            throw new \RuntimeException('Unable to read email from provider.');
        }

        $organization = Organization::whereRaw('LOWER(email) = ?', [$email])->first();
        if ($organization) {
            return [
                'message' => 'Login successful',
                'account_type' => 'Organization',
                'user' => null,
                'organization' => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'email' => $organization->email,
                    'avatar' => $socialUser->getAvatar(),
                    'avatar_url' => $socialUser->getAvatar(),
                    'verified_status' => $organization->verified_status,
                ],
            ];
        }

        $role = Role::firstOrCreate(['role_name' => 'Donor']);

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $socialUser->getName() ?: 'Social User',
                'password' => Hash::make('password'),
                'status' => 'active',
                'role_id' => $role->id,
            ]
        );

        $user->last_seen_at = now();
        if (!$user->name && $socialUser->getName()) {
            $user->name = $socialUser->getName();
        }
        if (!$user->password) {
            $user->password = Hash::make('password');
        }
        $user->save();

        return [
            'message' => 'Login successful',
            'account_type' => 'Donor',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $socialUser->getAvatar(),
            ],
            'organization' => null,
        ];
    }

    protected function redirectToFrontendError(string $message): RedirectResponse
    {
        $frontend = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');
        
        return redirect("{$frontend}/oauth/callback?error=" . urlencode($message));
    }
}
