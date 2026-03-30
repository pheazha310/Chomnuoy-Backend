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
                $table->string('recipient_type', 50)->nullable()->after('sender_email');
            }
            if (!Schema::hasColumn('notifications', 'recipient_id')) {
                $table->unsignedBigInteger('recipient_id')->nullable()->after('recipient_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            foreach (['recipient_id', 'recipient_type'] as $column) {
                if (Schema::hasColumn('notifications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
