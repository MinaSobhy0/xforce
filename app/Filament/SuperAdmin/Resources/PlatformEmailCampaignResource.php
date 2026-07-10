<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\PlatformEmailCampaignResource\Pages;
use App\Models\PlatformEmailCampaign;
use App\Models\PlatformEmailList;
use App\Services\Ai\LlmProviderRegistry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlatformEmailCampaignResource extends Resource
{
    protected static ?string $model = PlatformEmailCampaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Engagement';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Email Campaign';

    protected static ?string $pluralModelLabel = 'Email Campaigns';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Basics')->schema([
                Forms\Components\TextInput::make('name')
                    ->helperText('Internal name (recipients never see this).')
                    ->required()->maxLength(255),
                Forms\Components\TextInput::make('subject')->required()->maxLength(255),
                Forms\Components\TextInput::make('preheader')
                    ->helperText('Preview snippet shown in the inbox after the subject.')
                    ->maxLength(255),
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('from_name')
                        ->placeholder(fn () => config('mail.from.name'))
                        ->maxLength(255),
                    Forms\Components\TextInput::make('reply_to')
                        ->email()
                        ->placeholder(fn () => config('mail.reply_to.address'))
                        ->maxLength(255),
                ]),
            ])->columns(1),

            Forms\Components\Section::make('Audience')->schema([
                Forms\Components\Select::make('list_id')
                    ->label('Send to list')
                    ->relationship('list', 'name')
                    ->preload()
                    ->searchable()
                    ->required()
                    ->helperText(fn () => static::listSizeHint()),
            ]),

            Forms\Components\Section::make('Body')->schema([
                Forms\Components\RichEditor::make('body_html')
                    ->label('Message body')
                    ->toolbarButtons(['bold', 'italic', 'underline', 'link', 'orderedList', 'bulletList', 'h2', 'h3', 'blockquote', 'undo', 'redo'])
                    ->helperText('If AI personalization is on below, treat this as the BRIEF, not the final copy — the AI will rewrite it per-recipient.')
                    ->required(),
            ]),

            Forms\Components\Section::make('AI personalization')
                ->description('When enabled, the LLM rewrites the body per-recipient using their tenant / plan facts.')
                ->collapsed()
                ->schema([
                    Forms\Components\Toggle::make('ai_personalize')
                        ->live()
                        ->helperText('Off = same body sent to everyone with token replacement ({{ name_hint }}).'),

                    Forms\Components\Select::make('ai_model')
                        ->options(LlmProviderRegistry::available())
                        ->helperText(fn () => count(LlmProviderRegistry::available()) === 0
                            ? 'No AI provider is configured. Add an API key to .env to enable this.'
                            : 'Cheapest listed first.')
                        ->disabled(fn () => count(LlmProviderRegistry::available()) === 0)
                        ->visible(fn (Forms\Get $get) => (bool) $get('ai_personalize'))
                        ->required(fn (Forms\Get $get) => (bool) $get('ai_personalize')),

                    Forms\Components\Textarea::make('ai_prompt_template')
                        ->label('Author constraints (optional)')
                        ->rows(3)
                        ->placeholder("Keep the tone warm and Egyptian-Arabic-friendly. Never mention competitors. Keep under 200 words.")
                        ->visible(fn (Forms\Get $get) => (bool) $get('ai_personalize')),

                    Forms\Components\Toggle::make('ai_use_batch_api')
                        ->label('Use Batch API for scheduled sends')
                        ->helperText('Halves cost; results within ~24h. Only useful for non-urgent broadcasts.')
                        ->visible(fn (Forms\Get $get) => (bool) $get('ai_personalize')),
                ]),

            Forms\Components\Section::make('Schedule')->schema([
                Forms\Components\DateTimePicker::make('scheduled_at')
                    ->helperText('Leave blank to send immediately when you click Send Now.')
                    ->minDate(now()),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('subject')->wrap()->limit(50),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => PlatformEmailCampaign::STATUS_DRAFT,
                        'info' => PlatformEmailCampaign::STATUS_SCHEDULED,
                        'warning' => PlatformEmailCampaign::STATUS_SENDING,
                        'success' => PlatformEmailCampaign::STATUS_SENT,
                        'danger' => PlatformEmailCampaign::STATUS_CANCELLED,
                    ]),
                Tables\Columns\TextColumn::make('list.name')->label('List'),
                Tables\Columns\IconColumn::make('ai_personalize')->label('AI')->boolean(),
                Tables\Columns\TextColumn::make('sent_count')->label('Sent')->numeric(),
                Tables\Columns\TextColumn::make('opened_count')->label('Opened')->numeric()->toggleable(),
                Tables\Columns\TextColumn::make('scheduled_at')->dateTime()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->toggleable()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(PlatformEmailCampaign::STATUSES),
                Tables\Filters\TernaryFilter::make('ai_personalize')->label('AI enabled'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => $record->isEditable()),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\ReplicateAction::make()
                    ->label('Duplicate')
                    ->excludeAttributes([
                        'status', 'started_at', 'finished_at', 'scheduled_at',
                        'sent_count', 'delivered_count', 'opened_count', 'clicked_count',
                        'bounced_count', 'unsubscribed_count', 'complained_count', 'failed_count',
                        'ai_total_cost_usd_cents',
                    ])
                    ->beforeReplicaSaved(function (PlatformEmailCampaign $replica): void {
                        $replica->name = $replica->name.' (copy)';
                        $replica->status = PlatformEmailCampaign::STATUS_DRAFT;
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformEmailCampaigns::route('/'),
            'create' => Pages\CreatePlatformEmailCampaign::route('/create'),
            'edit' => Pages\EditPlatformEmailCampaign::route('/{record}/edit'),
            'view' => Pages\ViewPlatformEmailCampaign::route('/{record}'),
        ];
    }

    protected static function listSizeHint(): string
    {
        $total = PlatformEmailList::query()->where('is_active', true)->count();
        return "Choose from {$total} active list(s). New members added after Send Now do NOT retroactively get this campaign.";
    }
}
