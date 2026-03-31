<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Laravel')
            ->assertSee('Documentation');
    }

    public function test_api_root_returns_organizations(): void
    {
        $category = Category::create([
            'category_name' => 'Education',
        ]);

        Organization::create([
            'name' => 'Test Organization',
            'email' => 'org@example.com',
            'password' => bcrypt('secret123'),
            'category_id' => $category->id,
            'location' => 'Phnom Penh',
            'description' => 'Test description',
        ]);

        $this->getJson('/api')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('service', 'chomnuoy-backend')
            ->assertJsonCount(1, 'organizations')
            ->assertJsonCount(1, 'payload.organizations')
            ->assertJsonPath('organizations.0.name', 'Test Organization')
            ->assertJsonPath('payload.organizations.0.name', 'Test Organization')
            ->assertJsonMissingPath('organizations.0.password');
    }
}
