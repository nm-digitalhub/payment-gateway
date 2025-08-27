<?php

namespace NMDigitalHub\PaymentGateway\Filament\Actions;

use NMDigitalHub\PaymentGateway\DataObjects\PaymentRequest;
use NMDigitalHub\PaymentGateway\Enums\PaymentProvider;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class PaymentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'payment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('יצירת תשלום')
            ->icon('heroicon-o-credit-card')
            ->color('success')
            ->requiresConfirmation(false)
            ->modalHeading('יצירת בקשת תשלום')
            ->modalDescription('מלא את הפרטים ליצירת בקשת התשלום')
            ->modalSubmitActionLabel('יצירת תשלום')
            ->form([
                Section::make('פרטי תשלום')
                    ->schema([
                        TextInput::make('amount')
                            ->label('סכום')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->prefix('₪'),
                        
                        Select::make('currency')
                            ->label('מטבע')
                            ->options([
                                'ILS' => 'שקל חדש',
                                'USD' => 'דולר אמריקני',
                                'EUR' => 'אירו',
                            ])
                            ->default('ILS')
                            ->required(),
                        
                        TextInput::make('description')
                            ->label('תיאור')
                            ->placeholder('תיאור התשלום')
                            ->columnSpanFull(),
                    ])->columns(2),
                
                Section::make('פרטי לקוח')
                    ->schema([
                        TextInput::make('customer_name')
                            ->label('שם מלא')
                            ->required(),
                        
                        TextInput::make('customer_email')
                            ->label('כתובת אימייל')
                            ->email()
                            ->required(),
                        
                        TextInput::make('customer_phone')
                            ->label('מספר טלפון')
                            ->tel(),
                    ])->columns(3),
                
                Section::make('הגדרות מתקדמות')
                    ->schema([
                        Select::make('provider')
                            ->label('ספק תשלום')
                            ->options(collect(PaymentProvider::cases())->mapWithKeys(
                                fn($case) => [$case->value => $case->getHebrewName()]
                            ))
                            ->placeholder('בחר ספק (אוטומטי אם לא נבחר)'),
                        
                        Toggle::make('save_payment_method')
                            ->label('שמור אמצעי תשלום')
                            ->helperText('ישמור את פרטי הכרטיס לעתיד')
                            ->default(false),
                        
                        Textarea::make('notes')
                            ->label('הערות')
                            ->placeholder('הערות נוספות לתשלום')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2)
                    ->collapsed(),
            ])
            ->action(function (array $data, Model $record): void {
                try {
                    // יצירת PaymentRequest
                    $paymentRequest = \NMDigitalHub\PaymentGateway\Facades\Payment::request()
                        ->model($record)
                        ->amount($data['amount'])
                        ->currency($data['currency'])
                        ->customer($data['customer_name'], $data['customer_email'], $data['customer_phone'] ?? null)
                        ->description($data['description'] ?? 'תשלום עבור ' . class_basename($record))
                        ->metadata([
                            'record_id' => $record->getKey(),
                            'record_type' => get_class($record),
                            'created_by' => auth()->id(),
                            'notes' => $data['notes'] ?? null,
                        ])
                        ->savePaymentMethod($data['save_payment_method'] ?? false)
                        ->provider($data['provider'] ?? null);
                    
                    // עיבוד התשלום
                    $session = \NMDigitalHub\PaymentGateway\Facades\Payment::processPaymentRequest(
                        $paymentRequest, 
                        $data['provider'] ?? null
                    );
                    
                    // הודעת הצלחה עם קישור לתשלום
                    Notification::make()
                        ->title('בקשת התשלום נוצרה בהצלחה')
                        ->body("סכום: {$data['currency']} {$data['amount']}")
                        ->success()
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('open_payment')
                                ->label('פתח דף תשלום')
                                ->url($session->checkoutUrl)
                                ->openUrlInNewTab()
                                ->button(),
                        ])
                        ->send();
                        
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('שגיאה ביצירת התשלום')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * הגדרת סכום ברירת מחדל
     */
    public function defaultAmount(float|\Closure $amount): static
    {
        $this->fillDefaultFormDataUsing(function (Model $record) use ($amount) {
            $resolvedAmount = $amount instanceof \Closure ? $amount($record) : $amount;
            return ['amount' => $resolvedAmount];
        });

        return $this;
    }

    /**
     * הגדרת פרטי לקוח ברירת מחדל מהמודל
     */
    public function defaultCustomerFromModel(string $nameField = 'name', string $emailField = 'email', ?string $phoneField = 'phone'): static
    {
        $this->fillDefaultFormDataUsing(function (Model $record) use ($nameField, $emailField, $phoneField) {
            return [
                'customer_name' => $record->{$nameField} ?? '',
                'customer_email' => $record->{$emailField} ?? '',
                'customer_phone' => $phoneField && isset($record->{$phoneField}) ? $record->{$phoneField} : null,
            ];
        });

        return $this;
    }

    /**
     * הגדרת תיאור ברירת מחדל
     */
    public function defaultDescription(string|\Closure $description): static
    {
        $this->fillDefaultFormDataUsing(function (Model $record) use ($description) {
            $resolvedDescription = $description instanceof \Closure ? $description($record) : $description;
            return ['description' => $resolvedDescription];
        });

        return $this;
    }

    /**
     * הגדרת ספק תשלום קבוע
     */
    public function provider(string $provider): static
    {
        $this->fillDefaultFormDataUsing(fn() => ['provider' => $provider]);
        return $this;
    }

    /**
     * הגדרת מטבע קבוע
     */
    public function currency(string $currency): static
    {
        $this->fillDefaultFormDataUsing(fn() => ['currency' => $currency]);
        return $this;
    }

    /**
     * הסתרת שדות מסוימים
     */
    public function hideCustomerFields(): static
    {
        $this->form([
            Section::make('פרטי תשלום')
                ->schema([
                    TextInput::make('amount')
                        ->label('סכום')
                        ->required()
                        ->numeric()
                        ->minValue(0.01)
                        ->step(0.01)
                        ->prefix('₪'),
                    
                    Select::make('currency')
                        ->label('מטבע')
                        ->options([
                            'ILS' => 'שקל חדש',
                            'USD' => 'דולר אמריקני',
                            'EUR' => 'איירו',
                        ])
                        ->default('ILS')
                        ->required(),
                    
                    TextInput::make('description')
                        ->label('תיאור')
                        ->placeholder('תיאור התשלום')
                        ->columnSpanFull(),
                ])->columns(2),
        ]);

        return $this;
    }
}