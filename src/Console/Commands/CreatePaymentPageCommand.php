<?php

namespace NMDigitalHub\PaymentGateway\Console\Commands;

use Illuminate\Console\Command;
use NMDigitalHub\PaymentGateway\Models\PaymentPage;

class CreatePaymentPageCommand extends Command
{
    protected $signature = 'payment-gateway:create-page
                            {title : Page title}
                            {slug : Page slug (URL friendly)}
                            {--desc= : Page description}
                            {--lang=he : Page language}
                            {--public : Make page public}
                            {--auth : Require authentication}';
    
    protected $description = 'יצירת עמוד תשלום חדש';

    public function handle(): int
    {
        $title = $this->argument('title');
        $slug = $this->argument('slug');
        $description = $this->option('desc');
        $language = $this->option('lang');
        $isPublic = $this->option('public');
        $requireAuth = $this->option('auth');

        $this->info("📄 יוצר עמוד תשלום: {$title}");

        try {
            // Check if slug exists
            if (PaymentPage::where('slug', $slug)->exists()) {
                $this->error("❌ עמוד עם slug '{$slug}' כבר קיים");
                return self::FAILURE;
            }

            $page = PaymentPage::create([
                'title' => $title,
                'slug' => $slug,
                'description' => $description,
                'type' => 'checkout',
                'status' => 'published',
                'language' => $language,
                'is_public' => $isPublic,
                'require_auth' => $requireAuth,
                'content' => [
                    [
                        'type' => 'heading',
                        'data' => ['content' => $title, 'level' => 'h1']
                    ],
                    [
                        'type' => 'paragraph',
                        'data' => ['content' => $description ?: 'מלא את הפרטים למטה לביצוע התשלום']
                    ],
                    [
                        'type' => 'payment_form',
                        'data' => ['allowed_methods' => ['cardcom']]
                    ]
                ],
                'seo_meta' => [
                    'title' => $title,
                    'description' => $description,
                    'keywords' => 'תשלום, payment, secure'
                ]
            ]);

            $this->info("✅ עמוד נוצר בהצלחה!");
            $this->info("🔗 URL: /payment/{$slug}");
            $this->info("📋 ID: {$page->id}");
            
            if ($isPublic) {
                $this->info("🌐 העמוד זמין לכולם");
            } else {
                $this->info("🔒 העמוד פרטי");
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ שגיאה ביצירת עמוד: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}