<?php

namespace NMDigitalHub\PaymentGateway\Filament\Client\Resources;

use NMDigitalHub\PaymentGateway\Filament\Client\Resources\ClientPaymentPageResource\Pages;
use NMDigitalHub\PaymentGateway\Models\PaymentPage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;

class ClientPaymentPageResource extends Resource
{
    protected static ?string $model = PaymentPage::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    
    protected static ?string $navigationLabel = 'עמודי תשלום ציבוריים';
    
    protected static ?string $modelLabel = 'עמוד תשלום';
    
    protected static ?string $pluralModelLabel = 'עמודי תשלום';

    protected static ?string $navigationGroup = 'החשבון שלי';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        // לקוחות רואים רק עמודים ציבוריים ומפורסמים
        return parent::getEloquentQuery()
            ->where('is_public', true)
            ->where('status', PaymentPage::STATUS_PUBLISHED)
            ->orderBy('created_at', 'desc');
    }

    public static function canCreate(): bool
    {
        return false; // לקוחות לא יכולים ליצור עמודים
    }

    public static function canEdit($record): bool
    {
        return false; // לקוחות לא יכולים לערוך
    }

    public static function canDelete($record): bool
    {
        return false; // לקוחות לא יכולים למחוק
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('מידע בסיסי')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('כותרת')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->disabled(),
                        
                        Forms\Components\Textarea::make('description')
                            ->label('תיאור')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('מידע עמוד')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('סוג עמוד')
                            ->options(PaymentPage::getPageTypes())
                            ->disabled(),
                        
                        Forms\Components\Select::make('language')
                            ->label('שפה')
                            ->options(PaymentPage::getAvailableLanguages())
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('created_at')
                            ->label('נוצר בתאריך')
                            ->disabled(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('כותרת')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Slug נשמר ללוח'),
                
                BadgeColumn::make('type')
                    ->label('סוג')
                    ->formatStateUsing(fn($state) => PaymentPage::getPageTypes()[$state] ?? $state)
                    ->colors([
                        'primary' => PaymentPage::TYPE_CHECKOUT,
                        'success' => PaymentPage::TYPE_SUCCESS,
                        'danger' => PaymentPage::TYPE_FAILED,
                        'warning' => PaymentPage::TYPE_PENDING,
                        'info' => fn($state) => in_array($state, [PaymentPage::TYPE_LANDING, PaymentPage::TYPE_CUSTOM]),
                    ]),
                
                TextColumn::make('language')
                    ->label('שפה')
                    ->formatStateUsing(fn($state) => PaymentPage::getAvailableLanguages()[$state] ?? $state)
                    ->badge(),
                
                TextColumn::make('published_at')
                    ->label('תאריך פרסום')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('סוג עמוד')
                    ->options(PaymentPage::getPageTypes()),
                
                SelectFilter::make('language')
                    ->label('שפה')
                    ->options(PaymentPage::getAvailableLanguages()),
            ])
            ->actions([
                Action::make('view_page')
                    ->label('צפייה בעמוד')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn($record) => route('payment.page.show', $record->slug))
                    ->openUrlInNewTab(),
                    
                Tables\Actions\ViewAction::make()
                    ->label('פרטים'),
            ])
            ->bulkActions([])
            ->defaultSort('published_at', 'desc')
            ->paginated([10, 25]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientPaymentPages::route('/'),
            'view' => Pages\ViewClientPaymentPage::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('is_public', true)
            ->where('status', PaymentPage::STATUS_PUBLISHED)
            ->count();
            
        return $count > 0 ? (string) $count : null;
    }
}
