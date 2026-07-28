<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
 
class Niveau extends Model
{
    protected $fillable = ['libelle', 'cycle', 'ordre'];
 
    // ─── Relations ───
    public function exercices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Exercice::class);
    }
 
    public function eleves(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Eleve::class);
    }
 
    public function sujetsExamen(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SujetExamen::class);
    }
}
 