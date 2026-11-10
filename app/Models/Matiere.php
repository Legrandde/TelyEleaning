<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Matiere extends Model
{
    protected $fillable = ['nom', 'code', 'description', 'image'];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        return asset('storage/' . $this->image);
    }

    // ─── Relations ───
    public function exercices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Exercice::class);
    }

    public function sujetsExamen(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SujetExamen::class);
    }
}
