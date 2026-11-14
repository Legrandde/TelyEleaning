<?php

namespace App\Http\Controllers;

use App\Models\Eleve;
use App\Models\Professeur;
use App\Models\User;
use App\Services\NewsletterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
        ]);

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

        $users = $query->paginate((int) $request->query('per_page', 20));

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
            ],
        ]);
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
