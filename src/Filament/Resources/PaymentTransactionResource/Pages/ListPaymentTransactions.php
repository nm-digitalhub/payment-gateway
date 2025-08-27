<?php

namespace NMDigitalHub\PaymentGateway\Filament\Resources\PaymentTransactionResource\Pages;

use NMDigitalHub\PaymentGateway\Filament\Resources\PaymentTransactionResource;
use NMDigitalHub\PaymentGateway\Models\PaymentTransaction;
use NMDigitalHub\PaymentGateway\Enums\PaymentStatus;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ListPaymentTransactions extends ListRecords
{
    protected static string $resource = PaymentTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('יצירת עסקה חדשה')
                ->icon('heroicon-o-plus'),
                
            Action::make('sync_providers')
                ->label('סינכרון ספקים')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        $results = app('payment-gateway')->syncAllProviders();
                        
                        $successCount = collect($results)->where('success', true)->count();
                        $totalCount = count($results);
                        
                        Notification::make()
                            ->title("סינכרון הושלם")
                            ->body("סינכרנו {$successCount} מתוך {$totalCount} ספקים")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('שגיאה בסינכרון')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
                
            Action::make('health_check')
                ->label('בדיקת בריאות')
                ->icon('heroicon-o-heart')
                ->color('success')
                ->action(function () {
                    try {
                        $health = app('payment-gateway')->healthCheck();
                        
                        $message = "סטטוס על כללי: " . match($health['overall']) {
                            'healthy' => 'תקין ✓',
                            'warning' => 'אזהרה ⚠',
                            'critical' => 'קריטי ❌',
                            default => 'לא ידוע'
                        };
                        
                        Notification::make()
                            ->title('בדיקת בריאות הושלמה')
                            ->body($message)
                            ->color(match($health['overall']) {
                                'healthy' => 'success',
                                'warning' => 'warning', 
                                'critical' => 'danger',
                                default => 'info'
                            })
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('שגיאה בבדיקת בריאות')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('כל העסקאות')
                ->badge(PaymentTransaction::count()),
            
            'pending' => Tab::make('ממתינות')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PaymentStatus::PENDING->value))
                ->badge(PaymentTransaction::where('status', PaymentStatus::PENDING->value)->count())
                ->badgeColor('warning'),
            
            'success' => Tab::make('מושלמות')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PaymentStatus::SUCCESS->value))
                ->badge(PaymentTransaction::where('status', PaymentStatus::SUCCESS->value)->count())
                ->badgeColor('success'),
            
            'failed' => Tab::make('נכשלות')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PaymentStatus::FAILED->value))
                ->badge(PaymentTransaction::where('status', PaymentStatus::FAILED->value)->count())
                ->badgeColor('danger'),
            
            'refunded' => Tab::make('זוכו')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PaymentStatus::REFUNDED->value))
                ->badge(PaymentTransaction::where('status', PaymentStatus::REFUNDED->value)->count())
                ->badgeColor('info'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PaymentTransactionResource\Widgets\PaymentStatsOverview::class,
        ];
    }
}