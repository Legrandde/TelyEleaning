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
            'image'       => ['required'],
        ];
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
