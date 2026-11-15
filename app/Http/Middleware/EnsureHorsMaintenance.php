<?php

namespace App\Http\Middleware;

use App\Models\AppParametre;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mode maintenance de la plateforme : l'API répond 503 à tous, sauf aux administrateurs.
 * La connexion et les paramètres publics restent accessibles (pour que l'admin puisse se connecter
 * et que le frontend affiche la page de maintenance).
 */
class EnsureHorsMaintenance
{
    private const CHEMINS_OUVERTS = ['api/auth/*', 'api/site/parametres'];

    public function handle(Request $request, Closure $next): Response
    {
        $enMaintenance = Cache::remember('mode_maintenance', 30, fn () => AppParametre::actif('mode_maintenance'));

        if (! $enMaintenance || $request->is(...self::CHEMINS_OUVERTS)) {
            return $next($request);
        }

        if (auth('sanctum')->user()?->isAdministrateur()) {
            return $next($request);
        }

        return response()->json([
            'message' => (string) (AppParametre::tous()['message_maintenance'] ?? 'Maintenance en cours.'),
            'code' => 'maintenance',
        ], 503);
    }
}
