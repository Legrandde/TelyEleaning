<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    protected $table = 'medias';

    protected $fillable = ['chemin', 'nom', 'mime', 'taille', 'alt', 'user_id'];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return asset('storage/'.$this->chemin);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
