<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsVisit extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'ip_address',
        'path',
        'action',
        'metadata',
        'country',
        'country_code',
        'city',
        'os',
        'os_version',
        'browser',
        'browser_version',
        'device_type',
        'user_agent',
        'referer',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
