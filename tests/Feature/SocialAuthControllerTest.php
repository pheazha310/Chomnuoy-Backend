<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_token_login_returns_configuration_error_when_no_client_ids_are_set(): void
    {
        Config::set('services.google.client_id', null);
        putenv('GOOGLE_ALLOWED_CLIENT_IDS');

        $this->postJson('/api/auth/google/token', [
            'credential' => 'fake-token',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'google_not_configured');
    }

    public function test_google_token_login_accepts_client_id_from_allow_list(): void
    {
        Config::set('services.google.client_id', null);
        putenv('GOOGLE_ALLOWED_CLIENT_IDS=frontend-client-id.apps.googleusercontent.com');

        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'aud' => 'frontend-client-id.apps.googleusercontent.com',
                'email' => 'donor@example.com',
                'email_verified' => true,
                'name' => 'Donor User',
                'picture' => 'https://example.com/avatar.png',
            ], 200),
        ]);

        $this->postJson('/api/auth/google/token', [
            'credential' => 'fake-token',
        ])
            ->assertOk()
            ->assertJsonPath('account_type', 'Donor')
            ->assertJsonPath('user.email', 'donor@example.com');
    }

    public function test_google_token_login_returns_explicit_mismatch_error(): void
    {
        Config::set('services.google.client_id', 'backend-client-id.apps.googleusercontent.com');
        putenv('GOOGLE_ALLOWED_CLIENT_IDS=frontend-client-id.apps.googleusercontent.com');

        Http::fake([
            'https://oauth2.googleapis.com/tokeninfo*' => Http::response([
                'aud' => 'unexpected-client-id.apps.googleusercontent.com',
                'email' => 'donor@example.com',
                'email_verified' => true,
                'name' => 'Donor User',
            ], 200),
        ]);

        $this->postJson('/api/auth/google/token', [
            'credential' => 'fake-token',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'google_client_id_mismatch');
    }
}
