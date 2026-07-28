<?php

namespace App\Http\Requests\Eleve;
 
use Illuminate\Foundation\Http\FormRequest;
 
class UpdateEleveRequest extends FormRequest
{
    public function authorize(): bool { return true; }
 
    public function rules(): array
    {
        $eleveId = $this->route('eleve');
 
        return [
            'matricule' => ['sometimes', 'string', 'max:50', "unique:eleves,matricule,{$eleveId}"],
            'niveau_id' => ['sometimes', 'exists:niveaux,id'],
            'classe'    => ['sometimes', 'string', 'max:50'],
        ];
    }
}
 