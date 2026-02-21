<?php

namespace Modules\PatientPortal\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Modules\Patients\Models\Patient;

class MyProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static string $view = 'patientportal::filament.pages.my-profile';

    protected static ?int $navigationSort = 5;

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('patientportal::portal.my_profile');
    }

    public function getTitle(): string
    {
        return __('patientportal::portal.my_profile');
    }

    public function mount(): void
    {
        $patient = Auth::guard('patient')->user();

        $this->form->fill([
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'email' => $patient->email,
            'phone' => $patient->phone,
            'date_of_birth' => $patient->date_of_birth,
            'gender' => $patient->gender,
            'address' => $patient->address,
            'emergency_contact_name' => $patient->emergency_contact_name,
            'emergency_contact_phone' => $patient->emergency_contact_phone,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('patientportal::portal.personal_info'))
                    ->schema([
                        TextInput::make('first_name')
                            ->label(__('patientportal::portal.first_name'))
                            ->required()
                            ->maxLength(100),

                        TextInput::make('last_name')
                            ->label(__('patientportal::portal.last_name'))
                            ->required()
                            ->maxLength(100),

                        TextInput::make('email')
                            ->label(__('patientportal::portal.email'))
                            ->email()
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label(__('patientportal::portal.phone'))
                            ->tel()
                            ->disabled()
                            ->helperText(__('patientportal::portal.phone_change_note')),

                        DatePicker::make('date_of_birth')
                            ->label(__('patientportal::portal.date_of_birth'))
                            ->maxDate(now()->subYears(1)),

                        Select::make('gender')
                            ->label(__('patientportal::portal.gender'))
                            ->options([
                                'male' => __('patientportal::portal.male'),
                                'female' => __('patientportal::portal.female'),
                            ]),
                    ])
                    ->columns(2),

                Section::make(__('patientportal::portal.contact_info'))
                    ->schema([
                        Textarea::make('address')
                            ->label(__('patientportal::portal.address'))
                            ->rows(2)
                            ->maxLength(500),
                    ]),

                Section::make(__('patientportal::portal.emergency_contact'))
                    ->schema([
                        TextInput::make('emergency_contact_name')
                            ->label(__('patientportal::portal.emergency_name'))
                            ->maxLength(200),

                        TextInput::make('emergency_contact_phone')
                            ->label(__('patientportal::portal.emergency_phone'))
                            ->tel(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $patient = Auth::guard('patient')->user();

        $patient->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'date_of_birth' => $data['date_of_birth'],
            'gender' => $data['gender'],
            'address' => $data['address'],
            'emergency_contact_name' => $data['emergency_contact_name'],
            'emergency_contact_phone' => $data['emergency_contact_phone'],
        ]);

        Notification::make()
            ->title(__('patientportal::portal.profile_updated'))
            ->success()
            ->send();
    }
}
