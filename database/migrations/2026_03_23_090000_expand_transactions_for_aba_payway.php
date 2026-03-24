<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->after('user_id')->constrained('organizations')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->after('organization_id')->constrained('campaigns')->nullOnDelete();
            $table->foreignId('donation_id')->nullable()->after('campaign_id')->constrained('donations')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->after('donation_id')->constrained('payments')->nullOnDelete();
            $table->string('tran_id')->unique()->after('payment_id');
            $table->decimal('amount', 15, 2)->default(0)->after('tran_id');
            $table->string('currency', 10)->default('USD')->after('amount');
            $table->string('payment_option', 50)->default('abapay')->after('currency');
            $table->string('status', 50)->default('pending')->after('payment_option');
            $table->string('customer_name')->nullable()->after('status');
            $table->string('customer_email')->nullable()->after('customer_name');
            $table->string('customer_phone', 50)->nullable()->after('customer_email');
            $table->text('return_params')->nullable()->after('customer_phone');
            $table->text('checkout_url')->nullable()->after('return_params');
            $table->longText('qr_string')->nullable()->after('checkout_url');
            $table->text('deeplink')->nullable()->after('qr_string');
            $table->json('request_payload')->nullable()->after('deeplink');
            $table->json('response_payload')->nullable()->after('request_payload');
            $table->json('callback_payload')->nullable()->after('response_payload');
            $table->timestamp('paid_at')->nullable()->after('callback_payload');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_id');
            $table->dropConstrainedForeignId('donation_id');
            $table->dropConstrainedForeignId('campaign_id');
            $table->dropConstrainedForeignId('organization_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn([
                'tran_id',
                'amount',
                'currency',
                'payment_option',
                'status',
                'customer_name',
                'customer_email',
                'customer_phone',
                'return_params',
                'checkout_url',
                'qr_string',
                'deeplink',
                'request_payload',
                'response_payload',
                'callback_payload',
                'paid_at',
            ]);
        });
    }
};
