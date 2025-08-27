<?php

namespace NMDigitalHub\PaymentGateway\Filament\Resources;

use NMDigitalHub\PaymentGateway\Filament\Resources\PaymentPageResource\Pages;
use NMDigitalHub\PaymentGateway\Models\PaymentPage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PaymentPageResource extends Resource
{
    protected static ?string $model = PaymentPage::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    
    protected static ?string $navigationLabel = 'עמודי תשלום';
    
    protected static ?string $modelLabel = 'עמוד תשלום';
    
    protected static ?string $pluralModelLabel = 'עמודי תשלום';

    protected static ?string $navigationGroup = 'תשלומים';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('מידע בסיסי')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('כותרת')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => 
                                $operation === 'create' ? $set('slug', Str::slug($state)) : null
                            ),
                        
                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->unique(PaymentPage::class, 'slug', ignoreRecord: true)
                            ->rules(['alpha_dash'])
                            ->helperText('משמש לבניית URL ייחודי'),
                        
                        Forms\Components\Textarea::make('description')
                            ->label('תיאור')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('הגדרות עמוד')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('סוג עמוד')
                            ->options(PaymentPage::getPageTypes())
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                // אוטו-גנרציה של תוכן לפי סוג
                                $page = new PaymentPage(['type' => $state]);
                                $set('content', $page->generateDefaultContent());
                            }),
                        
                        Forms\Components\Select::make('status')
                            ->label('סטטוס')
                            ->options(PaymentPage::getPageStatuses())
                            ->default(PaymentPage::STATUS_DRAFT)
                            ->required(),
                        
                        Forms\Components\Select::make('template')
                            ->label('תבנית עיצוב')
                            ->options(PaymentPage::getAvailableTemplates())
                            ->default('default')
                            ->required(),
                        
                        Forms\Components\Select::make('language')
                            ->label('שפה')
                            ->options(PaymentPage::getAvailableLanguages())
                            ->default('he')
                            ->required(),
                    ])->columns(4),

                Forms\Components\Section::make('הגדרות גישה')
                    ->schema([
                        Forms\Components\Toggle::make('is_public')
                            ->label('עמוד ציבורי')
                            ->default(true)
                            ->helperText('האם העמוד זמין לצפייה ציבורית'),
                        
                        Forms\Components\Toggle::make('require_auth')
                            ->label('דרוש אימות')
                            ->default(false)
                            ->helperText('האם נדרש להתחבר לראות את העמוד'),
                    ])->columns(2),

                Forms\Components\Section::make('תוכן העמוד')
                    ->schema([
                        Forms\Components\Builder::make('content')
                            ->label('בלוקי תוכן')
                            ->blocks([
                                Forms\Components\Builder\Block::make('heading')
                                    ->label('כותרת')
                                    ->schema([
                                        Forms\Components\TextInput::make('content')
                                            ->label('כותרת')
                                            ->required(),
                                        Forms\Components\Select::make('level')
                                            ->label('רמת כותרת')
                                            ->options(['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3'])
                                            ->default('h1'),
                                    ])
                                    ->columns(2),
                                
                                Forms\Components\Builder\Block::make('paragraph')
                                    ->label('פסקה')
                                    ->schema([
                                        Forms\Components\RichEditor::make('content')
                                            ->label('תוכן')
                                            ->required()
                                            ->toolbarButtons([
                                                'bold', 'italic', 'link', 'bulletList', 'orderedList'
                                            ]),
                                    ]),
                                
                                Forms\Components\Builder\Block::make('payment_form')
                                    ->label('טופס תשלום')
                                    ->schema([
                                        Forms\Components\TagsInput::make('allowed_methods')
                                            ->label('שיטות תשלום מותרות')
                                            ->suggestions(['cardcom', 'stripe', 'paypal'])
                                            ->placeholder('הוסף שיטת תשלום'),
                                        
                                        Forms\Components\TextInput::make('redirect_url')
                                            ->label('הפניה לאחר תשלום')
                                            ->url(),
                                    ]),
                                
                                Forms\Components\Builder\Block::make('button')
                                    ->label('כפתור')
                                    ->schema([
                                        Forms\Components\TextInput::make('text')
                                            ->label('טקסט')
                                            ->required(),
                                        Forms\Components\TextInput::make('url')
                                            ->label('קישור')
                                            ->url(),
                                        Forms\Components\Select::make('style')
                                            ->label('סגנון')
                                            ->options([
                                                'primary' => 'ראשי',
                                                'secondary' => 'משני',
                                                'success' => 'הצלחה',
                                                'danger' => 'סכנה',
                                            ])
                                            ->default('primary'),
                                    ])
                                    ->columns(3),
                            ])
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('SEO ומטא דייטה')
                    ->schema([
                        Forms\Components\KeyValue::make('seo_meta')
                            ->label('נתוני SEO')
                            ->keyLabel('מפתח')
                            ->valueLabel('ערך')
                            ->default([
                                'title' => '',
                                'description' => '',
                                'keywords' => '',
                                'image' => '',
                            ])
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('תאריכים')
                    ->schema([
                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('תאריך פרסום')
                            ->helperText('אם לא מוגדר, העמוד יפורסם מייד'),
                        
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('תאריך תפוגה')
                            ->helperText('אם לא מוגדר, העמוד לא יפוג'),
                    ])->columns(2),

                Forms\Components\Section::make('עיצוב מתקדם')
                    ->schema([
                        Forms\Components\CodeEditor::make('custom_css')
                            ->label('CSS מותאם אישית')
                            ->language('css')
                            ->columnSpanFull(),
                        
                        Forms\Components\CodeEditor::make('custom_js')
                            ->label('JavaScript מותאם אישית')
                            ->language('javascript')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
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
                
                BadgeColumn::make('status')
                    ->label('סטטוס')
                    ->formatStateUsing(fn($state) => PaymentPage::getPageStatuses()[$state] ?? $state)
                    ->colors([
                        'success' => PaymentPage::STATUS_PUBLISHED,
                        'warning' => PaymentPage::STATUS_DRAFT,
                        'gray' => PaymentPage::STATUS_ARCHIVED,
                    ]),
                
                ToggleColumn::make('is_public')
                    ->label('ציבורי'),
                
                TextColumn::make('language')
                    ->label('שפה')
                    ->formatStateUsing(fn($state) => PaymentPage::getAvailableLanguages()[$state] ?? $state)
                    ->toggleable(),
                
                TextColumn::make('published_at')
                    ->label('תאריך פרסום')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                
                TextColumn::make('created_at')
                    ->label('נוצר')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('סוג עמוד')
                    ->options(PaymentPage::getPageTypes()),
                
                SelectFilter::make('status')
                    ->label('סטטוס')
                    ->options(PaymentPage::getPageStatuses()),
                
                SelectFilter::make('language')
                    ->label('שפה')
                    ->options(PaymentPage::getAvailableLanguages()),
            ])
            ->actions([
                Action::make('preview')
                    ->label('תצוגה מקדימה')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn($record) => route('payment.page.preview', $record->slug))
                    ->openUrlInNewTab(),
                    
                Tables\Actions\EditAction::make()
                    ->label('עריכה'),
                    
                Action::make('duplicate')
                    ->label('שכפול')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function ($record) {
                        $newRecord = $record->replicate();
                        $newRecord->slug = $record->slug . '-copy';
                        $newRecord->title = $record->title . ' (עותק)';
                        $newRecord->status = PaymentPage::STATUS_DRAFT;
                        $newRecord->save();
                        
                        Notification::make()
                            ->title('העמוד שוכפל בהצלחה')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('publish')
                        ->label('פרסום')
                        ->icon('heroicon-o-check')
                        ->action(function ($records) {
                            $records->each(fn($record) => 
                                $record->update(['status' => PaymentPage::STATUS_PUBLISHED])
                            );
                            
                            Notification::make()
                                ->title('העמודים פורסמו בהצלחה')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentPages::route('/'),
            'create' => Pages\CreatePaymentPage::route('/create'),
            'edit' => Pages\EditPaymentPage::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', PaymentPage::STATUS_DRAFT)->count();
    }
}