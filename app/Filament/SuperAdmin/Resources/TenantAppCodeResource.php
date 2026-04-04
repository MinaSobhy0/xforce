<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\TenantAppCodeResource\Pages;
use App\Models\TenantAppCode;
use Modules\Core\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TenantAppCodeResource extends Resource
{
    protected static ?string $model = TenantAppCode::class;

    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationLabel = 'Mobile App Codes';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'tenant.name'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Code Configuration')
                ->icon('heroicon-o-qr-code')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('tenant_id')
                        ->label('Clinic')
                        ->relationship('tenant', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('code')
                        ->label('App Code')
                        ->default(fn() => strtoupper(Str::random(8)))
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(10)
                        ->helperText('Unique code for mobile app onboarding')
                        ->suffixAction(
                            Forms\Components\Actions\Action::make('regenerate')
                                ->icon('heroicon-o-arrow-path')
                                ->action(fn(Forms\Set $set) => $set('code', strtoupper(Str::random(8))))
                        ),

                    Forms\Components\Select::make('type')
                        ->options(TenantAppCode::getTypeOptions())
                        ->default('default')
                        ->required(),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->default(true)
                        ->inline(false),

                    Forms\Components\DateTimePicker::make('expires_at')
                        ->label('Expires At')
                        ->nullable()
                        ->helperText('Leave empty for no expiration'),
                ]),

            Forms\Components\Section::make('Usage Limits')
                ->icon('heroicon-o-chart-bar')
                ->columns(2)
                ->collapsible()
                ->schema([
                    Forms\Components\TextInput::make('max_uses')
                        ->label('Maximum Uses')
                        ->numeric()
                        ->nullable()
                        ->minValue(1)
                        ->helperText('Leave empty for unlimited uses'),

                    Forms\Components\Placeholder::make('usage_count_display')
                        ->label('Current Usage')
                        ->content(fn(?TenantAppCode $record) => $record?->usage_count ?? 0),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Clinic')
                    ->searchable()
                    ->sortable()
                    ->description(fn(TenantAppCode $record) => $record->tenant?->slug . '.xforcehr.com'),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->copyable()
                    ->copyMessage('Code copied!')
                    ->searchable()
                    ->fontFamily('mono')
                    ->weight('bold'),

                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'default',
                        'info' => 'qr',
                        'success' => 'staff',
                        'warning' => 'invitation',
                    ]),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('usage_count')
                    ->label('Uses')
                    ->formatStateUsing(function ($state, TenantAppCode $record) {
                        $limit = $record->max_uses ?? '∞';
                        return "{$state}/{$limit}";
                    }),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->date()
                    ->placeholder('Never')
                    ->color(fn(TenantAppCode $record) =>
                        $record->expires_at && $record->expires_at->isPast() ? 'danger' : null
                    ),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Clinic')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('type')
                    ->options(TenantAppCode::getTypeOptions()),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\Filter::make('expired')
                    ->label('Expired')
                    ->query(fn(Builder $query) =>
                        $query->whereNotNull('expires_at')
                            ->where('expires_at', '<', now())
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('viewQr')
                    ->label('QR Code')
                    ->icon('heroicon-o-qr-code')
                    ->color('info')
                    ->modalHeading('QR Code')
                    ->modalContent(function (TenantAppCode $record) {
                        // Use web_link for QR (https:// URL works with all QR scanners)
                        $qrData = $record->web_link;

                        $renderer = new ImageRenderer(
                            new RendererStyle(250, 2),
                            new SvgImageBackEnd()
                        );
                        $writer = new Writer($renderer);
                        $qrCode = base64_encode($writer->writeString($qrData));

                        return view('filament.components.qr-code-modal', [
                            'qrCode' => $qrCode,
                            'code' => $record->code,
                            'deepLink' => $record->deep_link,
                            'webLink' => $record->web_link,
                            'tenant' => $record->tenant?->name,
                        ]);
                    })
                    ->modalWidth('sm')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Tables\Actions\Action::make('copyDeepLink')
                    ->label('Copy Link')
                    ->icon('heroicon-o-clipboard')
                    ->action(function (TenantAppCode $record) {
                        Notification::make()
                            ->title('Deep link copied!')
                            ->body($record->deep_link)
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),

                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('regenerate')
                        ->label('Regenerate Code')
                        ->icon('heroicon-o-arrow-path')
                        ->requiresConfirmation()
                        ->action(function (TenantAppCode $record) {
                            $record->update([
                                'code' => TenantAppCode::generateUniqueCode(),
                                'usage_count' => 0,
                            ]);

                            Notification::make()
                                ->title('Code regenerated')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('deactivate')
                        ->label('Deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn(TenantAppCode $record) => $record->is_active)
                        ->action(function (TenantAppCode $record) {
                            $record->update(['is_active' => false]);

                            Notification::make()
                                ->title('Code deactivated')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn(TenantAppCode $record) => !$record->is_active)
                        ->action(function (TenantAppCode $record) {
                            $record->update(['is_active' => true]);

                            Notification::make()
                                ->title('Code activated')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('deactivate')
                    ->label('Deactivate Selected')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        foreach ($records as $record) {
                            $record->update(['is_active' => false]);
                        }

                        Notification::make()
                            ->title('Codes deactivated')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenantAppCodes::route('/'),
            'create' => Pages\CreateTenantAppCode::route('/create'),
            'edit' => Pages\EditTenantAppCode::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('is_active', true)->count();
    }
}
