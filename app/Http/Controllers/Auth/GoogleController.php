<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    /**
     * Redirection vers Google
     */
    public function redirect()
    {
        return Socialite::driver('google')
            ->stateless()
            ->redirect();
    }

    /**
     * Callback Google
     */
    public function callback()
    {
        $googleUser = Socialite::driver('google')
            ->stateless()
            ->user();

        // Recherche de l'utilisateur par email
        $user = User::where('email', $googleUser->getEmail())->first();

        if (!$user) {
            // Création du compte uniquement lors de la première connexion
            $user = User::create([
                'email'      => $googleUser->getEmail(),
                'nom'        => $googleUser->getName(),
                'prenom'     => '',
                'password'   => Hash::make(Str::random(40)),
                'google_id'  => $googleUser->getId(),
                'avatar'     => $googleUser->getAvatar(),
                'role'       => 'eleve', // uniquement lors de la création
            ]);
        } else {
            // Mise à jour des informations Google
            // ⚠️ On ne modifie jamais le rôle
            $user->update([
                'nom'       => $googleUser->getName(),
                'google_id' => $googleUser->getId(),
                'avatar'    => $googleUser->getAvatar(),
            ]);
        }

        // Facultatif : supprimer les anciens tokens
        $user->tokens()->delete();

        // Génération d'un nouveau token
        $token = $user->createToken('google-auth-token')->plainTextToken;

        $frontendUrl = config('app.frontend_url');

        return redirect()->away(
            "{$frontendUrl}/auth/google/callback?token={$token}"
        );
    }
}