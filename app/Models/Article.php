<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Article du blog, rédigé avec les mêmes blocs que le page builder.
 * Un article publié avec une date future est « programmé » : invisible jusqu'à cette date.
 */
class Article extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'titre', 'slug', 'extrait', 'image_couverture', 'blocs', 'tags', 'statut', 'publie_le',
        'mis_en_avant', 'temps_lecture', 'meta_description', 'categorie_blog_id', 'auteur_id',
    ];

    protected $casts = [
        'blocs' => 'array',
        'tags' => 'array',
        'publie_le' => 'datetime',
        'mis_en_avant' => 'boolean',
    ];

    protected $appends = ['est_programme'];

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(CategorieBlog::class, 'categorie_blog_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id')->withTrashed();
    }

    /** Articles visibles par le public. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('statut', 'publie')->where('publie_le', '<=', now());
    }

    public function getEstProgrammeAttribute(): bool
    {
        return $this->statut === 'publie' && $this->publie_le?->isFuture() === true;
    }

    /**
     * Estime le temps de lecture (200 mots/minute) à partir des textes des blocs.
     *
     * @param  array<int, mixed>  $blocs
     */
    public static function estimerTempsLecture(array $blocs): int
    {
        $mots = 0;
        array_walk_recursive($blocs, function ($valeur) use (&$mots) {
            if (is_string($valeur) && ! preg_match('#^(https?://|/)#', $valeur)) {
                $mots += str_word_count(strip_tags($valeur), 0, 'àâäéèêëîïôöùûüçÀÂÄÉÈÊËÎÏÔÖÙÛÜÇ\'-');
            }
        });

        return max(1, (int) ceil($mots / 200));
    }
}
