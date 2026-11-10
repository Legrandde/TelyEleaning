<?php

namespace App\Http\Controllers;

use App\Models\Correction;
use App\Models\Exercice;
use App\Models\Ressource;
use App\Models\SujetExamen;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProfesseurDashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $user = Auth::user();
        $professeur = $user->professeur;
        $professeurId = $professeur?->id;

        $exercicesQuery = Exercice::query();
        $ressourcesQuery = Ressource::query();

        if ($professeurId) {
            $exercicesQuery->where('professeur_id', $professeurId);
            $ressourcesQuery->where('professeur_id', $professeurId);
        }

        $mesExercicesCount = (clone $exercicesQuery)->count();
        $mesRessourcesCount = (clone $ressourcesQuery)->count();
        $tousSujetsCount = SujetExamen::count();
        $toutesCorrectionsCount = Correction::count();

        $derniersExercices = (clone $exercicesQuery)
            ->with(['matiere:id,nom', 'niveau:id,libelle', 'correction:id,exercice_id'])
            ->latest()
            ->take(5)
            ->get();

        $dernieresRessources = (clone $ressourcesQuery)
            ->with(['matiere:id,nom', 'niveau:id,libelle'])
            ->latest()
            ->take(5)
            ->get();

        $derniersSujets = SujetExamen::with(['matiere:id,nom', 'niveau:id,libelle'])
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'professeur' => $professeur ? $professeur->load('user') : null,
            'counts' => [
                'mes_exercices'  => $mesExercicesCount,
                'mes_ressources' => $mesRessourcesCount,
                'sujets'         => $tousSujetsCount,
                'corrections'    => $toutesCorrectionsCount,
            ],
            'derniers_exercices'   => $derniersExercices,
            'dernieres_ressources' => $dernieresRessources,
            'derniers_sujets'      => $derniersSujets,
        ]);
    }
}
