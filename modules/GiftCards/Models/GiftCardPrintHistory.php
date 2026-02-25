<?php

namespace Modules\GiftCards\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class GiftCardPrintHistory extends BaseModel
{
    use HasTenancy;

    protected $table = 'gc_print_history';

    protected $fillable = [
        'tenant_id',
        'gift_card_id',
        'print_format',
        'printer_name',
        'ip_address',
        'user_agent',
        'printed_by',
    ];

    // Print formats
    public const FORMAT_PDF = 'pdf';
    public const FORMAT_PHYSICAL = 'physical';
    public const FORMAT_EMAIL = 'email';

    public const FORMATS = [
        self::FORMAT_PDF => 'PDF',
        self::FORMAT_PHYSICAL => 'Physical Print',
        self::FORMAT_EMAIL => 'Email',
    ];

    // Relationships
    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }

    public function printedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }

    // Accessors
    public function getFormatLabelAttribute(): string
    {
        return self::FORMATS[$this->print_format] ?? $this->print_format;
    }
}
