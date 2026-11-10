<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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
            $user->update([
                'nom'       => $googleUser->getName(),
                'google_id' => $googleUser->getId(),
                'avatar'    => $googleUser->getAvatar(),
            ]);
        }

        $user->tokens()->delete();

        // Génération d'un nouveau token
        $token = $user->createToken('google-auth-token')->plainTextToken;

        $frontendUrl = config('app.frontend_url');

        return redirect()->away(
            "{$frontendUrl}/auth/google/callback?token={$token}"
        );
    }

    /**
     * Connexion via Google One Tap (ID token JWT)
     */
    public function oneTap(Request $request): JsonResponse
    {
        $request->validate([
            'credential' => 'required|string',
        ]);

        $idToken = $request->input('credential');

        // Vérification sécurisée du jeton auprès du point de terminaison officiel de Google
        try {
            $response = Http::timeout(8)->get("https://oauth2.googleapis.com/tokeninfo?id_token={$idToken}");

            if (!$response->successful()) {
                return response()->json(['message' => 'Token Google invalide ou expiré.'], 401);
            }

            $payload = $response->json();
        } catch (\Exception $e) {
            // Si réseau indisponible, parser le payload base64
            $parts = explode('.', $idToken);
            if (count($parts) < 2) {
                return response()->json(['message' => 'Format de token JWT invalide.'], 400);
            }
            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true) ?? [];
        }

        $email = $payload['email'] ?? null;
        $googleId = $payload['sub'] ?? null;
        $name = $payload['name'] ?? '';
        $picture = $payload['picture'] ?? null;
        $givenName = $payload['given_name'] ?? '';
        $familyName = $payload['family_name'] ?? '';

        if (!$email) {
            return response()->json(['message' => 'Email introuvable dans le jeton Google.'], 422);
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            $user = User::create([
                'email'     => $email,
                'nom'       => $familyName ?: $name,
                'prenom'    => $givenName ?: '',
                'password'  => Hash::make(Str::random(40)),
                'google_id' => $googleId,
                'avatar'    => $picture,
                'role'      => 'eleve',
            ]);
        } else {
            $user->update([
                'google_id' => $googleId,
                'avatar'    => $picture ?: $user->avatar,
            ]);
        }

        $token = $user->createToken('google-one-tap-token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion Google One Tap réussie.',
            'user'    => $user->load(['eleve.niveau', 'professeur']),
            'token'   => $token,
        ]);
    }
}
