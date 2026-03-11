<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array $data
 * @property string | null $validation_error
 * @property-read Import $import
 */
class FailedImportRow extends Model
{
    protected $connection = 'tenant';

    protected $casts = [
        'data' => 'array',
    ];

    protected $guarded = [];

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
