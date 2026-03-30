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
        Schema::create('regional_project_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->nullable()
                ->constrained('organizations')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('province', 120);
            $table->string('organization_name', 150)->nullable();
            $table->integer('campaigns')->default(0);
            $table->string('impact', 120)->nullable();
            $table->string('status', 50)->default('Medium Impact');
            $table->timestamps();

            $table->index(['organization_id', 'province'], 'regional_project_status_org_province_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regional_project_statuses');
    }
};
