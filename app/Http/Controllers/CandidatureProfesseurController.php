<?php

namespace App\Http\Controllers;

use App\Http\Requests\Candidature\StoreCandidatureRequest;
use App\Models\CandidatureProfesseur;
use App\Models\Professeur;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CandidatureProfesseurController extends Controller
{
    /**
     * Soumission d'une candidature par un professeur depuis la Landing Page
     */
    public function store(StoreCandidatureRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Vérifier si une candidature en attente existe déjà pour cet email
        $existante = CandidatureProfesseur::where('email', $validated['email'])
            ->where('statut', 'en_attente')
            ->first();

        if ($existante) {
            return response()->json([
                'message' => 'Une candidature avec cet email est déjà en cours d\'examen par notre équipe.',
            ], 422);
        }

        $cvPath = null;
        if ($request->hasFile('cv')) {
            $cvPath = $request->file('cv')->store('candidatures/cv', 'public');
        }

        $candidature = CandidatureProfesseur::create([
            'nom'               => $validated['nom'],
            'prenom'            => $validated['prenom'],
            'email'             => strtolower($validated['email']),
            'telephone'         => $validated['telephone'] ?? null,
            'specialite'        => $validated['specialite'],
            'etablissement'     => $validated['etablissement'] ?? null,
            'diplome'           => $validated['diplome'] ?? null,
            'experience_annees' => $validated['experience_annees'] ?? 0,
            'message'           => $validated['message'] ?? null,
            'cv_path'           => $cvPath,
            'statut'            => 'en_attente',
        ]);

        return response()->json([
            'message'     => 'Votre candidature a été soumise avec succès ! Notre équipe pédagogique l\'examinera et vous recevrez vos identifiants par email.',
            'candidature' => $candidature,
        ], 201);
    }

    /**
     * Liste des candidatures (Espace Admin)
     */
    public function index(Request $request): JsonResponse
    {
        $query = CandidatureProfesseur::with('user');

        if ($request->filled('statut') && in_array($request->statut, ['en_attente', 'approuve', 'rejete'])) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('nom', 'like', "%{$q}%")
                    ->orWhere('prenom', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('specialite', 'like', "%{$q}%")
                    ->orWhere('etablissement', 'like', "%{$q}%");
            });
        }

        $candidatures = $query->latest()->paginate(20);

        $counts = [
            'total'      => CandidatureProfesseur::count(),
            'en_attente' => CandidatureProfesseur::where('statut', 'en_attente')->count(),
            'approuve'   => CandidatureProfesseur::where('statut', 'approuve')->count(),
            'rejete'     => CandidatureProfesseur::where('statut', 'rejete')->count(),
        ];

        return response()->json([
            'candidatures' => $candidatures,
            'counts'       => $counts,
        ]);
    }

    /**
     * Affichage d'une candidature
     */
    public function show($id): JsonResponse
    {
        $candidature = CandidatureProfesseur::with('user')->findOrFail($id);

        return response()->json($candidature);
    }

    /**
     * Approbation par l'administrateur et création du compte professeur
     */
    public function approuver(Request $request, $id): JsonResponse
    {
        $candidature = CandidatureProfesseur::findOrFail($id);

        if ($candidature->statut === 'approuve' && $candidature->user_id) {
            return response()->json([
                'message' => 'Cette candidature a déjà été approuvée.',
                'user'    => $candidature->user,
            ], 400);
        }

        // Mot de passe personnalisé ou auto-généré
        $plainPassword = $request->input('password') ?: 'Tely@' . Str::random(8);

        $user = User::where('email', $candidature->email)->first();

        if (!$user) {
            $user = User::create([
                'nom'      => $candidature->nom,
                'prenom'   => $candidature->prenom,
                'email'    => $candidature->email,
                'password' => Hash::make($plainPassword),
                'role'     => 'professeur',
            ]);
        } else {
            $user->update([
                'role'     => 'professeur',
                'password' => Hash::make($plainPassword),
            ]);
        }

        Professeur::updateOrCreate(
            ['user_id' => $user->id],
            ['specialite' => $candidature->specialite]
        );

        $candidature->update([
            'statut'  => 'approuve',
            'user_id' => $user->id,
        ]);

        return response()->json([
            'message'            => "Candidature approuvée avec succès ! Le compte enseignant a été créé.",
            'user'               => $user->fresh(['professeur']),
            'generated_password' => $plainPassword,
            'candidature'        => $candidature->fresh(),
        ]);
    }

    /**
     * Rejet d'une candidature par l'administrateur
     */
    public function rejeter(Request $request, $id): JsonResponse
    {
        $candidature = CandidatureProfesseur::findOrFail($id);

        $candidature->update([
            'statut'      => 'rejete',
            'motif_rejet' => $request->input('motif_rejet'),
        ]);

        return response()->json([
            'message'     => 'La candidature a été marquée comme rejetée.',
            'candidature' => $candidature,
        ]);
    }

    /**
     * Suppression d'une candidature
     */
    public function destroy($id): JsonResponse
    {
        $candidature = CandidatureProfesseur::findOrFail($id);
        $candidature->delete();

        return response()->json([
            'message' => 'Candidature supprimée avec succès.',
        ]);
    }
}
