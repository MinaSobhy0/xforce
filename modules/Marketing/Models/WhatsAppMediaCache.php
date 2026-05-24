<?php

namespace Modules\Marketing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * sha256 → Meta media_id cache. Lives in the tenant schema; rows expire
 * naturally as Meta-side media_ids age out (~30 days).
 */
class WhatsAppMediaCache extends Model
{
    protected $table = 'whatsapp_media_cache';

    protected $primaryKey = 'sha256';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'sha256',
        'media_id',
        'mime',
        'size_bytes',
        'uploaded_at',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'uploaded_at' => 'datetime',
    ];
}
