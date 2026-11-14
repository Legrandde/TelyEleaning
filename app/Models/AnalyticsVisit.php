<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsVisit extends Model
{
    use Prunable;

    /** Durée de conservation des traces de visite (recommandation CNIL : 13 mois maximum). */
    public const CONSERVATION_MOIS = 13;

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subMonths(self::CONSERVATION_MOIS));
    }

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
