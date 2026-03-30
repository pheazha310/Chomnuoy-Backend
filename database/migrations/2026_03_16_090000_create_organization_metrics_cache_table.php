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
        Schema::create('organization_metrics_cache', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->nullable()
                ->constrained('organizations')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_revenue', 15, 2)->default(0);
            $table->unsignedInteger('active_donors')->default(0);
            $table->unsignedInteger('material_units')->default(0);
            $table->decimal('avg_donation', 15, 2)->default(0);
            $table->decimal('previous_total_revenue', 15, 2)->default(0);
            $table->unsignedInteger('previous_active_donors')->default(0);
            $table->unsignedInteger('previous_material_units')->default(0);
            $table->decimal('previous_avg_donation', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'period_start', 'period_end'], 'org_metrics_cache_period_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_metrics_cache');
    }
};
