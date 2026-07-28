<?php

namespace App\Http\Controllers;
 
use App\Http\Requests\Professeur\StoreProfesseurRequest;
use App\Models\Professeur;
use Illuminate\Http\JsonResponse;
 
class ProfesseurController extends Controller
{
    public function index(): JsonResponse
    {
        $professeurs = Professeur::with('user')->withCount('exercices')->paginate(20);
 
        return response()->json($professeurs);
    }
 
    public function store(StoreProfesseurRequest $request): JsonResponse
    {
        $professeur = Professeur::create($request->validated());
 
        return response()->json([
            'message'    => 'Professeur créé avec succès.',
            'professeur' => $professeur->load('user'),
        ], 201);
    }
 
    public function show(Professeur $professeur): JsonResponse
    {
        return response()->json(
            $professeur->load(['user', 'exercices.matiere', 'exercices.niveau'])
        );
    }
 
    public function update(StoreProfesseurRequest $request, Professeur $professeur): JsonResponse
    {
        $professeur->update($request->validated());
 
        return response()->json([
            'message'    => 'Professeur mis à jour.',
            'professeur' => $professeur,
        ]);
    }
 
    public function destroy(Professeur $professeur): JsonResponse
    {
        $professeur->delete();
 
        return response()->json(['message' => 'Professeur supprimé.']);
    }
}