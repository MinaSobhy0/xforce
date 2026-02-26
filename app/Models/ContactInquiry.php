<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactInquiry extends Model
{

    protected $connection = 'central';

    protected $table = 'public.contact_inquiries';

    protected $fillable = [
        'clinic_name',
        'contact_name',
        'email',
        'phone',
        'country',
        'message',
        'status',
        'notes',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
