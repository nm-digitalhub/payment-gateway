<?php

namespace NMDigitalHub\PaymentGateway\Filament\Resources\PaymentPageResource\Pages;

use NMDigitalHub\PaymentGateway\Filament\Resources\PaymentPageResource;
use NMDigitalHub\PaymentGateway\Models\PaymentPage;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

class ListPaymentPages extends ListRecords
{
    protected static string $resource = PaymentPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('עמוד חדש'),
                
            Actions\Action::make('bulk_publish')
                ->label('פרסום הכל')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->action(function () {
                    $count = PaymentPage::where('status', PaymentPage::STATUS_DRAFT)
                        ->update(['status' => PaymentPage::STATUS_PUBLISHED]);
                        
                    Notification::make()
                        ->title("פורסמו {$count} עמודים")
                        ->success()
                        ->send();
                })
                ->requiresConfirmation(),
        ];
    }

    public function getTitle(): string
    {
        return 'ניהול עמודי תשלום';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // Add statistics widgets if needed
        ];
    }
}
