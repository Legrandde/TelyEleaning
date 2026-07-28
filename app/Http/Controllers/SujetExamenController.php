<?php

namespace App\Http\Controllers;
 
use App\Http\Requests\SujetExamen\StoreSujetExamenRequest;
use App\Http\Requests\SujetExamen\UpdateSujetExamenRequest;
use App\Models\SujetExamen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
 
class SujetExamenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SujetExamen::with(['matiere', 'niveau'])
            ->withCount('exercices');
 
        if ($request->filled('matiere_id')) {
            $query->where('matiere_id', $request->matiere_id);
        }
 
        if ($request->filled('niveau_id')) {
            $query->where('niveau_id', $request->niveau_id);
        }
 
        if ($request->filled('annee')) {
            $query->parAnnee($request->annee);
        }
 
        if ($request->filled('type')) {
            $query->parType($request->type);
        }
 
        $sujets = $query->orderByDesc('annee')->paginate(15);
 
        return response()->json($sujets);
    }
 
    public function store(StoreSujetExamenRequest $request): JsonResponse
    {
        $sujet = SujetExamen::create($request->safe()->except('exercices'));
 
        // Attacher les exercices avec leur ordre dans le sujet
        if ($request->filled('exercices')) {
            $pivot = collect($request->exercices)->mapWithKeys(fn($ex) => [
                $ex['id'] => ['ordre' => $ex['ordre']]
            ])->toArray();
 
            $sujet->exercices()->sync($pivot);
        }
 
        return response()->json([
            'message' => 'Sujet d\'examen créé avec succès.',
            'sujet'   => $sujet->load(['matiere', 'niveau', 'exercices']),
        ], 201);
    }
 
    public function show(SujetExamen $sujetExamen): JsonResponse
    {
        $sujetExamen->load([
            'matiere',
            'niveau',
            'exercices.correction',
            'exercices.categorie',
        ]);
 
        return response()->json($sujetExamen);
    }
 
    public function update(UpdateSujetExamenRequest $request, SujetExamen $sujetExamen): JsonResponse
    {
        $sujetExamen->update($request->validated());
 
        return response()->json([
            'message' => 'Sujet mis à jour.',
            'sujet'   => $sujetExamen,
        ]);
    }
 
    public function destroy(SujetExamen $sujetExamen): JsonResponse
    {
        $sujetExamen->delete();
 
        return response()->json(['message' => 'Sujet supprimé.']);
    }
}
 