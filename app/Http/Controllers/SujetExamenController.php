<?php

namespace App\Http\Controllers;

use App\Http\Requests\SujetExamen\StoreSujetExamenRequest;
use App\Http\Requests\SujetExamen\UpdateSujetExamenRequest;
use App\Models\SujetExamen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SujetExamenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SujetExamen::with(['matiere', 'niveau'])
            ->withCount('exercices');

        if ($request->filled('matiere_id')) {
            $query->where('matiere_id', $request->matiere_id);
        }

        if ($request->filled('niveau_id')) {
            $query->where('niveau_id', $request->niveau_id);
        }

        if ($request->filled('annee')) {
            $query->parAnnee((int) $request->annee);
        }

        if ($request->filled('session')) {
            $query->where('session', $request->session);
        }

        if ($request->filled('type')) {
            $query->parType($request->type);
        }

        if ($request->filled('q')) {
            $safeQ = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $request->q);
            $query->where('titre', 'like', "%{$safeQ}%");
        }

        $sujets = $query->latest('annee')->paginate(15);

        if (!auth('sanctum')->check()) {
            $sujets->getCollection()->transform(function (SujetExamen $s) {
                $s->ennonce = mb_substr($s->ennonce ?? '', 0, 180);
                $s->fichier_pdf = null;
                return $s;
            });
        }

        return response()->json($sujets);
    }

    public function store(StoreSujetExamenRequest $request): JsonResponse
    {
        $sujet = SujetExamen::create($request->validated());

        if ($request->filled('exercices')) {
            $exercicesAvecOrdre = [];
            foreach ($request->exercices as $ordre => $exerciceId) {
                $exercicesAvecOrdre[$exerciceId] = ['ordre' => $ordre + 1];
            }
            $sujet->exercices()->sync($exercicesAvecOrdre);
        }

        return response()->json([
            'message' => 'Sujet d\'examen créé avec succès.',
            'sujet'   => $sujet->load(['matiere', 'niveau', 'exercices']),
        ], 201);
    }

    public function show(Request $request, SujetExamen $sujet): JsonResponse
    {
        $isAuthenticated = auth('sanctum')->check();

        if ($isAuthenticated) {
            $sujet->load([
                'matiere',
                'niveau',
                'exercices.correction',
                'exercices.categorie',
            ]);
        } else {
            $sujet->load([
                'matiere',
                'niveau',
                'exercices:id,titre',
            ]);
            // Masquer l'épreuve complète, le PDF et les corrigés pour les visiteurs non connectés
            $sujet->ennonce = mb_substr($sujet->ennonce ?? '', 0, 180);
            $sujet->fichier_pdf = null;
        }

        return response()->json($sujet);
    }

    public function update(UpdateSujetExamenRequest $request, SujetExamen $sujetExamen): JsonResponse
    {
        $sujetExamen->update($request->validated());

        return response()->json([
            'message' => 'Sujet mis à jour.',
            'sujet'   => $sujetExamen,
        ]);
    }

    public function destroy(Request $request, SujetExamen $sujetExamen): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->isAdministrateur()) {
            return response()->json(['message' => 'Action non autorisée. Seul un administrateur peut supprimer un sujet d\'examen officiel.'], 403);
        }

        $sujetExamen->delete();

        return response()->json(['message' => 'Sujet supprimé.']);
    }
}
