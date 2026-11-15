<?php

namespace App\Http\Controllers;

use App\Models\Eleve;
use App\Models\Professeur;
use App\Models\User;
use App\Services\AnonymisationService;
use App\Services\JournalService;
use App\Services\NewsletterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gestion des comptes utilisateurs par l'administrateur :
 * liste, création d'un compte avec son rôle, changement de rôle.
 */
class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'role'     => ['nullable', 'in:eleve,professeur,administrateur'],
            'search'   => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'statut'   => ['nullable', 'in:actif,suspendu'],
        ]);

        $users = $this->requeteFiltree($request)->paginate((int) $request->query('per_page', 20));

        // Compteurs par rôle pour les onglets de filtre
        $counts = User::select('role', DB::raw('COUNT(*) as total'))
            ->groupBy('role')
            ->pluck('total', 'role');

        return response()->json([
            'users'  => $users,
            'counts' => [
                'tous'           => (int) $counts->sum(),
                'eleve'          => (int) ($counts['eleve'] ?? 0),
                'professeur'     => (int) ($counts['professeur'] ?? 0),
                'administrateur' => (int) ($counts['administrateur'] ?? 0),
                'suspendus'      => User::whereNotNull('suspendu_le')->count(),
            ],
        ]);
    }

    private function requeteFiltree(Request $request): Builder
    {
        $query = User::query()
            ->with(['professeur:id,user_id,specialite', 'eleve:id,user_id,classe'])
            ->latest();

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                    ->orWhere('prenom', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($statut = $request->query('statut')) {
            $statut === 'suspendu' ? $query->whereNotNull('suspendu_le') : $query->whereNull('suspendu_le');
        }

        return $query;
    }

    /**
     * Export CSV des comptes (mêmes filtres que la liste).
     */
    public function export(Request $request): StreamedResponse
    {
        $request->validate([
            'role'   => ['nullable', 'in:eleve,professeur,administrateur'],
            'search' => ['nullable', 'string', 'max:100'],
            'statut' => ['nullable', 'in:actif,suspendu'],
        ]);

        $query = $this->requeteFiltree($request);
        JournalService::log('utilisateurs.export', 'Export CSV des comptes utilisateurs', null, $request->only(['role', 'search', 'statut']));

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'Prénom', 'Nom', 'E-mail', 'Rôle', 'Statut', 'Inscrit le', 'Dernière connexion'], ';');
            $query->chunk(500, function ($users) use ($out) {
                foreach ($users as $u) {
                    fputcsv($out, [
                        $u->id,
                        $this->celluleSure($u->prenom),
                        $this->celluleSure($u->nom),
                        $this->celluleSure($u->email),
                        $u->role,
                        $u->isSuspendu() ? 'suspendu' : 'actif',
                        $u->created_at?->format('Y-m-d H:i'),
                        $u->derniere_connexion_le?->format('Y-m-d H:i'),
                    ], ';');
                }
            });
            fclose($out);
        }, 'utilisateurs-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralise l'injection de formules dans les tableurs. */
    private function celluleSure(?string $valeur): string
    {
        $valeur = (string) $valeur;

        return preg_match('/^[=+\-@\t\r]/', $valeur) ? "'" . $valeur : $valeur;
    }

    public function updateStatut(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'suspendu' => ['required', 'boolean'],
            'motif'    => ['nullable', 'string', 'max:255'],
        ]);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas suspendre votre propre compte.'], 403);
        }

        if ($data['suspendu']) {
            if ($user->isAdministrateur() && User::where('role', 'administrateur')->whereNull('suspendu_le')->count() <= 1) {
                return response()->json(['message' => 'Impossible : il doit rester au moins un administrateur actif.'], 409);
            }
            $user->update(['suspendu_le' => now(), 'motif_suspension' => $data['motif'] ?? null]);
            $user->tokens()->delete();
            JournalService::log('utilisateur.suspendu', "Compte #{$user->id} suspendu", $user, ['motif' => $data['motif'] ?? null]);
            $message = "Compte suspendu. L'utilisateur a été déconnecté.";
        } else {
            $user->update(['suspendu_le' => null, 'motif_suspension' => null]);
            JournalService::log('utilisateur.reactive', "Compte #{$user->id} réactivé", $user);
            $message = 'Compte réactivé.';
        }

        return response()->json(['message' => $message, 'user' => $user->fresh(['professeur', 'eleve'])]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte ici.'], 403);
        }

        if ($user->isAdministrateur() && User::where('role', 'administrateur')->count() <= 1) {
            return response()->json(['message' => 'Impossible : il doit rester au moins un administrateur.'], 409);
        }

        AnonymisationService::anonymiser($user, 'utilisateur.supprime', "Compte #{$user->id} ({$user->role}) supprimé et anonymisé");

        return response()->json(['message' => 'Compte supprimé.']);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prenom'     => ['required', 'string', 'max:100'],
            'nom'        => ['required', 'string', 'max:100'],
            'email'      => ['required', 'email', 'max:255', 'unique:users,email'],
            'role'       => ['required', 'in:eleve,professeur,administrateur'],
            'password'   => ['nullable', 'string', 'min:8', 'max:255'],
            'specialite' => ['nullable', 'string', 'max:150'],
        ]);

        // Mot de passe fourni par l'admin, sinon généré et communiqué une seule fois
        $plainPassword = !empty($data['password']) ? $data['password'] : 'Tely@' . Str::random(12);

        $user = DB::transaction(function () use ($data, $plainPassword) {
            $user = User::create([
                'prenom'   => $data['prenom'],
                'nom'      => $data['nom'],
                'email'    => $data['email'],
                'password' => Hash::make($plainPassword),
                'role'     => $data['role'],
            ]);

            $this->ensureProfile($user, $data['specialite'] ?? null);

            return $user;
        });

        NewsletterService::souhaiterBienvenue($user, $plainPassword);
        JournalService::log('utilisateur.cree', "Compte #{$user->id} créé ({$user->role})", $user);

        return response()->json([
            'message'            => 'Utilisateur créé avec succès.',
            'user'               => $user->fresh(['professeur', 'eleve']),
            'generated_password' => empty($data['password']) ? $plainPassword : null,
        ], 201);
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', 'in:eleve,professeur,administrateur'],
        ]);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'Vous ne pouvez pas modifier votre propre rôle.',
            ], 403);
        }

        // Ne jamais laisser la plateforme sans administrateur
        if ($user->isAdministrateur() && $data['role'] !== 'administrateur'
            && User::where('role', 'administrateur')->count() <= 1) {
            return response()->json([
                'message' => 'Impossible : il doit rester au moins un administrateur.',
            ], 409);
        }

        if ($user->role === $data['role']) {
            return response()->json([
                'message' => 'Ce rôle est déjà attribué.',
                'user'    => $user->load(['professeur', 'eleve']),
            ]);
        }

        DB::transaction(function () use ($user, $data) {
            $user->update(['role' => $data['role']]);
            $this->ensureProfile($user);
        });

        // Les jetons existants portent les anciens droits : on force une reconnexion
        $user->tokens()->delete();
        JournalService::log('utilisateur.role', "Rôle du compte #{$user->id} changé en {$data['role']}", $user, ['role' => $data['role']]);

        return response()->json([
            'message' => 'Rôle mis à jour.',
            'user'    => $user->fresh(['professeur', 'eleve']),
        ]);
    }

    /**
     * Crée le profil métier correspondant au rôle s'il n'existe pas encore.
     * Les profils existants sont conservés (historique des exercices d'un professeur, etc.).
     */
    private function ensureProfile(User $user, ?string $specialite = null): void
    {
        if ($user->isProfesseur()) {
            Professeur::firstOrCreate(
                ['user_id' => $user->id],
                ['specialite' => $specialite ?? '']
            );
        } elseif ($user->isEleve()) {
            Eleve::firstOrCreate(['user_id' => $user->id]);
        }
    }
}
