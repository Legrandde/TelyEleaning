<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AppParametre;
use App\Models\Eleve;
use App\Models\User;
use App\Services\CourrielService;
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
     * Redirection vers Google.
     * Le frontend fournit un nonce aléatoire (conservé dans son sessionStorage) : il est transmis
     * à Google comme « state » puis renvoyé au frontend, qui refuse tout retour sans nonce identique.
     * Un lien de callback forgé par un tiers ne peut donc pas connecter la victime sur son compte.
     */
    public function redirect(Request $request)
    {
        $driver = Socialite::driver('google')->stateless();
        $nonce = (string) $request->query('nonce', '');

        if (preg_match('/^[A-Za-z0-9_-]{16,128}$/', $nonce)) {
            $driver->with(['state' => $nonce]);
        }

        return $driver->redirect();
    }

    /**
     * Callback Google
     */
    public function callback(Request $request)
    {
        $front = rtrim((string) config('app.frontend_url'), '/');

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            return redirect()->away("{$front}/auth/login?error=google_auth_failed");
        }

        if (! filter_var($googleUser->user['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN) || ! $googleUser->getEmail()) {
            return redirect()->away("{$front}/auth/login?error=google_auth_failed");
        }

        [$user, $erreur] = $this->rattacherCompte(
            (string) $googleUser->getEmail(),
            (string) $googleUser->getId(),
            (string) ($googleUser->user['family_name'] ?? $googleUser->getName()),
            (string) ($googleUser->user['given_name'] ?? ''),
            $googleUser->getAvatar()
        );

        if ($erreur) {
            return redirect()->away("{$front}/auth/login?erreur={$erreur}");
        }

        $user->forceFill(['derniere_connexion_le' => now()])->save();
        $token = $user->createToken('google-auth-token')->plainTextToken;

        $state = (string) $request->query('state', '');
        $fragment = http_build_query(array_filter([
            'token' => $token,
            'state' => preg_match('/^[A-Za-z0-9_-]{16,128}$/', $state) ? $state : null,
        ]));

        return redirect()->away("{$front}/auth/google/callback#{$fragment}");
    }

    /**
     * Retrouve ou crée le compte associé à une identité Google vérifiée.
     *
     * @return array{0: ?User, 1: ?string} l'utilisateur, ou un code d'erreur
     */
    private function rattacherCompte(string $email, string $googleId, string $nom, string $prenom, ?string $avatar): array
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            if (! AppParametre::actif('inscriptions_ouvertes')) {
                return [null, 'inscriptions_fermees'];
            }

            $user = User::create([
                'email'     => $email,
                'nom'       => $nom !== '' ? $nom : $email,
                'prenom'    => $prenom,
                'password'  => Hash::make(Str::random(40)),
                'google_id' => $googleId,
                'avatar'    => $avatar,
                'role'      => 'eleve',
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            Eleve::firstOrCreate(['user_id' => $user->id]);
            NewsletterService::souhaiterBienvenue($user);

            return [$user, null];
        }

        if ($user->isSuspendu()) {
            return [null, 'compte_suspendu'];
        }

        // Protection contre le pré-détournement : un compte créé avec un mot de passe sur une adresse
        // jamais vérifiée a pu l'être par un tiers. Google prouve que l'adresse appartient à la personne
        // qui se connecte : on invalide alors le mot de passe et toutes les sessions existantes.
        if (! $user->google_id && ! $user->email_verified_at) {
            $user->forceFill(['password' => Hash::make(Str::random(40))])->save();
            $user->tokens()->delete();
            CourrielService::envoyer(
                $user->email,
                'Votre compte Tely est maintenant lié à Google',
                "Bonjour,\n\nVotre compte Tely vient d'être relié à votre compte Google. Par sécurité, l'ancien mot de passe a été désactivé et les autres sessions ont été déconnectées.\n\nVous pouvez continuer à vous connecter avec Google, ou définir un nouveau mot de passe via « Mot de passe oublié ».\n\nL'équipe Tely"
            );
        }

        $user->forceFill([
            'google_id'         => $googleId,
            'avatar'            => $avatar ?: $user->avatar,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        return [$user, null];
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

        [$user, $erreur] = $this->rattacherCompte($email, (string) $googleId, (string) ($familyName ?: $name), (string) $givenName, $picture);

        if ($erreur === 'compte_suspendu') {
            return response()->json([
                'message' => 'Votre compte est suspendu. Contactez le support pour plus d\'informations.',
                'code'    => 'compte_suspendu',
            ], 403);
        }
        if ($erreur === 'inscriptions_fermees') {
            return response()->json([
                'message' => 'Les inscriptions sont momentanément fermées.',
                'code'    => 'inscriptions_fermees',
            ], 403);
        }

        $user->forceFill(['derniere_connexion_le' => now()])->save();
        $token = $user->createToken('google-one-tap-token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion Google One Tap réussie.',
            'user'    => $user->load(['eleve.niveau', 'professeur']),
            'token'   => $token,
        ]);
    }
}
