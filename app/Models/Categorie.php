<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
 
class Categorie extends Model
{
    protected $fillable = ['nom', 'couleur', 'icone'];
 
    // ─── Relations ───
    public function exercices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Exercice::class);
    }
}
 
