<?php

namespace App\Http\Controllers;

use App\Models\Categorie;
use App\Models\Correction;
use App\Models\Eleve;
use App\Models\Exercice;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Professeur;
use App\Models\SujetExamen;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $counts = [
            'eleves'      => Eleve::count(),
            'professeurs' => Professeur::count(),
            'exercices'   => Exercice::count(),
            'sujets'      => SujetExamen::count(),
            'matieres'    => Matiere::count(),
            'niveaux'     => Niveau::count(),
            'categories'  => Categorie::count(),
            'corrections' => Correction::count(),
            'users'       => User::count(),
        ];

        $exercicesParMatiere = Matiere::withCount('exercices')
            ->orderByDesc('exercices_count')
            ->take(6)
            ->get(['id', 'nom', 'code']);

        $exercicesParNiveau = Niveau::withCount('exercices')
            ->orderBy('ordre')
            ->get(['id', 'libelle', 'cycle']);

        $exercicesParDifficulte = Exercice::select('difficulte', DB::raw('count(*) as total'))
            ->groupBy('difficulte')
            ->pluck('total', 'difficulte');

        $derniersExercices = Exercice::with(['matiere:id,nom', 'niveau:id,libelle'])
            ->latest()
            ->take(5)
            ->get(['id', 'titre', 'type', 'difficulte', 'matiere_id', 'niveau_id', 'created_at']);

        $derniersUtilisateurs = User::latest()
            ->take(6)
            ->get(['id', 'nom', 'prenom', 'email', 'role', 'avatar', 'created_at']);

        $derniersSujets = SujetExamen::with(['matiere:id,nom', 'niveau:id,libelle'])
            ->latest()
            ->take(5)
            ->get(['id', 'titre', 'type', 'annee', 'session', 'matiere_id', 'niveau_id', 'created_at']);

        return response()->json([
            'counts'                 => $counts,
            'exercices_par_matiere'  => $exercicesParMatiere,
            'exercices_par_niveau'   => $exercicesParNiveau,
            'exercices_par_difficulte' => $exercicesParDifficulte,
            'derniers_exercices'     => $derniersExercices,
            'derniers_utilisateurs'  => $derniersUtilisateurs,
            'derniers_sujets'        => $derniersSujets,
        ]);
    }
}
