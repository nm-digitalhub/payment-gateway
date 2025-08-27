<?php

namespace NMDigitalHub\PaymentGateway\Filament\Resources;

use NMDigitalHub\PaymentGateway\Filament\Resources\PaymentTransactionResource\Pages;
use NMDigitalHub\PaymentGateway\Filament\Resources\PaymentTransactionResource\RelationManagers;
use NMDigitalHub\PaymentGateway\Models\PaymentTransaction;
use NMDigitalHub\PaymentGateway\Enums\PaymentStatus;
use NMDigitalHub\PaymentGateway\Enums\PaymentProvider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\DateRangeFilter;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentTransactionResource extends Resource
{
    protected static ?string $model = PaymentTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    
    protected static ?string $navigationLabel = 'עסקאות תשלום';
    
    protected static ?string $modelLabel = 'עסקת תשלום';
    
    protected static ?string $pluralModelLabel = 'עסקאות תשלום';

    protected static ?string $navigationGroup = 'תשלומים';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('פרטי העסקה')
                    ->schema([
                        Forms\Components\TextInput::make('transaction_id')
                            ->label('מזהה עסקה')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\Select::make('provider')
                            ->label('ספק תשלום')
                            ->options(collect(PaymentProvider::cases())->mapWithKeys(
                                fn($case) => [$case->value => $case->getDisplayName()]
                            ))
                            ->required(),
                        
                        Forms\Components\TextInput::make('reference')
                            ->label('מראה ייחודי')
                            ->required()
                            ->maxLength(255),
                    ])->columns(3),

                Forms\Components\Section::make('פרטי התשלום')
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label('סכום')
                            ->required()
                            ->numeric()
                            ->minValue(0),
                        
                        Forms\Components\TextInput::make('currency')
                            ->label('מטבע')
                            ->required()
                            ->default('ILS')
                            ->maxLength(3),
                        
                        Forms\Components\Select::make('status')
                            ->label('סטטוס')
                            ->options(collect(PaymentStatus::cases())->mapWithKeys(
                                fn($case) => [$case->value => $case->getHebrewName()]
                            ))
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make('פרטי לקוח')
                    ->schema([
                        Forms\Components\TextInput::make('customer_email')
                            ->label('אימייל לקוח')
                            ->email()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('customer_name')
                            ->label('שם לקוח')
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('customer_phone')
                            ->label('טלפון לקוח')
                            ->tel()
                            ->maxLength(255),
                    ])->columns(3),

                Forms\Components\Section::make('נתונים טכניים')
                    ->schema([
                        Forms\Components\KeyValue::make('metadata')
                            ->label('מטא דייטה')
                            ->keyLabel('מפתח')
                            ->valueLabel('ערך')
                            ->reorderable(false)
                            ->columnSpanFull(),
                        
                        Forms\Components\Textarea::make('gateway_response')
                            ->label('תגובת ספק')
                            ->rows(3)
                            ->columnSpanFull(),
                        
                        Forms\Components\TextInput::make('failure_reason')
                            ->label('סיבת כשלון')
                            ->maxLength(500)
                            ->columnSpanFull(),
                        
                        Forms\Components\TextInput::make('gateway_transaction_id')
                            ->label('מזהה עסקה אצל ספק')
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('authorization_code')
                            ->label('קוד אישור')
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('תאריכים')
                    ->schema([
                        Forms\Components\DateTimePicker::make('completed_at')
                            ->label('תאריך השלמה'),
                        
                        Forms\Components\DateTimePicker::make('created_at')
                            ->label('תאריך יצירה')
                            ->disabled(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_id')
                    ->label('מזהה עסקה')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('מזהה העסקה נשמר ללוח')
                    ->weight(FontWeight::Medium),
                
                BadgeColumn::make('status')
                    ->label('סטטוס')
                    ->formatStateUsing(fn($state) => PaymentStatus::from($state)->getHebrewName())
                    ->colors([
                        'success' => fn($state) => PaymentStatus::from($state)->getColor() === 'success',
                        'warning' => fn($state) => PaymentStatus::from($state)->getColor() === 'warning',
                        'danger' => fn($state) => PaymentStatus::from($state)->getColor() === 'danger',
                        'info' => fn($state) => PaymentStatus::from($state)->getColor() === 'info',
                    ]),
                
                TextColumn::make('provider')
                    ->label('ספק')
                    ->formatStateUsing(fn($state) => PaymentProvider::from($state)->getHebrewName())
                    ->sortable(),
                
                TextColumn::make('amount')
                    ->label('סכום')
                    ->formatStateUsing(fn($state, $record) => 
                        match($record->currency) {
                            'ILS' => '₪' . number_format($state, 2),
                            'USD' => '$' . number_format($state, 2),
                            'EUR' => '€' . number_format($state, 2),
                            default => $record->currency . ' ' . number_format($state, 2)
                        }
                    )
                    ->sortable()
                    ->weight(FontWeight::Bold),
                
                TextColumn::make('customer_email')
                    ->label('לקוח')
                    ->searchable()
                    ->limit(30),
                
                TextColumn::make('created_at')
                    ->label('תאריך יצירה')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                
                TextColumn::make('completed_at')
                    ->label('תאריך השלמה')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('סטטוס')
                    ->options(collect(PaymentStatus::cases())->mapWithKeys(
                        fn($case) => [$case->value => $case->getHebrewName()]
                    )),
                
                SelectFilter::make('provider')
                    ->label('ספק תשלום')
                    ->options(collect(PaymentProvider::cases())->mapWithKeys(
                        fn($case) => [$case->value => $case->getHebrewName()]
                    )),
                
                DateRangeFilter::make('created_at')
                    ->label('תאריך יצירה'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('צפייה')
                    ->icon('heroicon-o-eye'),
                    
                Tables\Actions\EditAction::make()
                    ->label('עריכה')
                    ->icon('heroicon-o-pencil'),
                    
                Action::make('refund')
                    ->label('זיכוי')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn($record) => $record->status->canBeRefunded())
                    ->requiresConfirmation()
                    ->modalHeading('אישור זיכוי')
                    ->modalDescription('האם אתה בטוח שברצונך לבצע זיכוי?')
                    ->action(function ($record) {
                        try {
                            app('payment-gateway')->refundPayment($record->transaction_id, null, $record->provider);
                            
                            Notification::make()
                                ->title('זיכוי בוצע בהצלחה')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('שגיאה בביצוע זיכוי')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                    
                Action::make('verify')
                    ->label('אימות')
                    ->icon('heroicon-o-check-circle')
                    ->color('info')
                    ->action(function ($record) {
                        try {
                            $transaction = app('payment-gateway')->verifyPayment($record->reference, $record->provider);
                            
                            $record->update([
                                'status' => $transaction->status,
                                'gateway_response' => $transaction->gatewayResponse,
                                'completed_at' => $transaction->completedAt,
                            ]);
                            
                            Notification::make()
                                ->title('אימות הושלם בהצלחה')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('שגיאה באימות')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('export')
                        ->label('ייצוא ל-CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function ($records) {
                            // יישום ייצוא CSV
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('פרטי העסקה')
                    ->schema([
                        Infolists\Components\TextEntry::make('transaction_id')
                            ->label('מזהה עסקה')
                            ->copyable(),
                        
                        Infolists\Components\TextEntry::make('provider')
                            ->label('ספק')
                            ->formatStateUsing(fn($state) => PaymentProvider::from($state)->getHebrewName()),
                        
                        Infolists\Components\TextEntry::make('status')
                            ->label('סטטוס')
                            ->formatStateUsing(fn($state) => PaymentStatus::from($state)->getHebrewName())
                            ->badge()
                            ->color(fn($state) => PaymentStatus::from($state)->getColor()),
                    ])->columns(3),
                    
                Infolists\Components\Section::make('פרטי תשלום')
                    ->schema([
                        Infolists\Components\TextEntry::make('amount')
                            ->label('סכום')
                            ->formatStateUsing(fn($state, $record) => 
                                match($record->currency) {
                                    'ILS' => '₪' . number_format($state, 2),
                                    'USD' => '$' . number_format($state, 2),
                                    'EUR' => '€' . number_format($state, 2),
                                    default => $record->currency . ' ' . number_format($state, 2)
                                }
                            ),
                        
                        Infolists\Components\TextEntry::make('currency')
                            ->label('מטבע'),
                        
                        Infolists\Components\TextEntry::make('reference')
                            ->label('מראה')
                            ->copyable(),
                    ])->columns(3),
                    
                Infolists\Components\Section::make('פרטי לקוח')
                    ->schema([
                        Infolists\Components\TextEntry::make('customer_email')
                            ->label('אימייל'),
                        
                        Infolists\Components\TextEntry::make('customer_name')
                            ->label('שם'),
                        
                        Infolists\Components\TextEntry::make('customer_phone')
                            ->label('טלפון'),
                    ])->columns(3),
                    
                Infolists\Components\Section::make('מטא דייטה')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('metadata')
                            ->label('נתונים נוספים'),
                    ])->visible(fn($record) => !empty($record->metadata)),
                    
                Infolists\Components\Section::make('תאריכים')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('נוצר בתאריך')
                            ->dateTime(),
                        
                        Infolists\Components\TextEntry::make('completed_at')
                            ->label('הושלם בתאריך')
                            ->dateTime()
                            ->placeholder('לא הושלם'),
                    ])->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentTransactions::route('/'),
            'create' => Pages\CreatePaymentTransaction::route('/create'),
            'view' => Pages\ViewPaymentTransaction::route('/{record}'),
            'edit' => Pages\EditPaymentTransaction::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', PaymentStatus::PENDING->value)->count();
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['customer']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['transaction_id', 'reference', 'customer_email', 'customer_name'];
    }
}