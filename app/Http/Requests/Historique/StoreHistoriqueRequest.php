<?php

namespace App\Http\Requests\Historique;
 
use Illuminate\Foundation\Http\FormRequest;
 
class StoreHistoriqueRequest extends FormRequest
{
    public function authorize(): bool { return true; }
 
    public function rules(): array
    {
        return [
            'eleve_id'    => ['required', 'exists:eleves,id'],
            'exercice_id' => ['required', 'exists:exercices,id'],
            'action'      => ['required', 'in:consulte,correction_vue,favori'],
        ];
    }
 
    public function messages(): array
    {
        return [
            'eleve_id.required'    => 'L\'élève est obligatoire.',
            'eleve_id.exists'      => 'Cet élève n\'existe pas.',
            'exercice_id.required' => 'L\'exercice est obligatoire.',
            'exercice_id.exists'   => 'Cet exercice n\'existe pas.',
            'action.required'      => 'L\'action est obligatoire.',
            'action.in'            => 'Action invalide. Valeurs : consulte, correction_vue, favori.',
        ];
    }
}
 