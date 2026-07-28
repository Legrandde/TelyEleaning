<?php

namespace App\Http\Requests\Niveau;
 
use Illuminate\Foundation\Http\FormRequest;
 
class StoreNiveauRequest extends FormRequest
{
    public function authorize(): bool { return true; }
 
    public function rules(): array
    {
        return [
            'libelle' => ['required', 'string', 'max:100', 'unique:niveaux,libelle'],
            'cycle'   => ['required', 'string', 'max:100'],
            'ordre'   => ['required', 'integer', 'min:1'],
        ];
    }
 
    public function messages(): array
    {
        return [
            'libelle.required' => 'Le libellé est obligatoire.',
            'libelle.unique'   => 'Ce niveau existe déjà.',
            'cycle.required'   => 'Le cycle est obligatoire.',
            'ordre.required'   => 'L\'ordre est obligatoire.',
            'ordre.integer'    => 'L\'ordre doit être un entier.',
        ];
    }
}
 