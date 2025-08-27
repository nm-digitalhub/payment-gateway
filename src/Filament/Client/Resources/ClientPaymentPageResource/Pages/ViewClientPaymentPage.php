<?php

namespace NMDigitalHub\PaymentGateway\Filament\Client\Resources\ClientPaymentPageResource\Pages;

use NMDigitalHub\PaymentGateway\Filament\Client\Resources\ClientPaymentPageResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions;

class ViewClientPaymentPage extends ViewRecord
{
    protected static string $resource = ClientPaymentPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_live')
                ->label('צפייה בעמוד המקורי')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn($record) => route('payment.page.show', $record->slug))
                ->openUrlInNewTab(),
        ];
    }

    public function getTitle(): string
    {
        return 'פרטי עמוד תשלום: ' . $this->getRecord()->title;
    }
}
