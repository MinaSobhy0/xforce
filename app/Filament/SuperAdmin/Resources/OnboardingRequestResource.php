<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\OnboardingRequestResource\Pages;
use App\Models\OnboardingRequest;
use App\Models\SubscriptionPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class OnboardingRequestResource extends Resource
{
    protected static ?string $model = OnboardingRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Onboarding Requests';

    protected static ?string $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        return (string) OnboardingRequest::pending()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return OnboardingRequest::pending()->count() > 0 ? 'warning' : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Clinic Information')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('clinic_name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('slug')
                        ->required()
                        ->maxLength(100)
                        ->unique(ignoreRecord: true)
                        ->alphaDash()
                        ->helperText('Will be used for subdomain: slug.xforcehr.com'),

                    Forms\Components\Select::make('country')
                        ->options([
                            'EG' => 'Egypt',
                            'SA' => 'Saudi Arabia',
                            'AE' => 'UAE',
                            'KW' => 'Kuwait',
                            'QA' => 'Qatar',
                            'BH' => 'Bahrain',
                            'OM' => 'Oman',
                            'JO' => 'Jordan',
                            'LB' => 'Lebanon',
                        ])
                        ->default('EG')
                        ->required(),

                    Forms\Components\TextInput::make('city')
                        ->maxLength(100),

                    Forms\Components\Select::make('timezone')
                        ->options([
                            'Africa/Cairo' => 'Cairo (EET)',
                            'Asia/Riyadh' => 'Riyadh (AST)',
                            'Asia/Dubai' => 'Dubai (GST)',
                            'Asia/Kuwait' => 'Kuwait (AST)',
                        ])
                        ->default('Africa/Cairo')
                        ->required(),
                ]),

            Forms\Components\Section::make('Owner Information')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('owner_name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('owner_email')
                        ->email()
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('owner_phone')
                        ->tel()
                        ->maxLength(20),
                ]),

            Forms\Components\Section::make('Subscription')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('subscription_plan_id')
                        ->label('Plan')
                        ->relationship('plan', 'code')
                        ->options(fn() => SubscriptionPlan::active()->pluck('code', 'id'))
                        ->required(),

                    Forms\Components\TextInput::make('promo_code')
                        ->maxLength(50),

                    Forms\Components\Select::make('source')
                        ->options(OnboardingRequest::SOURCES)
                        ->default('manual')
                        ->required(),

                    Forms\Components\Select::make('status')
                        ->options(OnboardingRequest::STATUSES)
                        ->default('pending')
                        ->required()
                        ->disabled(fn($record) => $record && $record->status !== 'pending'),
                ]),

            Forms\Components\Section::make('Notes')
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->rows(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('clinic_name')
                    ->label('Clinic')
                    ->description(fn($record) => $record->slug . '.xforcehr.com')
                    ->searchable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('owner_name')
                    ->label('Owner')
                    ->description(fn($record) => $record->owner_email)
                    ->searchable(),

                Tables\Columns\TextColumn::make('plan.code')
                    ->label('Plan')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('source')
                    ->formatStateUsing(fn($state) => OnboardingRequest::SOURCES[$state] ?? $state)
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'pending' => 'warning',
                        'info_requested' => 'gray',
                        'in_progress' => 'info',
                        'approved', 'provisioned' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Applied')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(OnboardingRequest::STATUSES),

                Tables\Filters\SelectFilter::make('source')
                    ->options(OnboardingRequest::SOURCES),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve & Provision')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Onboarding Request')
                    ->modalDescription('This will create the tenant schema and send a welcome email to the owner.')
                    ->form([
                        Forms\Components\Toggle::make('send_welcome_email')
                            ->label('Send welcome email after provisioning')
                            ->default(true),
                        Forms\Components\Toggle::make('generate_password')
                            ->label('Generate temporary password')
                            ->default(true)
                            ->helperText('If disabled, owner will need to use password reset'),
                    ])
                    ->visible(fn($record) => in_array($record->status, ['pending', 'info_requested']))
                    ->action(function ($record, array $data) {
                        $record->approve(auth()->id());

                        // Generate temporary password if requested
                        $temporaryPassword = null;
                        if ($data['generate_password'] ?? true) {
                            $temporaryPassword = \Illuminate\Support\Str::random(12);
                        }

                        // Queue tenant provisioning job
                        // dispatch(new \App\Jobs\ProvisionTenant($record, $temporaryPassword));

                        // Send welcome email
                        if ($data['send_welcome_email'] ?? true) {
                            \Illuminate\Support\Facades\Mail::to($record->owner_email)
                                ->queue(new \App\Mail\WelcomeProvisioned($record, $temporaryPassword));
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Request approved')
                            ->body('Tenant provisioning has been queued.' .
                                ($data['send_welcome_email'] ? ' Welcome email will be sent.' : ''))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reject Onboarding Request')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Reason for rejection')
                            ->required()
                            ->rows(3),
                        Forms\Components\Toggle::make('send_email')
                            ->label('Send rejection email to applicant')
                            ->default(true),
                    ])
                    ->visible(fn($record) => $record->status === 'pending')
                    ->action(function ($record, array $data) {
                        $record->reject(auth()->id(), $data['rejection_reason']);

                        if ($data['send_email'] ?? true) {
                            // Send rejection email
                            \Illuminate\Support\Facades\Mail::to($record->owner_email)
                                ->queue(new \App\Mail\OnboardingRejected($record, $data['rejection_reason']));
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Request rejected')
                            ->body($data['send_email'] ? 'Rejection email sent to applicant.' : 'No email sent.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('request_info')
                    ->label('Request Info')
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->modalHeading('Request Additional Information')
                    ->form([
                        Forms\Components\TextInput::make('subject')
                            ->label('Email Subject')
                            ->default('Additional Information Required for Your XLinic Application')
                            ->required(),
                        Forms\Components\RichEditor::make('message')
                            ->label('Message')
                            ->required()
                            ->default(fn($record) => "Dear {$record->owner_name},\n\nThank you for your interest in XLinic. We need some additional information to process your application for {$record->clinic_name}.\n\nPlease provide:\n- \n\nBest regards,\nXLinic Team"),
                    ])
                    ->visible(fn($record) => $record->status === 'pending')
                    ->action(function ($record, array $data) {
                        // Send info request email
                        \Illuminate\Support\Facades\Mail::to($record->owner_email)
                            ->queue(new \App\Mail\OnboardingInfoRequest($record, $data['subject'], $data['message']));

                        // Update status to show we're waiting for info
                        $record->update([
                            'status' => 'info_requested',
                            'notes' => ($record->notes ? $record->notes . "\n\n" : '') .
                                "[" . now()->format('Y-m-d H:i') . "] Info requested: " . strip_tags($data['message']),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Information request sent')
                            ->body("Email sent to {$record->owner_email}")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->visible(fn() => false), // Disable bulk delete for safety
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Clinic Information')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('clinic_name'),
                    Infolists\Components\TextEntry::make('slug')
                        ->label('Subdomain')
                        ->formatStateUsing(fn($state) => $state . '.xforcehr.com'),
                    Infolists\Components\TextEntry::make('country'),
                    Infolists\Components\TextEntry::make('city'),
                    Infolists\Components\TextEntry::make('timezone'),
                ]),

            Infolists\Components\Section::make('Owner Information')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('owner_name'),
                    Infolists\Components\TextEntry::make('owner_email')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('owner_phone')
                        ->copyable(),
                ]),

            Infolists\Components\Section::make('Subscription')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('plan.code')
                        ->label('Plan')
                        ->badge(),
                    Infolists\Components\TextEntry::make('promo_code')
                        ->placeholder('None'),
                    Infolists\Components\TextEntry::make('source')
                        ->formatStateUsing(fn($state) => OnboardingRequest::SOURCES[$state] ?? $state),
                ]),

            Infolists\Components\Section::make('Status')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('status')
                        ->badge()
                        ->color(fn(string $state) => match ($state) {
                            'pending' => 'warning',
                            'in_progress' => 'info',
                            'approved', 'provisioned' => 'success',
                            'rejected' => 'danger',
                            default => 'gray',
                        }),
                    Infolists\Components\TextEntry::make('created_at')
                        ->label('Applied')
                        ->dateTime(),
                    Infolists\Components\TextEntry::make('approved_at')
                        ->dateTime()
                        ->placeholder('Not approved'),
                    Infolists\Components\TextEntry::make('rejection_reason')
                        ->visible(fn($record) => $record->status === 'rejected')
                        ->columnSpanFull(),
                ]),

            Infolists\Components\Section::make('Notes')
                ->schema([
                    Infolists\Components\TextEntry::make('notes')
                        ->placeholder('No notes'),
                ])
                ->collapsible(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOnboardingRequests::route('/'),
            'create' => Pages\CreateOnboardingRequest::route('/create'),
            'view' => Pages\ViewOnboardingRequest::route('/{record}'),
            'edit' => Pages\EditOnboardingRequest::route('/{record}/edit'),
        ];
    }
}
