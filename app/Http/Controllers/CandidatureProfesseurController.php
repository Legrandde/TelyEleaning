<?php

namespace App\Http\Controllers;

use App\Http\Requests\Candidature\StoreCandidatureRequest;
use App\Models\AppParametre;
use App\Models\CandidatureProfesseur;
use App\Models\Professeur;
use App\Models\User;
use App\Services\CourrielService;
use App\Services\NewsletterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
            // Stockage privé : les CV (données personnelles) ne sont jamais servis publiquement
            $cvPath = $request->file('cv')->store('candidatures/cv', 'local');
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

        // Accusé de réception au candidat
        CourrielService::envoyerNonSollicite(
            $candidature->email,
            'Candidature enseignant reçue — Tely Elearning',
            "Bonjour,\n\nNous accusons bonne réception de votre candidature pour rejoindre l’équipe enseignante de Tely Elearning en spécialité « {$candidature->specialite} ».\n\nNotre équipe pédagogique examine attentivement chaque dossier. Vous recevrez une réponse par e-mail avec vos identifiants d’accès dès que votre candidature aura été validée.\n\nMerci pour votre intérêt,\nL’équipe Tely Elearning"
        );

        // Notification aux administrateurs si le paramètre est activé
        $notifActive = AppParametre::where('cle', 'notif_email_candidature')->value('valeur') ?? '1';
        if ($notifActive === '1') {
            $front = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');
            $admins = User::where('role', 'administrateur')->pluck('email');
            foreach ($admins as $adminEmail) {
                CourrielService::envoyer(
                    $adminEmail,
                    "Nouvelle candidature enseignant : {$candidature->prenom} {$candidature->nom}",
                    "Bonjour,\n\nUne nouvelle candidature enseignant vient d’être déposée sur Tely Elearning :\n• Candidat : {$candidature->prenom} {$candidature->nom}\n• E-mail : {$candidature->email}\n• Spécialité : {$candidature->specialite}\n• Établissement : " . ($candidature->etablissement ?: 'Non renseigné') . "\n\nRendez-vous dans l’espace d’administration pour examiner son dossier.",
                    ['Examiner la candidature', "{$front}/Dashbord-admin/candidatures"]
                );
            }
        }

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
     * Téléchargement du CV d'un candidat (Espace Admin, stockage privé)
     */
    public function cv($id)
    {
        $candidature = CandidatureProfesseur::findOrFail($id);
        $path = $candidature->cv_path;

        if (!$path || str_contains($path, '..') || !str_starts_with($path, 'candidatures/cv/')) {
            return response()->json(['message' => 'Aucun CV disponible.'], 404);
        }

        // Rétrocompatibilité : anciens CV déposés sur le disque public -> déplacés vers le disque privé
        if (!Storage::disk('local')->exists($path) && Storage::disk('public')->exists($path)) {
            Storage::disk('local')->writeStream($path, Storage::disk('public')->readStream($path));
            Storage::disk('public')->delete($path);
        }

        if (!Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'Fichier CV introuvable.'], 404);
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $nomFichier = 'CV-' . Str::slug($candidature->prenom . ' ' . $candidature->nom) . ($extension ? ".{$extension}" : '');

        return Storage::disk('local')->download($path, $nomFichier);
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

        $request->validate([
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ]);

        $user = User::where('email', $candidature->email)->first();

        // L'e-mail d'une candidature n'est pas vérifié : on ne touche jamais aux identifiants
        // d'un compte existant, et on ne rétrograde jamais un administrateur.
        if ($user?->isAdministrateur()) {
            return response()->json([
                'message' => 'Cet e-mail appartient à un compte administrateur : la candidature ne peut pas être approuvée.',
            ], 409);
        }

        $plainPassword = null;

        if (!$user) {
            // Mot de passe personnalisé ou auto-généré (uniquement pour un nouveau compte)
            $plainPassword = $request->input('password') ?: 'Tely@' . Str::random(12);

            $user = User::create([
                'nom'      => $candidature->nom,
                'prenom'   => $candidature->prenom,
                'email'    => $candidature->email,
                'password' => Hash::make($plainPassword),
                'role'     => 'professeur',
            ]);
        } else {
            // Compte existant : promotion du rôle uniquement, le mot de passe reste inchangé
            $user->update(['role' => 'professeur']);
        }

        Professeur::updateOrCreate(
            ['user_id' => $user->id],
            ['specialite' => $candidature->specialite]
        );

        $candidature->update([
            'statut'  => 'approuve',
            'user_id' => $user->id,
        ]);

        NewsletterService::souhaiterBienvenue($user, $plainPassword);

        return response()->json([
            'message'            => $plainPassword
                ? "Candidature approuvée avec succès ! Le compte enseignant a été créé."
                : "Candidature approuvée : le compte existant a été promu enseignant (son mot de passe est inchangé).",
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

        $motif = trim((string) $request->input('motif_rejet'));
        $precision = $motif !== '' ? "\n\nMotif indiqué par l’équipe pédagogique :\n{$motif}" : '';

        CourrielService::envoyer(
            $candidature->email,
            'Suite donnée à votre candidature — Tely Elearning',
            "Bonjour {$candidature->prenom},\n\nNous vous remercions pour l’intérêt que vous portez à Tely Elearning et pour le temps consacré à votre candidature en spécialité « {$candidature->specialite} ».\n\nAprès étude de votre dossier, nous ne sommes malheureusement pas en mesure d’y donner une suite favorable pour le moment.{$precision}\n\nNous vous souhaitons pleine réussite dans vos projets pédagogiques,\nL’équipe Tely Elearning"
        );

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
        $cvPath = $candidature->cv_path;
        $candidature->delete();

        // Suppression du CV associé (données personnelles), sur le disque privé ou l'ancien disque public
        if ($cvPath && !str_contains($cvPath, '..') && str_starts_with($cvPath, 'candidatures/cv/')) {
            Storage::disk('local')->delete($cvPath);
            Storage::disk('public')->delete($cvPath);
        }

        return response()->json([
            'message' => 'Candidature supprimée avec succès.',
        ]);
    }
}
