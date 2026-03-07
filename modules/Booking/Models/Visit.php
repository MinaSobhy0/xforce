<?php

namespace Modules\Booking\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasSequence;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Collection;
use Modules\Patients\Models\Patient;
use Modules\Core\Models\Branch;
use Modules\Billing\Models\Invoice;
use Modules\Auth\Models\User;
use Modules\Packages\Models\Package;
use Carbon\Carbon;

class Visit extends BaseModel
{
    use HasSequence;

    protected string $sequenceCode = 'visit';
    protected string $sequenceColumn = 'code';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'patient_id',
        'code',
        'check_in_at',
        'checked_in_by',
        'check_out_at',
        'checked_out_by',
        'status',
        'invoice_id',
        'total_minor',
        'source',
        'chief_complaint',
        'notes',
    ];

    protected $casts = [
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'total_minor' => 'integer',
    ];

    // Source constants
    public const SOURCE_WALK_IN = 'walk_in';
    public const SOURCE_APPOINTMENT = 'appointment';
    public const SOURCE_ONLINE = 'online';

    public const SOURCES = [
        self::SOURCE_WALK_IN => 'Walk-in',
        self::SOURCE_APPOINTMENT => 'Appointment',
        self::SOURCE_ONLINE => 'Online Booking',
    ];

    // Status constants
    public const STATUS_OPEN = 'open';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_INVOICED = 'invoiced';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_OPEN => 'Open',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_INVOICED => 'Invoiced',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const STATUS_COLORS = [
        self::STATUS_OPEN => 'warning',
        self::STATUS_COMPLETED => 'success',
        self::STATUS_INVOICED => 'info',
        self::STATUS_CANCELLED => 'danger',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Visit $visit) {
            if (empty($visit->status)) {
                $visit->status = self::STATUS_OPEN;
            }
            if (empty($visit->check_in_at)) {
                $visit->check_in_at = now();
            }
            if (empty($visit->checked_in_by) && auth()->check()) {
                $visit->checked_in_by = auth()->id();
            }
        });
    }

    // Relationships
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the effective invoice for this visit.
     * Returns the visit's invoice, or for package-only visits, the package subscription's invoice.
     */
    public function getEffectiveInvoiceAttribute(): ?Invoice
    {
        // First check direct invoice
        if ($this->invoice) {
            return $this->invoice;
        }

        // For visits with only package appointments, get the first package subscription's invoice
        $packageAppointments = $this->appointments->filter(fn ($apt) => $apt->isPackageSession());
        if ($packageAppointments->isNotEmpty()) {
            foreach ($packageAppointments as $apt) {
                if ($apt->packageSubscription?->invoice) {
                    return $apt->packageSubscription->invoice;
                }
            }
        }

        return null;
    }

    public function appointments(): BelongsToMany
    {
        return $this->belongsToMany(Appointment::class, 'visit_appointments')
            ->withTimestamps();
    }

    public function products(): HasMany
    {
        return $this->hasMany(SessionProduct::class);
    }

    public function soldProducts(): HasMany
    {
        return $this->hasMany(SessionProduct::class)->where('usage_type', 'sold');
    }

    /**
     * Packages pending purchase during this visit.
     */
    public function pendingPackages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'visit_pending_packages')
            ->withPivot(['package_price_minor', 'payment_option'])
            ->withTimestamps();
    }

    /**
     * Add a package to pending purchases for this visit.
     */
    public function addPendingPackage(Package $package, string $paymentOption = 'full'): void
    {
        if (!$this->pendingPackages()->where('package_id', $package->id)->exists()) {
            $this->pendingPackages()->attach($package->id, [
                'tenant_id' => $this->tenant_id,
                'package_price_minor' => $package->effective_price_minor,
                'payment_option' => $paymentOption,
            ]);
        }
    }

    /**
     * Remove a package from pending purchases.
     */
    public function removePendingPackage(Package $package): void
    {
        $this->pendingPackages()->detach($package->id);
    }

    /**
     * Update payment option for a pending package.
     */
    public function updatePendingPackagePaymentOption(int $packageId, string $paymentOption): void
    {
        $this->pendingPackages()->updateExistingPivot($packageId, [
            'payment_option' => $paymentOption,
        ]);
    }

    /**
     * Check if a package is pending purchase.
     */
    public function hasPendingPackage(int $packageId): bool
    {
        return $this->pendingPackages()->where('package_id', $packageId)->exists();
    }

    /**
     * Get total for pending packages based on payment options.
     */
    public function getPendingPackagesTotalMinor(): int
    {
        $total = 0;
        foreach ($this->pendingPackages as $package) {
            $total += $package->pivot->package_price_minor;
        }
        return $total;
    }

    /**
     * Get total deposit amount for pending packages.
     */
    public function getPendingPackagesDepositMinor(): int
    {
        $total = 0;
        foreach ($this->pendingPackages as $package) {
            $priceMinor = $package->pivot->package_price_minor;
            $paymentOption = $package->pivot->payment_option;

            if ($paymentOption === 'full') {
                $total += $priceMinor;
            } else {
                // Use min_deposit_percent from package
                $depositPercent = $package->min_deposit_percent ?? 100;
                $total += (int) ceil($priceMinor * $depositPercent / 100);
            }
        }
        return $total;
    }

    /**
     * Get all sold products including those from appointments (for legacy data)
     */
    public function getAllSoldProducts(): \Illuminate\Support\Collection
    {
        // Products linked directly to visit
        $visitProducts = $this->products()->where('usage_type', 'sold')->with('product')->get();

        // Products from appointments in this visit (legacy data without visit_id)
        $appointmentIds = $this->appointments()->pluck('appointments.id');
        $appointmentProducts = SessionProduct::with('product')
            ->whereIn('appointment_id', $appointmentIds)
            ->where('usage_type', 'sold')
            ->whereNull('visit_id')
            ->get();

        return $visitProducts->merge($appointmentProducts)->unique('id');
    }

    // Accessors
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    public function getDurationAttribute(): ?int
    {
        if (!$this->check_out_at) {
            return $this->check_in_at->diffInMinutes(now());
        }
        return $this->check_in_at->diffInMinutes($this->check_out_at);
    }

    public function getDurationDisplayAttribute(): string
    {
        $minutes = $this->duration;
        if ($minutes < 60) {
            return "{$minutes}m";
        }
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return "{$hours}h {$mins}m";
    }

    public function getFormattedTotalAttribute(): string
    {
        return number_format($this->total_minor / 100, 2);
    }

    public function getSourceLabelAttribute(): ?string
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }

    public function getCheckedInByNameAttribute(): ?string
    {
        return $this->checkedInBy?->name;
    }

    public function getCheckedOutByNameAttribute(): ?string
    {
        return $this->checkedOutBy?->name;
    }

    /**
     * Get all practitioners involved in this visit
     */
    public function getPractitionersAttribute(): Collection
    {
        return $this->appointments
            ->pluck('practitioner')
            ->filter()
            ->unique('id');
    }

    /**
     * Get all services in this visit
     */
    public function getServicesAttribute(): Collection
    {
        return $this->appointments
            ->pluck('service')
            ->filter()
            ->unique('id');
    }

    /**
     * Get completed appointments
     */
    public function getCompletedAppointmentsAttribute(): Collection
    {
        return $this->appointments->filter(fn ($apt) => $apt->status === Appointment::STATUS_COMPLETED);
    }

    /**
     * Get open (in-progress) appointments
     */
    public function getOpenAppointmentsAttribute(): Collection
    {
        return $this->appointments->filter(fn ($apt) => in_array($apt->status, [
            Appointment::STATUS_SCHEDULED,
            Appointment::STATUS_CONFIRMED,
            Appointment::STATUS_CHECKED_IN,
            Appointment::STATUS_IN_PROGRESS,
        ]));
    }

    /**
     * Check if visit has any open appointments
     */
    public function hasOpenAppointments(): bool
    {
        return $this->open_appointments->isNotEmpty();
    }

    // State checks
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isInvoiced(): bool
    {
        return $this->status === self::STATUS_INVOICED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canCheckout(): bool
    {
        return $this->isOpen() && $this->appointments->isNotEmpty();
    }

    // Actions
    public function addAppointment(Appointment $appointment): void
    {
        if (!$this->appointments()->where('appointment_id', $appointment->id)->exists()) {
            $this->appointments()->attach($appointment->id);
        }
    }

    public function markCompleted(): bool
    {
        $this->status = self::STATUS_COMPLETED;
        return $this->save();
    }

    public function markInvoiced(Invoice $invoice): bool
    {
        $this->status = self::STATUS_INVOICED;
        $this->invoice_id = $invoice->id;
        $this->check_out_at = now();
        if (auth()->check()) {
            $this->checked_out_by = auth()->id();
        }
        return $this->save();
    }

    public function cancel(): bool
    {
        $this->status = self::STATUS_CANCELLED;
        return $this->save();
    }

    /**
     * Calculate total from appointments, products, and pending packages
     */
    public function calculateTotal(): int
    {
        $appointmentsTotal = $this->appointments()
            ->where('status', Appointment::STATUS_COMPLETED)
            ->sum('net_price');

        $productsTotal = $this->products()
            ->where('usage_type', 'sold')
            ->selectRaw('SUM((unit_price_minor - COALESCE(discount_minor, 0)) * quantity) as total')
            ->value('total') ?? 0;

        // Include full package prices (invoice shows full amount, payment may be partial)
        $packagesTotal = $this->getPendingPackagesTotalMinor();

        $this->total_minor = $appointmentsTotal + $productsTotal + $packagesTotal;
        $this->save();

        return $this->total_minor;
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeInvoiced($query)
    {
        return $query->where('status', self::STATUS_INVOICED);
    }

    public function scopeForPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('check_in_at', today());
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('check_in_at', '>=', now()->subDays($days));
    }

    /**
     * Get the checkout URL for this visit
     */
    public function getCheckoutUrlAttribute(): string
    {
        return \Modules\Booking\Filament\Pages\Checkout::getUrl(['visit_id' => $this->id]);
    }
}
