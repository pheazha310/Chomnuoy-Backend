<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    protected array $providers = ['google'];

    public function status(): JsonResponse
    {
        $providers = [];

        foreach ($this->providers as $provider) {
            $providers[$provider] = [
                'configured' => $this->hasProviderConfiguration($provider),
            ];
        }

        return response()->json([
            'providers' => $providers,
        ]);
    }

    public function redirect(string $provider): RedirectResponse
    {
        if (!in_array($provider, $this->providers, true)) {
            abort(404);
        }

        if (!$this->hasProviderConfiguration($provider)) {
            return $this->redirectToFrontendError(ucfirst($provider) . ' login is not configured yet.');
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
            $payload = $this->buildPayloadForUser(
                email: $socialUser->getEmail(),
                defaultName: $socialUser->getName() ?: 'Social User',
                avatar: $socialUser->getAvatar(),
            );

            return $this->redirectToFrontendPayload($payload);
        } catch (\Throwable $e) {
            Log::error('Social login failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return $this->redirectToFrontendError('Social login failed. Please try again.');
        }
    }

    public function googleTokenLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'credential' => ['required', 'string'],
        ]);

        $googleClientId = trim((string) Config::get('services.google.client_id', ''));
        if ($googleClientId === '') {
            return response()->json([
                'message' => 'Google login is not configured yet.',
            ], 422);
        }

        try {
            $response = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $validated['credential'],
            ]);

            if (!$response->ok()) {
                return response()->json([
                    'message' => 'Unable to verify Google login. Please try again.',
                ], 422);
            }

            $googleUser = $response->json();
            $audience = trim((string) ($googleUser['aud'] ?? ''));
            $email = (string) ($googleUser['email'] ?? '');
            $emailVerified = filter_var($googleUser['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($audience !== $googleClientId) {
                return response()->json([
                    'message' => 'Google login is not allowed for this application.',
                ], 422);
            }

            if (!$emailVerified || trim($email) === '') {
                return response()->json([
                    'message' => 'Google account email could not be verified.',
                ], 422);
            }

            $payload = $this->buildPayloadForUser(
                email: $email,
                defaultName: (string) ($googleUser['name'] ?? 'Google User'),
                avatar: (string) ($googleUser['picture'] ?? ''),
            );

            return response()->json($payload);
        } catch (\Throwable $e) {
            Log::error('Google token login failed', [
                'provider' => 'google',
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Google login failed. Please try again.',
            ], 500);
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

    protected function hasProviderConfiguration(string $provider): bool
    {
        if ($provider === 'google') {
            $clientId = trim((string) Config::get('services.google.client_id', ''));
            return $clientId !== '';
        }

        $clientId = trim((string) Config::get("services.{$provider}.client_id", ''));
        $clientSecret = trim((string) Config::get("services.{$provider}.client_secret", ''));
        $redirect = trim((string) Config::get("services.{$provider}.redirect", ''));

        return $clientId !== '' && $clientSecret !== '' && $redirect !== '';
    }

    protected function buildPayloadForUser(?string $email, string $defaultName, ?string $avatar = null): array
    {
        $normalizedEmail = strtolower(trim((string) $email));

        if ($normalizedEmail === '') {
            throw new \RuntimeException('Unable to read email from provider.');
        }

        if (Organization::whereRaw('LOWER(email) = ?', [$normalizedEmail])->exists()) {
            throw new \RuntimeException('This email belongs to an organization account.');
        }

        $role = Role::firstOrCreate(['role_name' => 'Donor']);

        $user = User::firstOrCreate(
            ['email' => $normalizedEmail],
            [
                'name' => $defaultName,
                'status' => 'active',
                'role_id' => $role->id,
            ]
        );

        $user->last_seen_at = now();
        if (!$user->name && $defaultName !== '') {
            $user->name = $defaultName;
        }
        $user->save();

        return [
            'message' => 'Login successful',
            'account_type' => 'Donor',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $avatar,
            ],
            'organization' => null,
        ];
    }
}
