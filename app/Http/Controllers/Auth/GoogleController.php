<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function callback()
    {
        $googleUser = Socialite::driver('google')
            ->stateless()
            ->user();

        $user = User::updateOrCreate(
            [
                'email' => $googleUser->getEmail()
            ],
            [
                'nom'       => $googleUser->getName(),
                'prenom'    => '',
                'password'    => '',
                'google_id' => $googleUser->getId(),
                'avatar'    => $googleUser->getAvatar(),
                'role'      => 'eleve',
            ]
        );

        $token = $user->createToken('google-auth-token')->plainTextToken;

        $frontendUrl = config('app.frontend_url');

        return redirect()->away("{$frontendUrl}/auth/google/callback?token={$token}");
    }
}