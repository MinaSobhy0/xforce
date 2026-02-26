<?php

namespace Modules\Patients\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Patients\Filament\Resources\PatientResource\Pages;
use Modules\Patients\Filament\Resources\PatientResource\RelationManagers;
use Modules\Patients\Filament\Forms\Components\FitzpatrickTypeSelector;
use Modules\Patients\Models\Patient;
use Modules\Patients\Models\PatientMedicalHistory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use XLinic\Framework\Core\Filament\RelationManagers\ActivityLogRelationManager;

class PatientResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = Patient::class;

    protected static ?string $moduleCode = 'patients';

    protected static ?string $permissionKey = 'patients';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function getNavigationLabel(): string
    {
        return __('patients::patients.navigation.patients');
    }

    public static function getModelLabel(): string
    {
        return __('patients::patients.labels.patient');
    }

    public static function getPluralModelLabel(): string
    {
        return __('patients::patients.labels.patients');
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            return static::getModel()::whereMonth('created_at', now()->month)->count() ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Patient')
                    ->tabs([
                        // Personal Information Tab
                        Forms\Components\Tabs\Tab::make(__('patients::patients.labels.personal_info'))
                            ->icon('heroicon-o-user')
                            ->schema([
                                Forms\Components\Section::make(__('patients::patients.labels.personal_info'))
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('code')
                                            ->label(__('patients::patients.fields.code'))
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->visibleOn('edit'),

                                        Forms\Components\TextInput::make('first_name')
                                            ->label(__('patients::patients.fields.first_name'))
                                            ->required()
                                            ->maxLength(100),

                                        Forms\Components\TextInput::make('last_name')
                                            ->label(__('patients::patients.fields.last_name'))
                                            ->required()
                                            ->maxLength(100),

                                        Forms\Components\DatePicker::make('date_of_birth')
                                            ->label(__('patients::patients.fields.date_of_birth'))
                                            ->maxDate(now())
                                            ->displayFormat('d/m/Y'),

                                        Forms\Components\Select::make('gender')
                                            ->label(__('patients::patients.fields.gender'))
                                            ->options([
                                                'male' => __('patients::patients.gender_options.male'),
                                                'female' => __('patients::patients.gender_options.female'),
                                                'other' => __('patients::patients.gender_options.other'),
                                            ]),

                                        Forms\Components\TextInput::make('national_id')
                                            ->label(__('patients::patients.fields.national_id'))
                                            ->maxLength(50),

                                        Forms\Components\TextInput::make('occupation')
                                            ->label(__('patients::patients.fields.occupation'))
                                            ->maxLength(100),
                                    ]),

                                Forms\Components\Section::make(__('patients::patients.labels.contact_info'))
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('phone')
                                            ->label(__('patients::patients.fields.phone'))
                                            ->tel()
                                            ->required()
                                            ->maxLength(20)
                                            ->unique(
                                                table: Patient::class,
                                                column: 'phone',
                                                ignoreRecord: true,
                                                modifyRuleUsing: fn ($rule) => $rule->whereNull('deleted_at'),
                                            ),

                                        Forms\Components\TextInput::make('secondary_phone')
                                            ->label(__('patients::patients.fields.secondary_phone'))
                                            ->tel()
                                            ->maxLength(20),

                                        Forms\Components\TextInput::make('email')
                                            ->label(__('patients::patients.fields.email'))
                                            ->email()
                                            ->maxLength(255),

                                        Forms\Components\Textarea::make('address')
                                            ->label(__('patients::patients.fields.address'))
                                            ->rows(2)
                                            ->columnSpanFull(),

                                        Forms\Components\TextInput::make('city')
                                            ->label(__('patients::patients.fields.city'))
                                            ->maxLength(100),

                                        Forms\Components\Select::make('country')
                                            ->label(__('patients::patients.fields.country'))
                                            ->searchable()
                                            ->default('Egypt')
                                            ->options([
                                                'Egypt' => 'Egypt',
                                                'Saudi Arabia' => 'Saudi Arabia',
                                                'UAE' => 'UAE',
                                                'Kuwait' => 'Kuwait',
                                                'Qatar' => 'Qatar',
                                                'Bahrain' => 'Bahrain',
                                                'Oman' => 'Oman',
                                                'Jordan' => 'Jordan',
                                                'Lebanon' => 'Lebanon',
                                            ]),
                                    ]),

                                Forms\Components\Section::make(__('patients::patients.fields.emergency_contact'))
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('emergency_contact_name')
                                            ->label(__('patients::patients.fields.emergency_contact'))
                                            ->maxLength(200),

                                        Forms\Components\TextInput::make('emergency_contact_phone')
                                            ->label(__('patients::patients.fields.emergency_phone'))
                                            ->tel()
                                            ->maxLength(20),

                                        Forms\Components\TextInput::make('emergency_contact_relation')
                                            ->label('Relation')
                                            ->maxLength(50),
                                    ]),

                                Forms\Components\Section::make('Referral & Status')
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\Select::make('referral_source')
                                            ->label(__('patients::patients.fields.referral_source'))
                                            ->options(config('patients.referral_sources'))
                                            ->searchable(),

                                        Forms\Components\Select::make('referred_by_patient_id')
                                            ->label(__('patients::patients.fields.referred_by'))
                                            ->relationship('referredBy', 'first_name')
                                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                                            ->searchable(['first_name', 'last_name', 'phone'])
                                            ->preload(),

                                        Forms\Components\Select::make('status')
                                            ->label(__('patients::patients.fields.status'))
                                            ->options([
                                                'active' => __('patients::patients.status_options.active'),
                                                'inactive' => __('patients::patients.status_options.inactive'),
                                                'blocked' => __('patients::patients.status_options.blocked'),
                                            ])
                                            ->default('active')
                                            ->required(),

                                        Forms\Components\TagsInput::make('tags')
                                            ->label(__('patients::patients.fields.tags'))
                                            ->columnSpanFull(),

                                        Forms\Components\Textarea::make('notes')
                                            ->label(__('patients::patients.fields.notes'))
                                            ->rows(3)
                                            ->columnSpanFull(),
                                    ]),

                                Forms\Components\Section::make('Marketing Preferences')
                                    ->columns(4)
                                    ->schema([
                                        Forms\Components\Toggle::make('marketing_consent')
                                            ->label('Marketing Consent'),

                                        Forms\Components\Toggle::make('sms_consent')
                                            ->label('SMS')
                                            ->default(true),

                                        Forms\Components\Toggle::make('email_consent')
                                            ->label('Email')
                                            ->default(true),

                                        Forms\Components\Toggle::make('whatsapp_consent')
                                            ->label('WhatsApp')
                                            ->default(true),
                                    ]),
                            ]),

                        // Medical History Tab
                        Forms\Components\Tabs\Tab::make(__('patients::patients.labels.medical_history'))
                            ->icon('heroicon-o-heart')
                            ->schema([
                                Forms\Components\Section::make(__('patients::patients.sections.skin_assessment'))
                                    ->schema([
                                        FitzpatrickTypeSelector::make('medicalHistory.fitzpatrick_type')
                                            ->label(__('patients::patients.medical.fitzpatrick_type'))
                                            ->columnSpanFull(),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\Select::make('medicalHistory.blood_type')
                                                    ->label(__('patients::patients.medical.blood_type'))
                                                    ->options(PatientMedicalHistory::BLOOD_TYPES),

                                                Forms\Components\Select::make('medicalHistory.sun_exposure_level')
                                                    ->label(__('patients::patients.medical.sun_exposure'))
                                                    ->options([
                                                        'minimal' => __('patients::patients.medical.sun_levels.minimal'),
                                                        'moderate' => __('patients::patients.medical.sun_levels.moderate'),
                                                        'high' => __('patients::patients.medical.sun_levels.high'),
                                                    ]),
                                            ]),
                                    ]),

                                Forms\Components\Section::make('Health Information')
                                    ->columns(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('medicalHistory.height_cm')
                                            ->label('Height (cm)')
                                            ->numeric()
                                            ->minValue(50)
                                            ->maxValue(250),

                                        Forms\Components\TextInput::make('medicalHistory.weight_kg')
                                            ->label('Weight (kg)')
                                            ->numeric()
                                            ->minValue(20)
                                            ->maxValue(300),

                                        Forms\Components\Toggle::make('medicalHistory.is_smoker')
                                            ->label('Smoker'),
                                    ]),

                                Forms\Components\Section::make('Medical Conditions')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\TagsInput::make('medicalHistory.allergies')
                                            ->label(__('patients::patients.medical.allergies'))
                                            ->placeholder('Add allergies...'),

                                        Forms\Components\TagsInput::make('medicalHistory.current_medications')
                                            ->label(__('patients::patients.medical.medications'))
                                            ->placeholder('Add medications...'),

                                        Forms\Components\CheckboxList::make('medicalHistory.medical_conditions')
                                            ->label(__('patients::patients.medical.medical_conditions'))
                                            ->options(PatientMedicalHistory::COMMON_CONDITIONS)
                                            ->columns(3)
                                            ->columnSpanFull(),

                                        Forms\Components\CheckboxList::make('medicalHistory.contraindications')
                                            ->label(__('patients::patients.medical.contraindications'))
                                            ->options(PatientMedicalHistory::CONTRAINDICATIONS)
                                            ->columns(3)
                                            ->columnSpanFull(),
                                    ]),

                                Forms\Components\Section::make("Women's Health")
                                    ->columns(4)
                                    ->visible(fn ($get) => $get('gender') === 'female')
                                    ->schema([
                                        Forms\Components\Toggle::make('medicalHistory.is_pregnant')
                                            ->label(__('patients::patients.medical.pregnancy_status')),

                                        Forms\Components\Toggle::make('medicalHistory.is_breastfeeding')
                                            ->label(__('patients::patients.medical.breastfeeding')),

                                        Forms\Components\Toggle::make('medicalHistory.hormone_therapy')
                                            ->label('Hormone Therapy'),

                                        Forms\Components\DatePicker::make('medicalHistory.last_menstrual_date')
                                            ->label('Last Menstrual Date'),
                                    ]),

                                Forms\Components\Section::make('Previous Treatments')
                                    ->schema([
                                        Forms\Components\TagsInput::make('medicalHistory.previous_cosmetic_treatments')
                                            ->label(__('patients::patients.medical.previous_treatments'))
                                            ->placeholder('Add previous treatments...'),

                                        Forms\Components\TagsInput::make('medicalHistory.skin_concerns')
                                            ->label(__('patients::patients.medical.skin_concerns'))
                                            ->placeholder('Add skin concerns...'),

                                        Forms\Components\Textarea::make('medicalHistory.notes')
                                            ->label(__('patients::patients.fields.notes'))
                                            ->rows(3),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->persistTabInQueryString(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('patients::patients.fields.code'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label(__('patients::patients.fields.full_name'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name']),

                Tables\Columns\TextColumn::make('phone')
                    ->label(__('patients::patients.fields.phone'))
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('email')
                    ->label(__('patients::patients.fields.email'))
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('age')
                    ->label(__('patients::patients.fields.age'))
                    ->suffix(' yrs')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('date_of_birth', $direction === 'desc' ? 'asc' : 'desc');
                    }),

                Tables\Columns\TextColumn::make('gender')
                    ->label(__('patients::patients.fields.gender'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => __("patients::patients.gender_options.{$state}"))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_visits')
                    ->label('Visits')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_visit_at')
                    ->label(__('patients::patients.fields.last_visit'))
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('patients::patients.fields.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'blocked' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('patients::patients.fields.created_at'))
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('patients::patients.fields.status'))
                    ->options([
                        'active' => __('patients::patients.status_options.active'),
                        'inactive' => __('patients::patients.status_options.inactive'),
                        'blocked' => __('patients::patients.status_options.blocked'),
                    ]),

                Tables\Filters\SelectFilter::make('gender')
                    ->label(__('patients::patients.fields.gender'))
                    ->options([
                        'male' => __('patients::patients.gender_options.male'),
                        'female' => __('patients::patients.gender_options.female'),
                    ]),

                Tables\Filters\SelectFilter::make('referral_source')
                    ->label(__('patients::patients.fields.referral_source'))
                    ->options(config('patients.referral_sources')),

                Tables\Filters\Filter::make('new_this_month')
                    ->label(__('patients::patients.filters.new_this_month'))
                    ->query(fn (Builder $query): Builder => $query->newThisMonth()),

                Tables\Filters\Filter::make('recent_visitors')
                    ->label('Visited in last 30 days')
                    ->query(fn (Builder $query): Builder => $query->recentVisitors(30)),

                Tables\Filters\Filter::make('inactive_visitors')
                    ->label('No visit in 90 days')
                    ->query(fn (Builder $query): Builder => $query->inactiveVisitors(90)),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('book_appointment')
                    ->label(__('patients::patients.actions.book_appointment'))
                    ->icon('heroicon-o-calendar')
                    ->color('success')
                    ->url(fn (Patient $record): string => '#'), // TODO: Link to booking
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\NotesRelationManager::class,
            RelationManagers\PhotosRelationManager::class,
            RelationManagers\ConsentFormsRelationManager::class,
            RelationManagers\AppointmentsRelationManager::class,
            RelationManagers\TreatmentPlansRelationManager::class,
            RelationManagers\InvoicesRelationManager::class,
            RelationManagers\PackagesRelationManager::class,
            RelationManagers\LoyaltyRelationManager::class,
            RelationManagers\AmrTestsRelationManager::class,
            ActivityLogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPatients::route('/'),
            'create' => Pages\CreatePatient::route('/create'),
            'view' => Pages\ViewPatient::route('/{record}'),
            'edit' => Pages\EditPatient::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'first_name', 'last_name', 'phone', 'email'];
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Phone' => $record->phone,
            'Email' => $record->email,
        ];
    }
}
