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
        'suspendu_le',
        'motif_suspension',
        'derniere_connexion_le',
        'onboarding_termine_le',
        'google_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'suspendu_le'           => 'datetime',
        'derniere_connexion_le' => 'datetime',
        'onboarding_termine_le' => 'datetime',
    ];

    protected $appends = [
        'nom_complet',
        'avatar_url',
        'est_suspendu',
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

    public function isSuspendu(): bool { return $this->suspendu_le !== null; }

    public function getEstSuspenduAttribute(): bool
    {
        return $this->isSuspendu();
    }

    public function getNomCompletAttribute(): string
    {
        return "{$this->prenom} {$this->nom}";
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar) {
            return null;
        }

        if (str_starts_with($this->avatar, 'http://') || str_starts_with($this->avatar, 'https://')) {
            return $this->avatar;
        }

        return asset('storage/' . $this->avatar);
    }
}
