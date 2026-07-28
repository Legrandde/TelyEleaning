<?php

namespace App\Http\Controllers;
 
use App\Http\Requests\Correction\StoreCorrectionRequest;
use App\Http\Requests\Correction\UpdateCorrectionRequest;
use App\Models\Correction;
use App\Models\Exercice;
use Illuminate\Http\JsonResponse;
 
class CorrectionController extends Controller
{
    // Récupérer la correction d'un exercice
    public function show(Exercice $exercice): JsonResponse
    {
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
        $correction = Correction::create($request->validated());
 
        return response()->json([
            'message'    => 'Correction ajoutée avec succès.',
            'correction' => $correction->load('exercice'),
        ], 201);
    }
 
    public function update(UpdateCorrectionRequest $request, Correction $correction): JsonResponse
    {
        $correction->update($request->validated());
 
        return response()->json([
            'message'    => 'Correction mise à jour.',
            'correction' => $correction,
        ]);
    }
 
    public function destroy(Correction $correction): JsonResponse
    {
        $correction->delete();
 
        return response()->json(['message' => 'Correction supprimée.']);
    }
}
