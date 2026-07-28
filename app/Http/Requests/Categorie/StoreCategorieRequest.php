<?php

namespace App\Http\Requests\Categorie;
 
use Illuminate\Foundation\Http\FormRequest;
 
class StoreCategorieRequest extends FormRequest
{
    public function authorize(): bool { return true; }
 
    public function rules(): array
    {
        return [
            'nom'    => ['required', 'string', 'max:100'],
            'couleur'=> ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icone'  => ['nullable', 'string', 'max:50'],
        ];
    }
 
    public function messages(): array
    {
        return [
            'nom.required'      => 'Le nom de la catégorie est obligatoire.',
            'couleur.regex'     => 'La couleur doit être un code hexadécimal valide (ex: #4f8ef7).',
        ];
    }
}
 
 