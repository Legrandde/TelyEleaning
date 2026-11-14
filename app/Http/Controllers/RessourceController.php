<?php

namespace App\Http\Controllers;

use App\Models\Ressource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class RessourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Ressource::with(['matiere', 'niveau', 'professeur:id,user_id', 'professeur.user:id,nom,prenom,avatar']);

        if ($request->filled('matiere_id')) {
            $query->where('matiere_id', $request->matiere_id);
        }

        if ($request->filled('niveau_id')) {
            $query->where('niveau_id', $request->niveau_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('professeur_id')) {
            $query->where('professeur_id', $request->professeur_id);
        }

        if ($request->filled('q')) {
            $q = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $request->q);
            $query->where(function ($sub) use ($q) {
                $sub->where('titre', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        $ressources = $query->latest()->paginate(20);

        return response()->json($ressources);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'titre'         => 'required|string|max:255',
            'description'   => 'nullable|string',
            'type'          => 'required|string|in:cours,resume,fiche,td,autre',
            'matiere_id'    => 'required|exists:matieres,id',
            'niveau_id'     => 'required|exists:niveaux,id',
            'professeur_id' => 'nullable|exists:professeurs,id',
            'fichier'       => 'nullable|file|mimes:pdf,doc,docx,odt,rtf,ppt,pptx,xls,xlsx,zip|max:30720', // max 30MB
            // Lien externe uniquement en HTTPS : un chemin interne arbitraire n'est jamais accepté
            'fichier_path'  => 'nullable|string|max:500|url:https',
        ]);

        $user = Auth::user();
        $professeurId = null;
        if ($user?->isAdministrateur() && isset($validated['professeur_id'])) {
            $professeurId = $validated['professeur_id'];
        } elseif ($user?->professeur) {
            $professeurId = $user->professeur->id;
        }

        $fichierPath = $validated['fichier_path'] ?? null;
        $fichierNom = null;
        $format = null;
        $taille = null;

        if ($request->hasFile('fichier')) {
            $file = $request->file('fichier');
            $fichierNom = $file->getClientOriginalName();
            $format = strtolower($file->getClientOriginalExtension());
            $taille = $file->getSize();
            $fichierPath = $file->store('ressources', 'public');
        }

        if (!$fichierPath) {
            return response()->json(['message' => 'Un fichier ou une URL de fichier est requis.'], 422);
        }

        $ressource = Ressource::create([
            'titre'         => $validated['titre'],
            'description'   => $validated['description'] ?? null,
            'type'          => $validated['type'],
            'fichier_path'  => $fichierPath,
            'fichier_nom'   => $fichierNom ?? basename($fichierPath),
            'format'        => $format ?? pathinfo($fichierPath, PATHINFO_EXTENSION),
            'taille'        => $taille,
            'matiere_id'    => $validated['matiere_id'],
            'niveau_id'     => $validated['niveau_id'],
            'professeur_id' => $professeurId,
            'est_public'    => true,
        ]);

        return response()->json([
            'message'   => 'Ressource pédagogique publiée avec succès.',
            'ressource' => $ressource->load(['matiere', 'niveau', 'professeur.user']),
        ], 201);
    }

    public function show(Ressource $ressource): JsonResponse
    {
        return response()->json($ressource->load(['matiere', 'niveau', 'professeur:id,user_id', 'professeur.user:id,nom,prenom,avatar']));
    }

    public function update(Request $request, Ressource $ressource): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->isAdministrateur() && (!$user->professeur || $ressource->professeur_id !== $user->professeur->id)) {
            return response()->json(['message' => 'Action non autorisée. Vous ne pouvez modifier que vos propres ressources.'], 403);
        }

        $validated = $request->validate([
            'titre'        => 'sometimes|string|max:255',
            'description'  => 'nullable|string',
            'type'         => 'sometimes|string|in:cours,resume,fiche,td,autre',
            'matiere_id'   => 'sometimes|exists:matieres,id',
            'niveau_id'    => 'sometimes|exists:niveaux,id',
            'fichier'      => 'nullable|file|mimes:pdf,doc,docx,odt,rtf,ppt,pptx,xls,xlsx,zip|max:30720',
            'fichier_path' => 'nullable|string|max:500|url:https',
        ]);

        // Champ vide, ou URL actuelle renvoyée telle quelle par le formulaire : on conserve le fichier actuel
        if (
            empty($validated['fichier_path'])
            || $validated['fichier_path'] === $ressource->fichier_url
            || $validated['fichier_path'] === $ressource->fichier_path
        ) {
            unset($validated['fichier_path']);
        }

        if ($request->hasFile('fichier')) {
            $file = $request->file('fichier');
            $validated['fichier_nom'] = $file->getClientOriginalName();
            $validated['format'] = strtolower($file->getClientOriginalExtension());
            $validated['taille'] = $file->getSize();
            $validated['fichier_path'] = $file->store('ressources', 'public');
        } elseif (isset($validated['fichier_path'])) {
            $validated['fichier_nom'] = basename(parse_url($validated['fichier_path'], PHP_URL_PATH) ?: $validated['fichier_path']);
            $validated['format'] = strtolower(pathinfo($validated['fichier_nom'], PATHINFO_EXTENSION));
            $validated['taille'] = null;
        }

        $ancienChemin = $ressource->fichier_path;
        $ressource->update($validated);

        if ($ancienChemin !== $ressource->fichier_path) {
            $this->supprimerFichierStocke($ancienChemin, $ressource->id);
        }

        return response()->json([
            'message'   => 'Ressource mise à jour.',
            'ressource' => $ressource->load(['matiere', 'niveau', 'professeur.user']),
        ]);
    }

    public function destroy(Request $request, Ressource $ressource): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->isAdministrateur() && (!$user->professeur || $ressource->professeur_id !== $user->professeur->id)) {
            return response()->json(['message' => 'Action non autorisée. Vous ne pouvez supprimer que vos propres ressources.'], 403);
        }

        $chemin = $ressource->fichier_path;
        $ressource->delete();
        $this->supprimerFichierStocke($chemin, $ressource->id);

        return response()->json(['message' => 'Ressource supprimée avec succès.']);
    }

    /**
     * Supprime un fichier uploadé de ressource, uniquement s'il se trouve dans le dossier
     * `ressources/` du disque public et qu'aucune autre ressource ne le référence.
     * Empêche un chemin forgé de supprimer les fichiers d'autres utilisateurs.
     */
    private function supprimerFichierStocke(?string $chemin, int $ressourceId): void
    {
        if (
            !$chemin
            || str_contains($chemin, '..')
            || str_contains($chemin, '\\')
            || !preg_match('#^ressources/[^/]+$#', $chemin)
        ) {
            return;
        }

        $encoreUtilise = Ressource::where('fichier_path', $chemin)
            ->where('id', '!=', $ressourceId)
            ->exists();

        if (!$encoreUtilise && Storage::disk('public')->exists($chemin)) {
            Storage::disk('public')->delete($chemin);
        }
    }
}
