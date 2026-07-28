<?php

namespace App\Http\Requests\Auth;
 
use Illuminate\Foundation\Http\FormRequest;
 
class RegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; }
 
    public function rules(): array
    {
        return [
            'nom'      => ['required', 'string', 'max:100'],
            'prenom'   => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'role'     => ['required', 'in:eleve,professeur,administrateur'],
        ];
    }
 
    public function messages(): array
    {
        return [
            'nom.required'              => 'Le nom est obligatoire.',
            'prenom.required'           => 'Le prénom est obligatoire.',
            'email.required'            => 'L\'adresse email est obligatoire.',
            'email.unique'              => 'Cette adresse email est déjà utilisée.',
            'password.confirmed'        => 'La confirmation du mot de passe ne correspond pas.',
            'password.min'              => 'Le mot de passe doit contenir au moins 6 caractères.',
            'role.required'             => 'Le rôle est obligatoire.',
            'role.in'                   => 'Rôle invalide.',
        ];
    }
}
