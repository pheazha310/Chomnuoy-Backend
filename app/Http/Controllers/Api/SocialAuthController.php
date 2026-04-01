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
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    protected array $providers = ['google', 'facebook'];

    public function status(): JsonResponse
    {
        return response()->json([
            'providers' => [
                'google' => [
                    'configured' => filled((string) env('GOOGLE_CLIENT_ID')) && filled((string) env('GOOGLE_CLIENT_SECRET')),
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

    public function callback(string $provider): RedirectResponse
    {
        if (!in_array($provider, $this->providers, true)) {
            abort(404);
        }

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
            $email = strtolower(trim((string) ($socialUser->getEmail() ?? '')));

            if (!$email) {
                return $this->redirectToFrontendError('Unable to read email from provider.');
            }

            if (Organization::whereRaw('LOWER(email) = ?', [$email])->exists()) {
                return $this->redirectToFrontendError('This email belongs to an organization account.');
            }

            $role = Role::firstOrCreate(['role_name' => 'Donor']);

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $socialUser->getName() ?: 'Social User',
                    'status' => 'active',
                    'role_id' => $role->id,
                ]
            );

            $user->last_seen_at = now();
            if (!$user->name && $socialUser->getName()) {
                $user->name = $socialUser->getName();
            }
            $user->save();

            $payload = [
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

            return $this->redirectToFrontendPayload($payload);
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

    protected function redirectToFrontendError(string $message): RedirectResponse
    {
        $frontend = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');
        
        return redirect("{$frontend}/oauth/callback?error=" . urlencode($message));
    }
}
