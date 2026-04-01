<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Organization;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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

    public function test_database_seeder_populates_organizations_endpoint(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder']);

        $response = $this->getJson('/api/organizations');

        $response
            ->assertOk()
            ->assertJsonCount(3);

        $this->assertDatabaseHas('organizations', [
            'email' => 'education@chomnuoy.org',
            'verified_status' => 'verified',
        ]);
    }

    public function test_organization_avatar_url_uses_api_files_endpoint_and_https(): void
    {
        config([
            'app.url' => 'https://chomnuoy-backend-1.onrender.com',
        ]);

        $category = Category::create([
            'category_name' => 'Healthcare',
        ]);

        $organization = Organization::create([
            'name' => 'Avatar Test Organization',
            'email' => 'avatar-org@example.com',
            'password' => bcrypt('secret123'),
            'category_id' => $category->id,
            'location' => 'Phnom Penh',
            'description' => 'Avatar URL test',
            'avatar_path' => 'avatars/test image.jpg',
        ]);

        $this->assertSame(
            'https://chomnuoy-backend-1.onrender.com/api/files/avatars/test%20image.jpg',
            $organization->avatar_url
        );
    }

    public function test_payment_generate_returns_qr_payload(): void
    {
        config([
            'services.bakong.merchant.bakong_id' => 'merchant@bank',
            'services.bakong.merchant.name' => 'Chomnuoy',
            'services.bakong.merchant.city' => 'PHNOM PENH',
        ]);

        $this->postJson('/api/payment/generate', [
            'amount' => 1.50,
            'currency' => 'USD',
            'bill_number' => 'INV-1001',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('payment_persisted', true)
            ->assertJsonPath('warning', null)
            ->assertJsonStructure(['qr_code', 'md5', 'payment_id', 'expires_at']);
    }

    public function test_payment_generate_still_returns_qr_when_payment_persistence_fails(): void
    {
        config([
            'services.bakong.merchant.bakong_id' => 'merchant@bank',
            'services.bakong.merchant.name' => 'Chomnuoy',
            'services.bakong.merchant.city' => 'PHNOM PENH',
        ]);

        Schema::drop('payments');

        $this->postJson('/api/payment/generate', [
            'amount' => 2.00,
            'currency' => 'USD',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('payment_persisted', false)
            ->assertJsonPath('payment_id', null)
            ->assertJsonPath('warning', 'Payment persistence failed on the server.');
    }
}
