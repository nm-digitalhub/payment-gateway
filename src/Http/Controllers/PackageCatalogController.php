<?php

namespace NMDigitalHub\PaymentGateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Contracts\View\Factory;
use NMDigitalHub\PaymentGateway\Services\CardComService;
use NMDigitalHub\PaymentGateway\Services\MayaMobileService;
use NMDigitalHub\PaymentGateway\Services\ResellerClubService;
use NMDigitalHub\PaymentGateway\Models\Package;

/**
 * Package Catalog Controller
 * מבוסס על מערכת eSIM packages - קטלוג חבילות מאוחד
 */
class PackageCatalogController extends Controller
{
    public function __construct(
        private CardComService $cardComService,
        private MayaMobileService $mayaMobileService,
        private ResellerClubService $resellerClubService
    ) {}

    /**
     * Display package catalog - תצוגת קטלוג חבילות
     * מבוסס על /esim-packages index
     */
    public function index(Request $request): View
    {
        $filters = $request->only(['category', 'provider', 'price_range', 'search']);
        
        // Get packages from all providers - קבלת חבילות מכל הספקים
        $packages = $this->getAvailablePackages($filters);
        
        // Categories for filtering - קטגוריות לסינון
        $categories = $this->getPackageCategories();
        
        // Providers list - רשימת ספקים
        $providers = ['cardcom', 'maya-mobile', 'resellerclub'];
        
        $data = [
            'packages' => $packages,
            'categories' => $categories,
            'providers' => $providers,
            'filters' => $filters,
            'total_packages' => count($packages),
            'sync_status' => $this->getSyncStatus()
        ];

        return view('payment-gateway::packages.index', $data);
    }

    /**
     * Show specific package - תצוגת חבילה ספציפית
     * מבוסס על /esim-packages/{slug} show
     */
    public function show(string $packageSlug): View
    {
        $package = $this->findPackageBySlug($packageSlug);
        
        if (!$package) {
            abort(404, 'החבילה המבוקשת לא נמצאה');
        }

        // Get related packages - חבילות קשורות
        $relatedPackages = $this->getRelatedPackages($package);
        
        // Package availability - זמינות החבילה
        $availability = $this->checkPackageAvailability($package);

        $data = [
            'package' => $package,
            'related_packages' => $relatedPackages,
            'availability' => $availability,
            'pricing_details' => $this->getDetailedPricing($package),
            'features' => $this->getPackageFeatures($package)
        ];

        return view('payment-gateway::packages.show', $data);
    }

    /**
     * Search packages - חיפוש חבילות
     * AJAX endpoint for real-time search
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $filters = $request->only(['category', 'provider', 'price_min', 'price_max']);
        
        if (strlen($query) < 2) {
            return response()->json([
                'packages' => [],
                'message' => 'נדרש לפחות 2 תווים לחיפוש'
            ]);
        }

        $packages = $this->searchPackages($query, $filters);
        
        return response()->json([
            'success' => true,
            'packages' => $packages,
            'total' => count($packages),
            'query' => $query
        ]);
    }

    /**
     * Sync packages from all providers - סנכרון חבילות מכל הספקים
     * מבוסס על Maya Mobile sync system
     */
    public function sync(Request $request): JsonResponse
    {
        try {
            $provider = $request->get('provider', 'all');
            $results = [];

            if ($provider === 'all' || $provider === 'maya-mobile') {
                $results['maya_mobile'] = $this->mayaMobileService->syncPackages();
            }

            if ($provider === 'all' || $provider === 'resellerclub') {
                $results['resellerclub'] = $this->resellerClubService->syncPackages();
            }

            if ($provider === 'all' || $provider === 'cardcom') {
                $results['cardcom'] = $this->cardComService->syncPackages();
            }

            return response()->json([
                'success' => true,
                'message' => 'סנכרון חבילות הושלם בהצלחה',
                'results' => $results,
                'synced_at' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'שגיאה בסנכרון חבילות: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check package availability - בדיקת זמינות חבילה
     * AJAX endpoint for real-time availability
     */
    public function checkAvailability(string $packageSlug): JsonResponse
    {
        $package = $this->findPackageBySlug($packageSlug);
        
        if (!$package) {
            return response()->json([
                'success' => false,
                'message' => 'החבילה לא נמצאה'
            ], 404);
        }

        $availability = $this->checkPackageAvailability($package);
        
        return response()->json([
            'success' => true,
            'package_slug' => $packageSlug,
            'available' => $availability['available'],
            'status' => $availability['status'],
            'message' => $availability['message'],
            'stock' => $availability['stock'] ?? null
        ]);
    }

    /**
     * Get package pricing - קבלת תמחור חבילה
     * AJAX endpoint for dynamic pricing
     */
    public function getPricing(string $packageSlug): JsonResponse
    {
        $package = $this->findPackageBySlug($packageSlug);
        
        if (!$package) {
            return response()->json([
                'success' => false,
                'message' => 'החבילה לא נמצאה'
            ], 404);
        }

        $pricing = $this->getDetailedPricing($package);
        
        return response()->json([
            'success' => true,
            'package_slug' => $packageSlug,
            'pricing' => $pricing,
            'currency' => 'ILS',
            'last_updated' => now()->toISOString()
        ]);
    }

    /**
     * Get sync status - קבלת סטטוס סנכרון
     */
    public function getSyncStatus(): array
    {
        return [
            'last_sync' => cache('payment_gateway_last_sync', 'Never'),
            'total_packages' => cache('payment_gateway_total_packages', 0),
            'sync_in_progress' => cache('payment_gateway_sync_in_progress', false),
            'providers_status' => [
                'maya_mobile' => cache('payment_gateway_maya_mobile_status', 'unknown'),
                'resellerclub' => cache('payment_gateway_resellerclub_status', 'unknown'),
                'cardcom' => cache('payment_gateway_cardcom_status', 'unknown')
            ]
        ];
    }

    /**
     * Find package by slug - מציאת חבילה לפי slug
     */
    private function findPackageBySlug(string $slug): ?array
    {
        // Implement package finding logic based on providers
        // This would query from Maya Mobile, ResellerClub, or CardCom
        $packages = $this->getAvailablePackages();
        
        return collect($packages)->firstWhere('slug', $slug);
    }

    /**
     * Get available packages from all providers - קבלת חבילות זמינות מכל הספקים
     */
    private function getAvailablePackages(array $filters = []): array
    {
        $packages = [];

        try {
            // Maya Mobile packages
            $mayaPackages = $this->mayaMobileService->getAvailablePackages();
            $packages = array_merge($packages, $mayaPackages);

            // ResellerClub packages
            $resellerPackages = $this->resellerClubService->getAvailablePackages();
            $packages = array_merge($packages, $resellerPackages);

            // CardCom packages (if any)
            $cardcomPackages = $this->cardComService->getAvailablePackages();
            $packages = array_merge($packages, $cardcomPackages);

        } catch (\Exception $e) {
            // Log error and return cached packages if available
            \Log::error('Error fetching packages: ' . $e->getMessage());
            $packages = cache('payment_gateway_cached_packages', []);
        }

        // Apply filters
        if (!empty($filters)) {
            $packages = $this->filterPackages($packages, $filters);
        }

        // Cache the results
        cache(['payment_gateway_cached_packages' => $packages], now()->addMinutes(30));
        
        return $packages;
    }

    /**
     * Filter packages based on criteria - סינון חבילות לפי קריטריונים
     */
    private function filterPackages(array $packages, array $filters): array
    {
        return collect($packages)->filter(function ($package) use ($filters) {
            if (isset($filters['category']) && $package['category'] !== $filters['category']) {
                return false;
            }
            
            if (isset($filters['provider']) && $package['provider'] !== $filters['provider']) {
                return false;
            }
            
            if (isset($filters['search']) && !str_contains(strtolower($package['name']), strtolower($filters['search']))) {
                return false;
            }
            
            return true;
        })->values()->all();
    }

    /**
     * Search packages - חיפוש חבילות
     */
    private function searchPackages(string $query, array $filters): array
    {
        $allPackages = $this->getAvailablePackages($filters);
        
        return collect($allPackages)->filter(function ($package) use ($query) {
            return str_contains(strtolower($package['name']), strtolower($query)) ||
                   str_contains(strtolower($package['description'] ?? ''), strtolower($query));
        })->values()->all();
    }

    /**
     * Get package categories - קבלת קטגוריות חבילות
     */
    private function getPackageCategories(): array
    {
        return [
            'esim' => 'eSIM',
            'domains' => 'דומיינים',
            'hosting' => 'אחסון',
            'ssl' => 'אישורי SSL',
            'vps' => 'שרתים וירטואליים'
        ];
    }

    /**
     * Check package availability - בדיקת זמינות חבילה
     */
    private function checkPackageAvailability(array $package): array
    {
        // Implement real-time availability check based on provider
        return [
            'available' => true,
            'status' => 'in_stock',
            'message' => 'החבילה זמינה',
            'stock' => null
        ];
    }

    /**
     * Get detailed pricing - קבלת תמחור מפורט
     */
    private function getDetailedPricing(array $package): array
    {
        return [
            'base_price' => $package['price'] ?? 0,
            'discounted_price' => $package['discounted_price'] ?? null,
            'currency' => 'ILS',
            'vat_included' => true,
            'billing_cycle' => $package['billing_cycle'] ?? 'one_time'
        ];
    }

    /**
     * Get package features - קבלת תכונות חבילה
     */
    private function getPackageFeatures(array $package): array
    {
        return $package['features'] ?? [];
    }

    /**
     * Get related packages - קבלת חבילות קשורות
     */
    private function getRelatedPackages(array $package): array
    {
        $allPackages = $this->getAvailablePackages();
        
        return collect($allPackages)
            ->where('category', $package['category'])
            ->where('slug', '!=', $package['slug'])
            ->take(3)
            ->values()
            ->all();
    }
}