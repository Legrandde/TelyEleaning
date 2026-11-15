<?php

namespace App\Http\Requests\Page;

use App\Http\Requests\Concerns\ValideBlocs;
use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SavePageRequest extends FormRequest
{
    use ValideBlocs;

    public function authorize(): bool
    {
        return $this->user()?->isAdministrateur() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('slug')) {
            $this->merge(['slug' => str($this->input('slug'))->slug()->value()]);
        } elseif ($this->filled('titre') && $this->isMethod('post')) {
            $this->merge(['slug' => str($this->input('titre'))->slug()->value()]);
        }
    }

    /**
     * Règles exprimées en chaînes (compatibles avec le générateur Swagger) ;
     * le contrôle des liens et du contenu des blocs est fait dans after().
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $pageId = $this->route('page')?->id ?? 'NULL';
        $requis = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'titre' => [$requis, 'string', 'max:150'],
            'slug' => [
                $requis, 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                'not_in:'.implode(',', Page::SLUGS_RESERVES),
                "unique:pages,slug,{$pageId},id,deleted_at,NULL",
            ],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'image_og' => ['nullable', 'string', 'max:500'],
            'statut' => ['sometimes', 'in:brouillon,publie'],
            'est_accueil' => ['sometimes', 'boolean'],
            'dans_menu' => ['sometimes', 'boolean'],
            'dans_footer' => ['sometimes', 'boolean'],
            'blocs' => [$this->isMethod('post') ? 'present' : 'sometimes', 'array', 'max:60'],
            'blocs.*.id' => ['required', 'string', 'max:64'],
            'blocs.*.type' => ['required', 'in:'.implode(',', Page::TYPES_BLOCS)],
            'blocs.*.props' => ['present', 'array'],
            'blocs.*.masque' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validerBlocsEtLiens($validator, ['image_og']),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.not_in' => 'Cette adresse est réservée par l\'application.',
            'slug.unique' => 'Une autre page utilise déjà cette adresse.',
            'slug.regex' => 'L\'adresse ne peut contenir que des lettres minuscules, chiffres et tirets.',
        ];
    }
}
