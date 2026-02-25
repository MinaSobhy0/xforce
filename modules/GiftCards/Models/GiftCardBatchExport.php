<?php

namespace Modules\GiftCards\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasSequence;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class GiftCardBatchExport extends BaseModel
{
    use HasTenancy, HasSequence;

    protected $table = 'gc_batch_exports';

    protected string $sequenceCode = 'gc_batch';
    protected string $sequenceColumn = 'batch_code';

    protected $fillable = [
        'tenant_id',
        'batch_code',
        'template_id',
        'quantity',
        'export_format',
        'card_ids',
        'metadata',
        'exported_by',
        'exported_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'card_ids' => 'array',
        'metadata' => 'array',
        'exported_at' => 'datetime',
    ];

    // Export formats
    public const FORMAT_CSV = 'csv';
    public const FORMAT_PDF = 'pdf';
    public const FORMAT_EXCEL = 'excel';

    public const FORMATS = [
        self::FORMAT_CSV => 'CSV',
        self::FORMAT_PDF => 'PDF',
        self::FORMAT_EXCEL => 'Excel',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (GiftCardBatchExport $batch) {
            if (empty($batch->exported_by)) {
                $batch->exported_by = auth()->id();
            }
        });
    }

    // Relationships
    public function template(): BelongsTo
    {
        return $this->belongsTo(GiftCardTemplate::class, 'template_id');
    }

    public function exportedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'exported_by');
    }

    // Methods
    public function getCards()
    {
        if (!$this->card_ids) {
            return collect();
        }

        return GiftCard::whereIn('id', $this->card_ids)->get();
    }

    // Accessors
    public function getFormatLabelAttribute(): ?string
    {
        return self::FORMATS[$this->export_format] ?? $this->export_format;
    }
}
