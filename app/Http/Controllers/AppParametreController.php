<?php

namespace App\Http\Controllers;

use App\Models\AppParametre;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppParametreController extends Controller
{
    /**
     * Récupère tous les paramètres organisés par groupes
     */
    public function index(): JsonResponse
    {
        $merged = AppParametre::tous();

        return response()->json([
            'status'     => 'success',
            'parametres' => $merged,
        ]);
    }

    /**
     * Met à jour les paramètres
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->input('parametres', []);

        if (!is_array($data)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Format de paramètres invalide.',
            ], 422);
        }

        // Liste blanche : les clés inconnues sont ignorées
        $data = array_intersect_key($data, AppParametre::DEFAUTS);

        foreach ($data as $cle => $valeur) {
            if (!is_scalar($valeur) && $valeur !== null) {
                return response()->json(['status' => 'error', 'message' => "Valeur invalide pour {$cle}."], 422);
            }
            if (mb_strlen((string) $valeur) > 1000) {
                return response()->json(['status' => 'error', 'message' => "Valeur trop longue pour {$cle}."], 422);
            }
            if ((str_starts_with($cle, 'reseau_') || $cle === 'annonce_lien') && $valeur
                && !\App\Http\Requests\Page\SavePageRequest::lienSur((string) $valeur)) {
                return response()->json(['status' => 'error', 'message' => "Lien non autorisé pour {$cle}."], 422);
            }
            if (! AppParametre::valeurValide($cle, (string) ($valeur ?? ''))) {
                return response()->json(['status' => 'error', 'message' => "Valeur non autorisée pour {$cle}."], 422);
            }
        }

        $avant = AppParametre::tous();

        foreach ($data as $cle => $valeur) {
            $valeur = (string) ($valeur ?? '');
            $groupe = 'general';
            if (str_starts_with($cle, 'notif_') || str_contains($cle, 'alerte')) {
                $groupe = 'notifications';
            } elseif (str_starts_with($cle, 'annonce_') || str_starts_with($cle, 'reseau_')) {
                $groupe = 'site';
            } elseif (str_contains($cle, 'mdp') || str_contains($cle, 'session') || str_contains($cle, 'securite')) {
                $groupe = 'securite';
            } elseif (str_contains($cle, 'inscriptions') || str_contains($cle, 'candidatures') || str_contains($cle, 'exercice') || str_contains($cle, 'fichier')) {
                $groupe = 'pedagogie';
            }

            AppParametre::set($cle, $valeur, $groupe);
        }

        $modifies = array_keys(array_filter($data, fn ($v, $k) => (string) ($avant[$k] ?? '') !== (string) ($v ?? ''), ARRAY_FILTER_USE_BOTH));
        if ($modifies !== []) {
            \App\Services\JournalService::log('parametres.modifies', 'Paramètres modifiés : ' . implode(', ', $modifies), null, ['cles' => $modifies]);
        }
        \Illuminate\Support\Facades\Cache::forget('parametres_publics');
        \Illuminate\Support\Facades\Cache::forget('mode_maintenance');

        return response()->json([
            'status'     => 'success',
            'message'    => 'Paramètres enregistrés avec succès !',
            'parametres' => AppParametre::tous(),
        ]);
    }

    /**
     * Paramètres utiles au site public (sans authentification).
     */
    public function publics(): JsonResponse
    {
        $parametres = \Illuminate\Support\Facades\Cache::remember('parametres_publics', 300, fn () => array_intersect_key(
            AppParametre::tous(),
            array_flip(AppParametre::CLES_PUBLIQUES)
        ));

        return response()->json(['parametres' => $parametres]);
    }
}
