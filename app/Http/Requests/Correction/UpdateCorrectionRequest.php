<?php

namespace App\Http\Requests\Correction;
 
use Illuminate\Foundation\Http\FormRequest;
 
class UpdateCorrectionRequest extends FormRequest
{
    public function authorize(): bool { return true; }
 
    public function rules(): array
    {
        return [
            'contenu'     => ['sometimes', 'string'],
            'explication' => ['nullable', 'string', 'max:500'],
        ];
    }
}
