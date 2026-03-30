<?php

namespace Database\Seeders;

use App\Models\AdminSetting;
use Illuminate\Database\Seeder;

class AdminSettingsSeeder extends Seeder
{
    /**
     * Seed default admin settings used by the admin settings API.
     */
    public function run(): void
    {
        $settings = [
            [
                'setting_key' => 'platform_name',
                'section' => 'general',
                'setting_value' => 'Chomnuoy',
                'setting_type' => 'string',
                'description' => 'Public platform name displayed in admin experiences.',
            ],
            [
                'setting_key' => 'support_email',
                'section' => 'general',
                'setting_value' => 'support@chomnuoy.com',
                'setting_type' => 'string',
                'description' => 'Primary support email for administrators.',
            ],
            [
                'setting_key' => 'maintenance_mode',
                'section' => 'system',
                'setting_value' => '0',
                'setting_type' => 'boolean',
                'description' => 'Whether the platform is in maintenance mode.',
            ],
            [
                'setting_key' => 'default_currency',
                'section' => 'payments',
                'setting_value' => 'USD',
                'setting_type' => 'string',
                'description' => 'Default currency used in reporting and payments.',
            ],
            [
                'setting_key' => 'donations_enabled',
                'section' => 'payments',
                'setting_value' => '1',
                'setting_type' => 'boolean',
                'description' => 'Whether new donations are currently allowed.',
            ],
        ];

        foreach ($settings as $setting) {
            AdminSetting::query()->updateOrCreate(
                ['setting_key' => $setting['setting_key']],
                [
                    'section' => $setting['section'],
                    'setting_value' => $setting['setting_value'],
                    'setting_type' => $setting['setting_type'],
                    'description' => $setting['description'],
                ]
            );
        }
    }
}
