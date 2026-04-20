<?php

namespace Modules\Booking\Filament\Resources\TimeOffAllocationResource\Pages;

use App\Filament\Resources\Pages\BaseListRecords;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Modules\Booking\Filament\Resources\TimeOffAllocationResource;
use Modules\Booking\Models\TimeOffAllocation;
use Modules\Booking\Models\TimeOffType;
use Modules\Staff\Models\StaffProfile;

class ListTimeOffAllocations extends BaseListRecords
{
    protected static string $resource = TimeOffAllocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            $this->getBulkAllocationAction(),
            Actions\CreateAction::make(),
        ];
    }

    protected function getBulkAllocationAction(): Actions\Action
    {
        return Actions\Action::make('bulkAllocate')
            ->label(__('booking::time_off.allocations.bulk.button'))
            ->icon('heroicon-o-user-group')
            ->color('info')
            ->form([
                Forms\Components\Select::make('time_off_type_id')
                    ->label(__('booking::time_off.allocations.fields.type'))
                    ->options(fn () => TimeOffType::active()->ordered()->get()
                        ->mapWithKeys(fn ($type) => [$type->id => $type->translated_name]))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if (! $state) {
                            return;
                        }
                        $type = TimeOffType::find($state);
                        if (! $type) {
                            return;
                        }

                        $set('allocated_amount', $type->getEffectiveDefaultAllocation());
                        [$from, $to] = TimeOffAllocation::deriveDateRange($type, now());
                        $set('date_from', $from->toDateString());
                        $set('date_to', $to->toDateString());
                    }),

                Forms\Components\DatePicker::make('date_from')
                    ->label(__('booking::time_off.allocations.fields.date_from'))
                    ->native(false)
                    ->required()
                    ->default(now()->startOfYear()),

                Forms\Components\DatePicker::make('date_to')
                    ->label(__('booking::time_off.allocations.fields.date_to'))
                    ->native(false)
                    ->required()
                    ->after('date_from')
                    ->default(now()->endOfYear()),

                Forms\Components\Toggle::make('fan_out_monthly')
                    ->label(__('booking::time_off.allocations.bulk.fan_out_monthly'))
                    ->default(true)
                    ->visible(fn (Forms\Get $get) => $get('time_off_type_id') && TimeOffType::find($get('time_off_type_id'))?->isMonthly())
                    ->helperText(__('booking::time_off.allocations.bulk.fan_out_monthly_help')),

                Forms\Components\Select::make('staff_profile_ids')
                    ->label(__('booking::time_off.allocations.bulk.select_staff'))
                    ->options(fn () => StaffProfile::query()
                        ->with('user:id,first_name,last_name')
                        ->get()
                        ->mapWithKeys(fn (StaffProfile $sp) => [$sp->id => $sp->user?->full_name ?? "#{$sp->id}"])
                        ->toArray())
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText(__('booking::time_off.allocations.bulk.select_staff_help')),

                Forms\Components\Toggle::make('select_all_staff')
                    ->label(__('booking::time_off.allocations.bulk.all_staff'))
                    ->live()
                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                        if ($state) {
                            $set('staff_profile_ids', StaffProfile::query()->pluck('id')->toArray());
                        } else {
                            $set('staff_profile_ids', []);
                        }
                    }),

                Forms\Components\TextInput::make('allocated_amount')
                    ->label(fn (Forms\Get $get) => $get('time_off_type_id') && TimeOffType::find($get('time_off_type_id'))?->isHourBased()
                        ? __('booking::time_off.allocations.fields.allocated_hours')
                        : __('booking::time_off.allocations.fields.allocated_days'))
                    ->numeric()
                    ->step(0.5)
                    ->required()
                    ->helperText(__('booking::time_off.allocations.bulk.amount_help')),

                Forms\Components\Toggle::make('skip_existing')
                    ->label(__('booking::time_off.allocations.bulk.skip_existing'))
                    ->default(true)
                    ->helperText(__('booking::time_off.allocations.bulk.skip_existing_help')),
            ])
            ->action(function (array $data): void {
                $type = TimeOffType::find($data['time_off_type_id']);
                $staffProfileIds = $data['staff_profile_ids'] ?? [];
                $allocatedAmount = $data['allocated_amount'];
                $skipExisting = $data['skip_existing'] ?? true;

                $dateFrom = \Carbon\Carbon::parse($data['date_from'])->startOfDay();
                $dateTo = \Carbon\Carbon::parse($data['date_to'])->startOfDay();

                // For monthly types, fan out one allocation per calendar month across the range.
                $ranges = [];
                if ($type?->isMonthly() && ($data['fan_out_monthly'] ?? true)) {
                    $cursor = $dateFrom->copy()->startOfMonth();
                    while ($cursor->lessThanOrEqualTo($dateTo)) {
                        $ranges[] = [$cursor->copy()->startOfMonth(), $cursor->copy()->endOfMonth()];
                        $cursor->addMonthNoOverflow();
                    }
                } else {
                    $ranges[] = [$dateFrom, $dateTo];
                }

                $created = 0;
                $skipped = 0;

                foreach ($staffProfileIds as $staffProfileId) {
                    foreach ($ranges as [$from, $to]) {
                        $criteria = [
                            'tenant_id' => current_tenant_id(),
                            'staff_profile_id' => $staffProfileId,
                            'time_off_type_id' => $data['time_off_type_id'],
                            'date_from' => $from->toDateString(),
                            'date_to' => $to->toDateString(),
                        ];

                        $existing = TimeOffAllocation::where($criteria)->first();

                        if ($existing) {
                            if ($skipExisting) {
                                $skipped++;

                                continue;
                            }
                            $existing->update(['allocated_days' => $allocatedAmount]);
                            $created++;
                        } else {
                            TimeOffAllocation::create(array_merge($criteria, [
                                'allocated_days' => $allocatedAmount,
                                'used_days' => 0,
                                'carried_over_days' => 0,
                            ]));
                            $created++;
                        }
                    }
                }

                Notification::make()
                    ->title(__('booking::time_off.allocations.bulk.success'))
                    ->body(__('booking::time_off.allocations.bulk.success_message', [
                        'created' => $created,
                        'skipped' => $skipped,
                    ]))
                    ->success()
                    ->send();
            })
            ->modalHeading(__('booking::time_off.allocations.bulk.title'))
            ->modalSubmitActionLabel(__('booking::time_off.allocations.bulk.submit'))
            ->modalWidth('lg');
    }
}
