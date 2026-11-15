<?php

namespace App\Http\Controllers;

use App\Models\Eleve;
use App\Models\Exercice;
use App\Models\Historique;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Espace personnel de l'élève : tableau de bord, favoris, activité, onboarding.
 */
class EspaceEleveController extends Controller
{
    private const CHAMPS_CARTE = ['id', 'titre', 'type', 'difficulte', 'image', 'matiere_id', 'niveau_id', 'categorie_id', 'created_at'];

    public function tableauDeBord(Request $request): JsonResponse
    {
        $eleve = $this->eleve($request);
        $base = Historique::where('eleve_id', $eleve->id);

        $consultes = (clone $base)->where('action', 'consulte')->count();
        $correctionsVues = (clone $base)->where('action', 'correction_vue')->count();
        $favoris = (clone $base)->where('action', 'favori')->count();

        // Activité des 14 derniers jours (dernière interaction par exercice et par action)
        $depuis = now()->subDays(13)->startOfDay();
        $parJour = (clone $base)
            ->whereIn('action', ['consulte', 'correction_vue'])
            ->where('date_consultation', '>=', $depuis)
            ->get(['date_consultation'])
            ->groupBy(fn ($h) => $h->date_consultation->toDateString())
            ->map->count();

        $activite = collect(range(13, 0))->map(function (int $i) use ($parJour) {
            $jour = now()->subDays($i)->toDateString();

            return ['date' => $jour, 'total' => (int) ($parJour[$jour] ?? 0)];
        })->values();

        $matieres = Historique::query()
            ->join('exercices', 'exercices.id', '=', 'historiques.exercice_id')
            ->join('matieres', 'matieres.id', '=', 'exercices.matiere_id')
            ->where('historiques.eleve_id', $eleve->id)
            ->whereIn('historiques.action', ['consulte', 'correction_vue'])
            ->groupBy('matieres.id', 'matieres.nom')
            ->orderByDesc('total')
            ->limit(6)
            ->get(['matieres.id', 'matieres.nom', DB::raw('COUNT(*) as total')]);

        $recents = (clone $base)
            ->whereIn('action', ['consulte', 'correction_vue'])
            ->whereHas('exercice', fn ($q) => $q->actif())
            ->with(['exercice' => fn ($q) => $q->select(self::CHAMPS_CARTE)->with(['matiere:id,nom', 'niveau:id,libelle'])])
            ->latest('date_consultation')
            ->limit(6)
            ->get()
            ->filter(fn ($h) => $h->exercice !== null)
            ->unique('exercice_id')
            ->values();

        return response()->json([
            'eleve' => $eleve->load('niveau:id,libelle,cycle'),
            'onboarding_fait' => $request->user()->onboarding_termine_le !== null,
            'stats' => [
                'exercices_consultes' => $consultes,
                'corrections_vues' => $correctionsVues,
                'favoris' => $favoris,
                'serie_jours' => $this->serie($eleve),
            ],
            'activite' => $activite,
            'matieres' => $matieres,
            'recents' => $recents,
            'recommandations' => $this->recommandations($eleve),
        ]);
    }

    public function favoris(Request $request): JsonResponse
    {
        $eleve = $this->eleve($request);

        $favoris = Historique::where('eleve_id', $eleve->id)
            ->where('action', 'favori')
            ->whereHas('exercice', fn ($q) => $q->actif())
            ->with(['exercice' => fn ($q) => $q->select(self::CHAMPS_CARTE)->with(['matiere:id,nom', 'niveau:id,libelle'])])
            ->latest('date_consultation')
            ->paginate(24);

        return response()->json($favoris);
    }

    /** Identifiants des exercices favoris, pour l'affichage des cœurs sur les cartes. */
    public function favorisIds(Request $request): JsonResponse
    {
        $eleve = $this->eleve($request);

        return response()->json([
            'ids' => Historique::where('eleve_id', $eleve->id)->where('action', 'favori')->pluck('exercice_id'),
        ]);
    }

    public function basculerFavori(Request $request, Exercice $exercice): JsonResponse
    {
        $eleve = $this->eleve($request);

        if (! $exercice->est_actif) {
            return response()->json(['message' => 'Ressource introuvable.'], 404);
        }

        $existant = Historique::where([
            'eleve_id' => $eleve->id,
            'exercice_id' => $exercice->id,
            'action' => 'favori',
        ])->first();

        if ($existant) {
            $existant->delete();

            return response()->json(['favori' => false, 'message' => 'Retiré de vos favoris.']);
        }

        Historique::create([
            'eleve_id' => $eleve->id,
            'exercice_id' => $exercice->id,
            'action' => 'favori',
            'date_consultation' => now(),
        ]);

        return response()->json(['favori' => true, 'message' => 'Ajouté à vos favoris.'], 201);
    }

    public function historique(Request $request): JsonResponse
    {
        $request->validate(['action' => ['nullable', 'in:consulte,correction_vue']]);
        $eleve = $this->eleve($request);

        $historique = Historique::where('eleve_id', $eleve->id)
            ->whereIn('action', $request->filled('action') ? [$request->query('action')] : ['consulte', 'correction_vue'])
            ->whereHas('exercice', fn ($q) => $q->actif())
            ->with(['exercice' => fn ($q) => $q->select(self::CHAMPS_CARTE)->with(['matiere:id,nom', 'niveau:id,libelle'])])
            ->latest('date_consultation')
            ->paginate(20);

        return response()->json($historique);
    }

    /** Enregistre une interaction (consultation d'un corrigé, etc.). */
    public function enregistrerActivite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'exercice_id' => ['required', 'integer', 'exists:exercices,id,est_actif,1,deleted_at,NULL'],
            'action' => ['required', 'in:consulte,correction_vue'],
        ]);

        $eleve = $this->eleve($request);
        self::tracer($eleve, (int) $data['exercice_id'], $data['action']);

        return response()->json(['message' => 'Activité enregistrée.'], 201);
    }

    public function onboarding(Request $request): JsonResponse
    {
        $data = $request->validate([
            'niveau_id' => ['required', 'integer', 'exists:niveaux,id'],
            'classe' => ['nullable', 'string', 'max:50'],
            'matieres' => ['nullable', 'array', 'max:20'],
            'matieres.*' => ['integer', 'exists:matieres,id'],
            'objectif' => ['nullable', 'string', 'in:examen,remise_a_niveau,approfondir,autre'],
        ]);

        $eleve = $this->eleve($request);
        $eleve->update(array_filter([
            'niveau_id' => $data['niveau_id'],
            'classe' => $data['classe'] ?? null,
            'matieres_preferees' => $data['matieres'] ?? null,
            'objectif' => $data['objectif'] ?? null,
        ], fn ($v) => $v !== null));

        $request->user()->update(['onboarding_termine_le' => now()]);

        return response()->json([
            'message' => 'Votre espace est prêt !',
            'user' => $request->user()->fresh()->load(['eleve.niveau', 'professeur']),
        ]);
    }

    /**
     * Enregistre (ou rafraîchit) une interaction élève/exercice.
     */
    public static function tracer(Eleve $eleve, int $exerciceId, string $action): void
    {
        Historique::updateOrCreate(
            ['eleve_id' => $eleve->id, 'exercice_id' => $exerciceId, 'action' => $action],
            ['date_consultation' => now()]
        );
    }

    private function eleve(Request $request): Eleve
    {
        $user = $request->user();
        abort_unless($user?->isEleve(), 403, 'Accès réservé aux élèves.');

        return Eleve::firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * Exercices actifs non encore consultés, priorisant le niveau et les matières préférées de l'élève.
     */
    private function recommandations(Eleve $eleve): Collection
    {
        $dejaVus = Historique::where('eleve_id', $eleve->id)->pluck('exercice_id')->unique();
        $matieres = $eleve->matieres_preferees ?: Historique::query()
            ->join('exercices', 'exercices.id', '=', 'historiques.exercice_id')
            ->where('historiques.eleve_id', $eleve->id)
            ->groupBy('exercices.matiere_id')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(3)
            ->pluck('exercices.matiere_id')
            ->all();

        $requete = fn () => Exercice::apercuPublic()
            ->with(['matiere:id,nom', 'niveau:id,libelle'])
            ->whereNotIn('id', $dejaVus);

        $resultats = $requete()
            ->when($eleve->niveau_id, fn ($q) => $q->where('niveau_id', $eleve->niveau_id))
            ->when(! empty($matieres), fn ($q) => $q->whereIn('matiere_id', $matieres))
            ->latest()
            ->limit(6)
            ->get();

        // Complète avec les nouveautés du niveau puis de la plateforme
        if ($resultats->count() < 6 && $eleve->niveau_id) {
            $resultats = $resultats->concat($requete()
                ->where('niveau_id', $eleve->niveau_id)
                ->whereNotIn('id', $resultats->pluck('id'))
                ->latest()->limit(6 - $resultats->count())->get());
        }
        if ($resultats->count() < 6) {
            $resultats = $resultats->concat($requete()
                ->whereNotIn('id', $resultats->pluck('id'))
                ->latest()->limit(6 - $resultats->count())->get());
        }

        return $resultats->values();
    }

    /** Nombre de jours consécutifs (jusqu'à aujourd'hui ou hier) avec au moins une activité. */
    private function serie(Eleve $eleve): int
    {
        $jours = Historique::where('eleve_id', $eleve->id)
            ->where('date_consultation', '>=', now()->subDays(60))
            ->pluck('date_consultation')
            ->map(fn (Carbon $d) => $d->toDateString())
            ->unique()
            ->flip();

        $jour = now();
        if (! isset($jours[$jour->toDateString()])) {
            $jour = $jour->subDay();
        }

        $serie = 0;
        while (isset($jours[$jour->toDateString()])) {
            $serie++;
            $jour = $jour->subDay();
        }

        return $serie;
    }
}
