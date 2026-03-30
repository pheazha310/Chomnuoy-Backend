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
        Schema::create('regional_top_provinces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->nullable()
                ->constrained('organizations')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->integer('rank')->default(0);
            $table->string('province_name', 120);
            $table->integer('projects')->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('delta', 50)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'rank'], 'regional_top_prov_org_rank_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regional_top_provinces');
    }
};
