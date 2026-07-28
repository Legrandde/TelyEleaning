<?php

namespace App\Http\Requests\Correction;
 
use Illuminate\Foundation\Http\FormRequest;
 
class StoreCorrectionRequest extends FormRequest
{
    public function authorize(): bool { return true; }
 
    public function rules(): array
    {
        return [
            'exercice_id' => ['required', 'exists:exercices,id', 'unique:corrections,exercice_id'],
            'contenu'     => ['required', 'string'],
            'explication' => ['nullable', 'string', 'max:500'],
        ];
    }
 
    public function messages(): array
    {
        return [
            'exercice_id.required' => 'L\'exercice est obligatoire.',
            'exercice_id.exists'   => 'Cet exercice n\'existe pas.',
            'exercice_id.unique'   => 'Une correction existe déjà pour cet exercice.',
            'contenu.required'     => 'Le contenu de la correction est obligatoire.',
        ];
    }
}
 
