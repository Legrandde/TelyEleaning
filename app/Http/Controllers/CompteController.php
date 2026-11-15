<?php

namespace App\Http\Controllers;

use App\Models\Historique;
use App\Models\User;
use App\Services\AnonymisationService;
use App\Services\JournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Droits de l'utilisateur sur ses données (RGPD) : export et suppression du compte.
 */
class CompteController extends Controller
{
    public function exporter(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->load(['eleve.niveau', 'professeur']);

        $historique = $user->eleve
            ? Historique::where('eleve_id', $user->eleve->id)
                ->with('exercice:id,titre')
                ->get(['exercice_id', 'action', 'date_consultation'])
                ->map(fn ($h) => [
                    'exercice' => $h->exercice?->titre,
                    'action' => $h->action,
                    'date' => $h->date_consultation?->toIso8601String(),
                ])
            : [];

        JournalService::log('compte.export', "Export des données personnelles de l'utilisateur #{$user->id}", $user);

        return response()->json([
            'genere_le' => now()->toIso8601String(),
            'compte' => $user->only(['nom', 'prenom', 'email', 'role', 'created_at', 'derniere_connexion_le']),
            'profil' => $user->eleve ?? $user->professeur,
            'historique' => $historique,
            'newsletter' => \App\Models\AbonneNewsletter::where('email', $user->email)
                ->get(['email', 'demande_le', 'consenti_le', 'desabonne_le']),
            'candidatures' => \App\Models\CandidatureProfesseur::where('user_id', $user->id)
                ->orWhere('email', $user->email)
                ->get(['specialite', 'etablissement', 'statut', 'created_at']),
        ]);
    }

    public function supprimer(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'confirmation' => ['required', 'string'],
            'password' => ['nullable', 'string', 'max:255'],
        ]);

        if ($request->input('confirmation') !== 'SUPPRIMER') {
            return response()->json([
                'message' => 'Tapez SUPPRIMER pour confirmer.',
                'errors' => ['confirmation' => ['Tapez SUPPRIMER pour confirmer.']],
            ], 422);
        }

        if (! $user->google_id && ! Hash::check((string) $request->input('password'), $user->password)) {
            return response()->json([
                'message' => 'Mot de passe incorrect.',
                'errors' => ['password' => ['Mot de passe incorrect.']],
            ], 422);
        }

        if ($user->isAdministrateur() && User::where('role', 'administrateur')->count() <= 1) {
            return response()->json(['message' => 'Impossible : vous êtes le dernier administrateur.'], 409);
        }

        AnonymisationService::anonymiser($user, 'compte.supprime', "Suppression de son compte par l'utilisateur #{$user->id}");

        return response()->json(['message' => 'Votre compte a été supprimé.']);
    }
}
