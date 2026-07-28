<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
 
class Professeur extends Model
{
    protected $fillable = ['user_id', 'specialite'];
 
    // ─── Relations ───
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
 
    public function exercices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Exercice::class);
    }
}
 