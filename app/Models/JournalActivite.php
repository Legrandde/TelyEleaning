<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal d'audit des actions sensibles effectuées dans le back-office.
 */
class JournalActivite extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'journal_activites';

    protected $fillable = ['user_id', 'action', 'sujet_type', 'sujet_id', 'description', 'meta', 'ip'];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
