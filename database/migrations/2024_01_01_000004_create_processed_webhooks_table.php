<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('processed_webhooks', function (Blueprint $table) {
            $table->id();
            
            // Webhook identification
            $table->string('webhook_id')->unique()->index(); // Provider's webhook ID
            $table->string('provider')->index(); // 'cardcom', 'maya_mobile', etc.
            $table->string('event_type')->index(); // 'payment.completed', 'payment.failed', etc.
            
            // Idempotency keys
            $table->string('idempotency_key')->nullable()->index();
            $table->string('external_id')->nullable()->index(); // DealId, TransactionId, etc.
            
            // Request data
            $table->json('payload'); // Original webhook payload
            $table->json('headers')->nullable(); // Request headers
            $table->string('signature')->nullable(); // HMAC signature if provided
            $table->ipAddress('source_ip')->nullable();
            $table->string('user_agent')->nullable();
            
            // Processing status
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'duplicate'])->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamp('last_retry_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            
            // Related records
            $table->unsignedBigInteger('payment_transaction_id')->nullable()->index();
            $table->string('related_model_type')->nullable(); // Polymorphic relation
            $table->unsignedBigInteger('related_model_id')->nullable();
            $table->index(['related_model_type', 'related_model_id']);
            
            // Verification and security
            $table->boolean('signature_valid')->nullable();
            $table->boolean('source_verified')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->json('verification_details')->nullable();
            
            // Processing metadata
            $table->string('job_id')->nullable(); // Queue job ID
            $table->string('job_class')->nullable(); // Which job processed it
            $table->decimal('processing_time', 8, 3)->nullable(); // Seconds
            $table->json('processing_metadata')->nullable();
            
            // Additional metadata
            $table->json('metadata')->nullable();
            
            // Laravel timestamps
            $table->timestamps();
            
            // Indexes for performance and querying
            $table->index(['provider', 'status']);
            $table->index(['event_type', 'status']);
            $table->index(['created_at', 'status']);
            $table->index(['processed_at']);
            $table->index(['retry_count', 'status']);
            
            // Unique constraint for idempotency
            $table->unique(['provider', 'webhook_id'], 'unique_provider_webhook');
            $table->unique(['provider', 'external_id'], 'unique_provider_external_id');
        });
        
        // Add foreign key constraint to payment_transactions if it exists
        if (Schema::hasTable('payment_transactions')) {
            Schema::table('processed_webhooks', function (Blueprint $table) {
                $table->foreign('payment_transaction_id')
                      ->references('id')
                      ->on('payment_transactions')
                      ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_webhooks');
    }
};