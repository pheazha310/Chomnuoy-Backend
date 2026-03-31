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

        // Role table
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('role_name')->unique();
            $table->timestamps();
        });

        // User table
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('status', 50)->default('active');

            // Foreign Key
            $table->foreignId('role_id')
                ->constrained('roles')
                ->onDelete('cascade');

            $table->timestamps();
        });


        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('category_name')->unique();
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('method_name')->unique();
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('category_id')->constrained('categories')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->string('verified_status', 50)->default('pending');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('goal_amount', 15, 2)->default(0);
            $table->decimal('current_amount', 15, 2)->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
        });


        Schema::create('user_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('password');
            $table->timestamp('last_login')->nullable();
        });

        Schema::create('user_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->text('action');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('organization_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('approved_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('status', 50);
            $table->timestamp('verification_date')->nullable();
        });

        Schema::create('organization_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('updated_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->text('change_description');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('organization_document', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('document_type');
            $table->string('document_path');
        });

        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('campaigns')->cascadeOnUpdate()->nullOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->enum('donation_type', ['money', 'material']);
            $table->string('status', 50)->default('pending');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('donation_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donation_id')->constrained('donations')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('old_status', 50);
            $table->string('new_status', 50);
            $table->timestamp('changed_at')->useCurrent();
        });

        Schema::create('material_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donation_id')->constrained('donations')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('item_name');
            $table->integer('quantity')->default(1);
            $table->text('description')->nullable();
        });

        Schema::create('material_pickups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('donation_id')->constrained('donations')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('pickup_address');
            $table->timestamp('schedule_date')->nullable();
            $table->string('status', 50)->default('pending');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('md5', 32)->unique(); // MD5 is always 32 characters
            $table->text('qr_code'); // Use text instead of varchar(1000)
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('bill_number')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('store_label')->nullable();
            $table->string('terminal_label')->nullable();
            $table->string('merchant_name')->nullable();
            $table->enum('status', ['PENDING', 'SUCCESS', 'FAILED', 'EXPIRED'])->default('PENDING');
            $table->json('bakong_response')->nullable();
            $table->string('transaction_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->boolean('telegram_sent')->default(false);
            $table->integer('check_attempts')->default(0);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'expires_at']);
            $table->index(['status', 'last_checked_at']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnUpdate()->cascadeOnDelete();
            $table->tinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->cascadeOnDelete();
            $table->text('message');
            $table->string('type', 50)->default('general');
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->text('action');
            $table->string('affected_table');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('report', function (Blueprint $table) {
            $table->id();
            $table->string('report_type', 100);
            $table->foreignId('generated_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('file_path');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('campaign_image', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('image_path');
        });

        Schema::create('campaign_update', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnUpdate()->cascadeOnDelete();
            $table->text('update_description');
            $table->string('image')->nullable();
            $table->timestamp('update_date')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_update');
        Schema::dropIfExists('campaign_image');
        Schema::dropIfExists('report');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('material_pickups');
        Schema::dropIfExists('material_items');
        Schema::dropIfExists('donation_status_history');
        Schema::dropIfExists('donations');
        Schema::dropIfExists('organization_document');
        Schema::dropIfExists('organization_history');
        Schema::dropIfExists('organization_verifications');
        Schema::dropIfExists('user_history');
        Schema::dropIfExists('user_credentials');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
    }
};
