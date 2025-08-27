<?php

namespace NMDigitalHub\PaymentGateway\Console\Commands;

use Illuminate\Console\Command;
use NMDigitalHub\PaymentGateway\Services\PackageSyncService;

/**
 * פקודת CLI לסנכרון חבילות מספקים - מממש את הדרישות מ-pullre.md
 */
class SyncPackagesCommand extends Command
{
    protected $signature = 'sync:packages 
                            {--provider= : Sync specific provider (maya_mobile|resellerclub|all)}
                            {--limit=100 : Limit number of packages to sync}
                            {--dry-run : Show what would be synced without making changes}';

    protected $description = 'Sync packages from external providers (ResellerClub, Maya Mobile)';

    public function handle(PackageSyncService $syncService): int
    {
        $provider = $this->option('provider') ?: 'all';
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        $this->info("Starting package sync...");
        $this->info("Provider: {$provider}");
        $this->info("Limit: {$limit}");
        $this->info("Dry run: " . ($dryRun ? 'Yes' : 'No'));
        $this->newLine();

        $startTime = microtime(true);

        try {
            if ($provider === 'all') {
                $results = $syncService->syncAllProviders([
                    'limit' => $limit,
                    'dry_run' => $dryRun,
                ]);
                $this->displayAllProvidersResults($results);
            } else {
                $results = $syncService->syncProviderPackages($provider, [
                    'limit' => $limit,
                    'dry_run' => $dryRun,
                ]);
                $this->displaySingleProviderResults($results);
            }

            $duration = round(microtime(true) - $startTime, 2);
            $this->newLine();
            $this->info("✅ Sync completed in {$duration}s");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Sync failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * הצגת תוצאות סנכרון כל הספקים
     */
    protected function displayAllProvidersResults(array $results): void
    {
        $this->info("📊 Overall Results:");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Packages', $results['total_packages']],
                ['Created', $results['summary']['created']],
                ['Updated', $results['summary']['updated']],
                ['Skipped', $results['summary']['skipped']],
                ['Errors', $results['summary']['errors']],
                ['Duration', $results['total_duration'] . 's'],
            ]
        );

        $this->newLine();
        $this->info("🔄 Provider Details:");

        $providerRows = [];
        foreach ($results['providers'] as $providerName => $result) {
            $status = $result['success'] ? '✅' : '❌';
            $providerRows[] = [
                $status . ' ' . $providerName,
                $result['total_fetched'] ?? 0,
                $result['created'] ?? 0,
                $result['updated'] ?? 0,
                count($result['errors'] ?? []),
                ($result['duration'] ?? 0) . 's',
            ];
        }

        $this->table(
            ['Provider', 'Fetched', 'Created', 'Updated', 'Errors', 'Duration'],
            $providerRows
        );

        // הצגת שגיאות אם יש
        foreach ($results['providers'] as $providerName => $result) {
            if (!empty($result['errors'])) {
                $this->newLine();
                $this->warn("⚠️  Errors in {$providerName}:");
                foreach ($result['errors'] as $error) {
                    $this->line("  • {$error}");
                }
            }
        }
    }

    /**
     * הצגת תוצאות סנכרון ספק יחיד
     */
    protected function displaySingleProviderResults(array $results): void
    {
        if (!$results['success']) {
            $this->error("❌ Sync failed: " . ($results['error'] ?? 'Unknown error'));
            return;
        }

        $dryRun = $results['dry_run'] ?? false;
        $action = $dryRun ? 'Would be' : 'Actually';

        $this->info("📊 {$results['provider']} Results:");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Fetched', $results['total_fetched']],
                ["{$action} Created", $results['created'] ?? 0],
                ["{$action} Updated", $results['updated'] ?? 0],
                ['Skipped', $results['skipped'] ?? 0],
                ['Errors', count($results['errors'] ?? [])],
                ['Duration', ($results['duration'] ?? 0) . 's'],
            ]
        );

        // הצגת שגיאות
        if (!empty($results['errors'])) {
            $this->newLine();
            $this->warn("⚠️  Errors:");
            foreach ($results['errors'] as $error) {
                $this->line("  • {$error}");
            }
        }

        if ($dryRun && ($results['created'] > 0 || $results['updated'] > 0)) {
            $this->newLine();
            $this->comment("💡 Run without --dry-run to execute these changes");
        }
    }
}