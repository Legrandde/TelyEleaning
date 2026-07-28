<?php

namespace App\Http\Requests\SujetExamen;
 
use Illuminate\Foundation\Http\FormRequest;
 
class StoreSujetExamenRequest extends FormRequest
{
    public function authorize(): bool { return true; }
 
    public function rules(): array
    {
        return [
            'titre'      => ['required', 'string', 'max:255'],
            'annee'      => ['required', 'integer', 'min:2000', 'max:' . date('Y')],
            'session'    => ['required', 'in:juin,septembre,janvier'],
            'type'       => ['required', 'in:bac,brevet,composition,autre'],
            'matiere_id' => ['required', 'exists:matieres,id'],
            'niveau_id'  => ['required', 'exists:niveaux,id'],
            // IDs des exercices à attacher (optionnel à la création)
            'exercices'              => ['nullable', 'array'],
            'exercices.*.id'         => ['required', 'exists:exercices,id'],
            'exercices.*.ordre'      => ['required', 'integer', 'min:1'],
        ];
    }
 
    public function messages(): array
    {
        return [
            'titre.required'      => 'Le titre du sujet est obligatoire.',
            'annee.required'      => 'L\'année est obligatoire.',
            'annee.min'           => 'L\'année doit être supérieure à 2000.',
            'annee.max'           => 'L\'année ne peut pas dépasser l\'année en cours.',
            'session.required'    => 'La session est obligatoire.',
            'session.in'          => 'Session invalide. Valeurs : juin, septembre, janvier.',
            'type.required'       => 'Le type est obligatoire.',
            'type.in'             => 'Type invalide. Valeurs : bac, brevet, composition, autre.',
            'matiere_id.required' => 'La matière est obligatoire.',
            'matiere_id.exists'   => 'Cette matière n\'existe pas.',
            'niveau_id.required'  => 'Le niveau est obligatoire.',
            'niveau_id.exists'    => 'Ce niveau n\'existe pas.',
            'exercices.*.id.exists' => 'Un exercice sélectionné n\'existe pas.',
        ];
    }
}
 
 