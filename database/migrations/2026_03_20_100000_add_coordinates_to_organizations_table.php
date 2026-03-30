<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (!Schema::hasColumn('organizations', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('location');
            }

            if (!Schema::hasColumn('organizations', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $drops = [];

            if (Schema::hasColumn('organizations', 'latitude')) {
                $drops[] = 'latitude';
            }

            if (Schema::hasColumn('organizations', 'longitude')) {
                $drops[] = 'longitude';
            }

            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
