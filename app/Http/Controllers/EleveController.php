<?php

namespace App\Http\Controllers;
 
use App\Http\Requests\Eleve\StoreEleveRequest;
use App\Http\Requests\Eleve\UpdateEleveRequest;
use App\Models\Eleve;
use Illuminate\Http\JsonResponse;
 
class EleveController extends Controller
{
    public function index(): JsonResponse
    {
        $eleves = Eleve::with(['user', 'niveau'])->paginate(20);
 
        return response()->json($eleves);
    }
 
    public function store(StoreEleveRequest $request): JsonResponse
    {
        $eleve = Eleve::create($request->validated());
 
        return response()->json([
            'message' => 'Élève créé avec succès.',
            'eleve'   => $eleve->load(['user', 'niveau']),
        ], 201);
    }
 
    public function show(Eleve $eleve): JsonResponse
    {
        return response()->json(
            $eleve->load(['user', 'niveau', 'historiques.exercice'])
        );
    }
 
    public function update(UpdateEleveRequest $request, Eleve $eleve): JsonResponse
    {
        $eleve->update($request->validated());
 
        return response()->json([
            'message' => 'Élève mis à jour.',
            'eleve'   => $eleve->load(['user', 'niveau']),
        ]);
    }
 
    public function destroy(Eleve $eleve): JsonResponse
    {
        $eleve->delete();
 
        return response()->json(['message' => 'Élève supprimé.']);
    }
}