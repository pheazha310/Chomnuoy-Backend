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
        Schema::create('material_province_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->nullable()
                ->constrained('organizations')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('province', 120);
            $table->integer('total_items')->default(0);
            $table->string('organization_name', 150)->nullable();
            $table->string('status', 50)->default('On Track');
            $table->timestamps();

            $table->index(['organization_id', 'province'], 'material_province_dist_org_province_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_province_distributions');
    }
};
