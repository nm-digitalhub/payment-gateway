<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_settings', function (Blueprint $table) {
            $table->id();
            
            // Provider identification
            $table->string('provider_name')->index(); // 'cardcom', 'maya_mobile', etc.
            $table->enum('provider_type', ['payment', 'service'])->default('payment');
            $table->string('display_name')->nullable();
            $table->text('description')->nullable();
            
            // Status and mode
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_test_mode')->default(false)->index();
            $table->string('environment')->default('production')->index();
            
            // Configuration (encrypted)
            $table->json('configuration')->nullable(); // Encrypted configuration
            $table->json('capabilities')->nullable(); // What the provider supports
            $table->json('credentials')->nullable(); // Encrypted API keys, secrets
            
            // Webhook configuration
            $table->string('webhook_url')->nullable();
            $table->string('webhook_secret')->nullable();
            
            // API settings
            $table->string('api_version')->nullable();
            $table->unsignedTinyInteger('priority')->default(1); // Lower = higher priority
            $table->unsignedInteger('rate_limit')->nullable(); // Requests per minute
            $table->unsignedInteger('timeout')->default(30); // Seconds
            $table->unsignedTinyInteger('retry_attempts')->default(3);
            
            // Health monitoring
            $table->timestamp('last_health_check')->nullable();
            $table->enum('health_status', ['healthy', 'unhealthy', 'unknown'])->default('unknown');
            $table->text('health_message')->nullable();
            
            // Multi-tenancy support (optional)
            $table->unsignedBigInteger('team_id')->nullable()->index();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            
            // Additional metadata
            $table->json('metadata')->nullable();
            
            // Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            
            // Laravel timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['provider_name', 'provider_type']);
            $table->index(['provider_type', 'is_active']);
            $table->index(['environment', 'is_active']);
            $table->index(['is_active', 'health_status']);
            $table->index(['team_id', 'is_active']);
            
            // Unique constraint per environment and team
            $table->unique(['provider_name', 'environment', 'team_id'], 'unique_provider_per_team_env');
        });
        
        // Add foreign key constraints if the tables exist
        if (Schema::hasTable('users')) {
            Schema::table('provider_settings', function (Blueprint $table) {
                $table->foreign('created_by')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');
                      
                $table->foreign('updated_by')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');
            });
        }
        
        // Optional: Add team/tenant foreign keys if those tables exist
        if (Schema::hasTable('teams')) {
            Schema::table('provider_settings', function (Blueprint $table) {
                $table->foreign('team_id')
                      ->references('id')
                      ->on('teams')
                      ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_settings');
    }
};