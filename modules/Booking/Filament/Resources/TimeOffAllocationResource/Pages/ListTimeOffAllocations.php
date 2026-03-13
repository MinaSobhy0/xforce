<?php

namespace Modules\Booking\Filament\Resources\TimeOffAllocationResource\Pages;

use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Auth\Models\User;
use Modules\Booking\Filament\Resources\TimeOffAllocationResource;
use Modules\Booking\Models\TimeOffAllocation;
use Modules\Booking\Models\TimeOffType;

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
                        if ($state) {
                            $type = TimeOffType::find($state);
                            if ($type) {
                                $set('allocated_amount', $type->getEffectiveDefaultAllocation());
                            }
                        }
                    }),

                Forms\Components\TextInput::make('year')
                    ->label(__('booking::time_off.allocations.fields.year'))
                    ->numeric()
                    ->default(now()->year)
                    ->required()
                    ->minValue(2020)
                    ->maxValue(2050),

                Forms\Components\Toggle::make('all_months')
                    ->label(__('booking::time_off.allocations.bulk.all_months'))
                    ->default(true)
                    ->visible(fn (Forms\Get $get) => $get('time_off_type_id') && TimeOffType::find($get('time_off_type_id'))?->isMonthly())
                    ->live()
                    ->helperText(__('booking::time_off.allocations.bulk.all_months_help')),

                Forms\Components\Select::make('months')
                    ->label(__('booking::time_off.allocations.bulk.select_months'))
                    ->options(fn () => collect(range(1, 12))->mapWithKeys(fn ($m) => [
                        $m => \Carbon\Carbon::create()->month($m)->translatedFormat('F')
                    ])->toArray())
                    ->multiple()
                    ->visible(fn (Forms\Get $get) => $get('time_off_type_id') && TimeOffType::find($get('time_off_type_id'))?->isMonthly() && !$get('all_months'))
                    ->required(fn (Forms\Get $get) => $get('time_off_type_id') && TimeOffType::find($get('time_off_type_id'))?->isMonthly() && !$get('all_months')),

                Forms\Components\Select::make('user_ids')
                    ->label(__('booking::time_off.allocations.bulk.select_staff'))
                    ->options(fn () => User::query()->get()->pluck('full_name', 'id'))
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
                            $set('user_ids', User::query()->pluck('id')->toArray());
                        } else {
                            $set('user_ids', []);
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
                $userIds = $data['user_ids'];
                $year = $data['year'];
                $allocatedAmount = $data['allocated_amount'];
                $skipExisting = $data['skip_existing'] ?? true;

                // Determine which months to allocate
                $months = [null]; // Default for yearly types
                if ($type?->isMonthly()) {
                    if ($data['all_months'] ?? false) {
                        $months = range(1, 12); // All 12 months
                    } else {
                        $months = $data['months'] ?? [];
                    }
                }

                $created = 0;
                $skipped = 0;

                foreach ($userIds as $userId) {
                    foreach ($months as $month) {
                        $criteria = [
                            'tenant_id' => current_tenant_id(),
                            'user_id' => $userId,
                            'time_off_type_id' => $data['time_off_type_id'],
                            'year' => $year,
                            'month' => $month,
                        ];

                        $existing = TimeOffAllocation::where($criteria)->first();

                        if ($existing) {
                            if ($skipExisting) {
                                $skipped++;
                                continue;
                            }
                            // Update existing
                            $existing->update(['allocated_days' => $allocatedAmount]);
                            $created++;
                        } else {
                            // Create new
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
