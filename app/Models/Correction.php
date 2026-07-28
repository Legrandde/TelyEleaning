<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
 
class Correction extends Model
{
    protected $fillable = ['exercice_id', 'contenu', 'explication'];
 
    // ─── Relations ───
    public function exercice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }
}