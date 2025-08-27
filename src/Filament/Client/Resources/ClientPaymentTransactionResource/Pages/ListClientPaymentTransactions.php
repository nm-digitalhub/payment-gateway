<?php

namespace NMDigitalHub\PaymentGateway\Filament\Client\Resources\ClientPaymentTransactionResource\Pages;

use NMDigitalHub\PaymentGateway\Filament\Client\Resources\ClientPaymentTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Widgets\StatsOverviewWidget;

class ListClientPaymentTransactions extends ListRecords
{
    protected static string $resource = ClientPaymentTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
    
    protected function getHeaderWidgets(): array
    {
        return [
            ClientPaymentStatsWidget::class,
        ];
    }

    public function getTitle(): string
    {
        return 'התשלומים שלי';
    }
}

class ClientPaymentStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $userEmail = auth()->user()?->email;
        
        if (!$userEmail) {
            return [];
        }

        $transactions = ClientPaymentTransactionResource::getModel()::where('customer_email', $userEmail);
        
        $totalSpent = $transactions->where('status', 'success')->sum('amount');
        $totalTransactions = $transactions->count();
        $successfulTransactions = $transactions->where('status', 'success')->count();
        $pendingTransactions = $transactions->where('status', 'pending')->count();

        return [
            StatsOverviewWidget\Stat::make('סה"כ הוצאות', '₪' . number_format($totalSpent, 2))
                ->description('סכום כולל של תשלומים מוצלחים')
                ->descriptionIcon('heroicon-m-currency-shekel')
                ->color('success'),

            StatsOverviewWidget\Stat::make('סה"כ תשלומים', $totalTransactions)
                ->description($successfulTransactions . ' מוצלחים')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('info'),

            StatsOverviewWidget\Stat::make('תשלומים ממתינים', $pendingTransactions)
                ->description('ממתינים לאישור')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingTransactions > 0 ? 'warning' : 'success'),
        ];
    }
}
