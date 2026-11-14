<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AbonneNewsletter extends Model
{
    use HasUlids;

    protected $table = 'abonnes_newsletter';

    protected $fillable = [
        'email',
        'demande_le',
        'consenti_le',
        'desabonne_le',
    ];

    protected function casts(): array
    {
        return [
            'demande_le'   => 'datetime',
            'consenti_le'  => 'datetime',
            'desabonne_le' => 'datetime',
        ];
    }
}
