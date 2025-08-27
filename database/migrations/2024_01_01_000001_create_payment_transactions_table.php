<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            
            // Transaction identifiers
            $table->string('transaction_id')->unique()->index();
            $table->string('external_transaction_id')->nullable()->index();
            
            // Provider and status
            $table->string('provider', 50)->index();
            $table->string('status', 50)->index();
            
            // Amounts and currency
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('ILS');
            $table->decimal('fee', 10, 2)->nullable();
            $table->decimal('net_amount', 10, 2)->nullable();
            
            // Customer information
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable()->index();
            $table->string('customer_phone')->nullable();
            $table->json('billing_address')->nullable();
            
            // Card information (masked/tokenized)
            $table->string('card_last_four', 4)->nullable();
            $table->string('card_brand', 50)->nullable();
            $table->date('card_expires_at')->nullable();
            
            // Gateway and security data
            $table->json('gateway_response')->nullable();
            $table->json('three_ds_data')->nullable();
            $table->json('webhook_data')->nullable();
            $table->json('metadata')->nullable();
            
            // Timestamps for transaction lifecycle
            $table->timestamp('processed_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            
            // Refund information
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->text('refund_reason')->nullable();
            
            // Additional tracking
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('payment_page_id')->nullable()->index();
            
            // Polymorphic relationship to orders/subscriptions
            $table->string('order_type')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->index(['order_type', 'order_id']);
            
            // Security and fraud detection
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedTinyInteger('risk_score')->nullable();
            $table->json('fraud_flags')->nullable();
            
            // Settlement information
            $table->date('settlement_date')->nullable();
            $table->string('settlement_currency', 3)->nullable();
            $table->decimal('settlement_amount', 10, 2)->nullable();
            $table->decimal('exchange_rate', 8, 4)->nullable();
            
            // Laravel timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['provider', 'status']);
            $table->index(['created_at', 'status']);
            $table->index(['amount', 'currency']);
            $table->index(['user_id', 'status']);
            
            // Foreign key constraints (if models exist)
            // Note: These will be added conditionally in the migration
        });
        
        // Add foreign key constraints if the tables exist
        if (Schema::hasTable('users')) {
            Schema::table('payment_transactions', function (Blueprint $table) {
                $table->foreign('user_id')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');
            });
        }
        
        if (Schema::hasTable('payment_pages')) {
            Schema::table('payment_transactions', function (Blueprint $table) {
                $table->foreign('payment_page_id')
                      ->references('id')
                      ->on('payment_pages')
                      ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};