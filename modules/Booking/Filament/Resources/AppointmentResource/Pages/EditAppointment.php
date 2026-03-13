<?php

namespace Modules\Booking\Filament\Resources\AppointmentResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Modules\Booking\Filament\Resources\AppointmentResource;

class EditAppointment extends BaseEditRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function afterSave(): void
    {
        // If the appointment was unscheduled and now has a practitioner, mark it as scheduled
        if ($this->record->is_unscheduled && $this->record->hasPractitionerAssigned()) {
            $this->record->markAsScheduled();
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('booking::appointments.sections.schedule'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('branch_id')
                                    ->label(__('booking::appointments.fields.branch'))
                                    ->relationship('branch', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live(),

                                Forms\Components\Select::make('practitioner_id')
                                    ->label(__('booking::appointments.fields.practitioner'))
                                    ->relationship('practitioner', 'first_name')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\DatePicker::make('date')
                                    ->label(__('booking::appointments.fields.date'))
                                    ->native(false)
                                    ->required(),

                                Forms\Components\TimePicker::make('start_time')
                                    ->label(__('booking::appointments.fields.start_time'))
                                    ->seconds(false)
                                    ->required(),

                                Forms\Components\TimePicker::make('end_time')
                                    ->label(__('booking::appointments.fields.end_time'))
                                    ->seconds(false)
                                    ->required(),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('room_id')
                                    ->label(__('booking::appointments.fields.room'))
                                    ->relationship('room', 'name')
                                    ->searchable()
                                    ->preload(),

                                Forms\Components\TextInput::make('duration_minutes')
                                    ->label(__('booking::appointments.fields.duration'))
                                    ->numeric()
                                    ->suffix(__('booking::appointments.minutes'))
                                    ->required(),
                            ]),
                    ]),

                Forms\Components\Section::make(__('booking::appointments.sections.pricing'))
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('price_minor')
                                    ->label(__('booking::appointments.fields.price'))
                                    ->numeric()
                                    ->prefix(current_currency())
                                    ->required(),

                                Forms\Components\TextInput::make('discount_minor')
                                    ->label(__('booking::appointments.fields.discount'))
                                    ->numeric()
                                    ->prefix(current_currency())
                                    ->default(0),

                                Forms\Components\Select::make('source')
                                    ->label(__('booking::appointments.fields.source'))
                                    ->options(\Modules\Booking\Models\Appointment::SOURCES)
                                    ->required(),
                            ]),
                    ]),

                Forms\Components\Section::make(__('booking::appointments.sections.notes'))
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label(__('booking::appointments.fields.notes'))
                            ->rows(3)
                            ->maxLength(1000),

                        Forms\Components\Textarea::make('internal_notes')
                            ->label(__('booking::appointments.fields.internal_notes'))
                            ->rows(2)
                            ->maxLength(1000),
                    ])
                    ->collapsible(),
            ]);
    }
}
