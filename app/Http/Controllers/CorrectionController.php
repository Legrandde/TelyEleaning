<?php

namespace App\Http\Controllers;

use App\Http\Requests\Correction\StoreCorrectionRequest;
use App\Http\Requests\Correction\UpdateCorrectionRequest;
use App\Models\Correction;
use App\Models\Exercice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CorrectionController extends Controller
{
    public function index(): JsonResponse
    {
        $corrections = Correction::with(['exercice.matiere', 'exercice.niveau'])
            ->latest()
            ->paginate(20);

        return response()->json($corrections);
    }

    // Récupérer la correction d'un exercice
    public function show(Exercice $exercice): JsonResponse
    {
        // Un exercice désactivé (brouillon) et son corrigé ne sont visibles que par un admin ou son auteur
        if (! $exercice->est_actif) {
            $user = auth()->user();
            $estAuteur = $user?->professeur && $exercice->professeur_id === $user->professeur->id;
            if (! $user?->isAdministrateur() && ! $estAuteur) {
                return response()->json(['message' => 'Ressource introuvable.'], 404);
            }
        }

        $correction = $exercice->correction;

        if (!$correction) {
            return response()->json([
                'message' => 'Aucune correction disponible pour cet exercice.',
            ], 404);
        }

        return response()->json($correction);
    }

    public function store(StoreCorrectionRequest $request): JsonResponse
    {
        $user = $request->user();
        $exercice = Exercice::findOrFail($request->validated('exercice_id'));

        if (!$user?->isAdministrateur() && (!$user?->professeur || $exercice->professeur_id !== $user->professeur->id)) {
            return response()->json(['message' => 'Action non autorisée. Vous ne pouvez ajouter un corrigé qu\'à vos propres exercices.'], 403);
        }

        $correction = Correction::create($request->validated());

        return response()->json([
            'message'    => 'Correction ajoutée avec succès.',
            'correction' => $correction->load('exercice'),
        ], 201);
    }

    public function update(UpdateCorrectionRequest $request, Correction $correction): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->isAdministrateur() && (!$user->professeur || $correction->exercice?->professeur_id !== $user->professeur->id)) {
            return response()->json(['message' => 'Action non autorisée. Vous ne pouvez modifier que les corrigés de vos propres exercices.'], 403);
        }

        $correction->update($request->validated());

        return response()->json([
            'message'    => 'Correction mise à jour.',
            'correction' => $correction,
        ]);
    }

    public function destroy(Request $request, Correction $correction): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->isAdministrateur() && (!$user->professeur || $correction->exercice?->professeur_id !== $user->professeur->id)) {
            return response()->json(['message' => 'Action non autorisée. Vous ne pouvez supprimer que les corrigés de vos propres exercices.'], 403);
        }

        $correction->delete();

        return response()->json(['message' => 'Correction supprimée.']);
    }
}
