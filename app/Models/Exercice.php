<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
 
class Exercice extends Model
{
    use SoftDeletes;
 
    protected $fillable = [
        'titre',
        'enonce',
        'type',
        'difficulte',
        'est_actif',
        'matiere_id',
        'niveau_id',
        'categorie_id',
        'professeur_id',
    ];
 
    protected $casts = [
        'est_actif' => 'boolean',
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
 
    public function categorie(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Categorie::class);
    }
 
    public function professeur(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Professeur::class);
    }
 
    public function correction(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Correction::class);
    }
 
    public function sujetsExamen(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(SujetExamen::class, 'exercice_sujet_examen')
                    ->withPivot('ordre');
    }
 
    public function historiques(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Historique::class);
    }
 
    // ─── Scopes ───
    public function scopeActif($query)
    {
        return $query->where('est_actif', true);
    }
 
    public function scopeParNiveau($query, int $niveauId)
    {
        return $query->where('niveau_id', $niveauId);
    }
 
    public function scopeParMatiere($query, int $matiereId)
    {
        return $query->where('matiere_id', $matiereId);
    }
 
    public function scopeParDifficulte($query, string $difficulte)
    {
        return $query->where('difficulte', $difficulte);
    }
 
    public function scopeAvecCorrection($query)
    {
        return $query->whereHas('correction');
    }
 
    // ─── Helpers ───
    public function aUneCorrection(): bool
    {
        return $this->correction()->exists();
    }
}
 