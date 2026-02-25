<?php

namespace Modules\Booking\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions\Action;
use Modules\Booking\Models\BookingRule;
use Modules\Booking\Models\BookingBlackoutDate;
use Modules\Core\Models\Branch;
use Modules\Services\Models\Service;
use Modules\Booking\Services\SlotGenerationService;
use Carbon\Carbon;
use App\Traits\ChecksResourcePermissions;

class BookingSlotConfigPage extends Page implements Forms\Contracts\HasForms, HasTable
{
    use Forms\Concerns\InteractsWithForms;
    use InteractsWithTable;
    use ChecksResourcePermissions;

    protected static ?string $moduleCode = 'booking';
    protected static ?string $permissionKey = 'appointments';

    protected static ?string $navigationIcon = 'heroicon-o-cog-8-tooth';
    protected static string $view = 'booking::filament.pages.booking-slot-config';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 50;
    protected static ?string $slug = 'booking-configuration';

    // Preview properties
    public ?string $previewServiceId = null;
    public ?string $previewDate = null;
    public ?array $previewSlots = null;

    public static function getNavigationLabel(): string
    {
        return __('booking::config.booking_configuration');
    }

    public function getTitle(): string
    {
        return __('booking::config.booking_configuration');
    }

    public function getSubheading(): ?string
    {
        return __('booking::config.booking_config_description');
    }

    public function mount(): void
    {
        $this->previewDate = now()->addDay()->format('Y-m-d');
        $this->ensureDefaultRulesExist();
    }

    /**
     * Ensure default tenant-level rules exist for essential settings
     */
    protected function ensureDefaultRulesExist(): void
    {
        $defaults = [
            [
                'code' => 'default-slot-duration',
                'name' => __('booking::config.default_slot_duration'),
                'rule_type' => BookingRule::TYPE_SLOT_DURATION,
                'actions' => ['duration_minutes' => 30],
                'priority' => 0,
            ],
            [
                'code' => 'default-buffer',
                'name' => __('booking::config.default_buffer'),
                'rule_type' => BookingRule::TYPE_SLOT_BUFFER,
                'actions' => ['buffer_minutes' => 5],
                'priority' => 0,
            ],
            [
                'code' => 'default-working-hours',
                'name' => __('booking::config.default_working_hours'),
                'rule_type' => BookingRule::TYPE_WORKING_HOURS,
                'actions' => ['start_time' => '09:00', 'end_time' => '21:00'],
                'priority' => 0,
            ],
            [
                'code' => 'default-advance-booking',
                'name' => __('booking::config.default_advance_booking'),
                'rule_type' => BookingRule::TYPE_MIN_ADVANCE,
                'actions' => ['min_hours' => 2],
                'priority' => 0,
            ],
            [
                'code' => 'default-max-advance',
                'name' => __('booking::config.default_max_advance'),
                'rule_type' => BookingRule::TYPE_MAX_ADVANCE,
                'actions' => ['max_days' => 60],
                'priority' => 0,
            ],
        ];

        foreach ($defaults as $default) {
            BookingRule::firstOrCreate(
                ['code' => $default['code'], 'scope_level' => 'tenant'],
                array_merge($default, [
                    'scope_level' => 'tenant',
                    'is_active' => true,
                    'conditions' => [],
                ])
            );
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_rule')
                ->label(__('booking::config.add_rule'))
                ->icon('heroicon-o-plus')
                ->url(route('filament.tenant.resources.booking-rules.create'))
                ->color('primary'),

            Action::make('manage_blackouts')
                ->label(__('booking::config.view_all_blackouts'))
                ->icon('heroicon-o-calendar-days')
                ->url(route('filament.tenant.resources.booking-blackout-dates.index'))
                ->color('gray'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(BookingRule::query()->orderByDesc('priority'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('booking::config.rule_name'))
                    ->searchable()
                    ->description(fn (BookingRule $record) => $record->description),

                Tables\Columns\TextColumn::make('rule_category_label')
                    ->label(__('booking::config.category'))
                    ->badge()
                    ->color(fn (BookingRule $record) => $record->rule_category_color),

                Tables\Columns\TextColumn::make('rule_type')
                    ->label(__('booking::config.type'))
                    ->formatStateUsing(fn (BookingRule $record) => $record->rule_type_label),

                Tables\Columns\TextColumn::make('scope_level')
                    ->label(__('booking::config.scope'))
                    ->formatStateUsing(fn (BookingRule $record) => $record->scope_description)
                    ->icon(fn (BookingRule $record) => match($record->scope_level) {
                        'tenant' => 'heroicon-o-building-office-2',
                        'branch' => 'heroicon-o-building-storefront',
                        'service' => 'heroicon-o-clipboard-document-list',
                        'practitioner' => 'heroicon-o-user',
                        default => 'heroicon-o-cog',
                    }),

                Tables\Columns\TextColumn::make('priority')
                    ->label(__('booking::config.priority'))
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state) => match(true) {
                        $state >= 80 => 'danger',
                        $state >= 50 => 'warning',
                        $state >= 20 => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('booking::config.active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('booking::config.updated'))
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('rule_category')
                    ->label(__('booking::config.category'))
                    ->options(fn () => collect(BookingRule::RULE_CATEGORIES)->mapWithKeys(
                        fn ($cat, $key) => [$key => $cat['label']]
                    ))
                    ->query(function ($query, array $data) {
                        if (!$data['value']) {
                            return $query;
                        }
                        $category = BookingRule::RULE_CATEGORIES[$data['value']] ?? null;
                        if ($category) {
                            return $query->whereIn('rule_type', $category['types']);
                        }
                        return $query;
                    }),
                Tables\Filters\SelectFilter::make('scope_level')
                    ->label(__('booking::config.scope'))
                    ->options(BookingRule::SCOPE_LEVELS),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('booking::config.active')),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->icon('heroicon-o-pencil')
                    ->url(fn (BookingRule $record) => route('filament.tenant.resources.booking-rules.edit', $record)),
                Tables\Actions\Action::make('toggle')
                    ->icon(fn (BookingRule $record) => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->label(fn (BookingRule $record) => $record->is_active ? __('booking::config.deactivate') : __('booking::config.activate'))
                    ->color(fn (BookingRule $record) => $record->is_active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (BookingRule $record) => $record->update(['is_active' => !$record->is_active])),
            ])
            ->headerActions([
                Tables\Actions\Action::make('create')
                    ->label(__('booking::config.add_rule'))
                    ->icon('heroicon-o-plus')
                    ->url(route('filament.tenant.resources.booking-rules.create')),
            ])
            ->emptyStateHeading(__('booking::config.no_rules'))
            ->emptyStateDescription(__('booking::config.no_rules_desc'))
            ->emptyStateIcon('heroicon-o-document-text')
            ->defaultSort('priority', 'desc')
            ->paginated([10, 25, 50]);
    }

    public function generatePreview(): void
    {
        if (!$this->previewServiceId || !$this->previewDate) {
            Notification::make()
                ->title(__('booking::config.select_service_date'))
                ->warning()
                ->send();
            return;
        }

        try {
            $service = Service::find($this->previewServiceId);
            if (!$service) {
                $this->previewSlots = [];
                return;
            }

            // Get current tenant's first branch for preview
            $branch = Branch::first();
            if (!$branch) {
                $this->previewSlots = [];
                return;
            }

            $slotService = app(SlotGenerationService::class);
            $slots = $slotService->generateAvailableSlots(
                $this->previewServiceId,
                $branch->id,
                Carbon::parse($this->previewDate)
            );

            $this->previewSlots = $slots->map(function ($slot) {
                return [
                    'time' => $slot['start_time'] ?? 'N/A',
                    'available' => !empty($slot['available_practitioners']),
                    'practitioners' => collect($slot['available_practitioners'] ?? [])->pluck('name')->implode(', '),
                    'room' => $slot['room']['name'] ?? null,
                    'blocked_reason' => $slot['blocked_reason'] ?? null,
                ];
            })->take(20)->toArray();

            Notification::make()
                ->title(__('booking::config.preview_generated'))
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title(__('booking::config.preview_error'))
                ->body($e->getMessage())
                ->danger()
                ->send();
            $this->previewSlots = [];
        }
    }

    /**
     * Get current configuration summary derived from active rules
     */
    public function getCurrentConfig(): array
    {
        $config = [
            'slot_duration' => 30,
            'buffer_minutes' => 5,
            'working_hours' => ['start' => '09:00', 'end' => '21:00'],
            'min_advance_hours' => 2,
            'max_advance_days' => 60,
            'allow_same_day' => true,
            'online_booking' => true,
            'auto_confirm' => false,
            'practitioner_selection' => true,
            'deposit_required' => false,
            'deposit_percentage' => null,
        ];

        // Load tenant-level rules to determine current defaults
        $tenantRules = BookingRule::where('scope_level', 'tenant')
            ->where('is_active', true)
            ->get();

        foreach ($tenantRules as $rule) {
            $actions = $rule->actions ?? [];

            switch ($rule->rule_type) {
                case BookingRule::TYPE_SLOT_DURATION:
                    $config['slot_duration'] = $actions['duration_minutes'] ?? $config['slot_duration'];
                    break;
                case BookingRule::TYPE_SLOT_BUFFER:
                    $config['buffer_minutes'] = $actions['buffer_minutes'] ?? $config['buffer_minutes'];
                    break;
                case BookingRule::TYPE_WORKING_HOURS:
                    $config['working_hours'] = [
                        'start' => $actions['start_time'] ?? $config['working_hours']['start'],
                        'end' => $actions['end_time'] ?? $config['working_hours']['end'],
                    ];
                    break;
                case BookingRule::TYPE_MIN_ADVANCE:
                    $config['min_advance_hours'] = $actions['min_hours'] ?? $config['min_advance_hours'];
                    break;
                case BookingRule::TYPE_MAX_ADVANCE:
                    $config['max_advance_days'] = $actions['max_days'] ?? $config['max_advance_days'];
                    break;
                case BookingRule::TYPE_SAME_DAY:
                    $config['allow_same_day'] = $actions['allow_same_day'] ?? $config['allow_same_day'];
                    break;
                case BookingRule::TYPE_ONLINE_ENABLED:
                    $config['online_booking'] = $actions['enabled'] ?? $config['online_booking'];
                    break;
                case BookingRule::TYPE_AUTO_CONFIRM:
                    $config['auto_confirm'] = $actions['auto_confirm'] ?? $config['auto_confirm'];
                    break;
                case BookingRule::TYPE_ONLINE_PRACTITIONER:
                    $config['practitioner_selection'] = $actions['allow_selection'] ?? $config['practitioner_selection'];
                    break;
                case BookingRule::TYPE_REQUIRE_DEPOSIT:
                    $config['deposit_required'] = $actions['required'] ?? $config['deposit_required'];
                    $config['deposit_percentage'] = $actions['percentage'] ?? $config['deposit_percentage'];
                    break;
            }
        }

        return $config;
    }

    /**
     * Get rules grouped by category for display
     */
    public function getRulesByCategory(): array
    {
        $rules = BookingRule::where('is_active', true)
            ->orderByDesc('priority')
            ->get()
            ->groupBy(fn ($rule) => $rule->rule_category);

        $grouped = [];
        foreach (BookingRule::RULE_CATEGORIES as $key => $category) {
            $categoryRules = $rules->get($key, collect());
            if ($categoryRules->isNotEmpty()) {
                $grouped[$key] = [
                    'label' => $category['label'],
                    'color' => $category['color'] ?? 'gray',
                    'rules' => $categoryRules,
                ];
            }
        }

        return $grouped;
    }

    public function getUpcomingBlackouts(): \Illuminate\Support\Collection
    {
        return BookingBlackoutDate::active()
            ->upcoming()
            ->orderByDate()
            ->limit(5)
            ->get();
    }

    public function getActiveRulesCount(): int
    {
        return BookingRule::active()->count();
    }

    public function getRulesCountByCategory(): array
    {
        return BookingRule::active()
            ->get()
            ->groupBy(fn ($rule) => $rule->rule_category)
            ->map(fn ($rules) => $rules->count())
            ->toArray();
    }

    public function getBlackoutDatesCount(): int
    {
        return BookingBlackoutDate::active()->upcoming()->count();
    }

    public function getServiceOptions(): array
    {
        return Service::where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function getViewData(): array
    {
        return [
            'currentConfig' => $this->getCurrentConfig(),
            'rulesByCategory' => $this->getRulesByCategory(),
            'rulesCountByCategory' => $this->getRulesCountByCategory(),
            'upcomingBlackouts' => $this->getUpcomingBlackouts(),
            'activeRulesCount' => $this->getActiveRulesCount(),
            'blackoutDatesCount' => $this->getBlackoutDatesCount(),
            'serviceOptions' => $this->getServiceOptions(),
        ];
    }
}
