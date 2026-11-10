<?php

namespace App\Http\Requests\Matiere;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMatiereRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $matiereId = $this->route('matiere');

        return [
            'nom'         => ['sometimes', 'string', 'max:100'],
            'code'        => ['sometimes', 'string', 'max:10', "unique:matieres,code,{$matiereId}"],
            'description' => ['nullable', 'string'],
            'image'       => ['nullable'],
        ];
    }
}
