<?php

namespace NMDigitalHub\PaymentGateway\Filament\Resources\PaymentPageResource\Pages;

use NMDigitalHub\PaymentGateway\Filament\Resources\PaymentPageResource;
use NMDigitalHub\PaymentGateway\Models\PaymentPage;
use Filament\Resources\Pages\CreateRecord;
use Filament\Actions;
use Filament\Notifications\Notification;

class CreatePaymentPage extends CreateRecord
{
    protected static string $resource = PaymentPageResource::class;

    public function getTitle(): string
    {
        return 'יצירת עמוד תשלום חדש';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('save_and_publish')
                ->label('שמירה ופרסום')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->action(function (array $data) {
                    $data['status'] = PaymentPage::STATUS_PUBLISHED;
                    $data['published_at'] = now();
                    
                    $record = static::getModel()::create($data);
                    
                    Notification::make()
                        ->title('העמוד נוצר ופורסם בהצלחה')
                        ->success()
                        ->send();
                        
                    return redirect(static::getResource()::getUrl('edit', ['record' => $record]));
                })
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // אוטו-גנרציה של slug אם חסר
        if (empty($data['slug']) && !empty($data['title'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['title']);
        }
        
        // הגדרת ברירות מחדל
        $data['created_by'] = auth()->id();
        
        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'עמוד התשלום נוצר בהצלחה';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
