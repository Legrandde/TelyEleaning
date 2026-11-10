<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidatureProfesseur extends Model
{
    protected $table = 'candidatures_professeurs';

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'telephone',
        'specialite',
        'etablissement',
        'diplome',
        'experience_annees',
        'message',
        'cv_path',
        'statut',
        'user_id',
        'motif_rejet',
    ];

    protected $casts = [
        'experience_annees' => 'integer',
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    protected $appends = ['cv_url', 'nom_complet'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getNomCompletAttribute(): string
    {
        return "{$this->prenom} {$this->nom}";
    }

    public function getCvUrlAttribute(): ?string
    {
        if (!$this->cv_path) {
            return null;
        }

        if (str_starts_with($this->cv_path, 'http://') || str_starts_with($this->cv_path, 'https://')) {
            return $this->cv_path;
        }

        return asset('storage/' . $this->cv_path);
    }
}
