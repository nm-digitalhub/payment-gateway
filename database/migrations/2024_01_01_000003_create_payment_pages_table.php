<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_pages', function (Blueprint $table) {
            $table->id();
            
            // Page identification
            $table->string('title');
            $table->string('slug')->unique()->index();
            $table->text('description')->nullable();
            
            // Page configuration
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_public')->default(true);
            $table->string('template')->default('default');
            $table->string('layout')->default('default');
            
            // Payment configuration
            $table->json('allowed_providers')->nullable(); // Which providers are allowed
            $table->json('required_fields')->nullable(); // Which fields are required
            $table->json('optional_fields')->nullable(); // Which fields are optional
            $table->boolean('collect_billing_address')->default(false);
            $table->boolean('require_phone')->default(false);
            $table->boolean('enable_coupons')->default(false);
            $table->boolean('save_payment_methods')->default(false);
            
            // Amount configuration
            $table->decimal('fixed_amount', 10, 2)->nullable(); // Fixed amount or null for dynamic
            $table->decimal('min_amount', 10, 2)->nullable();
            $table->decimal('max_amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('ILS');
            $table->json('allowed_currencies')->nullable();
            
            // Form customization
            $table->string('submit_button_text')->nullable();
            $table->text('success_message')->nullable();
            $table->text('error_message')->nullable();
            $table->string('success_redirect_url')->nullable();
            $table->string('cancel_redirect_url')->nullable();
            
            // Styling and branding
            $table->json('custom_css')->nullable();
            $table->json('custom_js')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('brand_color', 7)->nullable(); // Hex color
            $table->string('background_color', 7)->nullable();
            
            // Security and validation
            $table->json('allowed_domains')->nullable(); // For iframe embedding
            $table->boolean('require_https')->default(true);
            $table->boolean('enable_captcha')->default(false);
            $table->string('captcha_provider')->nullable(); // 'recaptcha', 'turnstile', etc.
            
            // Analytics and tracking
            $table->string('google_analytics_id')->nullable();
            $table->string('facebook_pixel_id')->nullable();
            $table->json('custom_tracking')->nullable();
            
            // Notifications
            $table->json('notification_emails')->nullable(); // Who gets notified
            $table->boolean('send_receipt_email')->default(true);
            $table->text('receipt_email_template')->nullable();
            
            // Multi-tenancy and ownership
            $table->unsignedBigInteger('team_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            
            // Statistics (denormalized for performance)
            $table->unsignedBigInteger('total_views')->default(0);
            $table->unsignedBigInteger('total_submissions')->default(0);
            $table->unsignedBigInteger('successful_payments')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            
            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('meta_tags')->nullable();
            
            // Additional metadata
            $table->json('metadata')->nullable();
            
            // Laravel timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['is_active', 'is_public']);
            $table->index(['team_id', 'is_active']);
            $table->index(['created_by']);
            $table->index(['currency']);
        });
        
        // Add foreign key constraints if the tables exist
        if (Schema::hasTable('users')) {
            Schema::table('payment_pages', function (Blueprint $table) {
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
        
        if (Schema::hasTable('teams')) {
            Schema::table('payment_pages', function (Blueprint $table) {
                $table->foreign('team_id')
                      ->references('id')
                      ->on('teams')
                      ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_pages');
    }
};