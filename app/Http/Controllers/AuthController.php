<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Models\User;
use App\Models\Professeur;
use App\Models\AppParametre;
use App\Models\Eleve;
use App\Services\NewsletterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $parAdmin = auth('sanctum')->user()?->isAdministrateur() ?? false;
        if (!$parAdmin && !AppParametre::actif('inscriptions_ouvertes')) {
            return response()->json([
                'message' => 'Les inscriptions sont momentanément fermées.',
                'code'    => 'inscriptions_fermees',
            ], 403);
        }

        $role = 'eleve';
        if (auth('sanctum')->user()?->isAdministrateur() && in_array($request->role, ['eleve', 'professeur', 'administrateur'], true)) {
            $role = $request->role;
        }

        $user = User::create([
            'nom'      => $request->nom,
            'prenom'   => $request->prenom,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $role,
        ]);

        if ($role === 'eleve') {
            Eleve::firstOrCreate(['user_id' => $user->id]);
        }

        NewsletterService::souhaiterBienvenue($user, auth('sanctum')->user()?->isAdministrateur() ? $request->password : null);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Compte créé avec succès.',
            'user'    => $user,
            'token'   => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Email ou mot de passe incorrect.',
            ], 401);
        }

        $user = Auth::user();

        if ($user->isSuspendu()) {
            Auth::logout();

            return response()->json([
                'message' => 'Votre compte est suspendu. Contactez le support pour plus d\'informations.',
                'code'    => 'compte_suspendu',
            ], 403);
        }

        $user->forceFill(['derniere_connexion_le' => now()])->save();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie.',
            'user'    => $user,
            'token'   => $token,
        ]);
    }

    public function logout(): JsonResponse
    {
        Auth::user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    public function me(): JsonResponse
    {
        $user = Auth::user()->load(['eleve.niveau', 'professeur']);

        return response()->json($user);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $validated = $request->validated();

        // Changer d'adresse e-mail = changer l'identifiant de connexion : mot de passe actuel exigé
        if (mb_strtolower($validated['email']) !== mb_strtolower($user->email)) {
            if ($user->google_id) {
                return response()->json([
                    'message' => 'Ce compte est lié à Google : son adresse e-mail ne peut pas être modifiée ici.',
                    'errors'  => ['email' => ['Adresse liée à votre compte Google.']],
                ], 422);
            }
            if (! Hash::check((string) $request->input('current_password'), $user->password)) {
                return response()->json([
                    'message' => 'Confirmez votre mot de passe actuel pour changer d\'adresse e-mail.',
                    'errors'  => ['current_password' => ['Mot de passe actuel incorrect.']],
                ], 422);
            }
            $user->forceFill(['email_verified_at' => null]);
        }

        $userData = [
            'nom'    => $validated['nom'],
            'prenom' => $validated['prenom'],
            'email'  => $validated['email'],
        ];

        if ($request->hasFile('avatar')) {
            $userData['avatar'] = $request->file('avatar')->store('avatars', 'public');
        } elseif ($request->filled('avatar')) {
            $userData['avatar'] = $request->input('avatar');
        }

        $user->update($userData);

        if ($user->isProfesseur() && isset($validated['specialite'])) {
            Professeur::updateOrCreate(
                ['user_id' => $user->id],
                ['specialite' => $validated['specialite']]
            );
        }

        if ($user->isEleve()) {
            $eleveData = array_filter([
                'classe'    => $validated['classe'] ?? null,
                'matricule' => $validated['matricule'] ?? null,
                'niveau_id' => $validated['niveau_id'] ?? null,
            ], fn($value) => !is_null($value));

            if (!empty($eleveData)) {
                Eleve::updateOrCreate(
                    ['user_id' => $user->id],
                    $eleveData
                );
            }
        }

        $user->refresh()->load(['eleve.niveau', 'professeur']);

        return response()->json([
            'message' => 'Profil mis à jour avec succès.',
            'user'    => $user,
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Le mot de passe actuel est incorrect.',
                'errors'  => ['current_password' => ['Le mot de passe actuel est incorrect.']]
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Mot de passe modifié avec succès.',
        ]);
    }
}
