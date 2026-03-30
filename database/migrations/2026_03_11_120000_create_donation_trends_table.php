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
        Schema::create('donation_trends', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('month_index');
            $table->string('month', 3);
            $table->unsignedInteger('financial');
            $table->unsignedInteger('material');
            $table->unsignedSmallInteger('year')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donation_trends');
    }
};
