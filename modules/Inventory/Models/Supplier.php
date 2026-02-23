<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;
use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasSequence;

class Supplier extends BaseModel
{
    use HasTranslations, HasSequence;

    protected $table = 'suppliers';

    public array $translatable = ['name'];

    protected string $sequenceCode = 'SUP';

    protected string $sequenceColumn = 'code';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'contact_person',
        'email',
        'phone',
        'mobile',
        'address',
        'city',
        'country',
        'tax_number',
        'payment_terms_days',
        'currency_code',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'id' => 'string',
        'name' => 'array',
        'payment_terms_days' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'payment_terms_days' => 30,
        'currency_code' => 'EGP',
        'is_active' => true,
    ];

    /**
     * Get purchase orders for this supplier.
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * Scope to active suppliers only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get full contact info.
     */
    public function getFullContactAttribute(): string
    {
        $parts = [];

        if ($this->contact_person) {
            $parts[] = $this->contact_person;
        }

        if ($this->phone) {
            $parts[] = $this->phone;
        }

        if ($this->email) {
            $parts[] = $this->email;
        }

        return implode(' | ', $parts);
    }

    /**
     * Get full address.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get total orders value.
     */
    public function getTotalOrdersValueAttribute(): int
    {
        return $this->purchaseOrders()->sum('total_amount_minor');
    }

    /**
     * Get pending orders count.
     */
    public function getPendingOrdersCountAttribute(): int
    {
        return $this->purchaseOrders()
            ->whereIn('status', [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_SENT])
            ->count();
    }
}
