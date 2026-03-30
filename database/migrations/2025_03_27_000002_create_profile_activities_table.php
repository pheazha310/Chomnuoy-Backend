<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('type'); // e.g., 'upload', 'review', 'certification', 'project'
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('icon')->nullable(); // icon identifier
            $table->string('status')->nullable(); // e.g., 'completed', 'pending'
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_activities');
    }
};
