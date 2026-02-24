<?php

namespace Modules\Booking\Filament\Pages;

use App\Traits\ChecksResourcePermissions;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Modules\Booking\Services\TreatmentAnalyticsService;
use Modules\Services\Models\Service;
use Modules\Equipment\Models\Equipment;

class TreatmentAnalytics extends Page implements HasForms
{
    use InteractsWithForms;
    use ChecksResourcePermissions;

    protected static ?string $moduleCode = 'booking';
    protected static ?string $permissionKey = 'appointments';
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Reports';
    protected static ?int $navigationSort = 30;
    protected static ?string $slug = 'treatment-analytics';

    protected static string $view = 'booking::filament.pages.treatment-analytics';

    public static function getNavigationLabel(): string
    {
        return __('booking::analytics.navigation_label');
    }

    // Filters
    public ?string $dateRange = '30';
    public ?string $serviceId = null;
    public ?string $practitionerId = null;

    // Data
    public array $sessionMetrics = [];
    public array $topServices = [];
    public array $practitionerPerformance = [];
    public array $equipmentUtilization = [];
    public array $consumablesUsage = [];
    public array $productsUsage = [];
    public array $sessionTrends = [];
    public array $parameterStats = [];

    protected TreatmentAnalyticsService $analyticsService;

    public function boot(TreatmentAnalyticsService $analyticsService): void
    {
        $this->analyticsService = $analyticsService;
    }

    public function mount(): void
    {
        $this->loadAnalytics();
    }

    public function getTitle(): string
    {
        return __('booking::analytics.title');
    }

    public function getHeading(): string
    {
        return __('booking::analytics.heading');
    }

    public function getSubheading(): ?string
    {
        return __('booking::analytics.subheading');
    }

    protected function getDateRange(): array
    {
        $endDate = now();
        $startDate = match ($this->dateRange) {
            '7' => now()->subDays(7),
            '30' => now()->subDays(30),
            '90' => now()->subDays(90),
            '180' => now()->subDays(180),
            '365' => now()->subYear(),
            default => now()->subDays(30),
        };

        return [$startDate, $endDate];
    }

    public function loadAnalytics(): void
    {
        [$startDate, $endDate] = $this->getDateRange();

        $this->sessionMetrics = $this->analyticsService->getSessionMetrics(
            $startDate,
            $endDate,
            $this->serviceId,
            $this->practitionerId
        );

        $this->topServices = $this->analyticsService->getTopServices($startDate, $endDate, 10);
        $this->practitionerPerformance = $this->analyticsService->getPractitionerPerformance($startDate, $endDate);
        $this->equipmentUtilization = $this->analyticsService->getEquipmentUtilization($startDate, $endDate);
        $this->consumablesUsage = $this->analyticsService->getConsumablesUsage($startDate, $endDate, $this->serviceId);
        $this->productsUsage = $this->analyticsService->getProductsUsage($startDate, $endDate);
        $this->sessionTrends = $this->analyticsService->getSessionTrends($startDate, $endDate);

        // Load parameter stats if service is selected
        if ($this->serviceId) {
            $this->parameterStats = $this->analyticsService->getParameterStatistics(
                $this->serviceId,
                $startDate,
                $endDate
            );
        } else {
            $this->parameterStats = [];
        }
    }

    public function updatedDateRange(): void
    {
        $this->loadAnalytics();
    }

    public function updatedServiceId(): void
    {
        $this->loadAnalytics();
    }

    public function updatedPractitionerId(): void
    {
        $this->loadAnalytics();
    }

    public function getAvailableServices(): array
    {
        return Service::query()
            ->active()
            ->ordered()
            ->get()
            ->mapWithKeys(fn ($s) => [$s->id => $s->translated_name])
            ->toArray();
    }

    public function getDateRangeOptions(): array
    {
        return [
            '7' => __('booking::analytics.date_ranges.7_days'),
            '30' => __('booking::analytics.date_ranges.30_days'),
            '90' => __('booking::analytics.date_ranges.90_days'),
            '180' => __('booking::analytics.date_ranges.180_days'),
            '365' => __('booking::analytics.date_ranges.1_year'),
        ];
    }

    public function getSkinReactionLabel(string $reaction): string
    {
        return __('booking::analytics.skin_reactions.' . $reaction);
    }

    public function formatCurrency(float $amount): string
    {
        return number_format($amount, 2) . ' ' . current_currency();
    }

    public function formatPercentage(float $value): string
    {
        return number_format($value, 1) . '%';
    }
}
