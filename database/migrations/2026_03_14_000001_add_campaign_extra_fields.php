<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('campaigns', 'campaign_type')) {
                $table->string('campaign_type', 50)->nullable()->after('id');
            }
            if (!Schema::hasColumn('campaigns', 'category')) {
                $table->string('category', 120)->nullable()->after('title');
            }
            if (!Schema::hasColumn('campaigns', 'payout_method')) {
                $table->string('payout_method', 60)->nullable()->after('description');
            }
            if (!Schema::hasColumn('campaigns', 'account_name')) {
                $table->string('account_name', 120)->nullable()->after('payout_method');
            }
            if (!Schema::hasColumn('campaigns', 'currency')) {
                $table->string('currency', 10)->nullable()->after('account_name');
            }
            if (!Schema::hasColumn('campaigns', 'payout_schedule')) {
                $table->string('payout_schedule', 40)->nullable()->after('currency');
            }
            if (!Schema::hasColumn('campaigns', 'receipt_message')) {
                $table->text('receipt_message')->nullable()->after('payout_schedule');
            }
            if (!Schema::hasColumn('campaigns', 'donation_tiers')) {
                $table->text('donation_tiers')->nullable()->after('receipt_message');
            }
            if (!Schema::hasColumn('campaigns', 'material_priority')) {
                $table->text('material_priority')->nullable()->after('donation_tiers');
            }
            if (!Schema::hasColumn('campaigns', 'pickup_instructions')) {
                $table->text('pickup_instructions')->nullable()->after('material_priority');
            }
            if (!Schema::hasColumn('campaigns', 'contact_name')) {
                $table->string('contact_name', 120)->nullable()->after('pickup_instructions');
            }
            if (!Schema::hasColumn('campaigns', 'contact_phone')) {
                $table->string('contact_phone', 30)->nullable()->after('contact_name');
            }
            if (!Schema::hasColumn('campaigns', 'storage_capacity')) {
                $table->string('storage_capacity', 80)->nullable()->after('contact_phone');
            }
            if (!Schema::hasColumn('campaigns', 'pickup_window')) {
                $table->string('pickup_window', 80)->nullable()->after('storage_capacity');
            }
            if (!Schema::hasColumn('campaigns', 'donor_updates')) {
                $table->text('donor_updates')->nullable()->after('pickup_window');
            }
            if (!Schema::hasColumn('campaigns', 'distribution_plan')) {
                $table->text('distribution_plan')->nullable()->after('donor_updates');
            }
            if (!Schema::hasColumn('campaigns', 'volunteer_needs')) {
                $table->text('volunteer_needs')->nullable()->after('distribution_plan');
            }
            if (!Schema::hasColumn('campaigns', 'enable_recurring')) {
                $table->boolean('enable_recurring')->default(false)->after('volunteer_needs');
            }
            if (!Schema::hasColumn('campaigns', 'material_item')) {
                $table->text('material_item')->nullable()->after('enable_recurring');
            }
            if (!Schema::hasColumn('campaigns', 'hybrid_items')) {
                $table->text('hybrid_items')->nullable()->after('material_item');
            }
            if (!Schema::hasColumn('campaigns', 'status')) {
                $table->string('status', 50)->default('active')->after('hybrid_items');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $columns = [
                'campaign_type',
                'category',
                'payout_method',
                'account_name',
                'currency',
                'payout_schedule',
                'receipt_message',
                'donation_tiers',
                'material_priority',
                'pickup_instructions',
                'contact_name',
                'contact_phone',
                'storage_capacity',
                'pickup_window',
                'donor_updates',
                'distribution_plan',
                'volunteer_needs',
                'enable_recurring',
                'material_item',
                'hybrid_items',
                'status',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('campaigns', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
