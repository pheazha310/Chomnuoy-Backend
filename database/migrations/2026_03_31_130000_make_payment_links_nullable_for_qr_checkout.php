<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function driver(): string
    {
        return Schema::getConnection()->getDriverName();
    }

    private function makeNullable(string $column): void
    {
        $driver = $this->driver();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY {$column} BIGINT UNSIGNED NULL");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE payments ALTER COLUMN {$column} DROP NOT NULL");
        }
    }

    private function makeRequired(string $column): void
    {
        $driver = $this->driver();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY {$column} BIGINT UNSIGNED NOT NULL");
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE payments ALTER COLUMN {$column} SET NOT NULL");
        }
    }

    public function up(): void
    {
        if (!Schema::hasTable('payments')) {
            return;
        }

        if (Schema::hasColumn('payments', 'donation_id')) {
            $this->makeNullable('donation_id');
        }

        if (Schema::hasColumn('payments', 'payment_method_id')) {
            $this->makeNullable('payment_method_id');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('payments')) {
            return;
        }

        if (Schema::hasColumn('payments', 'payment_method_id')) {
            DB::statement('UPDATE payments SET payment_method_id = 1 WHERE payment_method_id IS NULL');
            $this->makeRequired('payment_method_id');
        }

        if (Schema::hasColumn('payments', 'donation_id')) {
            DB::statement('UPDATE payments SET donation_id = 1 WHERE donation_id IS NULL');
            $this->makeRequired('donation_id');
        }
    }
};
