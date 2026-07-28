<?php

namespace App\Http\Requests\Professeur;
 
use Illuminate\Foundation\Http\FormRequest;
 
class StoreProfesseurRequest extends FormRequest
{
    public function authorize(): bool { return true; }
 
    public function rules(): array
    {
        return [
            'user_id'    => ['required', 'exists:users,id'],
            'specialite' => ['nullable', 'string', 'max:150'],
        ];
    }
 
    public function messages(): array
    {
        return [
            'user_id.required' => 'L\'utilisateur est obligatoire.',
            'user_id.exists'   => 'Cet utilisateur n\'existe pas.',
        ];
    }
}
 