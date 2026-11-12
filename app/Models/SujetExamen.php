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
        'ennonce',
        'fichier_pdf',
        'matiere_id',
        'niveau_id',
    ];

    protected $appends = ['pdf_url'];

    public function getPdfUrlAttribute(): ?string
    {
        if (!$this->fichier_pdf) {
            return null;
        }

        if (str_starts_with($this->fichier_pdf, 'http://') || str_starts_with($this->fichier_pdf, 'https://')) {
            return $this->fichier_pdf;
        }

        return asset('storage/' . $this->fichier_pdf);
    }

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
