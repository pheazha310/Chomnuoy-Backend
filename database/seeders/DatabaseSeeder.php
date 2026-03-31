<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Role;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
        $donorRole = Role::firstOrCreate(['role_name' => 'Donor']);
        Role::firstOrCreate(['role_name' => 'Organization']);
        $adminRole = Role::firstOrCreate(['role_name' => 'Admin']);

        User::firstOrCreate(
            ['email' => 'chomnouy168@gmail.com'],
            [
                'name' => 'Admin',
                'phone' => null,
                'password' => Hash::make('chomnouy168'),
                'status' => 'active',
                'role_id' => $adminRole->id ?? $donorRole->id,
            ]
        );

        Category::firstOrCreate(['category_name' => 'Child Support']);
        Category::firstOrCreate(['category_name' => 'Disaster Relief']);
        Category::firstOrCreate(['category_name' => 'Education']);
        Category::firstOrCreate(['category_name' => 'Food & Nutrition']);
        Category::firstOrCreate(['category_name' => 'Healthcare']);
        Category::firstOrCreate(['category_name' => 'Hospital']);
        Category::firstOrCreate(['category_name' => 'school']);
    }
}                                                                                                   
