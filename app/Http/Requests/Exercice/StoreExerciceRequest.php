<?php

namespace App\Http\Requests\Exercice;

use Illuminate\Foundation\Http\FormRequest;

class StoreExerciceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'titre'         => ['required', 'string', 'max:255'],
            'enonce'        => ['required', 'string'],
            'type'          => ['required', 'in:classique,qcm,probleme,sujet_examen'],
            'difficulte'    => ['required', 'in:facile,moyen,difficile'],
            'est_actif'     => ['boolean'],
            'image'         => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'matiere_id'    => ['required', 'exists:matieres,id'],
            'niveau_id'     => ['required', 'exists:niveaux,id'],
            'categorie_id'  => ['nullable', 'exists:categories,id'],
            'professeur_id' => ['required', 'exists:professeurs,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'titre.required'         => 'Le titre est obligatoire.',
            'enonce.required'        => 'L\'énoncé est obligatoire.',
            'type.required'          => 'Le type est obligatoire.',
            'type.in'                => 'Type invalide. Valeurs acceptées : classique, qcm, probleme, sujet_examen.',
            'difficulte.required'    => 'La difficulté est obligatoire.',
            'difficulte.in'          => 'Difficulté invalide. Valeurs acceptées : facile, moyen, difficile.',
            'matiere_id.required'    => 'La matière est obligatoire.',
            'matiere_id.exists'      => 'Cette matière n\'existe pas.',
            'niveau_id.required'     => 'Le niveau est obligatoire.',
            'niveau_id.exists'       => 'Ce niveau n\'existe pas.',
            'categorie_id.exists'    => 'Cette catégorie n\'existe pas.',
            'professeur_id.required' => 'Le professeur est obligatoire.',
            'professeur_id.exists'   => 'Ce professeur n\'existe pas.',
        ];
    }
}
