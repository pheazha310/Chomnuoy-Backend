<?php

namespace Database\Seeders;

use App\Models\DonationTrend;
use Illuminate\Database\Seeder;

class DonationTrendSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $year = now()->year;

        $rows = [
            ['month_index' => 1, 'month' => 'Jan', 'financial' => 32, 'material' => 24, 'year' => $year],
            ['month_index' => 2, 'month' => 'Feb', 'financial' => 44, 'material' => 35, 'year' => $year],
            ['month_index' => 3, 'month' => 'Mar', 'financial' => 36, 'material' => 47, 'year' => $year],
            ['month_index' => 4, 'month' => 'Apr', 'financial' => 60, 'material' => 32, 'year' => $year],
            ['month_index' => 5, 'month' => 'May', 'financial' => 52, 'material' => 63, 'year' => $year],
            ['month_index' => 6, 'month' => 'Jun', 'financial' => 72, 'material' => 39, 'year' => $year],
            ['month_index' => 7, 'month' => 'Jul', 'financial' => 90, 'material' => 10, 'year' => $year],
            ['month_index' => 8, 'month' => 'Aug', 'financial' => 87, 'material' => 60, 'year' => $year],
            ['month_index' => 9, 'month' => 'Sep', 'financial' => 67, 'material' => 46, 'year' => $year],
            ['month_index' => 10, 'month' => 'Oct', 'financial' => 94, 'material' => 39, 'year' => $year],
            ['month_index' => 11, 'month' => 'Nov', 'financial' => 82, 'material' => 39, 'year' => $year],
            ['month_index' => 12, 'month' => 'Dec', 'financial' => 79, 'material' => 85, 'year' => $year],
        ];

        foreach ($rows as $row) {
            DonationTrend::firstOrCreate(
                ['year' => $row['year'], 'month_index' => $row['month_index']],
                $row
            );
        }
    }
}
