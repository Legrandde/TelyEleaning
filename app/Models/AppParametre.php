<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppParametre extends Model
{
    use HasFactory;

    protected $table = 'app_parametres';

    protected $fillable = [
        'cle',
        'valeur',
        'groupe',
    ];

    /**
     * Obtenir une valeur avec valeur par défaut
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $param = static::where('cle', $key)->first();
        return $param ? $param->valeur : $default;
    }

    /**
     * Définir ou mettre à jour un paramètre
     */
    public static function set(string $key, mixed $value, string $group = 'general'): static
    {
        return static::updateOrCreate(
            ['cle' => $key],
            ['valeur' => is_bool($value) ? ($value ? '1' : '0') : (string) $value, 'groupe' => $group]
        );
    }
}
