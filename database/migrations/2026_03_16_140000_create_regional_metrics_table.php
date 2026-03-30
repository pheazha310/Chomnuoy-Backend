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
        Schema::create('regional_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->nullable()
                ->constrained('organizations')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('title', 120);
            $table->string('value', 120);
            $table->string('delta', 50)->nullable();
            $table->boolean('positive')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'title'], 'regional_metrics_org_title_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regional_metrics');
    }
};
