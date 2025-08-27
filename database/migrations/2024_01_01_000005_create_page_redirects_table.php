<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_redirects', function (Blueprint $table) {
            $table->id();
            
            // Redirect mapping
            $table->string('old_slug')->index();
            $table->string('new_slug')->index();
            $table->string('locale', 10)->default('he')->index();
            
            // HTTP redirect status
            $table->unsignedSmallInteger('status_code')->default(301); // 301, 302, etc.
            
            // Related page
            $table->unsignedBigInteger('page_id')->nullable()->index();
            $table->string('page_type')->nullable(); // 'payment_page', 'package', etc.
            
            // Tracking
            $table->unsignedBigInteger('hit_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            
            // Metadata
            $table->string('redirect_reason')->nullable(); // 'slug_change', 'seo_optimization', etc.
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            
            // Management
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            
            // Laravel timestamps
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['old_slug', 'locale']);
            $table->index(['new_slug', 'locale']);
            $table->index(['is_active', 'expires_at']);
            $table->index(['page_type', 'page_id']);
            
            // Unique constraint to prevent duplicate redirects
            $table->unique(['old_slug', 'locale'], 'unique_old_slug_locale');
        });
        
        // Add foreign key constraints if tables exist
        if (Schema::hasTable('users')) {
            Schema::table('page_redirects', function (Blueprint $table) {
                $table->foreign('created_by')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('page_redirects');
    }
};
