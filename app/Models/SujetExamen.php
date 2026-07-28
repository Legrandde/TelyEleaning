<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
 
class SujetExamen extends Model
{
    protected $table = 'sujets_examen';
 
    protected $fillable = [
        'titre',
        'annee',
        'session',
        'type',
        'matiere_id',
        'niveau_id',
    ];
 
    // ─── Relations ───
    public function matiere(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }
 
    public function niveau(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }
 
    public function exercices(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Exercice::class, 'exercice_sujet_examen')
                    ->withPivot('ordre')
                    ->orderByPivot('ordre');
    }
 
    // ─── Scopes ───
    public function scopeParAnnee($query, int $annee)
    {
        return $query->where('annee', $annee);
    }
 
    public function scopeParType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
 
 