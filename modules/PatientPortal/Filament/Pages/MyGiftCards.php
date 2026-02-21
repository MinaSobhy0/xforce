<?php

namespace Modules\PatientPortal\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Modules\GiftCards\Models\GiftCard;

class MyGiftCards extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static string $view = 'patientportal::filament.pages.my-gift-cards';

    protected static ?int $navigationSort = 7;

    public ?string $checkCode = null;
    public ?array $checkedCard = null;

    public static function getNavigationLabel(): string
    {
        return __('patientportal::portal.gift_cards');
    }

    public function getTitle(): string
    {
        return __('patientportal::portal.gift_cards');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return config('patientportal.features.gift_cards', true);
    }

    public function checkBalanceForm(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('checkCode')
                    ->label(__('patientportal::portal.gift_card_code'))
                    ->placeholder('GC-XXXXXX')
                    ->required(),
            ])
            ->statePath('checkCode');
    }

    public function checkBalance(): void
    {
        if (empty($this->checkCode)) {
            return;
        }

        $card = GiftCard::where('code', $this->checkCode)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$card) {
            Notification::make()
                ->title(__('patientportal::portal.card_not_found'))
                ->danger()
                ->send();
            $this->checkedCard = null;
            return;
        }

        $this->checkedCard = [
            'code' => $card->code,
            'status' => $card->status,
            'initial_balance' => $card->initial_balance_minor,
            'current_balance' => $card->current_balance_minor,
            'expires_at' => $card->expires_at?->format('M d, Y'),
        ];

        Notification::make()
            ->title(__('patientportal::portal.balance_found'))
            ->body(__('patientportal::portal.current_balance') . ': ' . number_format($card->current_balance_minor / 100, 2) . ' EGP')
            ->success()
            ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('code')
                    ->label(__('patientportal::portal.code'))
                    ->searchable(),

                TextColumn::make('initial_balance_minor')
                    ->label(__('patientportal::portal.initial_value'))
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . ' EGP'),

                TextColumn::make('current_balance_minor')
                    ->label(__('patientportal::portal.current_balance'))
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . ' EGP')
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),

                BadgeColumn::make('status')
                    ->label(__('patientportal::portal.status'))
                    ->colors([
                        'success' => 'active',
                        'warning' => 'expired',
                        'secondary' => 'redeemed',
                    ])
                    ->formatStateUsing(fn ($state) => __('gift_cards::gift_cards.statuses.' . $state)),

                TextColumn::make('expires_at')
                    ->label(__('patientportal::portal.expires'))
                    ->date('M d, Y')
                    ->placeholder(__('patientportal::portal.no_expiry')),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('patientportal::portal.no_gift_cards'))
            ->emptyStateDescription(__('patientportal::portal.no_gift_cards_desc'))
            ->emptyStateIcon('heroicon-o-gift');
    }

    protected function getTableQuery(): Builder
    {
        $patient = Auth::guard('patient')->user();

        return GiftCard::query()
            ->where(function ($q) use ($patient) {
                $q->where('purchaser_id', $patient->id)
                    ->orWhere('recipient_id', $patient->id);
            });
    }
}
