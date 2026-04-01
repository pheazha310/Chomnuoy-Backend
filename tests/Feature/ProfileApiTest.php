<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Role;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    public function test_admin_profile_update_accepts_avatar_only_payload(): void
    {
        Storage::fake('public');

        $role = Role::create(['role_name' => 'Admin']);
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $response = $this->post('/api/admin/profile/'.$user->id, [
            'avatar' => $this->fakePngUpload(),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('basic_information.id', $user->id);

        $this->assertNotNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertExists($user->fresh()->avatar_path);
    }

    public function test_admin_avatar_dedicated_endpoint_uploads_image(): void
    {
        Storage::fake('public');

        $role = Role::create(['role_name' => 'Admin']);
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $response = $this->post('/api/admin/profile/'.$user->id.'/avatar', [
            'avatar' => $this->fakePngUpload(),
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['message', 'avatar_url', 'avatar_path']);

        $this->assertNotNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertExists($user->fresh()->avatar_path);
    }

    public function test_payment_status_accepts_md5(): void
    {
        $payment = Payment::create([
            'md5' => 'abc123md5',
            'qr_code' => 'dummy-qr',
            'amount' => 10,
            'currency' => 'USD',
            'status' => 'PENDING',
            'expires_at' => now()->subMinute(),
            'check_attempts' => 0,
        ]);

        $response = $this->postJson('/api/payment/status', [
            'md5' => $payment->md5,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('payment.id', $payment->id)
            ->assertJsonPath('payment.md5', $payment->md5);
    }

    public function test_payment_status_requires_payment_identifier(): void
    {
        $response = $this->postJson('/api/payment/status', []);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_successful_payment_status_creates_donation_record_from_metadata(): void
    {
        $category = Category::create(['category_name' => 'Health']);
        $organization = Organization::create([
            'name' => 'Health Org',
            'email' => 'health@example.com',
            'password' => bcrypt('password123'),
            'category_id' => $category->id,
            'verified_status' => 'verified',
        ]);

        $role = Role::create(['role_name' => 'Donor']);
        $user = User::create([
            'name' => 'Donor',
            'email' => 'donor@example.com',
            'password' => bcrypt('password123'),
            'status' => 'active',
            'role_id' => $role->id,
        ]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'md5' => 'success-md5',
            'qr_code' => 'dummy-qr',
            'amount' => 15,
            'currency' => 'USD',
            'status' => 'SUCCESS',
            'transaction_reference' => json_encode([
                'source' => 'qr_checkout',
                'user_id' => $user->id,
                'organization_id' => $organization->id,
                'donation_type' => 'money',
            ]),
            'expires_at' => now()->addMinutes(5),
            'check_attempts' => 0,
        ]);

        $response = $this->postJson('/api/payment/status', [
            'md5' => $payment->md5,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('donations', [
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'amount' => '15.00',
            'donation_type' => 'money',
            'status' => 'completed',
        ]);
    }

    private function fakePngUpload(): \Illuminate\Http\UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO7Z0fQAAAAASUVORK5CYII='
        ));

        return new \Illuminate\Http\UploadedFile(
            $path,
            'avatar.png',
            'image/png',
            null,
            true
        );
    }
}
