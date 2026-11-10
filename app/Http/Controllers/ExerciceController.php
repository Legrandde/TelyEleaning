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
        $query = Exercice::with(['matiere', 'niveau', 'categorie', 'professeur.user'])
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
            $query->where(function ($q) use ($request) {
                $q->where('titre', 'like', "%{$request->q}%")
                  ->orWhere('enonce', 'like', "%{$request->q}%");
            });
        }

        $exercices = $query->latest()->paginate(15);

        return response()->json($exercices);
    }

    public function store(StoreExerciceRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('exercices', 'public');
        }

        $exercice = Exercice::create($data);

        return response()->json([
            'message'  => 'Exercice créé avec succès.',
            'exercice' => $exercice->load(['matiere', 'niveau', 'categorie']),
        ], 201);
    }

    public function show(Exercice $exercice): JsonResponse
    {
        $exercice->load([
            'matiere',
            'niveau',
            'categorie',
            'professeur.user',
            'correction',
            'sujetsExamen',
        ]);

        return response()->json($exercice);
    }

    public function update(UpdateExerciceRequest $request, Exercice $exercice): JsonResponse
    {
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

    public function destroy(Exercice $exercice): JsonResponse
    {
        $exercice->delete();

        return response()->json(['message' => 'Exercice supprimé.']);
    }
}
