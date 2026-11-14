<?php

namespace App\Http\Controllers;

use App\Http\Requests\Exercice\StoreExerciceRequest;
use App\Http\Requests\Exercice\UpdateExerciceRequest;
use App\Models\AppParametre;
use App\Models\Exercice;
use App\Models\User;
use App\Services\CourrielService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExerciceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Exercice::with([
            'matiere:id,nom,code',
            'niveau:id,libelle,cycle,ordre',
            'categorie:id,nom',
            'professeur:id,user_id',
            'professeur.user:id,nom,prenom,avatar'
        ])
        ->select('id', 'titre', 'enonce', 'type', 'difficulte', 'est_actif', 'image', 'matiere_id', 'niveau_id', 'categorie_id', 'professeur_id', 'created_at')
        ->actif();

        // ─── Filtres ───
        if ($request->filled('niveau_id')) {
            $query->parNiveau((int) $request->niveau_id);
        }

        if ($request->filled('matiere_id')) {
            $query->parMatiere((int) $request->matiere_id);
        }

        if ($request->filled('difficulte')) {
            $query->parDifficulte((string) $request->difficulte);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('avec_correction')) {
            $query->avecCorrection();
        }

        // ─── Recherche par mot-clé ───
        if ($request->filled('q')) {
            $safeQ = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $request->q);
            $query->where(function ($q) use ($safeQ) {
                $q->where('titre', 'like', "%{$safeQ}%")
                  ->orWhere('enonce', 'like', "%{$safeQ}%");
            });
        }

        $exercices = $query->latest()->paginate(15);

        // Si l'utilisateur n'est pas connecté, ne renvoyer qu'un aperçu de l'énoncé (protection réseau)
        if (!auth('sanctum')->check()) {
            $exercices->getCollection()->transform(function (Exercice $ex) {
                $ex->enonce = mb_substr($ex->enonce ?? '', 0, 180);
                return $ex;
            });
        }

        return response()->json($exercices);
    }

    public function store(StoreExerciceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        // Si l'utilisateur est un professeur, forcer son propre ID
        if ($user && $user->isProfesseur() && $user->professeur) {
            $data['professeur_id'] = $user->professeur->id;
        }

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('exercices', 'public');
        }

        $exercice = Exercice::create($data);
        $exercice->load(['matiere', 'niveau', 'categorie']);

        // Notification par e-mail si le paramètre notif_email_exercice est activé
        $notifExercice = AppParametre::where('cle', 'notif_email_exercice')->value('valeur') ?? '0';
        if ($notifExercice === '1') {
            $front = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');
            $admins = User::where('role', 'administrateur')->pluck('email');
            foreach ($admins as $adminEmail) {
                CourrielService::envoyer(
                    $adminEmail,
                    "Nouvel exercice publié : {$exercice->titre}",
                    "Bonjour,\n\nUn nouvel exercice vient d’être publié sur Tely Elearning :\n• Titre : {$exercice->titre}\n• Matière : " . ($exercice->matiere?->nom ?? '—') . "\n• Niveau : " . ($exercice->niveau?->libelle ?? '—') . "\n• Difficulté : " . ucfirst($exercice->difficulte ?? 'moyen'),
                    ['Voir l’exercice', "{$front}/ennoncer?id={$exercice->id}"]
                );
            }
        }

        return response()->json([
            'message'  => 'Exercice créé avec succès.',
            'exercice' => $exercice,
        ], 201);
    }

    public function show(Request $request, Exercice $exercice): JsonResponse
    {
        $user = auth('sanctum')->user();

        // Un exercice désactivé n'est visible que par un administrateur ou son auteur
        if (!$exercice->est_actif) {
            $estAuteur = $user?->professeur && $exercice->professeur_id === $user->professeur->id;
            if (!$user?->isAdministrateur() && !$estAuteur) {
                return response()->json(['message' => 'Ressource introuvable.'], 404);
            }
        }

        // Auteur : uniquement les champs publics (pas d'e-mail, google_id, rôle…)
        $relations = [
            'matiere',
            'niveau',
            'categorie',
            'professeur:id,user_id',
            'professeur.user:id,nom,prenom,avatar',
        ];

        if ($user) {
            $exercice->load([...$relations, 'correction', 'sujetsExamen']);
        } else {
            $exercice->load($relations);
            // Masquer le corrigé, le PDF complet et tronquer l'énoncé pour les visiteurs non connectés
            $exercice->setRelation('correction', null);
            $exercice->enonce = mb_substr($exercice->enonce ?? '', 0, 180);
            $exercice->fichier_pdf = null;
        }

        return response()->json($exercice);
    }

    public function update(UpdateExerciceRequest $request, Exercice $exercice): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->isAdministrateur() && (!$user->professeur || $exercice->professeur_id !== $user->professeur->id)) {
            return response()->json(['message' => 'Action non autorisée. Vous ne pouvez modifier que vos propres exercices.'], 403);
        }

        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('exercices', 'public');
        }

        $exercice->update($data);

        return response()->json([
            'message'  => 'Exercice mis à jour.',
            'exercice' => $exercice->load(['matiere', 'niveau']),
        ]);
    }

    public function destroy(Request $request, Exercice $exercice): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->isAdministrateur() && (!$user->professeur || $exercice->professeur_id !== $user->professeur->id)) {
            return response()->json(['message' => 'Action non autorisée. Vous ne pouvez supprimer que vos propres exercices.'], 403);
        }

        $exercice->delete();

        return response()->json(['message' => 'Exercice supprimé.']);
    }
}
