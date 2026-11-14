<?php

namespace App\Http\Requests\Matiere;

use Illuminate\Foundation\Http\FormRequest;

class StoreMatiereRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nom'         => ['required', 'string', 'max:100'],
            'code'        => ['required', 'string', 'max:10', 'unique:matieres,code'],
            'description' => ['nullable', 'string'],
            'image'       => self::reglesImage(true),
        ];
    }

    /**
     * Fichier : uniquement une image (jamais un script exécutable).
     * Texte : uniquement une URL http(s) ou un chemin relatif, sans caractère dangereux.
     */
    public static function reglesImage(bool $obligatoire): array
    {
        $presence = $obligatoire ? 'required' : 'nullable';

        if (request()->hasFile('image')) {
            return [$presence, 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'];
        }

        return [$presence, 'string', 'max:500', 'regex:#^(https?://|/)[^\s"\'<>\\\\]+$#i'];
    }

    public function messages(): array
    {
        return [
            'nom.required'   => 'Le nom de la matière est obligatoire.',
            'code.required'  => 'Le code est obligatoire.',
            'code.unique'    => 'Ce code de matière est déjà utilisé.',
            'image.required' => 'L\'image d\'illustration de la matière est obligatoire.',
        ];
    }
}
