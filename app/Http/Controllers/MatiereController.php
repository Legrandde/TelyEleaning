<?php

namespace App\Http\Controllers;

use App\Http\Requests\Matiere\StoreMatiereRequest;
use App\Http\Requests\Matiere\UpdateMatiereRequest;
use App\Models\Matiere;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class MatiereController extends Controller
{
    public function index(): JsonResponse
    {
        $matieres = Cache::remember('matieres_index', 3600, function () {
            return Matiere::withCount('exercices')->orderBy('nom')->get();
        });

        return response()->json($matieres);
    }

    public function store(StoreMatiereRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('matieres', 'public');
            $data['image'] = $path;
        }

        $matiere = Matiere::create($data);
        Cache::forget('matieres_index');

        return response()->json([
            'message' => 'Matière créée avec succès.',
            'matiere' => $matiere,
        ], 201);
    }

    public function show(Matiere $matiere): JsonResponse
    {
        return response()->json($matiere->load('exercices'));
    }

    public function update(UpdateMatiereRequest $request, Matiere $matiere): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('matieres', 'public');
            $data['image'] = $path;
        }

        $matiere->update($data);
        Cache::forget('matieres_index');

        return response()->json([
            'message' => 'Matière mise à jour.',
            'matiere' => $matiere,
        ]);
    }

    public function destroy(Matiere $matiere): JsonResponse
    {
        $matiere->delete();
        Cache::forget('matieres_index');

        return response()->json(['message' => 'Matière supprimée.']);
    }
}
