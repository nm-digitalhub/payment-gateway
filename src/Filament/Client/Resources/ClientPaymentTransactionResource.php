<?php

namespace NMDigitalHub\PaymentGateway\Filament\Client\Resources;

use NMDigitalHub\PaymentGateway\Filament\Client\Resources\ClientPaymentTransactionResource\Pages;
use NMDigitalHub\PaymentGateway\Models\PaymentTransaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;

class ClientPaymentTransactionResource extends Resource
{
    protected static ?string $model = PaymentTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    
    protected static ?string $navigationLabel = 'התשלומים שלי';
    
    protected static ?string $modelLabel = 'תשלום';
    
    protected static ?string $pluralModelLabel = 'תשלומים';

    protected static ?string $navigationGroup = 'החשבון שלי';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('customer_email', Auth::user()?->email)
            ->orderBy('created_at', 'desc');
    }

    public static function canCreate(): bool
    {
        return false; // Clients cannot create transactions directly
    }

    public static function canEdit($record): bool
    {
        return false; // Clients cannot edit transactions
    }

    public static function canDelete($record): bool
    {
        return false; // Clients cannot delete transactions
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('פרטי תשלום')
                    ->schema([
                        Forms\Components\TextInput::make('reference')
                            ->label('מספר עסקה')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('amount')
                            ->label('סכום')
                            ->disabled()
                            ->prefix('₪'),
                        
                        Forms\Components\Select::make('currency')
                            ->label('מטבע')
                            ->options([
                                'ILS' => 'שקל חדש',
                                'USD' => 'דולר אמריקני',
                                'EUR' => 'אירו',
                            ])
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('status')
                            ->label('סטטוס')
                            ->disabled(),
                    ])->columns(2),
                
                Forms\Components\Section::make('פרטי לקוח')
                    ->schema([
                        Forms\Components\TextInput::make('customer_name')
                            ->label('שם מלא')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('customer_email')
                            ->label('כתובת אימייל')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('customer_phone')
                            ->label('מספר טלפון')
                            ->disabled(),
                    ])->columns(3),
                
                Forms\Components\Section::make('מידע נוסף')
                    ->schema([
                        Forms\Components\Textarea::make('failure_reason')
                            ->label('סיבת כישלון')
                            ->disabled()
                            ->visible(fn($record) => $record->status === 'failed'),
                        
                        Forms\Components\KeyValue::make('metadata')
                            ->label('מטא דייטה')
                            ->disabled()
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label('מספר עסקה')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('מספר עסקה נשמר ללוח'),
                
                TextColumn::make('amount')
                    ->label('סכום')
                    ->money('ILS')
                    ->sortable(),
                
                BadgeColumn::make('status')
                    ->label('סטטוס')
                    ->formatStateUsing(fn($state) => match($state) {
                        'success' => 'בוצע בהצלחה',
                        'failed' => 'נכשל',
                        'pending' => 'בהמתנה',
                        'cancelled' => 'בוטל',
                        'refunded' => 'הוחזר',
                        default => $state,
                    })
                    ->colors([
                        'success' => 'success',
                        'danger' => fn($state) => in_array($state, ['failed', 'cancelled']),
                        'warning' => 'pending',
                        'info' => 'refunded',
                    ]),
                
                TextColumn::make('provider')
                    ->label('ספק תשלום')
                    ->formatStateUsing(fn($state) => match($state) {
                        'cardcom' => 'CardCom',
                        'paypal' => 'PayPal',
                        'stripe' => 'Stripe',
                        default => ucfirst($state),
                    })
                    ->badge(),
                
                TextColumn::make('created_at')
                    ->label('תאריך')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                
                TextColumn::make('completed_at')
                    ->label('הושלם ב')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('סטטוס')
                    ->options([
                        'success' => 'בוצע בהצלחה',
                        'failed' => 'נכשל',
                        'pending' => 'בהמתנה',
                        'cancelled' => 'בוטל',
                        'refunded' => 'הוחזר',
                    ]),
                
                SelectFilter::make('provider')
                    ->label('ספק תשלום')
                    ->options([
                        'cardcom' => 'CardCom',
                        'paypal' => 'PayPal',
                        'stripe' => 'Stripe',
                    ]),
                
                Filter::make('amount_range')
                    ->label('טווח סכומים')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('amount_from')
                                    ->label('מסכום')
                                    ->numeric(),
                                Forms\Components\TextInput::make('amount_to')
                                    ->label('עד סכום')
                                    ->numeric(),
                            ])
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['amount_from'],
                                fn(Builder $query, $amount): Builder => $query->where('amount', '>=', $amount),
                            )
                            ->when(
                                $data['amount_to'],
                                fn(Builder $query, $amount): Builder => $query->where('amount', '<=', $amount),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('צפייה'),
                    
                Action::make('download_receipt')
                    ->label('הורד קבלה')
                    ->icon('heroicon-o-document-arrow-down')
                    ->action(fn($record) => redirect()->route('payment.receipt.download', $record))
                    ->visible(fn($record) => $record->status === 'success'),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientPaymentTransactions::route('/'),
            'view' => Pages\ViewClientPaymentTransaction::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('customer_email', Auth::user()?->email)
            ->where('status', 'pending')
            ->count() ?: null;
    }
}
