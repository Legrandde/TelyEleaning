<?php

namespace App\Http\Controllers;

use App\Http\Requests\Page\SavePageRequest;
use App\Models\Page;
use App\Services\JournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pages construites avec le page builder.
 * Lecture publique des pages publiées, gestion complète réservée aux administrateurs.
 */
class PageController extends Controller
{
    private const CHAMPS_PUBLICS = ['id', 'titre', 'slug', 'meta_description', 'image_og', 'blocs', 'est_accueil', 'publie_le', 'updated_at'];

    // ─── Public ─────────────────────────────────────────────────

    /** Liens de navigation (menu et pied de page). */
    public function navigation(): JsonResponse
    {
        $pages = Page::publiee()
            ->where(fn ($q) => $q->where('dans_menu', true)->orWhere('dans_footer', true))
            ->orderBy('titre')
            ->get(['titre', 'slug', 'dans_menu', 'dans_footer']);

        return response()->json([
            'menu' => $pages->where('dans_menu', true)->values()->map->only(['titre', 'slug']),
            'footer' => $pages->where('dans_footer', true)->values()->map->only(['titre', 'slug']),
        ]);
    }

    public function accueil(): JsonResponse
    {
        $page = Page::publiee()->where('est_accueil', true)->first(self::CHAMPS_PUBLICS);

        if ($page) {
            $page->blocs = $this->blocsVisibles($page->blocs);
        }

        return response()->json(['page' => $page]);
    }

    public function afficher(string $slug): JsonResponse
    {
        $page = Page::publiee()->where('slug', $slug)->firstOrFail(self::CHAMPS_PUBLICS);
        $page->blocs = $this->blocsVisibles($page->blocs);

        return response()->json(['page' => $page]);
    }

    // ─── Administration ────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'statut' => ['nullable', 'in:brouillon,publie'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $pages = Page::query()
            ->with(['modifiePar:id,nom,prenom'])
            ->when($request->query('statut'), fn ($q, $s) => $q->where('statut', $s))
            ->when(trim((string) $request->query('search')), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('titre', 'like', "%{$s}%")->orWhere('slug', 'like', "%{$s}%")))
            ->orderByDesc('est_accueil')
            ->latest('updated_at')
            ->get(['id', 'titre', 'slug', 'statut', 'est_accueil', 'dans_menu', 'dans_footer', 'publie_le', 'modifie_par_id', 'updated_at', 'created_at']);

        return response()->json(['pages' => $pages]);
    }

    public function show(Page $page): JsonResponse
    {
        return response()->json(['page' => $page->load(['auteur:id,nom,prenom', 'modifiePar:id,nom,prenom'])]);
    }

    public function store(SavePageRequest $request): JsonResponse
    {
        $page = DB::transaction(function () use ($request) {
            $data = $this->donnees($request);
            $data['auteur_id'] = $request->user()->id;

            return Page::create($data);
        });

        JournalService::log('page.creee', "Page « {$page->titre} » créée", $page);

        return response()->json(['message' => 'Page créée.', 'page' => $page], 201);
    }

    public function update(SavePageRequest $request, Page $page): JsonResponse
    {
        $etaitPubliee = $page->statut === 'publie';

        DB::transaction(function () use ($request, $page) {
            $page->update($this->donnees($request, $page));
        });

        $publiee = ! $etaitPubliee && $page->statut === 'publie';
        JournalService::log(
            $publiee ? 'page.publiee' : 'page.modifiee',
            "Page « {$page->titre} » ".($publiee ? 'publiée' : 'modifiée'),
            $page
        );

        return response()->json(['message' => 'Page enregistrée.', 'page' => $page->fresh()]);
    }

    public function dupliquer(Request $request, Page $page): JsonResponse
    {
        $copie = $page->replicate(['publie_le']);
        $copie->titre = Str::limit($page->titre.' (copie)', 150, '');
        $copie->slug = $this->slugDisponible($page->slug.'-copie');
        $copie->statut = 'brouillon';
        $copie->est_accueil = false;
        $copie->auteur_id = $request->user()->id;
        $copie->modifie_par_id = $request->user()->id;
        $copie->save();

        JournalService::log('page.dupliquee', "Page « {$page->titre} » dupliquée", $copie);

        return response()->json(['message' => 'Page dupliquée.', 'page' => $copie], 201);
    }

    public function destroy(Page $page): JsonResponse
    {
        // Libère le slug pour une future page
        $page->update(['slug' => $this->slugDisponible($page->slug.'-supprimee'), 'est_accueil' => false]);
        $page->delete();
        JournalService::log('page.supprimee', "Page « {$page->titre} » supprimée", $page);

        return response()->json(['message' => 'Page supprimée.']);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $blocs
     * @return array<int, array<string, mixed>>
     */
    private function blocsVisibles(?array $blocs): array
    {
        return array_values(array_filter($blocs ?? [], fn ($b) => empty($b['masque'])));
    }

    /**
     * @return array<string, mixed>
     */
    private function donnees(SavePageRequest $request, ?Page $page = null): array
    {
        $data = $request->validated();
        $data['modifie_par_id'] = $request->user()->id;

        if (isset($data['blocs'])) {
            $data['blocs'] = array_values(array_map(fn (array $b) => [
                'id' => $b['id'],
                'type' => $b['type'],
                'props' => $b['props'] ?? [],
                'masque' => (bool) ($b['masque'] ?? false),
            ], $data['blocs']));
        }

        $statut = $data['statut'] ?? $page?->statut ?? 'brouillon';
        if ($statut === 'publie' && ! $page?->publie_le) {
            $data['publie_le'] = now();
        }

        // Une seule page d'accueil à la fois
        if (! empty($data['est_accueil'])) {
            Page::where('est_accueil', true)
                ->when($page, fn ($q) => $q->whereKeyNot($page->id))
                ->update(['est_accueil' => false]);
        }

        return $data;
    }

    private function slugDisponible(string $base): string
    {
        $slug = $base;
        $i = 2;
        while (Page::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
