<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrganizationLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_can_be_found_by_its_email(): void
    {
        $category = Category::create([
            'category_name' => 'Education',
        ]);

        $organization = Organization::create([
            'name' => 'PNC',
            'email' => 'org@example.com',
            'password' => Hash::make('secret123'),
            'category_id' => $category->id,
            'location' => 'Phnom Penh',
            'description' => 'Profile lookup test',
        ]);

        $this->getJson('/api/organizations/by-email?email=ORG@example.com')
            ->assertOk()
            ->assertJsonPath('id', $organization->id)
            ->assertJsonPath('email', 'org@example.com');
    }

    public function test_organization_can_be_resolved_from_admin_email_when_user_name_matches_one_organization(): void
    {
        $category = Category::create([
            'category_name' => 'Education',
        ]);

        $adminRole = Role::create([
            'role_name' => 'Admin',
        ]);

        $organization = Organization::create([
            'name' => 'PNC',
            'email' => 'organization@example.com',
            'password' => Hash::make('secret123'),
            'category_id' => $category->id,
            'location' => 'Phnom Penh',
            'description' => 'Profile lookup fallback test',
        ]);

        User::create([
            'name' => 'PNC',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'role_id' => $adminRole->id,
        ]);

        $this->getJson('/api/organizations/by-email?email=admin@example.com')
            ->assertOk()
            ->assertJsonPath('id', $organization->id)
            ->assertJsonPath('name', 'PNC');
    }

    public function test_organization_lookup_returns_null_when_user_name_match_is_ambiguous(): void
    {
        $category = Category::create([
            'category_name' => 'Education',
        ]);

        $adminRole = Role::create([
            'role_name' => 'Admin',
        ]);

        Organization::create([
            'name' => 'PNC',
            'email' => 'organization-a@example.com',
            'password' => Hash::make('secret123'),
            'category_id' => $category->id,
            'location' => 'Phnom Penh',
            'description' => 'First organization',
        ]);

        Organization::create([
            'name' => 'PNC',
            'email' => 'organization-b@example.com',
            'password' => Hash::make('secret123'),
            'category_id' => $category->id,
            'location' => 'Battambang',
            'description' => 'Second organization',
        ]);

        User::create([
            'name' => 'PNC',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'role_id' => $adminRole->id,
        ]);

        $this->getJson('/api/organizations/by-email?email=admin@example.com')
            ->assertOk()
            ->assertContent('{}');
    }
}
