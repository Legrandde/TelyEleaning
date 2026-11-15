<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CourrielService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            $token = Password::broker()->createToken($user);
            $front = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');
            $url = "{$front}/auth/reset-password?token={$token}&email=" . urlencode($user->email);

            CourrielService::envoyer(
                $user->email,
                'Réinitialisation de votre mot de passe — Tely Elearning',
                "Bonjour {$user->prenom},\n\nVous avez demandé à réinitialiser le mot de passe de votre compte Tely Elearning. Cliquez sur le bouton ci-dessous pour choisir votre nouveau mot de passe (ce lien est valable 60 minutes).\n\nSi vous n’êtes pas à l’origine de cette demande, ignorez simplement ce message : votre mot de passe actuel reste inchangé.",
                ['Choisir mon nouveau mot de passe', $url]
            );
        }

        // Réponse constante pour éviter l'énumération d'adresses e-mail
        return response()->json(['message' => 'Si un compte existe avec cette adresse, un lien de réinitialisation vous a été envoyé par e-mail.'], 200);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|string|min:8|max:255|confirmed',
        ]);

        $minimum = \App\Models\AppParametre::longueurMinMotDePasse();
        if (mb_strlen((string) $request->input('password')) < $minimum) {
            return response()->json([
                'message' => "Le mot de passe doit contenir au moins {$minimum} caractères.",
                'errors'  => ['password' => ["Le mot de passe doit contenir au moins {$minimum} caractères."]],
            ], 422);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                // Révocation de tous les tokens actifs pour sécuriser le compte après reset
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            $user = User::where('email', $request->email)->first();
            if ($user) {
                $front = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');
                CourrielService::envoyer(
                    $user->email,
                    'Votre mot de passe a été modifié — Tely Elearning',
                    "Bonjour {$user->prenom},\n\nLe mot de passe de votre compte Tely Elearning vient d’être réinitialisé avec succès.\n\nSi vous n’êtes pas à l’origine de cette opération, contactez immédiatement notre support.",
                    ['Se connecter', "{$front}/auth/login"]
                );
            }

            return response()->json(['message' => 'Mot de passe réinitialisé avec succès.'], 200);
        }

        return response()->json(['message' => 'Token invalide ou expiré.'], 400);
    }
}
