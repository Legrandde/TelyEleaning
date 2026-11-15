<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloque toute requête authentifiée d'un compte suspendu et révoque son jeton.
 */
class EnsureCompteActif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isSuspendu()) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'Votre compte est suspendu. Contactez le support pour plus d\'informations.',
                'code' => 'compte_suspendu',
            ], 403);
        }

        return $next($request);
    }
}
