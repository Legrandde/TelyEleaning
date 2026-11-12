<?php

namespace App\Http\Controllers;

use App\Http\Requests\Exercice\StoreExerciceRequest;
use App\Http\Requests\Exercice\UpdateExerciceRequest;
use App\Models\Exercice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExerciceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Exercice::with([
            'matiere:id,nom,code',
            'niveau:id,libelle,cycle,ordre',
            'categorie:id,nom',
            'professeur:id,user_id',
            'professeur.user:id,nom,prenom,avatar,email'
        ])
        ->select('id', 'titre', 'enonce', 'type', 'difficulte', 'est_actif', 'image', 'matiere_id', 'niveau_id', 'categorie_id', 'professeur_id', 'created_at')
        ->actif();

        // ─── Filtres ───
        if ($request->filled('niveau_id')) {
            $query->parNiveau($request->niveau_id);
        }

        if ($request->filled('matiere_id')) {
            $query->parMatiere($request->matiere_id);
        }

        if ($request->filled('difficulte')) {
            $query->parDifficulte($request->difficulte);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('avec_correction')) {
            $query->avecCorrection();
        }

        // ─── Recherche par mot-clé ───
        if ($request->filled('q')) {
            $safeQ = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $request->q);
            $query->where(function ($q) use ($safeQ) {
                $q->where('titre', 'like', "%{$safeQ}%")
                  ->orWhere('enonce', 'like', "%{$safeQ}%");
            });
        }

        $exercices = $query->latest()->paginate(15);

        // Si l'utilisateur n'est pas connecté, ne renvoyer qu'un aperçu de l'énoncé (protection réseau)
        if (!auth('sanctum')->check()) {
            $exercices->getCollection()->transform(function (Exercice $ex) {
                $ex->enonce = mb_substr($ex->enonce ?? '', 0, 180);
                return $ex;
            });
        }

        return response()->json($exercices);
    }

    public function store(StoreExerciceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        // Si l'utilisateur est un professeur, forcer son propre ID
        if ($user && $user->isProfesseur() && $user->professeur) {
            $data['professeur_id'] = $user->professeur->id;
        }

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('exercices', 'public');
        }

        $exercice = Exercice::create($data);

        return response()->json([
            'message'  => 'Exercice créé avec succès.',
            'exercice' => $exercice->load(['matiere', 'niveau', 'categorie']),
        ], 201);
    }

    public function show(Request $request, Exercice $exercice): JsonResponse
    {
        $isAuthenticated = auth('sanctum')->check();

        if ($isAuthenticated) {
            $exercice->load([
                'matiere',
                'niveau',
                'categorie',
                'professeur.user',
                'correction',
                'sujetsExamen',
            ]);
        } else {
            $exercice->load([
                'matiere',
                'niveau',
                'categorie',
                'professeur.user',
            ]);
            // Masquer le corrigé et tronquer l'énoncé dans la réponse JSON pour les visiteurs non connectés
            $exercice->setRelation('correction', null);
            $exercice->enonce = mb_substr($exercice->enonce ?? '', 0, 180);
        }

        return response()->json($exercice);
    }

    public function update(UpdateExerciceRequest $request, Exercice $exercice): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->isAdministrateur() && (!$user->professeur || $exercice->professeur_id !== $user->professeur->id)) {
            return response()->json(['message' => 'Action non autorisée. Vous ne pouvez modifier que vos propres exercices.'], 403);
        }

        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('exercices', 'public');
        }

        $exercice->update($data);

        return response()->json([
            'message'  => 'Exercice mis à jour.',
            'exercice' => $exercice->load(['matiere', 'niveau']),
        ]);
    }

    public function destroy(Request $request, Exercice $exercice): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->isAdministrateur() && (!$user->professeur || $exercice->professeur_id !== $user->professeur->id)) {
            return response()->json(['message' => 'Action non autorisée. Vous ne pouvez supprimer que vos propres exercices.'], 403);
        }

        $exercice->delete();

        return response()->json(['message' => 'Exercice supprimé.']);
    }
}
