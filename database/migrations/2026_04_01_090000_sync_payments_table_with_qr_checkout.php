<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'donation_id')) {
                $table->foreignId('donation_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('donations')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('payments', 'payment_method_id')) {
                $table->foreignId('payment_method_id')
                    ->nullable()
                    ->after('donation_id')
                    ->constrained('payment_methods')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('payments', 'transaction_reference')) {
                $table->string('transaction_reference')->nullable()->after('payment_method_id');
            }

            if (!Schema::hasColumn('payments', 'payment_status')) {
                $table->string('payment_status', 50)->nullable()->after('transaction_reference');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'payment_status')) {
                $table->dropColumn('payment_status');
            }

            if (Schema::hasColumn('payments', 'transaction_reference')) {
                $table->dropColumn('transaction_reference');
            }

            if (Schema::hasColumn('payments', 'payment_method_id')) {
                $table->dropConstrainedForeignId('payment_method_id');
            }

            if (Schema::hasColumn('payments', 'donation_id')) {
                $table->dropConstrainedForeignId('donation_id');
            }
        });
    }
};
