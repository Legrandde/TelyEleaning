<?php

namespace App\Http\Controllers;

use App\Http\Requests\Matiere\StoreMatiereRequest;
use App\Http\Requests\Matiere\UpdateMatiereRequest;
use App\Models\Matiere;
use Illuminate\Http\JsonResponse;

class MatiereController extends Controller
{
    public function index(): JsonResponse
    {
        $matieres = Matiere::withCount('exercices')->orderBy('nom')->get();

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

        return response()->json([
            'message' => 'Matière mise à jour.',
            'matiere' => $matiere,
        ]);
    }

    public function destroy(Matiere $matiere): JsonResponse
    {
        $matiere->delete();

        return response()->json(['message' => 'Matière supprimée.']);
    }
}
