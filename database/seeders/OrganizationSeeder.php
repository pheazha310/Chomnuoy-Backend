<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = [
            [
                'name' => 'Bright Future Education Center',
                'email' => 'education@chomnuoy.org',
                'category_name' => 'Education',
                'location' => 'Phnom Penh',
                'latitude' => 11.5564,
                'longitude' => 104.9282,
                'description' => 'Supports students with scholarships, school supplies, and community learning programs.',
                'verified_status' => 'verified',
            ],
            [
                'name' => 'CareBridge Health Initiative',
                'email' => 'health@chomnuoy.org',
                'category_name' => 'Healthcare',
                'location' => 'Siem Reap',
                'latitude' => 13.3671,
                'longitude' => 103.8448,
                'description' => 'Connects rural families with clinics, medicine, and preventive health outreach.',
                'verified_status' => 'verified',
            ],
            [
                'name' => 'Hands of Hope Relief Network',
                'email' => 'relief@chomnuoy.org',
                'category_name' => 'Disaster Relief',
                'location' => 'Battambang',
                'latitude' => 13.0957,
                'longitude' => 103.2022,
                'description' => 'Delivers emergency supplies, food packages, and shelter assistance during crises.',
                'verified_status' => 'verified',
            ],
        ];

        foreach ($organizations as $organizationData) {
            $category = Category::query()->where('category_name', $organizationData['category_name'])->firstOrFail();

            Organization::query()->updateOrCreate(
                ['email' => $organizationData['email']],
                [
                    'name' => $organizationData['name'],
                    'password' => Hash::make('chomnuoy-org-123'),
                    'category_id' => $category->id,
                    'location' => $organizationData['location'],
                    'latitude' => $organizationData['latitude'],
                    'longitude' => $organizationData['longitude'],
                    'description' => $organizationData['description'],
                    'verified_status' => $organizationData['verified_status'],
                ]
            );
        }
    }
}
