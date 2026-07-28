<?php

namespace App\Http\Requests\Eleve;
 
use Illuminate\Foundation\Http\FormRequest;
 
class StoreEleveRequest extends FormRequest
{
    public function authorize(): bool { return true; }
 
    public function rules(): array
    {
        return [
            'user_id'    => ['required', 'exists:users,id'],
            'matricule'  => ['required', 'string', 'max:50', 'unique:eleves,matricule'],
            'niveau_id'  => ['required', 'exists:niveaux,id'],
            'classe'     => ['required', 'string', 'max:50'],
        ];
    }
 
    public function messages(): array
    {
        return [
            'user_id.required'   => 'L\'utilisateur est obligatoire.',
            'user_id.exists'     => 'Cet utilisateur n\'existe pas.',
            'matricule.required' => 'Le matricule est obligatoire.',
            'matricule.unique'   => 'Ce matricule est déjà utilisé.',
            'niveau_id.required' => 'Le niveau est obligatoire.',
            'niveau_id.exists'   => 'Ce niveau n\'existe pas.',
            'classe.required'    => 'La classe est obligatoire.',
        ];
    }
}
 
 
