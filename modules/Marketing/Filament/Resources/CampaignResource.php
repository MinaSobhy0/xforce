<?php

namespace Modules\Marketing\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Marketing\Filament\Resources\CampaignResource\Pages;
use Modules\Marketing\Filament\Resources\CampaignResource\RelationManagers;
use Modules\Marketing\Models\Campaign;
use Modules\Marketing\Models\MessageTemplate;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('marketing::marketing.navigation.campaigns');
    }

    public static function getModelLabel(): string
    {
        return __('marketing::marketing.labels.campaign');
    }

    public static function getPluralModelLabel(): string
    {
        return __('marketing::marketing.labels.campaigns');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('marketing::marketing.sections.campaign_details'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name.en')
                                    ->label(__('marketing::marketing.fields.name_en'))
                                    ->required(),

                                Forms\Components\TextInput::make('name.ar')
                                    ->label(__('marketing::marketing.fields.name_ar'))
                                    ->required(),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Textarea::make('description.en')
                                    ->label(__('marketing::marketing.fields.description_en'))
                                    ->rows(2),

                                Forms\Components\Textarea::make('description.ar')
                                    ->label(__('marketing::marketing.fields.description_ar'))
                                    ->rows(2),
                            ]),
                    ]),

                Forms\Components\Section::make(__('marketing::marketing.sections.message_settings'))
                    ->schema([
                        Forms\Components\Select::make('channel')
                            ->label(__('marketing::marketing.fields.channel'))
                            ->options(MessageTemplate::channels())
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('template_id')
                            ->label(__('marketing::marketing.fields.template'))
                            ->options(function (Forms\Get $get) {
                                $channel = $get('channel');
                                if (!$channel) {
                                    return [];
                                }
                                return MessageTemplate::where('channel', $channel)
                                    ->where('is_active', true)
                                    ->pluck('name', 'id')
                                    ->map(fn ($name) => is_array($name) ? ($name['en'] ?? reset($name)) : $name);
                            })
                            ->required()
                            ->searchable(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('marketing::marketing.sections.audience'))
                    ->schema([
                        Forms\Components\Repeater::make('audience_filters_json')
                            ->label(__('marketing::marketing.fields.audience_filters'))
                            ->schema([
                                Forms\Components\Select::make('field')
                                    ->label(__('marketing::marketing.fields.filter_field'))
                                    ->options([
                                        'last_visit_days_ago' => __('marketing::marketing.filter_fields.last_visit'),
                                        'total_spent_min' => __('marketing::marketing.filter_fields.total_spent_min'),
                                        'total_spent_max' => __('marketing::marketing.filter_fields.total_spent_max'),
                                        'treatment_id' => __('marketing::marketing.filter_fields.treatment'),
                                        'branch_id' => __('marketing::marketing.filter_fields.branch'),
                                        'gender' => __('marketing::marketing.filter_fields.gender'),
                                        'is_vip' => __('marketing::marketing.filter_fields.is_vip'),
                                        'tags' => __('marketing::marketing.filter_fields.tags'),
                                    ])
                                    ->required(),

                                Forms\Components\Select::make('operator')
                                    ->label(__('marketing::marketing.fields.filter_operator'))
                                    ->options([
                                        'equals' => '=',
                                        'not_equals' => '!=',
                                        'greater_than' => '>',
                                        'less_than' => '<',
                                        'contains' => __('marketing::marketing.operators.contains'),
                                        'in' => __('marketing::marketing.operators.in'),
                                    ])
                                    ->default('equals'),

                                Forms\Components\TextInput::make('value')
                                    ->label(__('marketing::marketing.fields.filter_value'))
                                    ->required(),
                            ])
                            ->columns(3)
                            ->addActionLabel(__('marketing::marketing.actions.add_filter'))
                            ->collapsible()
                            ->defaultItems(0),
                    ]),

                Forms\Components\Section::make(__('marketing::marketing.sections.scheduling'))
                    ->schema([
                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->label(__('marketing::marketing.fields.scheduled_at'))
                            ->helperText(__('marketing::marketing.helpers.scheduled_at'))
                            ->native(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('marketing::marketing.fields.name'))
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('channel')
                    ->label(__('marketing::marketing.fields.channel'))
                    ->colors([
                        'success' => 'whatsapp',
                        'info' => 'sms',
                        'primary' => 'email',
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('marketing::marketing.fields.status'))
                    ->colors([
                        'gray' => 'draft',
                        'info' => 'scheduled',
                        'warning' => fn ($state) => in_array($state, ['sending', 'paused']),
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state) => Campaign::statuses()[$state] ?? $state),

                Tables\Columns\TextColumn::make('total_recipients')
                    ->label(__('marketing::marketing.fields.recipients'))
                    ->numeric(),

                Tables\Columns\TextColumn::make('sent_count')
                    ->label(__('marketing::marketing.fields.sent'))
                    ->numeric(),

                Tables\Columns\TextColumn::make('delivered_count')
                    ->label(__('marketing::marketing.fields.delivered'))
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('failed_count')
                    ->label(__('marketing::marketing.fields.failed'))
                    ->numeric()
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label(__('marketing::marketing.fields.scheduled_at'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('marketing::marketing.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('channel')
                    ->label(__('marketing::marketing.fields.channel'))
                    ->options(MessageTemplate::channels()),

                Tables\Filters\SelectFilter::make('status')
                    ->label(__('marketing::marketing.fields.status'))
                    ->options(Campaign::statuses()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Campaign $record) => $record->isEditable()),

                Tables\Actions\Action::make('schedule')
                    ->label(__('marketing::marketing.actions.schedule'))
                    ->icon('heroicon-o-clock')
                    ->color('info')
                    ->form([
                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->label(__('marketing::marketing.fields.scheduled_at'))
                            ->required()
                            ->native(false)
                            ->minDate(now()),
                    ])
                    ->action(function (Campaign $record, array $data) {
                        $record->schedule(new \DateTime($data['scheduled_at']));
                    })
                    ->visible(fn (Campaign $record) => $record->status === Campaign::STATUS_DRAFT),

                Tables\Actions\Action::make('pause')
                    ->label(__('marketing::marketing.actions.pause'))
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (Campaign $record) => $record->pause())
                    ->visible(fn (Campaign $record) => $record->status === Campaign::STATUS_SENDING),

                Tables\Actions\Action::make('resume')
                    ->label(__('marketing::marketing.actions.resume'))
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->action(fn (Campaign $record) => $record->resume())
                    ->visible(fn (Campaign $record) => $record->status === Campaign::STATUS_PAUSED),

                Tables\Actions\Action::make('cancel')
                    ->label(__('marketing::marketing.actions.cancel'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Campaign $record) => $record->cancel())
                    ->visible(fn (Campaign $record) => in_array($record->status, [
                        Campaign::STATUS_SCHEDULED,
                        Campaign::STATUS_SENDING,
                        Campaign::STATUS_PAUSED,
                    ])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => true), // Only drafts should be deletable
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RecipientsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampaigns::route('/'),
            'create' => Pages\CreateCampaign::route('/create'),
            'view' => Pages\ViewCampaign::route('/{record}'),
            'edit' => Pages\EditCampaign::route('/{record}/edit'),
        ];
    }
}
