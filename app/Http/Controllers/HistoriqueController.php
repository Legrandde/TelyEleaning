<?php

namespace App\Http\Controllers;
 
use App\Http\Requests\Historique\StoreHistoriqueRequest;
use App\Models\Historique;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
 
class HistoriqueController extends Controller
{
    // Historique de l'élève connecté
    public function index(Request $request): JsonResponse
    {
        $eleve = Auth::user()->eleve;
 
        if (!$eleve) {
            return response()->json(['message' => 'Accès réservé aux élèves.'], 403);
        }
 
        $historiques = Historique::where('eleve_id', $eleve->id)
            ->with(['exercice.matiere', 'exercice.niveau'])
            ->when($request->filled('action'), fn($q) => $q->where('action', $request->action))
            ->recents($request->get('jours', 30))
            ->latest('date_consultation')
            ->paginate(20);
 
        return response()->json($historiques);
    }
 
    public function store(StoreHistoriqueRequest $request): JsonResponse
    {
        // Évite les doublons : met à jour si déjà consulté aujourd'hui
        $historique = Historique::updateOrCreate(
            [
                'eleve_id'    => $request->eleve_id,
                'exercice_id' => $request->exercice_id,
                'action'      => $request->action,
            ],
            ['date_consultation' => now()]
        );
 
        return response()->json([
            'message'    => 'Historique enregistré.',
            'historique' => $historique,
        ], 201);
    }
 
    public function destroy(Historique $historique): JsonResponse
    {
        $historique->delete();
 
        return response()->json(['message' => 'Entrée supprimée de l\'historique.']);
    }
}
 