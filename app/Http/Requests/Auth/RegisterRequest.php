<?php

namespace App\Http\Requests\Auth;

use App\Models\AppParametre;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterRequest extends FormRequest
{
    /**
     * Inscriptions fermées : refus AVANT la validation, pour ne pas révéler
     * via « email déjà utilisé » qu'une adresse possède un compte.
     */
    public function authorize(): bool
    {
        if (! AppParametre::actif('inscriptions_ouvertes') && ! auth('sanctum')->user()?->isAdministrateur()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Les inscriptions sont momentanément fermées.',
                'code'    => 'inscriptions_fermees',
            ], 403));
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'nom'      => ['required', 'string', 'max:100'],
            'prenom'   => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:'.AppParametre::longueurMinMotDePasse(), 'max:255', 'confirmed'],
            'role'     => ['nullable', 'in:eleve,professeur,administrateur'],
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
            'password.min'              => 'Le mot de passe doit contenir au moins :min caractères.',
            'role.in'                   => 'Rôle invalide.',
        ];
    }
}
