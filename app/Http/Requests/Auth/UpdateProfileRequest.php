<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        $avatarRules = ['nullable', 'string', 'max:500', 'regex:/^(https?:\/\/|\/|avatars\/).+/i'];
        if ($this->hasFile('avatar')) {
            $avatarRules = ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'];
        }

        return [
            'nom'        => ['required', 'string', 'max:255'],
            'prenom'     => ['required', 'string', 'max:255'],
            'email'      => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($userId)],
            'avatar'     => $avatarRules,
            'specialite' => ['nullable', 'string', 'max:255'],
            'classe'     => ['nullable', 'string', 'max:100'],
            'matricule'  => ['nullable', 'string', 'max:100'],
            'niveau_id'  => ['nullable', 'exists:niveaux,id'],
        ];
    }
}
