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
        $query = Ressource::with(['matiere', 'niveau', 'professeur.user']);

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
            $q = $request->q;
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
            'fichier_path'  => 'nullable|string|max:500',
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
        return response()->json($ressource->load(['matiere', 'niveau', 'professeur.user']));
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
            'fichier_path' => 'nullable|string|max:500',
        ]);

        if ($request->hasFile('fichier')) {
            if ($ressource->fichier_path && Storage::disk('public')->exists($ressource->fichier_path)) {
                Storage::disk('public')->delete($ressource->fichier_path);
            }

            $file = $request->file('fichier');
            $validated['fichier_nom'] = $file->getClientOriginalName();
            $validated['format'] = strtolower($file->getClientOriginalExtension());
            $validated['taille'] = $file->getSize();
            $validated['fichier_path'] = $file->store('ressources', 'public');
        }

        $ressource->update($validated);

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

        if ($ressource->fichier_path && Storage::disk('public')->exists($ressource->fichier_path)) {
            Storage::disk('public')->delete($ressource->fichier_path);
        }

        $ressource->delete();

        return response()->json(['message' => 'Ressource supprimée avec succès.']);
    }
}
