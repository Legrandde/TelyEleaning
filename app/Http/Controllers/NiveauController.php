<?php

namespace App\Http\Controllers;

use App\Http\Requests\Niveau\StoreNiveauRequest;
use App\Http\Requests\Niveau\UpdateNiveauRequest;
use App\Models\Niveau;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class NiveauController extends Controller
{
    public function index(): JsonResponse
    {
        $niveaux = Cache::remember('niveaux_index', 3600, function () {
            return Niveau::orderBy('ordre')->get();
        });

        return response()->json($niveaux);
    }

    public function store(StoreNiveauRequest $request): JsonResponse
    {
        $niveau = Niveau::create($request->validated());
        Cache::forget('niveaux_index');

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
        Cache::forget('niveaux_index');

        return response()->json([
            'message' => 'Niveau mis à jour.',
            'niveau'  => $niveau,
        ]);
    }

    public function destroy(Niveau $niveau): JsonResponse
    {
        $niveau->delete();
        Cache::forget('niveaux_index');

        return response()->json(['message' => 'Niveau supprimé.']);
    }
}
