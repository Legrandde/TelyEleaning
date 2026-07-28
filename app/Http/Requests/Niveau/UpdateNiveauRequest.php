<?php

namespace App\Http\Requests\Niveau;
 
use Illuminate\Foundation\Http\FormRequest;
 
class UpdateNiveauRequest extends FormRequest
{
    public function authorize(): bool { return true; }
 
    public function rules(): array
    {
        $niveauId = $this->route('niveau');
 
        return [
            'libelle' => ['sometimes', 'string', 'max:100', "unique:niveaux,libelle,{$niveauId}"],
            'cycle'   => ['sometimes', 'string', 'max:100'],
            'ordre'   => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
 