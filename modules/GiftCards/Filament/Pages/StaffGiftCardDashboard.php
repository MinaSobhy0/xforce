<?php

namespace Modules\GiftCards\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\GiftCards\Models\GiftCard;
use Modules\GiftCards\Models\GiftCardTemplate;
use Modules\GiftCards\Services\GiftCardService;
use Modules\Patients\Models\Patient;
use Livewire\Attributes\Computed;

class StaffGiftCardDashboard extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 13;

    protected static string $view = 'giftcards::filament.pages.staff-gift-card-dashboard';

    public ?array $sellData = [];
    public ?string $selectedCardId = null;
    public bool $showSellModal = false;

    public static function getNavigationLabel(): string
    {
        return __('giftcards::giftcards.staff_dashboard.title');
    }

    public function getTitle(): string
    {
        return __('giftcards::giftcards.staff_dashboard.title');
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Show in navigation for users who have assigned cards
        return GiftCard::where('assigned_to_staff_id', Auth::id())
            ->where('status', GiftCard::STATUS_DRAFT)
            ->exists();
    }

    public function mount(): void
    {
        $this->sellForm->fill();
    }

    #[Computed]
    public function myStatistics(): array
    {
        $staffId = Auth::id();

        return app(GiftCardService::class)->getStaffStatistics($staffId);
    }

    #[Computed]
    public function availableCards()
    {
        return GiftCard::with('template')
            ->where('assigned_to_staff_id', Auth::id())
            ->where('status', GiftCard::STATUS_DRAFT)
            ->orderBy('initial_value_minor')
            ->get();
    }

    #[Computed]
    public function cardsByDenomination()
    {
        return GiftCard::where('assigned_to_staff_id', Auth::id())
            ->where('status', GiftCard::STATUS_DRAFT)
            ->selectRaw('initial_value_minor, COUNT(*) as count')
            ->groupBy('initial_value_minor')
            ->orderBy('initial_value_minor')
            ->get()
            ->map(function ($item) {
                return [
                    'value' => format_money($item->initial_value_minor),
                    'count' => $item->count,
                ];
            });
    }

    public function openSellModal(string $cardId): void
    {
        $this->selectedCardId = $cardId;
        $this->sellForm->fill([
            'patient_type' => 'existing',
        ]);
        $this->dispatch('open-modal', id: 'sell-card-modal');
    }

    public function sellForm(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Radio::make('patient_type')
                    ->label(__('giftcards::giftcards.staff_dashboard.patient_type'))
                    ->options([
                        'existing' => __('giftcards::giftcards.staff_dashboard.existing_patient'),
                        'new' => __('giftcards::giftcards.staff_dashboard.new_patient'),
                    ])
                    ->default('existing')
                    ->reactive()
                    ->required(),

                // Existing patient select
                Forms\Components\Select::make('purchaser_patient_id')
                    ->label(__('giftcards::giftcards.fields.purchaser'))
                    ->options(fn () => Patient::orderBy('first_name')->get()->pluck('full_name', 'id'))
                    ->searchable()
                    ->required()
                    ->visible(fn (Forms\Get $get) => $get('patient_type') === 'existing'),

                // New patient fields
                Forms\Components\Section::make(__('giftcards::giftcards.staff_dashboard.new_patient'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('new_patient_first_name')
                                    ->label(__('patients::patients.fields.first_name'))
                                    ->required(),

                                Forms\Components\TextInput::make('new_patient_last_name')
                                    ->label(__('patients::patients.fields.last_name')),
                            ]),

                        Forms\Components\TextInput::make('new_patient_phone')
                            ->label(__('patients::patients.fields.phone'))
                            ->tel()
                            ->required(),

                        Forms\Components\TextInput::make('new_patient_email')
                            ->label(__('patients::patients.fields.email'))
                            ->email(),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('patient_type') === 'new'),

                Forms\Components\Select::make('recipient_patient_id')
                    ->label(__('giftcards::giftcards.fields.recipient'))
                    ->helperText(__('giftcards::giftcards.staff_dashboard.recipient_hint'))
                    ->options(fn () => Patient::orderBy('first_name')->get()->pluck('full_name', 'id'))
                    ->searchable()
                    ->nullable(),

                Forms\Components\Textarea::make('notes')
                    ->label(__('giftcards::giftcards.fields.notes'))
                    ->rows(2),
            ])
            ->statePath('sellData');
    }

    public function sellCard(): void
    {
        $data = $this->sellForm->getState();

        $card = GiftCard::find($this->selectedCardId);

        if (!$card) {
            Notification::make()
                ->title(__('giftcards::giftcards.messages.batch_failed'))
                ->danger()
                ->send();
            return;
        }

        // Verify card is assigned to current user
        if ($card->assigned_to_staff_id !== Auth::id()) {
            Notification::make()
                ->title(__('giftcards::giftcards.messages.batch_failed'))
                ->danger()
                ->send();
            return;
        }

        DB::transaction(function () use ($card, $data) {
            $purchaserId = null;

            // Create new patient if needed
            if ($data['patient_type'] === 'new') {
                $patient = Patient::create([
                    'tenant_id' => tenant_id(),
                    'first_name' => $data['new_patient_first_name'],
                    'last_name' => $data['new_patient_last_name'] ?? '',
                    'phone' => $data['new_patient_phone'],
                    'email' => $data['new_patient_email'] ?? null,
                ]);
                $purchaserId = $patient->id;
            } else {
                $purchaserId = $data['purchaser_patient_id'];
            }

            // Update card details
            $card->update([
                'purchaser_patient_id' => $purchaserId,
                'recipient_patient_id' => $data['recipient_patient_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'sold_by_staff_id' => Auth::id(),
            ]);

            // Activate the card
            $card->activate();
        });

        Notification::make()
            ->title(__('giftcards::giftcards.messages.activated'))
            ->success()
            ->send();

        // Reset and close modal
        $this->selectedCardId = null;
        $this->sellForm->fill();
        $this->dispatch('close-modal', id: 'sell-card-modal');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getForms(): array
    {
        return [
            'sellForm',
        ];
    }
}
