<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategorieBlog extends Model
{
    protected $table = 'categories_blog';

    protected $fillable = ['nom', 'slug', 'description', 'couleur', 'ordre'];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'categorie_blog_id');
    }
}
