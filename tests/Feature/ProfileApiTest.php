<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_fetch_profile_me(): void
    {
        $role = Role::create(['role_name' => 'Donor']);
        $user = User::create([
            'name' => 'Ella Kheza',
            'email' => 'ella@example.com',
            'password' => bcrypt('password123'),
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $token = $user->createToken('frontend')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/profile/me');

        $response
            ->assertOk()
            ->assertJsonPath('profile.id', $user->id)
            ->assertJsonPath('profile.email', 'ella@example.com');
    }

    public function test_avatar_url_stays_https_when_app_url_is_https(): void
    {
        config()->set('app.url', 'https://chomnuoy-backend-1.onrender.com');

        $role = Role::create(['role_name' => 'Donor']);
        $user = User::create([
            'name' => 'Ella Kheza',
            'email' => 'ella@example.com',
            'password' => bcrypt('password123'),
            'status' => 'active',
            'role_id' => $role->id,
            'avatar_path' => 'avatars/example.jpg',
        ]);

        $this->assertSame(
            'https://chomnuoy-backend-1.onrender.com/storage/avatars/example.jpg',
            $user->avatar_url
        );
    }

    public function test_api_root_includes_organizations_collection(): void
    {
        $category = Category::create(['category_name' => 'Education']);

        Organization::create([
            'name' => 'Partner Org',
            'email' => 'partner@example.com',
            'password' => bcrypt('password123'),
            'category_id' => $category->id,
            'location' => 'Phnom Penh',
            'description' => 'Community support organization',
            'verified_status' => 'verified',
        ]);

        $response = $this->getJson('/api');

        $response
            ->assertOk()
            ->assertJsonPath('service', 'chomnuoy-backend')
            ->assertJsonPath('organizations_count', 1)
            ->assertJsonPath('organizations.0.name', 'Partner Org');
    }
}
