<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'title')) {
                $table->string('title')->nullable()->after('name');
            }
            if (!Schema::hasColumn('users', 'bio')) {
                $table->text('bio')->nullable()->after('title');
            }
            if (!Schema::hasColumn('users', 'location')) {
                $table->string('location')->nullable()->after('bio');
            }
            if (!Schema::hasColumn('users', 'website')) {
                $table->string('website')->nullable()->after('location');
            }
            if (!Schema::hasColumn('users', 'linkedin_url')) {
                $table->string('linkedin_url')->nullable()->after('website');
            }
            if (!Schema::hasColumn('users', 'skills')) {
                $table->json('skills')->nullable()->after('linkedin_url');
            }
            if (!Schema::hasColumn('users', 'connections_count')) {
                $table->integer('connections_count')->default(0)->after('skills');
            }
            if (!Schema::hasColumn('users', 'project_reviews_count')) {
                $table->integer('project_reviews_count')->default(0)->after('connections_count');
            }
            if (!Schema::hasColumn('users', 'network_rank')) {
                $table->string('network_rank')->nullable()->after('project_reviews_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [
                'title',
                'bio',
                'location',
                'website',
                'linkedin_url',
                'skills',
                'connections_count',
                'project_reviews_count',
                'network_rank',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
