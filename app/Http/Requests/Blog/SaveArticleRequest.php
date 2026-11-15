<?php

namespace App\Http\Requests\Blog;

use App\Http\Requests\Concerns\ValideBlocs;
use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveArticleRequest extends FormRequest
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

        if (is_array($this->input('tags'))) {
            $tags = collect($this->input('tags'))
                ->filter(fn ($t) => is_string($t) && trim($t) !== '')
                ->map(fn (string $t) => mb_strtolower(trim($t)))
                ->unique()->values()->all();
            $this->merge(['tags' => $tags]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $articleId = $this->route('article')?->id ?? 'NULL';
        $requis = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'titre' => [$requis, 'string', 'max:200'],
            'slug' => [$requis, 'string', 'max:220', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', "unique:articles,slug,{$articleId},id,deleted_at,NULL"],
            'extrait' => ['nullable', 'string', 'max:400'],
            'image_couverture' => ['nullable', 'string', 'max:500'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'statut' => ['sometimes', 'in:brouillon,publie'],
            'publie_le' => ['nullable', 'date'],
            'mis_en_avant' => ['sometimes', 'boolean'],
            'categorie_blog_id' => ['nullable', 'integer', 'exists:categories_blog,id'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:40'],
            'blocs' => [$this->isMethod('post') ? 'present' : 'sometimes', 'array', 'max:80'],
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
            fn (Validator $validator) => $this->validerBlocsEtLiens($validator, ['image_couverture']),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'Un autre article utilise déjà cette adresse.',
            'slug.regex' => 'L\'adresse ne peut contenir que des lettres minuscules, chiffres et tirets.',
        ];
    }
}
