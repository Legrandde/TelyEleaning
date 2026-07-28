<?php

namespace App\Http\Controllers;
 
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
 
class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'nom'      => $request->nom,
            'prenom'   => $request->prenom,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $request->role,
        ]);
 
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
 
        $user  = Auth::user();
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
}
 