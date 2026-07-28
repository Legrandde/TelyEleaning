<?php

namespace App\Http\Controllers;
 
use App\Http\Requests\Niveau\StoreNiveauRequest;
use App\Http\Requests\Niveau\UpdateNiveauRequest;
use App\Models\Niveau;
use Illuminate\Http\JsonResponse;
 
class NiveauController extends Controller
{
    public function index(): JsonResponse
    {
        $niveaux = Niveau::orderBy('ordre')->get();
 
        return response()->json($niveaux);
    }
 
    public function store(StoreNiveauRequest $request): JsonResponse
    {
        $niveau = Niveau::create($request->validated());
 
        return response()->json([
            'message' => 'Niveau créé avec succès.',
            'niveau'  => $niveau,
        ], 201);
    }
 
    public function show(Niveau $niveau): JsonResponse
    {
        return response()->json($niveau->load('exercices'));
    }
 
    public function update(UpdateNiveauRequest $request, Niveau $niveau): JsonResponse
    {
        $niveau->update($request->validated());
 
        return response()->json([
            'message' => 'Niveau mis à jour.',
            'niveau'  => $niveau,
        ]);
    }
 
    public function destroy(Niveau $niveau): JsonResponse
    {
        $niveau->delete();
 
        return response()->json(['message' => 'Niveau supprimé.']);
    }
}
 