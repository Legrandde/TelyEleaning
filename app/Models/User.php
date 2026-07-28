<?php

namespace App\Models;
 
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, HasApiTokens;
 
    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'password',
        'avatar',
        'role',
    ];
 
    protected $hidden = [
        'password',
        'remember_token',
    ];
 
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
    ];
 
    // ─── Relations ───
    public function eleve(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Eleve::class);
    }
 
    public function professeur(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Professeur::class);
    }
 
    // ─── Helpers ───
    public function isEleve(): bool        { return $this->role === 'eleve'; }
    public function isProfesseur(): bool   { return $this->role === 'professeur'; }
    public function isAdministrateur(): bool { return $this->role === 'administrateur'; }
 
    public function getNomCompletAttribute(): string
    {
        return "{$this->prenom} {$this->nom}";
    }
}
