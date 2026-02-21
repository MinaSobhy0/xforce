<?php

namespace Modules\PatientPortal\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Modules\Loyalty\Models\LoyaltyTransaction;

class MyLoyalty extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static string $view = 'patientportal::filament.pages.my-loyalty';

    protected static ?int $navigationSort = 6;

    public static function getNavigationLabel(): string
    {
        return __('patientportal::portal.loyalty');
    }

    public function getTitle(): string
    {
        return __('patientportal::portal.loyalty_points');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return config('patientportal.features.loyalty', true);
    }

    protected function getViewData(): array
    {
        $patient = Auth::guard('patient')->user();

        $totalPoints = LoyaltyTransaction::where('patient_id', $patient->id)
            ->sum('points');

        $earnedPoints = LoyaltyTransaction::where('patient_id', $patient->id)
            ->where('points', '>', 0)
            ->sum('points');

        $redeemedPoints = LoyaltyTransaction::where('patient_id', $patient->id)
            ->where('points', '<', 0)
            ->sum('points');

        $tier = $this->calculateTier($totalPoints);

        return [
            'totalPoints' => $totalPoints,
            'earnedPoints' => $earnedPoints,
            'redeemedPoints' => abs($redeemedPoints),
            'tier' => $tier,
            'nextTier' => $this->getNextTier($tier),
            'pointsToNextTier' => $this->getPointsToNextTier($totalPoints),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('patientportal::portal.date'))
                    ->dateTime('M d, Y h:i A')
                    ->sortable(),

                BadgeColumn::make('type')
                    ->label(__('patientportal::portal.type'))
                    ->colors([
                        'success' => ['earn', 'bonus', 'referral'],
                        'danger' => 'redeem',
                        'warning' => 'expire',
                        'info' => 'adjust',
                    ])
                    ->formatStateUsing(fn ($state) => __('loyalty::loyalty.transaction_types.' . $state)),

                TextColumn::make('description')
                    ->label(__('patientportal::portal.description'))
                    ->limit(50),

                TextColumn::make('points')
                    ->label(__('patientportal::portal.points'))
                    ->formatStateUsing(fn ($state) => ($state > 0 ? '+' : '') . number_format($state))
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),

                TextColumn::make('balance_after')
                    ->label(__('patientportal::portal.balance'))
                    ->formatStateUsing(fn ($state) => number_format($state)),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('patientportal::portal.no_transactions'))
            ->emptyStateDescription(__('patientportal::portal.start_earning_points'))
            ->emptyStateIcon('heroicon-o-star');
    }

    protected function getTableQuery(): Builder
    {
        $patient = Auth::guard('patient')->user();

        return LoyaltyTransaction::query()
            ->where('patient_id', $patient->id);
    }

    protected function calculateTier(int $points): string
    {
        $tiers = config('loyalty.tiers', [
            'bronze' => 0,
            'silver' => 1000,
            'gold' => 5000,
            'platinum' => 10000,
        ]);

        $currentTier = 'bronze';
        foreach ($tiers as $tier => $minPoints) {
            if ($points >= $minPoints) {
                $currentTier = $tier;
            }
        }

        return $currentTier;
    }

    protected function getNextTier(string $currentTier): ?string
    {
        $tiers = array_keys(config('loyalty.tiers', []));
        $currentIndex = array_search($currentTier, $tiers);

        if ($currentIndex === false || $currentIndex >= count($tiers) - 1) {
            return null;
        }

        return $tiers[$currentIndex + 1];
    }

    protected function getPointsToNextTier(int $currentPoints): int
    {
        $tiers = config('loyalty.tiers', []);

        foreach ($tiers as $tier => $minPoints) {
            if ($currentPoints < $minPoints) {
                return $minPoints - $currentPoints;
            }
        }

        return 0;
    }
}
