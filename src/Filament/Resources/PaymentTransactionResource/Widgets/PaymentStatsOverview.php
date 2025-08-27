<?php

namespace NMDigitalHub\PaymentGateway\Filament\Resources\PaymentTransactionResource\Widgets;

use NMDigitalHub\PaymentGateway\Models\PaymentTransaction;
use NMDigitalHub\PaymentGateway\Enums\PaymentStatus;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class PaymentStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $stats = app('payment-gateway')->getPaymentStats();
        
        return [
            Stat::make('סה"\u05db עסקאות', $stats['total_transactions'])
                ->description('כל העסקאות במערכת')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('primary'),
            
            Stat::make('שיעור הצלחה', $stats['success_rate'] . '%')
                ->description('מתוך כל העסקאות')
                ->descriptionIcon($stats['success_rate'] >= 95 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($stats['success_rate'] >= 95 ? 'success' : ($stats['success_rate'] >= 85 ? 'warning' : 'danger')),
            
            Stat::make('סה"\u05db הכנסות', '₪' . Number::format($stats['total_revenue'], precision: 2))
                ->description('מעסקאות מוצלחות')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            
            Stat::make('עסקה ממוצעת', '₪' . Number::format($stats['average_transaction'], precision: 2))
                ->description('גודל עסקה רגילה')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),
        ];
    }
}