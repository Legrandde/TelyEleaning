<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ressource extends Model
{
    use HasFactory;

    protected $fillable = [
        'titre',
        'description',
        'type',
        'fichier_path',
        'fichier_nom',
        'format',
        'taille',
        'matiere_id',
        'niveau_id',
        'professeur_id',
        'est_public',
    ];

    protected $casts = [
        'est_public' => 'boolean',
        'taille'     => 'integer',
    ];

    protected $appends = ['fichier_url'];

    public function getFichierUrlAttribute(): ?string
    {
        if (!$this->fichier_path) {
            return null;
        }

        if (str_starts_with($this->fichier_path, 'http://') || str_starts_with($this->fichier_path, 'https://')) {
            return $this->fichier_path;
        }

        return asset('storage/' . $this->fichier_path);
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }

    public function professeur(): BelongsTo
    {
        return $this->belongsTo(Professeur::class);
    }
}
