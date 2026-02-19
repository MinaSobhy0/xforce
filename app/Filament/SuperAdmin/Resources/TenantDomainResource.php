<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\TenantDomainResource\Pages;
use App\Models\TenantDomain;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;

class TenantDomainResource extends Resource
{
    protected static ?string $model = TenantDomain::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'Domains & DNS';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 5;

    public static function getNavigationBadge(): ?string
    {
        $issues = TenantDomain::custom()->withSslIssues()->count();
        return $issues > 0 ? (string) $issues : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return TenantDomain::custom()->withSslIssues()->exists() ? 'danger' : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Domain Information')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('tenant_id')
                        ->label('Clinic')
                        ->relationship('tenant', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),

                    Forms\Components\TextInput::make('domain')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->placeholder('clinic.example.com'),

                    Forms\Components\Select::make('type')
                        ->options(TenantDomain::TYPES)
                        ->default('custom')
                        ->required(),

                    Forms\Components\Toggle::make('is_primary')
                        ->label('Primary Domain')
                        ->helperText('Primary domain is used for login and emails'),
                ]),

            Forms\Components\Section::make('SSL & Verification')
                ->columns(2)
                ->schema([
                    Forms\Components\Toggle::make('is_verified')
                        ->label('DNS Verified')
                        ->disabled(),

                    Forms\Components\Select::make('ssl_status')
                        ->options(TenantDomain::SSL_STATUSES)
                        ->default('pending')
                        ->disabled(),

                    Forms\Components\DateTimePicker::make('ssl_expires_at')
                        ->label('SSL Expires')
                        ->disabled(),

                    Forms\Components\DateTimePicker::make('dns_verified_at')
                        ->label('DNS Verified At')
                        ->disabled(),
                ]),

            Forms\Components\Section::make('Notes')
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->rows(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->weight(FontWeight::Bold)
                    ->copyable(),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Clinic')
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn($state) => TenantDomain::TYPES[$state] ?? $state)
                    ->color(fn($state) => $state === 'custom' ? 'info' : 'gray'),

                Tables\Columns\IconColumn::make('is_verified')
                    ->label('DNS')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('ssl_status')
                    ->label('SSL')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'valid' => 'success',
                        'expiring' => 'warning',
                        'expired', 'failed' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('ssl_expires_at')
                    ->label('SSL Expires')
                    ->date()
                    ->sortable()
                    ->placeholder('-'),

                Tables\Columns\IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(TenantDomain::TYPES),

                Tables\Filters\SelectFilter::make('ssl_status')
                    ->options(TenantDomain::SSL_STATUSES),

                Tables\Filters\TernaryFilter::make('is_verified')
                    ->label('DNS Verified'),
            ])
            ->actions([
                Tables\Actions\Action::make('show_dns_instructions')
                    ->label('DNS Setup')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->modalHeading('DNS Configuration Instructions')
                    ->modalWidth('lg')
                    ->modalContent(fn($record) => view('filament.super-admin.modals.dns-instructions', [
                        'record' => $record,
                        'targetHost' => config('app.domain', 'xlinic.com'),
                    ])),

                Tables\Actions\Action::make('verify_dns')
                    ->label('Verify DNS')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn($record) => !$record->is_verified)
                    ->action(function ($record) {
                        if ($record->verifyDns()) {
                            \Filament\Notifications\Notification::make()
                                ->title('DNS Verified')
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('DNS verification failed')
                                ->body('CNAME record not found or incorrect.')
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('provision_ssl')
                    ->label('Provision SSL')
                    ->icon('heroicon-o-shield-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Provision Let\'s Encrypt SSL')
                    ->modalDescription('This will automatically provision a free SSL certificate from Let\'s Encrypt for this domain. The domain must have valid DNS records pointing to our servers.')
                    ->visible(fn($record) => $record->is_verified && $record->ssl_status === 'pending')
                    ->action(function ($record) {
                        try {
                            $record->provisionSsl();
                            \Filament\Notifications\Notification::make()
                                ->title('SSL provisioning started')
                                ->body('The SSL certificate is being provisioned. This may take a few minutes.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('SSL provisioning failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('renew_ssl')
                    ->label('Renew SSL')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $record->is_verified && in_array($record->ssl_status, ['expiring', 'expired', 'failed']))
                    ->action(function ($record) {
                        $record->renewSsl();
                        \Filament\Notifications\Notification::make()
                            ->title('SSL renewed')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('verify_all')
                    ->label('Verify All DNS')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function ($records) {
                        $verified = 0;
                        foreach ($records as $record) {
                            if ($record->verifyDns()) {
                                $verified++;
                            }
                        }
                        \Filament\Notifications\Notification::make()
                            ->title("Verified {$verified} domains")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\BulkAction::make('renew_all_ssl')
                    ->label('Renew All SSL')
                    ->icon('heroicon-o-lock-closed')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $renewed = 0;
                        foreach ($records as $record) {
                            if ($record->is_verified) {
                                $record->renewSsl();
                                $renewed++;
                            }
                        }
                        \Filament\Notifications\Notification::make()
                            ->title("Renewed SSL for {$renewed} domains")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenantDomains::route('/'),
            'create' => Pages\CreateTenantDomain::route('/create'),
            'edit' => Pages\EditTenantDomain::route('/{record}/edit'),
        ];
    }
}
