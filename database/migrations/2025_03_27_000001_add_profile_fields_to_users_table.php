<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('title')->nullable()->after('name');
            $table->text('bio')->nullable()->after('title');
            $table->string('location')->nullable()->after('bio');
            $table->string('website')->nullable()->after('location');
            $table->string('linkedin_url')->nullable()->after('website');
            $table->json('skills')->nullable()->after('linkedin_url');
            $table->integer('connections_count')->default(0)->after('skills');
            $table->integer('project_reviews_count')->default(0)->after('connections_count');
            $table->string('network_rank')->nullable()->after('project_reviews_count');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'title',
                'bio',
                'location',
                'website',
                'linkedin_url',
                'skills',
                'connections_count',
                'project_reviews_count',
                'network_rank',
            ]);
        });
    }
};
