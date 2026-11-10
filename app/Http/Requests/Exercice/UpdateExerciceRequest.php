<?php

namespace App\Http\Requests\Exercice;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExerciceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'titre'        => ['sometimes', 'string', 'max:255'],
            'enonce'       => ['sometimes', 'string'],
            'type'         => ['sometimes', 'in:classique,qcm,probleme,sujet_examen'],
            'difficulte'   => ['sometimes', 'in:facile,moyen,difficile'],
            'est_actif'    => ['sometimes', 'boolean'],
            'image'        => ['nullable'],
            'matiere_id'   => ['sometimes', 'exists:matieres,id'],
            'niveau_id'    => ['sometimes', 'exists:niveaux,id'],
            'categorie_id' => ['nullable', 'exists:categories,id'],
        ];
    }
}
