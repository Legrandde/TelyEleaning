<?php

namespace App\Http\Controllers;
 
use App\Http\Requests\Categorie\StoreCategorieRequest;
use App\Models\Categorie;
use Illuminate\Http\JsonResponse;
 
class CategorieController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Categorie::withCount('exercices')->get());
    }
 
    public function store(StoreCategorieRequest $request): JsonResponse
    {
        $categorie = Categorie::create($request->validated());
 
        return response()->json([
            'message'   => 'Catégorie créée avec succès.',
            'categorie' => $categorie,
        ], 201);
    }
 
    public function show(Categorie $categorie): JsonResponse
    {
        return response()->json($categorie->load('exercices'));
    }
 
    public function update(StoreCategorieRequest $request, Categorie $categorie): JsonResponse
    {
        $categorie->update($request->validated());
 
        return response()->json([
            'message'   => 'Catégorie mise à jour.',
            'categorie' => $categorie,
        ]);
    }
 
    public function destroy(Categorie $categorie): JsonResponse
    {
        $categorie->delete();
 
        return response()->json(['message' => 'Catégorie supprimée.']);
    }
}
 