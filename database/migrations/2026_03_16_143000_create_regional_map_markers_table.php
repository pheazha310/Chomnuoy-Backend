<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('regional_map_markers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->nullable()
                ->constrained('organizations')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('name', 120);
            $table->decimal('latitude', 10, 6);
            $table->decimal('longitude', 10, 6);
            $table->string('impact', 30)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'name'], 'regional_map_markers_org_name_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regional_map_markers');
    }
};
