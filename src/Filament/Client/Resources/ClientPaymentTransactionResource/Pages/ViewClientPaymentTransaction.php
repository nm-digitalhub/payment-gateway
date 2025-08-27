<?php

namespace NMDigitalHub\PaymentGateway\Filament\Client\Resources\ClientPaymentTransactionResource\Pages;

use NMDigitalHub\PaymentGateway\Filament\Client\Resources\ClientPaymentTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Auth;

class ViewClientPaymentTransaction extends ViewRecord
{
    protected static string $resource = ClientPaymentTransactionResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);
        
        // בדיקה שהלקוח רואה רק את התשלומים שלו
        if ($this->record->customer_email !== Auth::user()?->email) {
            abort(403, 'אין לך הרשאה לראות תשלום זה');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_receipt')
                ->label('הורד קבלה')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->action(fn() => redirect()->route('payment.receipt.download', $this->record))
                ->visible($this->record->status === 'success'),
                
            Actions\Action::make('contact_support')
                ->label('צור קשר עם תמיכה')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('gray')
                ->url(route('support.create', [
                    'subject' => 'שאלה לגבי תשלום - ' . $this->record->reference,
                    'transaction_id' => $this->record->reference
                ]))
                ->openUrlInNewTab(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('פרטי תשלום')
                    ->schema([
                        TextEntry::make('reference')
                            ->label('מספר עסקה')
                            ->copyable()
                            ->copyMessage('מספר עסקה נשמר ללוח')
                            ->weight('bold'),
                        
                        TextEntry::make('amount')
                            ->label('סכום')
                            ->money('ILS')
                            ->weight('bold'),
                        
                        TextEntry::make('currency')
                            ->label('מטבע')
                            ->formatStateUsing(fn($state) => match($state) {
                                'ILS' => 'שקל חדש',
                                'USD' => 'דולר אמריקני',
                                'EUR' => 'אירו',
                                default => $state,
                            }),
                        
                        TextEntry::make('status')
                            ->label('סטטוס')
                            ->formatStateUsing(fn($state) => match($state) {
                                'success' => 'בוצע בהצלחה',
                                'failed' => 'נכשל',
                                'pending' => 'בהמתנה',
                                'cancelled' => 'בוטל',
                                'refunded' => 'הוחזר',
                                default => $state,
                            })
                            ->badge()
                            ->color(fn($state) => match($state) {
                                'success' => 'success',
                                'failed', 'cancelled' => 'danger',
                                'pending' => 'warning',
                                'refunded' => 'info',
                                default => 'gray',
                            }),
                        
                        TextEntry::make('provider')
                            ->label('ספק תשלום')
                            ->formatStateUsing(fn($state) => match($state) {
                                'cardcom' => 'CardCom',
                                'paypal' => 'PayPal',
                                'stripe' => 'Stripe',
                                default => ucfirst($state),
                            })
                            ->badge(),
                        
                        TextEntry::make('created_at')
                            ->label('תאריך יצירה')
                            ->dateTime('d/m/Y H:i'),
                        
                        TextEntry::make('completed_at')
                            ->label('הושלם בתאריך')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('לא הושלם')
                            ->visible(fn($record) => $record->completed_at),
                        
                        TextEntry::make('gateway_transaction_id')
                            ->label('מספר עסקה בשער')
                            ->placeholder('לא זמין')
                            ->copyable()
                            ->visible(fn($record) => $record->gateway_transaction_id),
                        
                        TextEntry::make('authorization_code')
                            ->label('קוד אישור')
                            ->placeholder('לא זמין')
                            ->copyable()
                            ->visible(fn($record) => $record->authorization_code),
                    ])->columns(3),
                
                Section::make('פרטי לקוח')
                    ->schema([
                        TextEntry::make('customer_name')
                            ->label('שם מלא'),
                        
                        TextEntry::make('customer_email')
                            ->label('כתובת אימייל')
                            ->copyable(),
                        
                        TextEntry::make('customer_phone')
                            ->label('מספר טלפון')
                            ->placeholder('לא צוין')
                            ->visible(fn($record) => $record->customer_phone),
                    ])->columns(3),
                
                Section::make('מידע נוסף')
                    ->schema([
                        TextEntry::make('failure_reason')
                            ->label('סיבת כישלון')
                            ->color('danger')
                            ->visible(fn($record) => $record->status === 'failed' && $record->failure_reason),
                        
                        KeyValueEntry::make('metadata')
                            ->label('פרטים נוספים')
                            ->visible(fn($record) => !empty($record->metadata))
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->visible(fn($record) => $record->status === 'failed' || !empty($record->metadata)),
            ]);
    }

    public function getTitle(): string
    {
        return 'פרטי תשלום - ' . $this->record->reference;
    }
}
