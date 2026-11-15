<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Page éditée avec le page builder : une suite ordonnée de blocs JSON.
 */
class Page extends Model
{
    use SoftDeletes;

    /** Types de blocs reconnus par le rendu frontend. */
    public const TYPES_BLOCS = [
        'hero', 'texte', 'image', 'texte_image', 'fonctionnalites', 'statistiques',
        'cta', 'faq', 'temoignages', 'tarifs', 'exercices_recents', 'logos',
        'video', 'espace', 'etapes',
    ];

    /** Slugs réservés par les routes du frontend. */
    public const SLUGS_RESERVES = [
        'auth', 'admin', 'api', 'exercices', 'exercice', 'sujets', 'sujet', 'espace',
        'profile', 'parametres', 'notifications', 'dashbord-admin', 'dashboard-professeur',
        'ennoncer', 'ennoncer-sujet', 'p', 'blog', 'page-builder',
    ];

    protected $fillable = [
        'titre', 'slug', 'meta_description', 'image_og', 'blocs', 'statut',
        'est_accueil', 'dans_menu', 'dans_footer', 'publie_le', 'auteur_id', 'modifie_par_id',
    ];

    protected $casts = [
        'blocs' => 'array',
        'est_accueil' => 'boolean',
        'dans_menu' => 'boolean',
        'dans_footer' => 'boolean',
        'publie_le' => 'datetime',
    ];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    public function modifiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifie_par_id');
    }

    public function scopePubliee(Builder $query): Builder
    {
        return $query->where('statut', 'publie');
    }
}
