<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('campaigns', 'location')) {
                $table->string('location')->nullable()->after('end_date');
            }

            if (!Schema::hasColumn('campaigns', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('location');
            }

            if (!Schema::hasColumn('campaigns', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $drops = [];

            if (Schema::hasColumn('campaigns', 'latitude')) {
                $drops[] = 'latitude';
            }

            if (Schema::hasColumn('campaigns', 'longitude')) {
                $drops[] = 'longitude';
            }

            if (Schema::hasColumn('campaigns', 'location')) {
                $drops[] = 'location';
            }

            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
