<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payments')) {
            return;
        }

        if (Schema::hasColumn('payments', 'donation_id')) {
            DB::statement('ALTER TABLE payments ALTER COLUMN donation_id DROP NOT NULL');
        }

        if (Schema::hasColumn('payments', 'payment_method_id')) {
            DB::statement('ALTER TABLE payments ALTER COLUMN payment_method_id DROP NOT NULL');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('payments')) {
            return;
        }

        if (Schema::hasColumn('payments', 'payment_method_id')) {
            DB::statement('UPDATE payments SET payment_method_id = 1 WHERE payment_method_id IS NULL');
            DB::statement('ALTER TABLE payments ALTER COLUMN payment_method_id SET NOT NULL');
        }

        if (Schema::hasColumn('payments', 'donation_id')) {
            DB::statement('UPDATE payments SET donation_id = 1 WHERE donation_id IS NULL');
            DB::statement('ALTER TABLE payments ALTER COLUMN donation_id SET NOT NULL');
        }
    }
};