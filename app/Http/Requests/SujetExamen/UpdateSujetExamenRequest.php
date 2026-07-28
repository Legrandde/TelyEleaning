<?php

namespace App\Http\Requests\SujetExamen;
 
use Illuminate\Foundation\Http\FormRequest;
 
class UpdateSujetExamenRequest extends FormRequest
{
    public function authorize(): bool { return true; }
 
    public function rules(): array
    {
        return [
            'titre'      => ['sometimes', 'string', 'max:255'],
            'annee'      => ['sometimes', 'integer', 'min:2000', 'max:' . date('Y')],
            'session'    => ['sometimes', 'in:juin,septembre,janvier'],
            'type'       => ['sometimes', 'in:bac,brevet,composition,autre'],
            'matiere_id' => ['sometimes', 'exists:matieres,id'],
            'niveau_id'  => ['sometimes', 'exists:niveaux,id'],
        ];
    }
}
 
 