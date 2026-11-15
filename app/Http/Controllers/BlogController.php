<?php

namespace App\Http\Controllers;

use App\Http\Requests\Blog\SaveArticleRequest;
use App\Models\AppParametre;
use App\Models\Article;
use App\Models\CategorieBlog;
use App\Services\JournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Blog : lecture publique des articles visibles, rédaction réservée aux administrateurs.
 */
class BlogController extends Controller
{
    private const CHAMPS_LISTE = [
        'id', 'titre', 'slug', 'extrait', 'image_couverture', 'tags', 'publie_le', 'mis_en_avant',
        'temps_lecture', 'vues', 'categorie_blog_id', 'auteur_id', 'statut',
    ];

    private const RELATIONS_PUBLIQUES = ['categorie:id,nom,slug,couleur', 'auteur:id,nom,prenom,avatar'];

    // ─── Public ─────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'categorie' => ['nullable', 'string', 'max:120'],
            'tag' => ['nullable', 'string', 'max:40'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $articles = Article::visible()
            ->with(self::RELATIONS_PUBLIQUES)
            ->when($request->query('categorie'), fn ($q, $slug) => $q->whereHas('categorie', fn ($c) => $c->where('slug', $slug)))
            ->when($request->query('tag'), fn ($q, $tag) => $q->whereJsonContains('tags', mb_strtolower($tag)))
            ->when(trim((string) $request->query('search')), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('titre', 'like', "%{$s}%")->orWhere('extrait', 'like', "%{$s}%")))
            ->latest('publie_le')
            ->paginate((int) $request->query('per_page', 9), self::CHAMPS_LISTE);

        $aLaUne = ! $request->hasAny(['categorie', 'tag', 'search']) && (int) $request->query('page', 1) === 1
            ? Article::visible()->with(self::RELATIONS_PUBLIQUES)->where('mis_en_avant', true)->latest('publie_le')->first(self::CHAMPS_LISTE)
            : null;

        return response()->json(['articles' => $articles, 'a_la_une' => $aLaUne]);
    }

    public function afficher(string $slug): JsonResponse
    {
        $article = Article::visible()->with(self::RELATIONS_PUBLIQUES)->where('slug', $slug)->firstOrFail();
        $article->blocs = array_values(array_filter($article->blocs ?? [], fn ($b) => empty($b['masque'])));

        // Compteur de vues sans toucher à updated_at
        Article::whereKey($article->id)->increment('vues', 1, ['updated_at' => $article->updated_at]);

        $similaires = Article::visible()
            ->with(self::RELATIONS_PUBLIQUES)
            ->whereKeyNot($article->id)
            ->when($article->categorie_blog_id, fn ($q) => $q->orderByRaw('categorie_blog_id = ? DESC', [$article->categorie_blog_id]))
            ->latest('publie_le')
            ->limit(3)
            ->get(self::CHAMPS_LISTE);

        return response()->json(['article' => $article, 'similaires' => $similaires]);
    }

    public function categories(): JsonResponse
    {
        $categories = CategorieBlog::query()
            ->withCount(['articles as articles_count' => fn ($q) => $q->visible()])
            ->orderBy('ordre')->orderBy('nom')
            ->get();

        $tags = Article::visible()->pluck('tags')->flatten()->filter()->countBy()->sortDesc()->take(20);

        return response()->json([
            'categories' => $categories,
            'tags' => $tags->map(fn ($n, $tag) => ['tag' => $tag, 'total' => $n])->values(),
        ]);
    }

    public function rss(): Response
    {
        $site = AppParametre::tous();
        $front = rtrim((string) config('app.frontend_url'), '/');
        $articles = Article::visible()->with('auteur:id,nom,prenom')->latest('publie_le')->limit(30)->get();

        $e = fn (?string $v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $items = $articles->map(fn (Article $a) => '<item>'
            .'<title>'.$e($a->titre).'</title>'
            .'<link>'.$e("{$front}/blog/{$a->slug}").'</link>'
            .'<guid isPermaLink="true">'.$e("{$front}/blog/{$a->slug}").'</guid>'
            .'<pubDate>'.$a->publie_le?->toRssString().'</pubDate>'
            .'<description>'.$e($a->extrait).'</description>'
            .'</item>')->implode('');

        $xml = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel>'
            .'<title>'.$e($site['site_nom'].' — Blog').'</title>'
            .'<link>'.$e("{$front}/blog").'</link>'
            .'<description>'.$e($site['site_description']).'</description>'
            .'<language>fr</language>'.$items.'</channel></rss>';

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }

    // ─── Administration : articles ────────────────────────────

    public function adminIndex(Request $request): JsonResponse
    {
        $request->validate([
            'statut' => ['nullable', 'in:brouillon,publie,programme'],
            'search' => ['nullable', 'string', 'max:100'],
            'categorie_blog_id' => ['nullable', 'integer'],
        ]);

        $articles = Article::query()
            ->with(self::RELATIONS_PUBLIQUES)
            ->when($request->query('statut'), function ($q, $statut) {
                match ($statut) {
                    'brouillon' => $q->where('statut', 'brouillon'),
                    'publie' => $q->visible(),
                    'programme' => $q->where('statut', 'publie')->where('publie_le', '>', now()),
                };
            })
            ->when($request->query('categorie_blog_id'), fn ($q, $id) => $q->where('categorie_blog_id', $id))
            ->when(trim((string) $request->query('search')), fn ($q, $s) => $q->where('titre', 'like', "%{$s}%"))
            ->latest('updated_at')
            ->paginate(20, [...self::CHAMPS_LISTE, 'updated_at']);

        return response()->json([
            'articles' => $articles,
            'counts' => [
                'tous' => Article::count(),
                'publie' => Article::visible()->count(),
                'programme' => Article::where('statut', 'publie')->where('publie_le', '>', now())->count(),
                'brouillon' => Article::where('statut', 'brouillon')->count(),
            ],
        ]);
    }

    public function adminShow(Article $article): JsonResponse
    {
        return response()->json(['article' => $article->load(self::RELATIONS_PUBLIQUES)]);
    }

    public function store(SaveArticleRequest $request): JsonResponse
    {
        $data = $this->donnees($request);
        $data['auteur_id'] = $request->user()->id;
        $article = Article::create($data);

        JournalService::log('article.cree', "Article « {$article->titre} » créé", $article);

        return response()->json(['message' => 'Article créé.', 'article' => $article], 201);
    }

    public function update(SaveArticleRequest $request, Article $article): JsonResponse
    {
        $etaitPublie = $article->statut === 'publie';
        $article->update($this->donnees($request, $article));

        $publie = ! $etaitPublie && $article->statut === 'publie';
        JournalService::log(
            $publie ? 'article.publie' : 'article.modifie',
            "Article « {$article->titre} » ".($publie ? 'publié' : 'modifié'),
            $article
        );

        return response()->json(['message' => 'Article enregistré.', 'article' => $article->fresh(self::RELATIONS_PUBLIQUES)]);
    }

    public function dupliquer(Request $request, Article $article): JsonResponse
    {
        $copie = $article->replicate(['publie_le', 'vues']);
        $copie->titre = Str::limit($article->titre.' (copie)', 200, '');
        $copie->slug = $this->slugDisponible($article->slug.'-copie');
        $copie->statut = 'brouillon';
        $copie->mis_en_avant = false;
        $copie->vues = 0;
        $copie->auteur_id = $request->user()->id;
        $copie->save();

        return response()->json(['message' => 'Article dupliqué.', 'article' => $copie], 201);
    }

    public function destroy(Article $article): JsonResponse
    {
        $article->update(['slug' => $this->slugDisponible($article->slug.'-supprime'), 'mis_en_avant' => false]);
        $article->delete();
        JournalService::log('article.supprime', "Article « {$article->titre} » supprimé", $article);

        return response()->json(['message' => 'Article supprimé.']);
    }

    // ─── Administration : catégories ──────────────────────────

    public function storeCategorie(Request $request): JsonResponse
    {
        $data = $this->donneesCategorie($request);
        $categorie = CategorieBlog::create($data);

        return response()->json(['message' => 'Catégorie créée.', 'categorie' => $categorie], 201);
    }

    public function updateCategorie(Request $request, CategorieBlog $categorie): JsonResponse
    {
        $categorie->update($this->donneesCategorie($request, $categorie));

        return response()->json(['message' => 'Catégorie mise à jour.', 'categorie' => $categorie]);
    }

    public function destroyCategorie(CategorieBlog $categorie): JsonResponse
    {
        $categorie->delete();

        return response()->json(['message' => 'Catégorie supprimée. Les articles concernés sont désormais sans catégorie.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function donneesCategorie(Request $request, ?CategorieBlog $categorie = null): array
    {
        $request->merge(['slug' => str($request->input('slug') ?: $request->input('nom'))->slug()->value()]);
        $id = $categorie?->id ?? 'NULL';

        return $request->validate([
            'nom' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:120', "unique:categories_blog,slug,{$id}"],
            'description' => ['nullable', 'string', 'max:300'],
            'couleur' => ['nullable', 'in:amber,emerald,sky,violet,rose,gray'],
            'ordre' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function donnees(SaveArticleRequest $request, ?Article $article = null): array
    {
        $data = $request->validated();

        if (array_key_exists('blocs', $data)) {
            $data['blocs'] = array_values(array_map(fn (array $b) => [
                'id' => $b['id'],
                'type' => $b['type'],
                'props' => $b['props'] ?? [],
                'masque' => (bool) ($b['masque'] ?? false),
            ], $data['blocs']));
            $data['temps_lecture'] = Article::estimerTempsLecture($data['blocs']);
        }

        // Publication sans date : publié immédiatement (une date future programme l'article)
        $statut = $data['statut'] ?? $article?->statut ?? 'brouillon';
        if ($statut === 'publie' && empty($data['publie_le']) && ! $article?->publie_le) {
            $data['publie_le'] = now();
        }

        // Un seul article « à la une »
        if (! empty($data['mis_en_avant'])) {
            Article::where('mis_en_avant', true)
                ->when($article, fn ($q) => $q->whereKeyNot($article->id))
                ->update(['mis_en_avant' => false]);
        }

        return $data;
    }

    private function slugDisponible(string $base): string
    {
        $slug = $base;
        $i = 2;
        while (Article::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
