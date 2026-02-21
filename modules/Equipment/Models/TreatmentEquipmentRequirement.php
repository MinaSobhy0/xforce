<?php

namespace Modules\Equipment\Models;

use XLinic\Framework\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Treatments\Models\Treatment;

class TreatmentEquipmentRequirement extends BaseModel
{
    protected $fillable = [
        'treatment_id',
        'equipment_type_id',
        'is_required',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }

    public function equipmentType(): BelongsTo
    {
        return $this->belongsTo(EquipmentType::class);
    }
}
