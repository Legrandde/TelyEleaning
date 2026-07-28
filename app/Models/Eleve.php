<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
 
class Eleve extends Model
{
    protected $fillable = ['user_id', 'matricule', 'niveau_id', 'classe'];
 
    // ─── Relations ───
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
 
    public function niveau(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }
 
    public function historiques(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Historique::class);
    }
 
    // Exercices consultés via historique
    public function exercicesConsultes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Exercice::class, 'historiques')
                    ->withPivot('action', 'date_consultation')
                    ->withTimestamps();
    }
}
 