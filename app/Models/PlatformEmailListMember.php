<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformEmailListMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'list_id',
        'email',
        'name_hint',
        'source_type',
        'source_id',
        'subscribed_at',
        'unsubscribed_at',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(PlatformEmailList::class, 'list_id');
    }

    public function isSubscribed(): bool
    {
        return $this->unsubscribed_at === null;
    }
}
