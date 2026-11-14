<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Eleve;
use App\Models\User;
use App\Services\NewsletterService;
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

            Eleve::firstOrCreate(['user_id' => $user->id]);
            NewsletterService::souhaiterBienvenue($user);
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
            "{$frontendUrl}/auth/google/callback#token={$token}"
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

        $idToken = (string) $request->input('credential');

        // Sans client ID configuré, impossible de vérifier l'audience : on refuse (fail-closed)
        $expectedClientId = config('services.google.client_id');
        if (empty($expectedClientId)) {
            report(new \RuntimeException('GOOGLE_CLIENT_ID non configuré : connexion One Tap refusée.'));
            return response()->json(['message' => 'Connexion Google indisponible.'], 503);
        }

        // Vérification du jeton auprès du point de terminaison officiel de Google
        // (jeton passé en paramètre encodé, jamais concaténé dans l'URL)
        try {
            $response = Http::timeout(8)->get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $idToken,
            ]);

            if (!$response->successful()) {
                return response()->json(['message' => 'Token Google invalide ou expiré.'], 401);
            }

            $payload = $response->json();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Impossible de valider le jeton Google. Veuillez réessayer.'], 503);
        }

        // Vérification de l'audience (client ID) pour empêcher l'usurpation inter-applications
        if (($payload['aud'] ?? null) !== $expectedClientId) {
            return response()->json(['message' => 'Token Google non autorisé pour cette application.'], 403);
        }

        if (!in_array($payload['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            return response()->json(['message' => 'Émetteur du token Google invalide.'], 403);
        }

        if ((int) ($payload['exp'] ?? 0) < time()) {
            return response()->json(['message' => 'Token Google expiré.'], 401);
        }

        // Vérification que l'email est validé par Google (champ obligatoire)
        $emailVerified = $payload['email_verified'] ?? null;
        if ($emailVerified !== 'true' && $emailVerified !== true) {
            return response()->json(['message' => 'Adresse email non vérifiée auprès de Google.'], 403);
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

            Eleve::firstOrCreate(['user_id' => $user->id]);
            NewsletterService::souhaiterBienvenue($user);
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
