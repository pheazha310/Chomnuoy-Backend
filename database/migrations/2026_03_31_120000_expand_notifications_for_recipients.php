<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('notifications', 'recipient_type')) {
                $table->string('recipient_type', 50)->nullable()->after('user_id');
            }

            if (!Schema::hasColumn('notifications', 'recipient_id')) {
                $table->unsignedBigInteger('recipient_id')->nullable()->after('recipient_type');
            }

            if (!Schema::hasColumn('notifications', 'sender_type')) {
                $table->string('sender_type', 50)->nullable()->after('recipient_id');
            }

            if (!Schema::hasColumn('notifications', 'sender_name')) {
                $table->string('sender_name')->nullable()->after('sender_type');
            }

            if (!Schema::hasColumn('notifications', 'sender_email')) {
                $table->string('sender_email')->nullable()->after('sender_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            foreach (['sender_email', 'sender_name', 'sender_type', 'recipient_id', 'recipient_type'] as $column) {
                if (Schema::hasColumn('notifications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
