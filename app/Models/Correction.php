<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Correction extends Model
{
    protected $fillable = ['exercice_id', 'contenu', 'explication', 'fichier_pdf'];

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
    public function exercice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }
}
