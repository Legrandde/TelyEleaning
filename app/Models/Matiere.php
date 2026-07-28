<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
 
class Matiere extends Model
{
    protected $fillable = ['nom', 'code', 'description'];
 
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
 