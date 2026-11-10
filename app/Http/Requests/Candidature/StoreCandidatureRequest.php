<?php

namespace App\Http\Requests\Candidature;

use Illuminate\Foundation\Http\FormRequest;

class StoreCandidatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom'               => ['required', 'string', 'max:100'],
            'prenom'            => ['required', 'string', 'max:100'],
            'email'             => ['required', 'string', 'email', 'max:150'],
            'telephone'         => ['nullable', 'string', 'max:50'],
            'specialite'        => ['required', 'string', 'max:150'],
            'etablissement'     => ['nullable', 'string', 'max:150'],
            'diplome'           => ['nullable', 'string', 'max:150'],
            'experience_annees' => ['nullable', 'integer', 'min:0', 'max:60'],
            'message'           => ['nullable', 'string', 'max:2000'],
            'cv'                => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required'        => 'Le nom est obligatoire.',
            'prenom.required'     => 'Le prénom est obligatoire.',
            'email.required'      => 'L\'adresse email est obligatoire.',
            'email.email'         => 'Veuillez saisir une adresse email valide.',
            'specialite.required' => 'La spécialité ou discipline enseignée est obligatoire.',
            'cv.max'              => 'Le fichier CV ne doit pas dépasser 10 Mo.',
            'cv.mimes'            => 'Le CV doit être au format PDF ou Word (pdf, doc, docx).',
        ];
    }
}
